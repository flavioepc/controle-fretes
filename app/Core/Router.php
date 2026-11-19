<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Roteador mínimo.
 *   $r->get('/fretes', [DashboardController::class, 'index'], ['auth', 'fretes']);
 * Middlewares: 'auth' (precisa estar logado), 'fretes' (precisa ter acesso ao sistema),
 *              'fretes:admin,financeiro' (só esses perfis) e 'pode:chave' / 'pode:a|b'
 *              (permissão do painel de permissões; ver App\Fretes\Permissoes).
 */
final class Router
{
    /** @var array<string, array<string, array{0: callable|array, 1: array}>> */
    private array $rotas = ['GET' => [], 'POST' => []];

    public function get(string $caminho, callable|array $acao, array $middlewares = []): void
    {
        $this->rotas['GET'][$this->normalizar($caminho)] = [$acao, $middlewares];
    }

    public function post(string $caminho, callable|array $acao, array $middlewares = []): void
    {
        $this->rotas['POST'][$this->normalizar($caminho)] = [$acao, $middlewares];
    }

    public function despachar(string $metodo, string $uri): void
    {
        $caminho = parse_url($uri, PHP_URL_PATH) ?: '/';
        if (BASE_URL !== '' && str_starts_with($caminho, BASE_URL)) {
            $caminho = substr($caminho, strlen(BASE_URL));
        }
        $caminho = $this->normalizar(rawurldecode($caminho));

        $rota = $this->rotas[$metodo][$caminho] ?? null;
        if ($rota === null) {
            http_response_code(404);
            View::render('erros/mensagem', [
                'titulo' => 'Página não encontrada',
                'mensagem' => 'O endereço acessado não existe.',
            ], Auth::logado() ? 'app' : 'auth');
            return;
        }

        [$acao, $middlewares] = $rota;
        foreach ($middlewares as $m) {
            if ($m === 'auth') {
                Auth::exigirLogin();
                continue;
            }
            // 'pode:fretes.lancar' ou 'pode:a|b' (permissões do painel, ver App\Fretes\Permissoes)
            if (str_starts_with($m, 'pode:')) {
                Auth::exigirSistema('fretes');
                \App\Fretes\Permissoes::exigir(substr($m, 5));
                continue;
            }
            // 'fretes' ou 'fretes:admin,financeiro' (sistema:perfis permitidos)
            [$sistema, $perfis] = array_pad(explode(':', $m, 2), 2, '');
            Auth::exigirSistema($sistema, $perfis === '' ? [] : explode(',', $perfis));
        }
        if ($metodo === 'POST') {
            Csrf::validar();
        }

        if (is_array($acao)) {
            [$classe, $metodoAcao] = $acao;
            (new $classe())->$metodoAcao();
        } else {
            $acao();
        }
    }

    private function normalizar(string $caminho): string
    {
        $c = '/' . trim($caminho, '/');
        return $c === '/' ? '/' : $c;
    }
}
