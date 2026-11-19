<?php
declare(strict_types=1);

namespace App\Controllers\Fretes;

use App\Core\Auth;
use App\Core\View;
use App\Fretes\ConfiguracaoRepository;

/** Configurações gerais do sistema de fretes (chaves liga/desliga, prazos de alerta...). */
final class ConfiguracaoController
{
    private ConfiguracaoRepository $repo;

    public function __construct()
    {
        $this->repo = new ConfiguracaoRepository();
    }

    public function index(): void
    {
        View::render('fretes/configuracoes', [
            'titulo' => 'Configurações', 'secao' => 'fretes', 'pagina' => 'config',
            'config' => $this->repo->todas(), 'erros' => $_SESSION['_erros'] ?? [],
        ]);
        unset($_SESSION['_erros']);
        limpar_antigo();
    }

    public function salvar(): void
    {
        $expira = !empty($_POST['credito_expira_virada_ano']) ? '1' : '0';
        $diasTexto = trim((string) ($_POST['alerta_vencimento_dias'] ?? ''));
        $pedidoMinimo = ler_moeda($_POST['frete_isento_pedido_minimo'] ?? '');

        $e = [];
        if (!ctype_digit($diasTexto) || (int) $diasTexto < 0 || (int) $diasTexto > 365) {
            $e['alerta_vencimento_dias'] = 'Informe um número inteiro de dias entre 0 e 365.';
        }
        if ($pedidoMinimo === null || is_nan($pedidoMinimo) || $pedidoMinimo <= 0) {
            $e['frete_isento_pedido_minimo'] = 'Informe um valor maior que zero.';
        }
        if ($e) {
            $_SESSION['_erros'] = $e;
            $_SESSION['_antigo'] = $_POST;
            flash('erro', 'Corrija os campos destacados.');
            redirecionar('fretes/configuracoes');
        }

        $usuarioId = (int) Auth::id();
        $this->repo->salvar('credito_expira_virada_ano', $expira, $usuarioId);
        $this->repo->salvar('alerta_vencimento_dias', (string) (int) $diasTexto, $usuarioId);
        $this->repo->salvar('frete_isento_pedido_minimo', number_format($pedidoMinimo, 2, '.', ''), $usuarioId);

        flash('sucesso', 'Configurações salvas.');
        redirecionar('fretes/configuracoes');
    }
}
