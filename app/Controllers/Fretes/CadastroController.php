<?php
declare(strict_types=1);

namespace App\Controllers\Fretes;

use App\Core\Auth;
use App\Core\View;
use App\Fretes\CadastroRepository;
use App\Portal\UsuarioRepository;

/** Cadastros administrativos: empresas, transportadoras, gestores e vendedores. */
final class CadastroController
{
    private const ABAS = ['empresas', 'transportadoras', 'gestores', 'vendedores'];

    private CadastroRepository $repo;

    public function __construct()
    {
        $this->repo = new CadastroRepository();
    }

    public function index(): void
    {
        $aba = (string) ($_GET['aba'] ?? 'empresas');
        if (!in_array($aba, self::ABAS, true)) {
            $aba = 'empresas';
        }
        // chaves soltas (nunca 'dados'): View::capturar() já usa esse nome para o array
        // inteiro da view, e extract(..., EXTR_SKIP) ignora silenciosamente a colisão
        $extra = match ($aba) {
            'empresas' => ['linhas' => $this->repo->listarEmpresas()],
            'transportadoras' => ['linhas' => $this->repo->listarTransportadoras()],
            'gestores' => ['linhas' => $this->repo->listarGestores(), 'usuarios' => $this->repo->usuariosPortal()],
            default => ['linhas' => $this->repo->listarVendedores(), 'usuarios' => $this->repo->usuariosPortal(),
                        'gestoresAtivos' => $this->repo->gestoresAtivos()],
        };

        View::render('fretes/cadastros', $extra + [
            'titulo' => 'Cadastros', 'secao' => 'fretes', 'pagina' => 'cadastros',
            'aba' => $aba, 'erros' => $_SESSION['_erros'] ?? [],
            'scripts' => [asset('js/cadastros.js')],
        ]);
        unset($_SESSION['_erros']);
        limpar_antigo();
    }

    public function salvarEmpresa(): void
    {
        $this->salvarNomeECnpj(
            'empresas',
            fn (array $d, ?int $id) => $this->repo->salvarEmpresa($d, $id),
            fn (string $nome, ?int $id) => $this->repo->existeNomeEmpresa($nome, $id)
        );
    }

    public function salvarTransportadora(): void
    {
        $this->salvarNomeECnpj(
            'transportadoras',
            fn (array $d, ?int $id) => $this->repo->salvarTransportadora($d, $id),
            fn (string $nome, ?int $id) => $this->repo->existeNomeTransportadora($nome, $id)
        );
    }

    public function salvarGestor(): void
    {
        $id = (int) ($_POST['id'] ?? 0) ?: null;
        $nome = trim((string) ($_POST['nome'] ?? ''));
        $usuarioId = (int) ($_POST['usuario_id'] ?? 0) ?: null;

        $e = [];
        if ($nome === '' || mb_strlen($nome) > 80) {
            $e['nome'] = 'Informe o nome (até 80 caracteres).';
        } elseif ($this->repo->existeNomeGestor($nome, $id)) {
            $e['nome'] = 'Já existe um gestor com esse nome.';
        }
        if ($usuarioId && !$this->repo->usuarioExiste($usuarioId)) {
            $e['usuario_id'] = 'Usuário inválido.';
        }
        if ($e) {
            $_SESSION['_erros'] = $e;
            $_SESSION['_antigo'] = $_POST;
            flash('erro', 'Corrija os campos destacados.');
            redirecionar('fretes/cadastros?aba=gestores');
        }

        $this->repo->salvarGestor(['nome' => $nome, 'usuario_id' => $usuarioId], $id);
        if ($usuarioId) {
            (new UsuarioRepository())->garantirAcesso($usuarioId, 'fretes', 'gestor');
        }
        flash('sucesso', $id ? 'Gestor atualizado.' : 'Gestor cadastrado.');
        redirecionar('fretes/cadastros?aba=gestores');
    }

    public function salvarVendedor(): void
    {
        $id = (int) ($_POST['id'] ?? 0) ?: null;
        $nome = trim((string) ($_POST['nome'] ?? ''));
        $gestorId = (int) ($_POST['gestor_id'] ?? 0) ?: null;
        $usuarioId = (int) ($_POST['usuario_id'] ?? 0) ?: null;
        $creditoExpiraRaw = (string) ($_POST['credito_expira'] ?? '');
        $creditoExpira = $creditoExpiraRaw === '' ? null : (int) $creditoExpiraRaw;

        $e = [];
        if ($nome === '' || mb_strlen($nome) > 80) {
            $e['nome'] = 'Informe o nome (até 80 caracteres).';
        } elseif ($this->repo->existeNomeVendedor($nome, $id)) {
            $e['nome'] = 'Já existe um vendedor com esse nome.';
        }
        if (!$this->repo->existeId('gestores', $gestorId, true)) {
            $e['gestor_id'] = 'Escolha o gestor.';
        }
        if ($usuarioId && !$this->repo->usuarioExiste($usuarioId)) {
            $e['usuario_id'] = 'Usuário inválido.';
        }
        if ($e) {
            $_SESSION['_erros'] = $e;
            $_SESSION['_antigo'] = $_POST;
            flash('erro', 'Corrija os campos destacados.');
            redirecionar('fretes/cadastros?aba=vendedores');
        }

        $this->repo->salvarVendedor([
            'nome' => $nome, 'gestor_id' => $gestorId, 'usuario_id' => $usuarioId, 'credito_expira' => $creditoExpira,
        ], $id);
        if ($usuarioId) {
            (new UsuarioRepository())->garantirAcesso($usuarioId, 'fretes', 'vendedor');
        }
        flash('sucesso', $id ? 'Vendedor atualizado.' : 'Vendedor cadastrado.');
        redirecionar('fretes/cadastros?aba=vendedores');
    }

    public function criarAcessoGestor(): void
    {
        $this->criarAcesso('gestores', 'gestor');
    }

    public function criarAcessoVendedor(): void
    {
        $this->criarAcesso('vendedores', 'vendedor');
    }

    public function alternarEmpresa(): void
    {
        $this->alternar('empresas', fn (int $id) => $this->repo->alternarEmpresa($id));
    }

    public function alternarTransportadora(): void
    {
        $this->alternar('transportadoras', fn (int $id) => $this->repo->alternarTransportadora($id));
    }

    public function alternarGestor(): void
    {
        $this->alternar('gestores', fn (int $id) => $this->repo->alternarGestor($id));
    }

    public function alternarVendedor(): void
    {
        $this->alternar('vendedores', fn (int $id) => $this->repo->alternarVendedor($id));
    }

    // ------------------------------------------------------------------

    private function salvarNomeECnpj(string $aba, callable $salvar, callable $existeNome): void
    {
        $id = (int) ($_POST['id'] ?? 0) ?: null;
        $nome = trim((string) ($_POST['nome'] ?? ''));
        $cnpj = preg_replace('/\D/', '', (string) ($_POST['cnpj'] ?? '')) ?: null;

        $e = [];
        if ($nome === '' || mb_strlen($nome) > 80) {
            $e['nome'] = 'Informe o nome (até 80 caracteres).';
        } elseif ($existeNome($nome, $id)) {
            $e['nome'] = 'Já existe um cadastro com esse nome.';
        }
        if ($cnpj !== null && strlen($cnpj) !== 14) {
            $e['cnpj'] = 'CNPJ deve ter 14 dígitos (ou deixe em branco).';
        }
        if ($e) {
            $_SESSION['_erros'] = $e;
            $_SESSION['_antigo'] = $_POST;
            flash('erro', 'Corrija os campos destacados.');
            redirecionar("fretes/cadastros?aba=$aba");
        }

        $salvar(['nome' => $nome, 'cnpj' => $cnpj], $id);
        flash('sucesso', $id ? 'Cadastro atualizado.' : 'Cadastro criado.');
        redirecionar("fretes/cadastros?aba=$aba");
    }

    /**
     * Cria o login da intranet para um gestor/vendedor: usuário com senha provisória,
     * acesso ao sistema de fretes com o perfil certo e vínculo com o cadastro.
     */
    private function criarAcesso(string $aba, string $perfil): void
    {
        $id = (int) ($_POST['id'] ?? 0);
        $email = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
        $pessoa = $id ? $this->repo->buscarPessoa($aba, $id) : null;
        $usuarios = new UsuarioRepository();

        $erro = null;
        if (!$pessoa) {
            $erro = 'Cadastro não encontrado.';
        } elseif ($pessoa['usuario_id'] && $usuarios->buscar((int) $pessoa['usuario_id'])) {
            $erro = "{$pessoa['nome']} já tem um login vinculado. Para trocar a senha use Usuários e acessos.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 150) {
            $erro = 'Informe um e-mail válido.';
        } elseif ($usuarios->existeEmail($email, null)) {
            $erro = "Já existe um usuário com o e-mail $email. Vincule pelo botão Editar.";
        }
        if ($erro) {
            flash('erro', $erro);
            redirecionar("fretes/cadastros?aba=$aba");
        }

        $nome = mb_convert_case(mb_strtolower($pessoa['nome']), MB_CASE_TITLE);
        $senha = senha_provisoria();
        $usuarioId = $usuarios->criar($nome, $email, $senha);
        try {
            $usuarios->garantirAcesso($usuarioId, 'fretes', $perfil);
            $this->repo->vincularUsuario($aba, $id, $usuarioId);
        } catch (\Throwable $e) {
            $usuarios->excluir($usuarioId);   // bancos diferentes: desfaz o usuário criado
            throw $e;
        }
        Auth::registrar(Auth::id(), 'usuario_criado', "$email ($perfil de fretes)");

        flash('sucesso', "Acesso criado para {$pessoa['nome']} — login: $email · senha provisória: $senha "
            . '(entregue à pessoa; ela cadastra uma nova senha no primeiro acesso).');
        redirecionar("fretes/cadastros?aba=$aba");
    }

    private function alternar(string $aba, callable $alternar): void
    {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id) {
            $alternar($id);
        }
        redirecionar("fretes/cadastros?aba=$aba");
    }
}
