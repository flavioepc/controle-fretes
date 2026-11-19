<?php
declare(strict_types=1);

namespace App\Controllers\Fretes;

use App\Core\Auth;
use App\Core\View;
use App\Fretes\CreditoRepository;
use App\Fretes\Escopo;

/**
 * Uso de crédito: o vendedor pede, o gestor da equipe (ou o admin) aprova ou recusa.
 *   frete_gratis -> a aprovação RESERVA o valor; o lançamento do frete consome a reserva
 *   outro        -> a aprovação já debita o saldo (ex.: cobrir uma falta de caixa)
 */
final class CreditoController
{
    private Escopo $escopo;
    private CreditoRepository $repo;

    public function __construct()
    {
        $this->escopo = new Escopo();
        $this->repo = new CreditoRepository($this->escopo);
    }

    public function index(): void
    {
        $status = (string) ($_GET['status'] ?? 'abertas');
        $lista = $this->repo->listar($status);
        $aguardandoMim = array_values(array_filter($lista,
            fn ($s) => $s['status'] === 'pendente' && $this->escopo->podeDecidir($s)));

        View::render('fretes/credito', [
            'titulo' => 'Uso de crédito', 'secao' => 'fretes', 'pagina' => 'solicitacoes',
            'escopo' => $this->escopo, 'status' => $status, 'lista' => $lista,
            'aguardandoMim' => $aguardandoMim,
            'vendedores' => $this->repo->vendedoresParaSolicitar(),
            'erros' => $_SESSION['_erros'] ?? [],
            'scripts' => [asset('vendor/tom-select/tom-select.complete.min.js'), asset('js/selects.js'),
                          asset('js/credito.js')],
            'estilos' => [asset('vendor/tom-select/tom-select.bootstrap5.min.css')],
        ]);
        unset($_SESSION['_erros']);
        limpar_antigo();
    }

    public function solicitar(): void
    {
        $d = [
            'vendedor_id' => (int) ($_POST['vendedor_id'] ?? 0),
            'tipo' => ($_POST['tipo'] ?? '') === 'outro' ? 'outro' : 'frete_gratis',
            'valor' => ler_moeda($_POST['valor'] ?? ''),
            'cliente' => trim((string) ($_POST['cliente'] ?? '')) ?: null,
            'justificativa' => trim((string) ($_POST['justificativa'] ?? '')),
            'validade' => null,
        ];
        $validade = (string) ($_POST['validade'] ?? '');
        $e = [];

        if (!$d['vendedor_id'] || !$this->escopo->podeSolicitarPara($d['vendedor_id'])) {
            $e['vendedor_id'] = 'Escolha um vendedor da sua equipe.';
        }
        if ($d['valor'] === null || is_nan($d['valor']) || $d['valor'] <= 0) {
            $e['valor'] = 'Informe um valor maior que zero.';
        } elseif (!isset($e['vendedor_id'])) {
            $saldo = $this->repo->saldo($d['vendedor_id']);
            if ($d['valor'] > (float) $saldo['disponivel']) {
                $e['valor'] = 'Maior que o saldo disponível (' . moeda($saldo['disponivel']) . ').';
            }
        }
        if ($d['tipo'] === 'frete_gratis' && !$d['cliente']) {
            $e['cliente'] = 'Informe o cliente da negociação.';
        }
        if (mb_strlen($d['justificativa']) < 10 || mb_strlen($d['justificativa']) > 500) {
            $e['justificativa'] = 'Explique em poucas palavras (10 a 500 caracteres).';
        }
        if ($d['cliente'] && mb_strlen($d['cliente']) > 120) {
            $e['cliente'] = 'Até 120 caracteres.';
        }
        if ($validade !== '') {
            $dt = \DateTimeImmutable::createFromFormat('!Y-m-d', $validade);
            if (!$dt || $dt->format('Y-m-d') !== $validade || $validade < date('Y-m-d')) {
                $e['validade'] = 'Data inválida ou no passado.';
            } else {
                $d['validade'] = $validade;
            }
        }

        if ($e) {
            $_SESSION['_erros'] = $e;
            $_SESSION['_antigo'] = $_POST;
            flash('erro', 'Confira os campos do pedido.');
            redirecionar('fretes/credito#novo');
        }

        $id = $this->repo->criar($d, (int) Auth::id());
        flash('sucesso', "Pedido #$id enviado para aprovação.");
        redirecionar('fretes/credito');
    }

    public function decidir(): void
    {
        $id = (int) ($_POST['id'] ?? 0);
        $aprovar = ($_POST['acao'] ?? '') === 'aprovar';
        $motivo = trim((string) ($_POST['motivo'] ?? '')) ?: null;
        $s = $this->repo->buscar($id);

        if (!$s || !$this->escopo->podeDecidir($s)) {
            flash('erro', 'Você não pode decidir este pedido.');
            redirecionar('fretes/credito');
        }
        if (!$aprovar && !$motivo) {
            flash('erro', 'Para recusar, informe o motivo.');
            redirecionar('fretes/credito');
        }
        if ($motivo && mb_strlen($motivo) > 255) {
            $motivo = mb_substr($motivo, 0, 255);
        }

        $erro = $this->repo->decidir($id, $aprovar, (int) Auth::id(), $motivo);
        if ($erro) {
            flash('erro', "Pedido #$id: $erro.");
        } else {
            flash('sucesso', $aprovar
                ? ($s['tipo'] === 'frete_gratis'
                    ? "Pedido #$id aprovado. O valor fica reservado até o frete ser lançado."
                    : "Pedido #$id aprovado e debitado do saldo de {$s['vendedor']}.")
                : "Pedido #$id recusado.");
        }
        redirecionar('fretes/credito');
    }

    public function cancelar(): void
    {
        $id = (int) ($_POST['id'] ?? 0);
        $s = $this->repo->buscar($id);
        if (!$s || !$this->escopo->podeCancelar($s) || ($s['tipo'] === 'outro' && $s['status'] !== 'pendente')) {
            flash('erro', 'Este pedido não pode ser cancelado.');
            redirecionar('fretes/credito');
        }
        $ok = $this->repo->cancelar($id, (int) Auth::id(), trim((string) ($_POST['motivo'] ?? '')) ?: null);
        flash($ok ? 'sucesso' : 'erro', $ok
            ? "Pedido #$id cancelado" . ($s['status'] === 'aprovada' ? ' e a reserva foi liberada.' : '.')
            : 'Não foi possível cancelar (o frete grátis já foi usado em um lançamento).');
        redirecionar('fretes/credito');
    }
}
