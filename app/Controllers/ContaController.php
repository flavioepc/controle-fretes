<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\View;

/** Dados do próprio usuário: troca de senha (obrigatória quando a senha é provisória). */
final class ContaController
{
    public function formularioSenha(): void
    {
        View::render('portal/trocar-senha', [
            'titulo' => 'Alterar senha',
            'secao' => 'portal',
            'obrigatoria' => !empty(Auth::usuario()['trocar_senha']),
            'erro' => flash('erro_senha'),
        ]);
    }

    public function trocarSenha(): void
    {
        $atual = (string) ($_POST['senha_atual'] ?? '');
        $nova = (string) ($_POST['senha_nova'] ?? '');
        $confirma = (string) ($_POST['senha_confirma'] ?? '');

        $resultado = $nova !== $confirma
            ? 'A confirmação não é igual à nova senha.'
            : Auth::trocarSenha($atual, $nova);

        if ($resultado !== true) {
            flash('erro_senha', $resultado);
            redirecionar('trocar-senha');
        }
        flash('sucesso', 'Senha alterada.');
        redirecionar('/');
    }
}
