<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\View;

final class AuthController
{
    public function formulario(): void
    {
        if (Auth::logado()) {
            redirecionar('/');
        }
        View::render('auth/login', ['titulo' => 'Entrar', 'erro' => flash('erro'),
            'email' => flash('email') ?? ''], 'auth');
    }

    public function entrar(): void
    {
        $email = trim((string) ($_POST['email'] ?? ''));
        $senha = (string) ($_POST['senha'] ?? '');

        $resultado = ($email === '' || $senha === '')
            ? 'Informe e-mail e senha.'
            : Auth::tentar($email, $senha);

        if ($resultado !== true) {
            flash('erro', $resultado);
            flash('email', $email);
            redirecionar('login');
        }

        $destino = $_SESSION['_voltar_para'] ?? null;
        unset($_SESSION['_voltar_para']);
        // só aceita voltar para dentro do próprio sistema
        if (is_string($destino) && str_starts_with($destino, BASE_URL . '/') && !str_contains($destino, '//')) {
            header('Location: ' . $destino);
            exit;
        }
        redirecionar('/');
    }

    public function sair(): void
    {
        Auth::sair();
        redirecionar('login');
    }
}
