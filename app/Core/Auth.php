<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Login único do portal. Guarda na sessão o id do usuário e os sistemas/perfis dele.
 */
final class Auth
{
    /** hash qualquer: faz o password_verify gastar o mesmo tempo quando o e-mail não existe */
    private const HASH_FALSO = '$2y$10$r0/EJsXKqdBpy4qWaMq66umYfoICVuOqGEKhKuTQA33nvIgf75iKO';

    public static function tentar(string $email, string $senha): bool|string
    {
        $email = mb_strtolower(trim($email));
        if (self::bloqueado($email)) {
            return 'Muitas tentativas. Aguarde alguns minutos e tente de novo.';
        }

        $u = Database::um('SELECT * FROM usuarios WHERE email = ?', [$email], 'portal');

        // password_verify roda mesmo sem usuário, para não revelar quais e-mails existem
        $hash = $u['senha_hash'] ?? self::HASH_FALSO;
        if (!password_verify($senha, $hash) || !$u || !(int) $u['ativo']) {
            self::log(null, 'login_falhou', $email);
            return 'E-mail ou senha inválidos.';
        }

        if (password_needs_rehash($u['senha_hash'], PASSWORD_DEFAULT)) {
            Database::get('portal')->prepare('UPDATE usuarios SET senha_hash = ? WHERE id = ?')
                ->execute([password_hash($senha, PASSWORD_DEFAULT), $u['id']]);
        }

        session_regenerate_id(true);           // evita fixação de sessão
        $_SESSION['usuario'] = [
            'id'           => (int) $u['id'],
            'nome'         => $u['nome'],
            'email'        => $u['email'],
            'trocar_senha' => (bool) $u['trocar_senha'],
        ];
        $_SESSION['sistemas'] = self::carregarSistemas((int) $u['id']);

        Database::get('portal')->prepare('UPDATE usuarios SET ultimo_login = NOW() WHERE id = ?')
            ->execute([$u['id']]);
        self::log((int) $u['id'], 'login', null);
        return true;
    }

    public static function sair(): void
    {
        if (self::logado()) {
            self::log(self::id(), 'logout', null);
        }
        $_SESSION = [];
        session_regenerate_id(true);
    }

    public static function logado(): bool
    {
        return isset($_SESSION['usuario']['id']);
    }

    public static function usuario(): ?array
    {
        return $_SESSION['usuario'] ?? null;
    }

    public static function id(): ?int
    {
        return $_SESSION['usuario']['id'] ?? null;
    }

    /** Perfil do usuário no sistema (ex.: 'admin', 'gestor') ou null se não tem acesso. */
    public static function perfil(string $sistema): ?string
    {
        return $_SESSION['sistemas'][$sistema]['perfil'] ?? null;
    }

    /** @return array<string, array> sistemas liberados para o usuário logado */
    public static function sistemas(): array
    {
        return $_SESSION['sistemas'] ?? [];
    }

    public static function exigirLogin(): void
    {
        if (!self::logado()) {
            $ehPagina = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET'
                && str_contains($_SERVER['HTTP_ACCEPT'] ?? 'text/html', 'text/html');
            if ($ehPagina) {
                $_SESSION['_voltar_para'] = $_SERVER['REQUEST_URI'] ?? null;
            }
            redirecionar('login');
        }
        self::sincronizar();

        // senha provisória: só deixa trocar a senha (ou sair) até cadastrar uma nova
        if (!empty($_SESSION['usuario']['trocar_senha'])) {
            $caminho = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
            if (!in_array(rtrim($caminho, '/'), [url('trocar-senha'), url('sair')], true)) {
                flash('aviso', 'Cadastre uma nova senha para continuar.');
                redirecionar('trocar-senha');
            }
        }
    }

    /**
     * Relê do banco, uma vez por requisição, se o usuário segue ativo e quais sistemas ele acessa:
     * inativar alguém ou tirar um acesso vale na hora, sem esperar o próximo login.
     */
    private static function sincronizar(): void
    {
        static $feito = false;
        if ($feito) {
            return;
        }
        $feito = true;

        $u = Database::um('SELECT nome, email, ativo, trocar_senha FROM usuarios WHERE id = ?', [self::id()], 'portal');
        if (!$u || !(int) $u['ativo']) {
            self::sair();
            flash('erro', 'Seu acesso foi desativado. Fale com o administrador.');
            redirecionar('login');
        }
        $_SESSION['usuario']['nome'] = $u['nome'];
        $_SESSION['usuario']['email'] = $u['email'];
        $_SESSION['usuario']['trocar_senha'] = (bool) $u['trocar_senha'];
        $_SESSION['sistemas'] = self::carregarSistemas((int) self::id());
    }

    /** Troca a senha do usuário logado. Devolve true ou a mensagem de erro. */
    public static function trocarSenha(string $atual, string $nova): bool|string
    {
        $u = Database::um('SELECT senha_hash FROM usuarios WHERE id = ?', [self::id()], 'portal');
        if (!$u || !password_verify($atual, $u['senha_hash'])) {
            return 'A senha atual não confere.';
        }
        if ($erro = self::validarSenha($nova)) {
            return $erro;
        }
        if (password_verify($nova, $u['senha_hash'])) {
            return 'A nova senha precisa ser diferente da atual.';
        }
        Database::get('portal')->prepare('UPDATE usuarios SET senha_hash = ?, trocar_senha = 0 WHERE id = ?')
            ->execute([password_hash($nova, PASSWORD_DEFAULT), self::id()]);
        $_SESSION['usuario']['trocar_senha'] = false;
        self::registrar(self::id(), 'senha_trocada', null);
        return true;
    }

    /** Regra mínima de senha. Devolve a mensagem de erro ou null se a senha serve. */
    public static function validarSenha(string $senha): ?string
    {
        if (mb_strlen($senha) < 8) {
            return 'A senha precisa ter pelo menos 8 caracteres.';
        }
        if (!preg_match('/[A-Za-z]/', $senha) || !preg_match('/\d/', $senha)) {
            return 'A senha precisa ter letras e números.';
        }
        return null;
    }

    /** Grava uma ação na trilha de auditoria do portal. */
    public static function registrar(?int $usuarioId, string $acao, ?string $detalhe): void
    {
        self::log($usuarioId, $acao, $detalhe);
    }

    public static function exigirSistema(string $sistema, array $perfis = []): void
    {
        self::exigirLogin();
        $perfil = self::perfil($sistema);
        if ($perfil === null || ($perfis && !in_array($perfil, $perfis, true))) {
            http_response_code(403);
            View::render('erros/mensagem', [
                'titulo' => 'Acesso não permitido',
                'mensagem' => 'Seu usuário não tem permissão para esta área. Fale com o administrador.',
            ]);
            exit;
        }
    }

    private static function carregarSistemas(int $usuarioId): array
    {
        $linhas = Database::todos(
            'SELECT s.slug, s.nome, s.descricao, s.url, s.externo, s.icone, us.perfil
               FROM usuario_sistema us JOIN sistemas s ON s.id = us.sistema_id
              WHERE us.usuario_id = ? AND s.ativo = 1
              ORDER BY s.ordem, s.nome', [$usuarioId], 'portal');
        $sistemas = [];
        foreach ($linhas as $l) {
            $sistemas[$l['slug']] = $l;
        }
        return $sistemas;
    }

    private static function bloqueado(string $email): bool
    {
        $r = Database::um(
            "SELECT COUNT(*) n FROM log_acessos
              WHERE acao = 'login_falhou' AND detalhe = ?
                AND criado_em > NOW() - INTERVAL ? MINUTE",
            [$email, (int) config('login.janela_minutos')], 'portal');
        return (int) ($r['n'] ?? 0) >= (int) config('login.max_tentativas');
    }

    private static function log(?int $usuarioId, string $acao, ?string $detalhe): void
    {
        Database::get('portal')->prepare(
            'INSERT INTO log_acessos (usuario_id, sistema, acao, detalhe, ip) VALUES (?, ?, ?, ?, ?)'
        )->execute([$usuarioId, 'portal', $acao, $detalhe, $_SERVER['REMOTE_ADDR'] ?? null]);
    }
}
