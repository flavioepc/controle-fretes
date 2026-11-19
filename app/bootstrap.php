<?php
declare(strict_types=1);

define('RAIZ', dirname(__DIR__));
define('APP', RAIZ . '/app');

// Autoload simples: App\Core\Router -> app/Core/Router.php
spl_autoload_register(function (string $classe): void {
    if (str_starts_with($classe, 'App\\')) {
        $arquivo = APP . '/' . str_replace('\\', '/', substr($classe, 4)) . '.php';
        if (is_file($arquivo)) {
            require $arquivo;
        }
    }
});

require APP . '/helpers.php';

$GLOBALS['config'] = require RAIZ . '/config/config.php';
date_default_timezone_set(config('timezone'));

error_reporting(E_ALL);
ini_set('log_errors', '1');                     // em produção os erros vão para o log do PHP, não para a tela
ini_set('display_errors', config('debug') ? '1' : '0');

if (PHP_SAPI !== 'cli') {
    header('X-Frame-Options: SAMEORIGIN');          // só a própria intranet embute suas páginas (clickjacking)
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
    if (https_ativo()) {
        header('Strict-Transport-Security: max-age=31536000');
    }
}

// URL base: /controle-fretes no XAMPP, '' no servidor embutido do PHP
$script = $_SERVER['SCRIPT_NAME'] ?? '';
define('BASE_URL', rtrim((string) preg_replace('#/(public/)?index\.php$#', '', $script), '/'));

if (PHP_SAPI !== 'cli') {                       // scripts de linha de comando (tools/) não usam sessão
    App\Core\Sessao::iniciar();
}
