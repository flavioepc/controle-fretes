<?php
declare(strict_types=1);

namespace App\Controllers\Fretes;

use App\Core\Auth;
use App\Core\View;
use App\Fretes\Permissoes;

/** Painel de permissões: o que cada perfil pode fazer no sistema de fretes (padrão: só admin). */
final class PermissaoController
{
    public function index(): void
    {
        View::render('fretes/permissoes', [
            'titulo' => 'Permissões', 'secao' => 'fretes', 'pagina' => 'permissoes',
            'historico' => Permissoes::historico(),
            'scripts' => [asset('js/permissoes.js')],
        ]);
    }

    public function salvar(): void
    {
        // $_POST['p'][perfil][chave] = '1' para cada caixa marcada (as travadas nem são enviadas)
        $marcadas = is_array($_POST['p'] ?? null) ? $_POST['p'] : [];
        $qtd = Permissoes::salvar($marcadas, (int) Auth::id());
        flash($qtd ? 'sucesso' : 'aviso', $qtd
            ? "Permissões salvas: $qtd alteração(ões). Já valem para quem está usando o sistema."
            : 'Nada mudou.');
        redirecionar('fretes/permissoes');
    }

    public function restaurar(): void
    {
        $qtd = Permissoes::restaurarPadrao((int) Auth::id());
        flash('sucesso', $qtd
            ? "Permissões de volta ao padrão do sistema ($qtd alteração(ões) desfeita(s))."
            : 'As permissões já estavam no padrão do sistema.');
        redirecionar('fretes/permissoes');
    }
}
