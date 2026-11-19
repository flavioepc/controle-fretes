<?php
declare(strict_types=1);

namespace App\Controllers\Fretes;

use App\Core\Auth;
use App\Core\View;
use App\Fretes\FaturaRepository;

/** Conferência e baixa de faturas (boletos) das transportadoras. */
final class FaturaController
{
    private FaturaRepository $repo;

    public function __construct()
    {
        $this->repo = new FaturaRepository();
    }

    public function index(): void
    {
        $f = [
            'status' => (string) ($_GET['status'] ?? '') ?: 'vencidas',
            'transportadora' => (int) ($_GET['transportadora'] ?? 0) ?: null,
            'empresa' => (int) ($_GET['empresa'] ?? 0) ?: null,
            'busca' => trim((string) ($_GET['busca'] ?? '')),
        ];
        if ($f['status'] === 'pendentes') {       // nome antigo da aba (links e favoritos)
            $f['status'] = 'vencidas';
        }
        $pagina = max(1, (int) ($_GET['pagina'] ?? 1));
        $res = $this->repo->listar($f, $pagina);

        View::render('fretes/faturas', [
            'titulo' => 'Faturas', 'secao' => 'fretes', 'pagina' => 'faturas',
            'f' => $f, 'res' => $res, 'paginaAtual' => $pagina,
            'paginas' => max(1, (int) ceil($res['total'] / FaturaRepository::POR_PAGINA)),
            'opcoes' => $this->repo->opcoesFiltro(),
            'scripts' => [asset('vendor/tom-select/tom-select.complete.min.js'), asset('js/selects.js')],
            'estilos' => [asset('vendor/tom-select/tom-select.bootstrap5.min.css')],
        ]);
    }

    public function ver(): void
    {
        $id = (int) ($_GET['id'] ?? 0);
        $fatura = $this->repo->buscar($id);
        if (!$fatura) {
            flash('erro', 'Fatura não encontrada.');
            redirecionar('fretes/faturas');
        }

        View::render('fretes/fatura', [
            'titulo' => 'Fatura ' . $fatura['numero_documento'], 'secao' => 'fretes', 'pagina' => 'faturas',
            'fatura' => $this->repo->comUltimaImpressao([$fatura])[0],
            'fretes' => $this->repo->fretesVinculados($id),
            'alteracoes' => $this->repo->alteracoes($id),
            'erros' => $_SESSION['_erros'] ?? [],
            'scripts' => [asset('js/fatura.js')],
        ]);
        unset($_SESSION['_erros']);
        limpar_antigo();
    }

    /** Escolha das faturas para imprimir de uma vez: as que tiveram fretes digitados no período. */
    public function impressao(): void
    {
        $hoje = date('Y-m-d');
        $f = [
            'de' => $this->data($_GET['de'] ?? null) ?? $hoje,
            'ate' => $this->data($_GET['ate'] ?? null) ?? $hoje,
            // padrão: só o que o próprio usuário digitou ("todos" mostra os de qualquer um)
            'meus' => ($_GET['quem'] ?? 'meus') !== 'todos',
            'transportadora' => (int) ($_GET['transportadora'] ?? 0) ?: null,
            'situacao' => in_array($_GET['situacao'] ?? '', ['impressas', 'todas'], true) ? $_GET['situacao'] : 'pendentes',
        ];
        if ($f['de'] > $f['ate']) {
            [$f['de'], $f['ate']] = [$f['ate'], $f['de']];
        }
        $faturas = $this->repo->paraImpressao($f + ['usuario' => $f['meus'] ? Auth::id() : null]);

        View::render('fretes/impressao-lote', [
            'titulo' => 'Imprimir faturas', 'secao' => 'fretes', 'pagina' => 'impressao',
            'f' => $f, 'faturas' => $faturas,
            'opcoes' => $this->repo->opcoesFiltro(),
            'scripts' => [asset('vendor/tom-select/tom-select.complete.min.js'), asset('js/selects.js'),
                          asset('js/impressao-lote.js')],
            'estilos' => [asset('vendor/tom-select/tom-select.bootstrap5.min.css')],
        ]);
    }

    /**
     * Relatório para imprimir e mandar ao financeiro junto com o boleto (o operador também acessa).
     * ?id=X (uma fatura) ou ?ids=1,2,3 (várias, cada uma na sua folha).
     * Modelos: detalhado (lista de fretes), vendedor (subtotais por vendedor), protocolo (capa em 2 vias).
     */
    public function imprimir(): void
    {
        $ids = array_slice(array_unique(array_filter(array_map('intval',
            explode(',', (string) ($_GET['ids'] ?? $_GET['id'] ?? ''))))), 0, 300);
        $lote = [];
        $faturas = $this->repo->comUltimaImpressao(array_filter(array_map(fn ($id) => $this->repo->buscar($id), $ids)));
        foreach ($faturas as $fatura) {
            $lote[] = ['fatura' => $fatura, 'fretes' => $this->repo->fretesVinculados((int) $fatura['id'])];
        }
        if (!$lote) {
            flash('erro', 'Nenhuma fatura encontrada para imprimir.');
            redirecionar('fretes/impressao');
        }
        $modelo = (string) ($_GET['modelo'] ?? '');
        if (!in_array($modelo, ['detalhado', 'vendedor', 'protocolo'], true)) {
            $modelo = 'detalhado';
        }

        View::render('fretes/fatura-impressao', [
            'titulo' => count($lote) === 1 ? 'Fatura ' . $lote[0]['fatura']['numero_documento'] : count($lote) . ' faturas',
            'lote' => $lote, 'modelo' => $modelo,
        ], 'impressao');
    }

    /**
     * Chamado pelo js/impressao.js quando a janela de impressão abre (botão ou Ctrl+P).
     * Responde JSON; não redireciona.
     */
    public function registrarImpressao(): void
    {
        $ids = array_slice(explode(',', (string) ($_POST['ids'] ?? '')), 0, 300);
        $modelo = (string) ($_POST['modelo'] ?? '');
        if (!in_array($modelo, ['detalhado', 'vendedor', 'protocolo'], true)) {
            $modelo = 'detalhado';
        }
        $qtd = $this->repo->registrarImpressao($ids, Auth::id(), $modelo);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => true, 'registradas' => $qtd]);
    }

    public function conferir(): void
    {
        $id = (int) ($_POST['id'] ?? 0);
        $fatura = $this->repo->buscar($id);
        if (!$fatura) {
            flash('erro', 'Fatura não encontrada.');
            redirecionar('fretes/faturas');
        }
        if (in_array($fatura['status'], ['enviada_pagto', 'paga'], true)) {
            flash('erro', 'Esta fatura já foi enviada para pagamento e não pode mais ser conferida.');
            redirecionar('fretes/faturas/ver?id=' . $id);
        }

        [$d, $erros] = $this->validarConferencia($_POST, (float) $fatura['total_fretes']);
        if ($erros) {
            $_SESSION['_erros'] = $erros;
            $_SESSION['_antigo'] = $_POST;
            flash('erro', 'Corrija os campos destacados.');
            redirecionar('fretes/faturas/ver?id=' . $id);
        }

        $this->repo->conferir($id, $d);
        flash('sucesso', $d['status'] === 'conferida'
            ? 'Fatura conferida. Já pode ser enviada para pagamento.'
            : 'Fatura marcada como divergente.');
        redirecionar('fretes/faturas/ver?id=' . $id);
    }

    public function enviar(): void
    {
        $id = (int) ($_POST['id'] ?? 0);
        $ok = $this->repo->enviarPagamento($id);
        flash($ok ? 'sucesso' : 'erro', $ok
            ? 'Fatura enviada para pagamento.'
            : 'A fatura precisa estar conferida antes de enviar para pagamento.');
        redirecionar('fretes/faturas/ver?id=' . $id);
    }

    public function pagar(): void
    {
        $id = (int) ($_POST['id'] ?? 0);
        if (!$this->repo->buscar($id)) {
            flash('erro', 'Fatura não encontrada.');
            redirecionar('fretes/faturas');
        }
        $pagoEm = $this->data($_POST['pago_em'] ?? null) ?? date('Y-m-d');
        $recebidoPor = trim((string) ($_POST['recebido_por'] ?? '')) ?: null;
        $recebidoEm = $this->data($_POST['recebido_em'] ?? null);

        $ok = $this->repo->marcarPaga($id, $pagoEm, $recebidoPor, $recebidoEm);
        flash($ok ? 'sucesso' : 'erro', $ok
            ? 'Fatura marcada como paga.'
            : 'A fatura precisa estar enviada para pagamento antes de ser marcada como paga.');
        redirecionar('fretes/faturas/ver?id=' . $id);
    }

    /**
     * Corrige os dados de uma fatura já enviada para pagamento ou paga (ex.: boleto prorrogado, data de
     * pagamento errada). Não muda o status nem os fretes; o boleto continua tendo que bater com o total.
     * Fica no histórico com o motivo (faturas_alteracoes).
     */
    public function alterar(): void
    {
        $id = (int) ($_POST['id'] ?? 0);
        $fatura = $this->repo->buscar($id);
        if (!$fatura) {
            flash('erro', 'Fatura não encontrada.');
            redirecionar('fretes/faturas');
        }
        if (!in_array($fatura['status'], ['enviada_pagto', 'paga'], true)) {
            flash('erro', 'Só faturas enviadas para pagamento ou pagas usam "Alterar dados"; as outras ainda podem ser conferidas.');
            redirecionar('fretes/faturas/ver?id=' . $id);
        }

        $paga = $fatura['status'] === 'paga';
        $e = [];
        $d = [
            'vencimento' => $this->dataOpcional($_POST['vencimento'] ?? '', 'vencimento', $e),
            'desconto' => ler_moeda($_POST['desconto'] ?? '') ?? 0.0,
            'valor_boleto' => ler_moeda($_POST['valor_boleto'] ?? ''),
            'responsavel' => trim((string) ($_POST['responsavel'] ?? '')),
            'obs' => trim((string) ($_POST['obs'] ?? '')) ?: null,
            'enviado_pagto_em' => $this->dataOpcional($_POST['enviado_pagto_em'] ?? '', 'enviado_pagto_em', $e),
        ];
        if ($paga) {
            $d['pago_em'] = $this->dataOpcional($_POST['pago_em'] ?? '', 'pago_em', $e);
            $d['recebido_em'] = $this->dataOpcional($_POST['recebido_em'] ?? '', 'recebido_em', $e);
            $d['recebido_por'] = trim((string) ($_POST['recebido_por'] ?? '')) ?: null;
        }
        $motivo = trim((string) ($_POST['motivo'] ?? ''));

        if (is_nan($d['desconto']) || $d['desconto'] < 0) {
            $e['desconto'] = 'Valor inválido.';
        } elseif ($d['desconto'] > (float) $fatura['total_fretes']) {
            $e['desconto'] = 'Maior que o total dos fretes (' . moeda($fatura['total_fretes']) . ').';
        }
        if ($d['valor_boleto'] !== null && (is_nan($d['valor_boleto']) || $d['valor_boleto'] < 0)) {
            $e['valor_boleto'] = 'Valor inválido.';
        } elseif ($d['valor_boleto'] !== null && !isset($e['desconto'])) {
            // a fatura já foi conferida: o boleto continua tendo que bater com fretes − desconto
            $totalAPagar = round((float) $fatura['total_fretes'] - $d['desconto'], 2);
            if (abs(round($d['valor_boleto'] - $totalAPagar, 2)) > 0.01) {
                $e['valor_boleto'] = 'Não bate com o total a pagar (' . moeda($totalAPagar) . '). Ajuste o desconto '
                    . 'ou corrija os fretes da fatura.';
            }
        }
        $d['responsavel'] = $d['responsavel'] === '' ? null : $d['responsavel'];
        if ($d['responsavel'] !== null && mb_strlen($d['responsavel']) > 60) {
            $e['responsavel'] = 'Até 60 caracteres.';
        }
        if ($d['obs'] !== null && mb_strlen($d['obs']) > 255) {
            $e['obs'] = 'Até 255 caracteres.';
        }
        $hoje = date('Y-m-d');
        foreach (['enviado_pagto_em', 'pago_em', 'recebido_em'] as $c) {
            if (!isset($e[$c]) && !empty($d[$c]) && $d[$c] > $hoje) {
                $e[$c] = 'Não pode ser no futuro.';
            }
        }
        if ($paga && $d['recebido_por'] !== null && mb_strlen($d['recebido_por']) > 60) {
            $e['recebido_por'] = 'Até 60 caracteres.';
        }
        // o que já estava preenchido não pode ser apagado (as faturas trazidas da planilha têm campos vazios,
        // por isso só é obrigatório o que já tinha valor)
        foreach (['responsavel' => 'Informe quem conferiu.', 'enviado_pagto_em' => 'Informe a data.',
                  'pago_em' => 'Informe a data do pagamento.'] as $c => $msg) {
            if (array_key_exists($c, $d) && !isset($e[$c]) && $d[$c] === null && !empty($fatura[$c])) {
                $e[$c] = $msg;
            }
        }
        if (mb_strlen($motivo) < 5 || mb_strlen($motivo) > 200) {
            $e['motivo'] = 'Explique o motivo da alteração (de 5 a 200 caracteres).';
        }

        if ($e) {
            $_SESSION['_erros'] = $e + ['_form' => 'alterar'];
            $_SESSION['_antigo'] = $_POST;
            flash('erro', 'Corrija os campos destacados.');
            redirecionar('fretes/faturas/ver?id=' . $id . '#alterar');
        }

        $erro = $this->repo->alterarDados($id, $d, $motivo, Auth::id());
        flash($erro ? 'erro' : 'sucesso', $erro ? "Nada foi alterado: $erro." : 'Dados da fatura alterados.');
        redirecionar('fretes/faturas/ver?id=' . $id);
    }

    /** Conhecimento com o nº errado: troca o nº da fatura (todos os fretes dela vão junto). */
    public function trocarConhecimento(): void
    {
        $id = (int) ($_POST['id'] ?? 0);
        $numero = mb_strtoupper(trim((string) ($_POST['numero'] ?? '')));
        $motivo = trim((string) ($_POST['motivo'] ?? ''));
        $e = [];
        if ($numero === '' || mb_strlen($numero) > 40) {
            $e['numero'] = 'Informe o nº correto (até 40 caracteres).';
        }
        if (mb_strlen($motivo) < 5 || mb_strlen($motivo) > 200) {
            $e['motivo_numero'] = 'Explique o motivo (de 5 a 200 caracteres).';
        }
        $this->voltarSeErroCorrecao($id, $e, 'conhecimento');

        $erro = $this->repo->trocarNumero($id, $numero, $motivo, (int) Auth::id());
        flash($erro ? 'erro' : 'sucesso', $erro ? "Nº não trocado: $erro." : "Conhecimento corrigido para $numero.");
        redirecionar('fretes/faturas/ver?id=' . $id);
    }

    /**
     * Tira fretes lançados no conhecimento errado: vão para outro nº da mesma transportadora
     * ou ficam sem conhecimento (aguardando). Os valores e o saldo dos vendedores não mudam.
     */
    public function retirarFretes(): void
    {
        $id = (int) ($_POST['id'] ?? 0);
        $fretes = array_map('intval', (array) ($_POST['fretes'] ?? []));
        $destino = mb_strtoupper(trim((string) ($_POST['destino'] ?? '')));
        $motivo = trim((string) ($_POST['motivo'] ?? ''));
        $e = [];
        if (!array_filter($fretes)) {
            $e['fretes'] = 'Marque na lista os fretes que vão sair deste conhecimento.';
        }
        if (mb_strlen($destino) > 40) {
            $e['destino'] = 'Até 40 caracteres.';
        }
        if (mb_strlen($motivo) < 5 || mb_strlen($motivo) > 200) {
            $e['motivo_retirar'] = 'Explique o motivo (de 5 a 200 caracteres).';
        }
        $this->voltarSeErroCorrecao($id, $e, 'retirar');

        [$erro, $destinoId] = $this->repo->retirarFretes($id, $fretes, $destino, $motivo, (int) Auth::id());
        if ($erro) {
            flash('erro', "Nada foi retirado: $erro.");
        } else {
            flash('sucesso', count($fretes) . ' frete(s) retirado(s) '
                . ($destinoId ? "para o conhecimento $destino." : 'e deixado(s) sem conhecimento (aguardando fatura).'));
        }
        redirecionar('fretes/faturas/ver?id=' . $id);
    }

    // ------------------------------------------------------------------

    /** PRG das correções de conhecimento: guarda erros e digitado e reabre o formulário certo. */
    private function voltarSeErroCorrecao(int $id, array $e, string $form): void
    {
        if (!$e) {
            return;
        }
        $_SESSION['_erros'] = $e + ['_form' => $form];
        $_SESSION['_antigo'] = $_POST;
        flash('erro', $e['fretes'] ?? 'Corrija os campos destacados.');
        redirecionar('fretes/faturas/ver?id=' . $id . '#corrigir-conhecimento');
    }

    /** Data opcional do formulário: vazio = null; preenchida e inválida marca o erro no campo. */
    private function dataOpcional(mixed $s, string $campo, array &$e): ?string
    {
        $s = trim((string) $s);
        if ($s === '') {
            return null;
        }
        $d = $this->data($s);
        if (!$d) {
            $e[$campo] = 'Data inválida.';
        }
        return $d;
    }

    /** @return array{0: array, 1: array<string,string>} */
    private function validarConferencia(array $in, float $totalFretes): array
    {
        $e = [];
        $d = [
            'vencimento' => $this->data($in['vencimento'] ?? null),
            'desconto' => ler_moeda($in['desconto'] ?? '') ?? 0.0,
            'valor_boleto' => ler_moeda($in['valor_boleto'] ?? ''),
            'responsavel' => trim((string) ($in['responsavel'] ?? '')),
            'status' => ($in['status'] ?? '') === 'divergente' ? 'divergente' : 'conferida',
            'obs' => trim((string) ($in['obs'] ?? '')) ?: null,
        ];

        if ($d['responsavel'] === '' || mb_strlen($d['responsavel']) > 60) {
            $e['responsavel'] = 'Informe quem conferiu (até 60 caracteres).';
        }
        if (is_nan($d['desconto']) || $d['desconto'] < 0) {
            $e['desconto'] = 'Valor inválido.';
        }
        if ($d['valor_boleto'] !== null && (is_nan($d['valor_boleto']) || $d['valor_boleto'] < 0)) {
            $e['valor_boleto'] = 'Valor inválido.';
        }

        // trava de segurança: se o boleto não bate com o total a pagar, força divergente
        if (!isset($e['desconto']) && !isset($e['valor_boleto']) && $d['valor_boleto'] !== null) {
            $totalAPagar = round($totalFretes - $d['desconto'], 2);
            if (abs(round($d['valor_boleto'] - $totalAPagar, 2)) > 0.01) {
                $d['status'] = 'divergente';
            }
        }
        if ($d['status'] === 'divergente' && ($d['obs'] === null || mb_strlen($d['obs']) < 5)) {
            $e['obs'] = 'Explique a divergência (mínimo 5 caracteres).';
        }
        if ($d['obs'] !== null && mb_strlen($d['obs']) > 255) {
            $e['obs'] = 'Até 255 caracteres.';
        }
        return [$d, $e];
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
