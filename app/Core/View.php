<?php
declare(strict_types=1);

namespace App\Core;

final class View
{
    /**
     * Renderiza app/Views/{$view}.php dentro de app/Views/layouts/{$layout}.php.
     * Dentro do layout, o HTML da página fica em $conteudo.
     */
    public static function render(string $view, array $dados = [], ?string $layout = 'app'): void
    {
        $conteudo = self::capturar($view, $dados);
        if ($layout === null) {
            echo $conteudo;
            return;
        }
        echo self::capturar('layouts/' . $layout, $dados + ['conteudo' => $conteudo]);
    }

    private static function capturar(string $view, array $dados): string
    {
        $arquivo = APP . '/Views/' . $view . '.php';
        if (!is_file($arquivo)) {
            throw new \RuntimeException("View não encontrada: $view");
        }
        extract($dados, EXTR_SKIP);
        ob_start();
        require $arquivo;
        return (string) ob_get_clean();
    }
}
