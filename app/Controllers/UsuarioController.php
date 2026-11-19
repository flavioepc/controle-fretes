<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\View;
use App\Portal\UsuarioRepository;

/** Administração do portal: usuários da intranet e quais aplicativos cada um acessa. */
final class UsuarioController
{
    private UsuarioRepository $repo;

    public function __construct()
    {
        $this->repo = new UsuarioRepository();
    }

    public function index(): void
    {
        View::render('portal/usuarios', [
            'titulo' => 'Usuários e acessos', 'secao' => 'portal',
            'usuarios' => $this->repo->listar(), 'sistemas' => $this->repo->sistemas(),
            'erros' => $_SESSION['_erros'] ?? [],
            'scripts' => [asset('js/usuarios.js')],
        ]);
        unset($_SESSION['_erros']);
        limpar_antigo();
    }

    public function salvar(): void
    {
        $id = (int) ($_POST['id'] ?? 0) ?: null;
        $nome = trim((string) ($_POST['nome'] ?? ''));
        $email = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
        $ativo = isset($_POST['ativo']) ? 1 : 0;

        // só aceita perfis que existem para cada sistema
        $acessos = [];
        $marcados = is_array($_POST['acesso'] ?? null) ? $_POST['acesso'] : [];
        foreach ($this->repo->sistemas() as $s) {
            $perfil = (string) ($marcados[$s['slug']] ?? '');
            if ($perfil !== '' && in_array($perfil, $s['perfis'], true)) {
                $acessos[(int) $s['id']] = $perfil;
            }
        }

        $e = [];
        if ($nome === '' || mb_strlen($nome) > 100) {
            $e['nome'] = 'Informe o nome (até 100 caracteres).';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 150) {
            $e['email'] = 'Informe um e-mail válido.';
        } elseif ($this->repo->existeEmail($email, $id)) {
            $e['email'] = 'Já existe um usuário com esse e-mail.';
        }
        if ($id && !$this->repo->buscar($id)) {
            $e['nome'] = 'Usuário não encontrado.';
        }
        // o administrador não pode se trancar fora da tela
        if ($id === Auth::id()) {
            if (!$ativo || ($marcados['usuarios'] ?? '') !== 'admin') {
                $e['acesso'] = 'Você não pode inativar a si mesmo nem tirar o seu acesso a esta tela.';
            }
        }
        if ($e) {
            $_SESSION['_erros'] = $e;
            $_SESSION['_antigo'] = $_POST;
            flash('erro', 'Corrija os campos destacados.');
            redirecionar('usuarios');
        }

        $senha = $id ? null : senha_provisoria();
        $novoId = $this->repo->salvar(['nome' => $nome, 'email' => $email, 'ativo' => $ativo], $acessos, $id, $senha);
        Auth::registrar(Auth::id(), $id ? 'usuario_alterado' : 'usuario_criado', $email);

        flash('sucesso', $id
            ? 'Usuário atualizado.'
            : "Usuário criado. Senha provisória: $senha — entregue ao usuário; ele vai cadastrar uma nova no primeiro acesso.");
        redirecionar('usuarios#u' . $novoId);
    }

    public function alternar(): void
    {
        $id = (int) ($_POST['id'] ?? 0);
        $u = $id ? $this->repo->buscar($id) : null;
        if (!$u) {
            redirecionar('usuarios');
        }
        if ($id === Auth::id()) {
            flash('erro', 'Você não pode inativar o seu próprio usuário.');
            redirecionar('usuarios');
        }
        if ((int) $u['ativo'] && $this->repo->outrosAdmins($id) === 0) {
            flash('erro', 'Este é o único administrador ativo do portal.');
            redirecionar('usuarios');
        }
        $this->repo->alternar($id);
        Auth::registrar(Auth::id(), (int) $u['ativo'] ? 'usuario_inativado' : 'usuario_ativado', $u['email']);
        flash('sucesso', (int) $u['ativo'] ? 'Usuário inativado.' : 'Usuário ativado.');
        redirecionar('usuarios');
    }

    public function redefinirSenha(): void
    {
        $id = (int) ($_POST['id'] ?? 0);
        $u = $id ? $this->repo->buscar($id) : null;
        if (!$u) {
            redirecionar('usuarios');
        }
        $senha = senha_provisoria();
        $this->repo->redefinirSenha($id, $senha);
        Auth::registrar(Auth::id(), 'senha_redefinida', $u['email']);
        flash('sucesso', "Nova senha provisória de {$u['nome']}: $senha — ele vai cadastrar uma nova no próximo acesso.");
        redirecionar('usuarios#u' . $id);
    }
}
