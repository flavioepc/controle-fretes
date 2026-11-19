<?php
declare(strict_types=1);

namespace App\Controllers\Fretes;

use App\Core\View;
use App\Fretes\DashboardRepository;
use App\Fretes\Escopo;
use App\Fretes\Filtros;

final class DashboardController
{
    public function index(): void
    {
        $escopo = new Escopo();
        $filtros = new Filtros($_GET, $escopo);
        $repo = new DashboardRepository($escopo, $filtros);

        $serie = $repo->serieTemporal();
        $grupo = $repo->saldoPorGrupo();

        View::render('fretes/dashboard', [
            'titulo'     => 'Dashboard de Fretes',
            'secao'      => 'fretes',
            'pagina'     => 'dashboard',
            'scripts'    => [
                asset('vendor/chartjs/chart.umd.min.js'),
                asset('js/dashboard.js'),
            ],
            'escopo'     => $escopo,
            'filtros'    => $filtros,
            'opcoes'     => $repo->opcoesFiltro(),
            'saldos'     => $repo->resumoSaldos(),
            'periodo'    => $repo->resumoPeriodo(),
            'serie'      => $serie,
            'grupo'      => $grupo,
            'ranking'    => $repo->rankingVendedores(),
            'faturas'    => $escopo->veFaturas() ? $repo->resumoFaturas() : null,
            'pendencias' => $repo->pendencias(),
            // dados dos gráficos vão para o JavaScript em JSON
            'graficos'   => [
                'serie' => [
                    'semanal' => $serie['semanal'],
                    'rotulos' => array_map(fn ($l) => $l['dia'], $serie['linhas']),
                    'cliente' => array_map(fn ($l) => round((float) $l['cliente'], 2), $serie['linhas']),
                    'transp'  => array_map(fn ($l) => round((float) $l['transp'], 2), $serie['linhas']),
                ],
                'grupo' => [
                    'rotulos' => array_map(fn ($l) => $l['nome'], $grupo['linhas']),
                    'valores' => array_map(fn ($l) => round((float) $l['saldo'], 2), $grupo['linhas']),
                ],
            ],
        ]);
    }
}
