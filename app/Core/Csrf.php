<?php
declare(strict_types=1);

namespace App\Core;

/** Proteção contra envio de formulário forjado por outro site. */
final class Csrf
{
    public static function token(): string
    {
        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf'];
    }

    public static function campo(): string
    {
        return '<input type="hidden" name="_csrf" value="' . e(self::token()) . '">';
    }

    public static function validar(): void
    {
        $enviado = $_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!is_string($enviado) || !hash_equals(self::token(), $enviado)) {
            http_response_code(419);
            View::render('erros/mensagem', [
                'titulo' => 'Sessão expirada',
                'mensagem' => 'O formulário expirou. Volte, atualize a página e tente novamente.',
            ], 'auth');
            exit;
        }
    }
}
