<?php
declare(strict_types=1);

namespace App\Controllers\Fretes;

use App\Core\Auth;
use App\Core\View;
use App\Fretes\CreditoExpiradoRepository;
use App\Fretes\Escopo;

/** Créditos expirados na virada do ano: consulta e resgate (total ou parcial). */
final class CreditoExpiradoController
{
    private Escopo $escopo;
    private CreditoExpiradoRepository $repo;

    public function __construct()
    {
        $this->escopo = new Escopo();
        $this->repo = new CreditoExpiradoRepository($this->escopo);
    }

    public function index(): void
    {
        $status = (string) ($_GET['status'] ?? 'pendentes');
        $hoje = (int) date('Y');

        View::render('fretes/creditos-expirados', [
            'titulo' => 'Créditos expirados', 'secao' => 'fretes', 'pagina' => 'expirados',
            'escopo' => $this->escopo, 'status' => $status,
            'lista' => $this->repo->listar($status),
            'resumo' => $this->repo->resumo(),
            'chaveGeralAtiva' => $this->repo->chaveGeralAtiva(),
            'anosJaProcessados' => $this->repo->anosJaProcessados(),
            'anoAtual' => $hoje,
            'erros' => $_SESSION['_erros'] ?? [],
        ]);
        unset($_SESSION['_erros']);
        limpar_antigo();
    }

    public function resgatar(): void
    {
        $id = (int) ($_POST['id'] ?? 0);
        $credito = $this->repo->buscar($id);
        if (!$credito || !$this->escopo->veTudo()) {
            flash('erro', 'Você não pode resgatar este crédito.');
            redirecionar('fretes/creditos-expirados');
        }

        $valor = ler_moeda($_POST['valor'] ?? '');
        $motivo = trim((string) ($_POST['motivo'] ?? '')) ?: null;
        $disponivel = (float) $credito['disponivel_resgate'];

        $erroValidacao = null;
        if ($valor === null || is_nan($valor) || $valor <= 0) {
            $erroValidacao = 'Informe um valor maior que zero.';
        } elseif ($valor > $disponivel + 0.001) {
            $erroValidacao = 'Maior que o disponível para resgate (' . moeda($disponivel) . ').';
        }
        if ($erroValidacao) {
            flash('erro', $erroValidacao);
            redirecionar('fretes/creditos-expirados');
        }

        $erro = $this->repo->resgatar($id, $valor, (int) Auth::id(), $motivo);
        flash($erro ? 'erro' : 'sucesso', $erro
            ?? ('Resgate de ' . moeda($valor) . " registrado para {$credito['vendedor']}."));
        redirecionar('fretes/creditos-expirados');
    }

    public function expirar(): void
    {
        if (!$this->escopo->veTudo()) {
            flash('erro', 'Você não pode fazer isso.');
            redirecionar('fretes/creditos-expirados');
        }

        $ano = (int) ($_POST['ano'] ?? 0);
        $hoje = (int) date('Y');
        if ($ano < 2000 || $ano >= $hoje) {
            flash('erro', 'Escolha um ano já encerrado.');
            redirecionar('fretes/creditos-expirados');
        }

        $r = $this->repo->expirarAno($ano, (int) Auth::id());
        $qtd = (int) $r['vendedores_expirados'];
        flash('sucesso', $qtd > 0
            ? "Expiração de $ano processada: $qtd vendedor(es), total " . moeda($r['total_expirado']) . '.'
            : "Nenhum saldo positivo a expirar em $ano (ou o ano já tinha sido processado antes).");
        redirecionar('fretes/creditos-expirados');
    }
}
