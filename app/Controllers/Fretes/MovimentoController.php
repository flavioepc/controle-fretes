<?php
declare(strict_types=1);

namespace App\Controllers\Fretes;

use App\Core\Auth;
use App\Core\View;
use App\Fretes\MovimentoRepository;
use App\Fretes\Permissoes;

/**
 * Pagamento de saldo negativo (o vendedor pagou / foi descontado em outro sistema) e
 * transferência de crédito entre vendedores, e o estorno dos dois. Quem pode cada um vem do painel de
 * permissões (padrão: admin e financeiro; a transferência também o operador).
 */
final class MovimentoController
{
    private MovimentoRepository $repo;

    public function __construct()
    {
        $this->repo = new MovimentoRepository();
    }

    public function index(): void
    {
        View::render('fretes/movimentos', [
            'titulo' => 'Pagamentos e transferências', 'secao' => 'fretes', 'pagina' => 'movimentos',
            'vendedores' => $this->repo->vendedores(), 'recentes' => $this->repo->recentes(),
            // cada parte da tela segue o painel de permissões (ex.: operador só transfere)
            'pode' => [
                'pagamento' => Permissoes::pode('movimentos.pagamento'),
                'transferencia' => Permissoes::pode('movimentos.transferencia'),
                'estorno' => Permissoes::pode('movimentos.estorno'),
            ],
            'erros' => $_SESSION['_erros'] ?? [],
            'scripts' => [asset('vendor/tom-select/tom-select.complete.min.js'), asset('js/selects.js'),
                          asset('js/movimentos.js')],
            'estilos' => [asset('vendor/tom-select/tom-select.bootstrap5.min.css')],
        ]);
        unset($_SESSION['_erros']);
        limpar_antigo();
    }

    public function pagar(): void
    {
        $vendedorId = (int) ($_POST['vendedor_id'] ?? 0);
        $valor = ler_moeda($_POST['valor'] ?? '');
        $data = $this->data($_POST['data'] ?? null);
        $referencia = trim((string) ($_POST['referencia'] ?? '')) ?: null;
        $obs = trim((string) ($_POST['obs'] ?? '')) ?: null;

        $e = [];
        $saldo = $vendedorId ? $this->repo->saldo($vendedorId) : null;
        if (!$saldo) {
            $e['vendedor_id'] = 'Escolha o vendedor.';
        } elseif ((float) $saldo['saldo'] >= 0) {
            $e['vendedor_id'] = 'Este vendedor não tem saldo negativo.';
        }
        if ($valor === null || is_nan($valor) || $valor <= 0) {
            $e['valor'] = 'Informe um valor maior que zero.';
        } elseif ($saldo && (float) $saldo['saldo'] < 0 && $valor > -(float) $saldo['saldo']) {
            $e['valor'] = 'Maior que o saldo negativo (' . moeda(-(float) $saldo['saldo']) . ').';
        }
        $this->validarData($data, $e);
        if ($referencia && mb_strlen($referencia) > 80) {
            $e['referencia'] = 'Até 80 caracteres.';
        }
        if ($obs && mb_strlen($obs) > 255) {
            $e['obs'] = 'Até 255 caracteres.';
        }
        $this->voltarSeErro($e, 'pagamento');

        $erro = $this->repo->registrarPagamento($vendedorId, $valor, $data, $referencia, (int) Auth::id(), $obs);
        if ($erro) {
            flash('erro', "Pagamento não registrado: $erro.");
        } else {
            flash('sucesso', 'Pagamento de ' . moeda($valor) . " registrado para {$saldo['vendedor']}.");
        }
        redirecionar('fretes/movimentos');
    }

    public function transferir(): void
    {
        $origem = (int) ($_POST['origem_id'] ?? 0);
        $destino = (int) ($_POST['destino_id'] ?? 0);
        $valor = ler_moeda($_POST['valor'] ?? '');
        $data = $this->data($_POST['data'] ?? null);
        $obs = trim((string) ($_POST['obs'] ?? '')) ?: null;

        $e = [];
        $saldoOrigem = $origem ? $this->repo->saldo($origem) : null;
        $saldoDestino = $destino ? $this->repo->saldo($destino) : null;
        if (!$saldoOrigem) {
            $e['origem_id'] = 'Escolha quem envia o crédito.';
        }
        if (!$saldoDestino || !$saldoDestino['ativo']) {
            $e['destino_id'] = 'Escolha quem recebe o crédito.';
        } elseif ($origem === $destino) {
            $e['destino_id'] = 'Escolha um vendedor diferente da origem.';
        }
        if ($valor === null || is_nan($valor) || $valor <= 0) {
            $e['valor'] = 'Informe um valor maior que zero.';
        } elseif ($saldoOrigem && $valor > (float) $saldoOrigem['disponivel']) {
            $e['valor'] = 'Maior que o disponível de quem envia (' . moeda($saldoOrigem['disponivel']) . ').';
        }
        $this->validarData($data, $e);
        if (!$obs || mb_strlen($obs) < 5) {
            $e['obs'] = 'Explique o motivo da transferência.';
        } elseif (mb_strlen($obs) > 200) {
            $e['obs'] = 'Até 200 caracteres.';
        }
        $this->voltarSeErro($e, 'transferencia');

        $erro = $this->repo->transferir($origem, $destino, $valor, $data, (int) Auth::id(), $obs);
        if ($erro) {
            flash('erro', "Transferência não feita: $erro.");
        } else {
            flash('sucesso', 'Transferidos ' . moeda($valor) . " de {$saldoOrigem['vendedor']} para {$saldoDestino['vendedor']}.");
        }
        redirecionar('fretes/movimentos');
    }

    /**
     * Estorno de pagamento ou transferência: grava o lançamento contrário (o original fica no extrato).
     * Só admin e financeiro, como o resto da tela (rota com $gerencia).
     */
    public function estornar(): void
    {
        $id = (int) ($_POST['movimento_id'] ?? 0);
        $motivo = trim((string) ($_POST['motivo'] ?? ''));

        $mov = $id ? $this->repo->paraEstornar($id) : null;
        if (!$mov) {
            flash('erro', 'Estorno não feito: lançamento não encontrado ou já estornado.');
            redirecionar('fretes/movimentos#recentes');
        }
        if (mb_strlen($motivo) < 5 || mb_strlen($motivo) > 200) {
            flash('erro', 'Estorno não feito: explique o motivo (de 5 a 200 caracteres).');
            redirecionar('fretes/movimentos#recentes');
        }

        $erro = $this->repo->estornar($id, (int) Auth::id(), $motivo);
        if ($erro) {
            flash('erro', "Estorno não feito: $erro.");
        } else {
            $oque = $mov['tipo'] === 'pagamento_negativo' ? 'Pagamento' : 'Transferência';
            flash('sucesso', "$oque de " . moeda(abs((float) $mov['valor'])) . " ({$mov['vendedor']}) estornado(a).");
        }
        redirecionar('fretes/movimentos#recentes');
    }

    // ------------------------------------------------------------------

    /** @param array<string,string> $e */
    private function validarData(?string $data, array &$e): void
    {
        if (!$data) {
            $e['data'] = 'Informe uma data válida.';
        } elseif ($data > date('Y-m-d')) {
            $e['data'] = 'A data não pode ser no futuro.';
        }
    }

    /** Padrão PRG: guarda erros e o que foi digitado e volta para o formulário certo. */
    private function voltarSeErro(array $e, string $form): void
    {
        if (!$e) {
            return;
        }
        $_SESSION['_erros'] = $e + ['_form' => $form];
        $_SESSION['_antigo'] = $_POST;
        flash('erro', 'Corrija os campos destacados.');
        redirecionar('fretes/movimentos#' . $form);
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
