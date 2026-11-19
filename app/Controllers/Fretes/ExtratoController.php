<?php
declare(strict_types=1);

namespace App\Controllers\Fretes;

use App\Core\View;
use App\Fretes\Escopo;
use App\Fretes\ExtratoRepository;

/** Extrato: fretes e movimentos de crédito numa linha do tempo, por vendedor. */
final class ExtratoController
{
    private Escopo $escopo;
    private ExtratoRepository $repo;

    public function __construct()
    {
        $this->escopo = new Escopo();
        $this->repo = new ExtratoRepository($this->escopo);
    }

    public function index(): void
    {
        $hoje = new \DateTimeImmutable('today');
        $de = $this->data($_GET['de'] ?? null) ?? $hoje->modify('first day of this month')->format('Y-m-d');
        $ate = $this->data($_GET['ate'] ?? null) ?? $hoje->format('Y-m-d');
        if ($de > $ate) {
            [$de, $ate] = [$ate, $de];
        }

        $vendedores = $this->repo->vendedoresVisiveis();
        if ($this->escopo->perfil === 'vendedor') {
            $vendedorId = $this->escopo->vendedorId;
        } else {
            $vendedorId = (int) ($_GET['vendedor'] ?? 0) ?: null;
            if ($vendedorId && !$this->repo->podeVer($vendedorId)) {
                $vendedorId = null;
            }
            if ($vendedorId === null && count($vendedores) === 1) {
                $vendedorId = (int) $vendedores[0]['id'];
            }
        }

        View::render('fretes/extrato', [
            'titulo' => 'Extrato', 'secao' => 'fretes', 'pagina' => 'extrato',
            'escopo' => $this->escopo, 'de' => $de, 'ate' => $ate,
            'vendedores' => $vendedores, 'vendedorId' => $vendedorId,
            'saldo' => $vendedorId ? $this->repo->saldo($vendedorId) : null,
            'extrato' => $vendedorId ? $this->repo->extrato($vendedorId, $de, $ate) : null,
            'scripts' => [asset('vendor/tom-select/tom-select.complete.min.js'), asset('js/selects.js')],
            'estilos' => [asset('vendor/tom-select/tom-select.bootstrap5.min.css')],
        ]);
    }

    private function data(?string $s): ?string
    {
        if (!$s || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) {
            return null;
        }
        [$a, $m, $d] = array_map('intval', explode('-', $s));
        return checkdate($m, $d, $a) ? $s : null;
    }
}
