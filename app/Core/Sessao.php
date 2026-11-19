<?php
declare(strict_types=1);

namespace App\Core;

final class Sessao
{
    public static function iniciar(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        session_name(config('sessao.nome'));
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => BASE_URL === '' ? '/' : BASE_URL . '/',
            'secure'   => https_ativo(),
            'httponly' => true,       // JavaScript não lê o cookie
            'samesite' => 'Lax',
        ]);
        ini_set('session.use_strict_mode', '1');
        session_start();

        // expira por inatividade
        $limite = (int) config('sessao.expira_minutos') * 60;
        if (isset($_SESSION['_ultimo_acesso']) && time() - $_SESSION['_ultimo_acesso'] > $limite) {
            session_unset();
            session_regenerate_id(true);
        }
        $_SESSION['_ultimo_acesso'] = time();
    }
}
