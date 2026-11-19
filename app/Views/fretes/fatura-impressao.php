<?php
/**
 * Relatório de fatura para imprimir e mandar ao financeiro com o boleto.
 * Uma ou várias faturas ($lote), cada uma na sua folha e com o seu campo de valor do boleto.
 * @var array $lote  lista de ['fatura' => linha de vw_faturas, 'fretes' => fretesVinculados()]
 * @var string $modelo
 */
use App\Core\Auth;

$modelos = [
    'detalhado' => ['Detalhado', 'bi-list-ul', 'Todos os fretes da fatura, um por linha'],
    'vendedor'  => ['Por vendedor', 'bi-people', 'Fretes agrupados com subtotal por vendedor'],
    'protocolo' => ['Protocolo (2 vias)', 'bi-scissors', 'Capa resumida em duas vias: financeiro e quem lançou'],
];
$usuario = Auth::usuario()['nome'] ?? '';
$emitido = date('d/m/Y H:i');
$varias = count($lote) > 1;

// "cliente R$ 10,00" | "isento: Garantia" | "grátis do vendedor"
$cobranca = function (array $f): string {
    if ($f['motivo_isencao']) {
        return 'isento: ' . e($f['motivo_isencao']) . ($f['numero_pedido'] ? ' · ped. ' . e($f['numero_pedido']) : '');
    }
    return (float) $f['valor_cliente'] > 0 ? 'cliente ' . moeda($f['valor_cliente']) : 'grátis do vendedor';
};
$valorTransp = fn (array $f) => $f['valor_transp'] === null ? '<em>aguardando</em>' : moeda($f['valor_transp'], false);

$topo = function (array $fatura, string $titulo, string $via = '') use ($emitido): void { ?>
  <div class="rel-topo">
    <div>
      <div class="rel-marca">FABRECK</div>
      <div class="rel-titulo"><?= e($titulo) ?></div>
    </div>
    <div class="rel-sub"><?= $via ? '<span class="via-nome">' . e($via) . '</span><br>' : '' ?>Fatura nº <strong><?= e($fatura['numero_documento']) ?></strong><br>
      Emitido em <?= $emitido ?></div>
  </div>
<?php };

$dados = function (array $fatura, array $fretes): void {
    $aguardando = count(array_filter($fretes, fn ($f) => $f['valor_transp'] === null)); ?>
  <div class="rel-dados">
    <div><div class="rot">Transportadora</div><div class="val"><?= e($fatura['transportadora']) ?></div></div>
    <div><div class="rot">Empresa</div><div class="val"><?= e($fatura['empresa'] ?? '—') ?></div></div>
    <div><div class="rot">Nº do documento</div><div class="val"><?= e($fatura['numero_documento']) ?></div></div>
    <div><div class="rot">Vencimento</div><div class="val" data-venc><?= data_br($fatura['vencimento']) ?></div></div>
    <div><div class="rot">Entrada</div><div class="val"><?= data_br($fatura['data_entrada']) ?></div></div>
    <div><div class="rot">Qtd. de fretes</div><div class="val"><?= count($fretes) ?><?= $aguardando ? " ($aguardando sem valor)" : '' ?></div></div>
    <div><div class="rot">Período das NFs</div><div class="val"><?= $fretes ? data_br($fretes[0]['data']) . ' a ' . data_br(end($fretes)['data']) : '—' ?></div></div>
    <div><div class="rot">Situação no sistema</div><div class="val"><?= e(str_replace('_pagto', ' p/ pagto', ucfirst($fatura['status']))) ?></div></div>
  </div>
<?php };

$totais = function (array $fatura): void { ?>
  <div class="rel-totais">
    <div><span>Soma dos fretes lançados</span><span class="num"><?= moeda($fatura['total_fretes']) ?></span></div>
    <?php if ((float) $fatura['desconto'] > 0): ?>
      <div><span>(−) Desconto</span><span class="num"><?= moeda($fatura['desconto']) ?></span></div>
    <?php endif; ?>
    <div class="destaque"><span>Total a pagar</span><span class="num"><?= moeda($fatura['total_a_pagar']) ?></span></div>
    <div><span>Valor do boleto</span><span class="num" data-boleto>—</span></div>
    <div><span>Diferença (boleto − fretes)</span><span class="num" data-dif>—</span></div>
    <div><span>Conferência</span><span data-selo><span class="selo selo-pendente">INFORME O BOLETO</span></span></div>
  </div>
<?php };

$assinaturas = function () use ($usuario): void { ?>
  <div class="rel-assinaturas">
    <div>Lançado e conferido por: <strong><?= e($usuario) ?></strong><br>Data: ____/____/________</div>
    <div>Recebido no financeiro por:<br>Data: ____/____/________</div>
  </div>
<?php };

$rodape = function (): void { ?>
  <div class="rel-rodape"><span>Controle de Fretes Fabreck</span><span>Anexar o boleto a este relatório</span></div>
<?php };

$primeira = $lote[0];
$voltar = $varias
    ? url('fretes/impressao')
    : url('fretes/lancamentos?' . http_build_query(['busca' => $primeira['fatura']['numero_documento'],
        'de' => $primeira['fretes'] ? $primeira['fretes'][0]['data'] : date('Y-m-01'), 'ate' => date('Y-m-d')]));
?>

<div class="barra-impressao nao-imprimir" data-registrar="<?= url('fretes/faturas/impressa') ?>"
     data-ids="<?= e(implode(',', array_map(fn ($i) => (int) $i['fatura']['id'], $lote))) ?>" data-modelo="<?= e($modelo) ?>">
  <div>
    <a class="btn btn-sm btn-outline-secondary" href="<?= $voltar ?>">
      <i class="bi bi-arrow-left"></i> <?= $varias ? 'Seleção' : 'Fretes' ?></a>
  </div>
  <div>
    <span class="form-label d-block">Modelo</span>
    <div class="btn-group btn-group-sm" role="group" aria-label="Modelo de impressão">
      <?php foreach ($modelos as $chave => [$rotulo, $icone, $dica]): ?>
        <a class="btn btn-outline-primary<?= $modelo === $chave ? ' active' : '' ?>" title="<?= e($dica) ?>"
           href="<?= url_com(['modelo' => $chave], 'fretes/faturas/imprimir') ?>"><i class="bi <?= $icone ?>"></i> <?= e($rotulo) ?></a>
      <?php endforeach; ?>
    </div>
  </div>
  <div class="small text-body-secondary align-self-center">
    <?= count($lote) ?> fatura(s) · total <?= moeda(array_sum(array_map(fn ($i) => $i['fatura']['total_a_pagar'], $lote))) ?><br>
    <?= $varias ? 'Digite o valor de cada boleto acima da folha dele.' : 'Digite o valor do boleto para conferir.' ?>
  </div>
  <div class="ms-auto">
    <button class="btn btn-sm btn-primary" type="button" id="btnImprimir"><i class="bi bi-printer"></i> Imprimir<?= $varias ? ' todas' : '' ?></button>
  </div>
</div>

<?php foreach ($lote as $n => ['fatura' => $fatura, 'fretes' => $fretes]): ?>
<section class="bloco-fatura" data-total="<?= e(number_format((float) $fatura['total_a_pagar'], 2, '.', '')) ?>">
  <div class="barra-fatura nao-imprimir">
    <strong><?= $varias ? ($n + 1) . '. ' : '' ?><?= e($fatura['transportadora']) ?> · <?= e($fatura['numero_documento']) ?></strong>
    <label>Valor do boleto
      <input class="form-control form-control-sm text-end in-boleto" inputmode="decimal" placeholder="0,00" autocomplete="off"
             value="<?= e(campo_moeda($fatura['valor_boleto'])) ?>"></label>
    <label>Vencimento
      <input type="date" class="form-control form-control-sm in-venc" value="<?= e($fatura['vencimento'] ?? '') ?>"></label>
    <span class="selo-tela" data-selo></span>
    <span class="ja-impressa<?= $fatura['qtd_impressoes'] ? ' sim' : '' ?>">
      <?= $fatura['qtd_impressoes']
          ? '<i class="bi bi-printer-fill"></i> Já impressa ' . $fatura['qtd_impressoes'] . 'x · última ' . e(date('d/m H:i', strtotime($fatura['ultima_impressao'])))
            . ($fatura['ultima_impressao_por'] ? ' por ' . e($fatura['ultima_impressao_por']) : '')
          : 'Ainda não impressa' ?></span>
  </div>

<?php if ($modelo === 'detalhado'): ?>
  <div class="folha">
    <?php $topo($fatura, 'Relatório de fretes da fatura'); $dados($fatura, $fretes); ?>
    <table class="rel">
      <thead><tr><th>#</th><th>Data</th><th>Nota fiscal</th><th>Vendedor</th><th>UF</th><th>Cobrança</th><th class="num">Transp. (R$)</th></tr></thead>
      <tbody>
      <?php foreach ($fretes as $i => $f): ?>
        <tr>
          <td><?= $i + 1 ?></td>
          <td><?= data_br($f['data']) ?></td>
          <td><?= e($f['nota_fiscal']) ?></td>
          <td><?= e($f['vendedor'] ?? 'sem vendedor') ?></td>
          <td><?= e($f['uf'] ?? '') ?></td>
          <td class="rel-nota"><?= $cobranca($f) ?></td>
          <td class="num"><?= $valorTransp($f) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$fretes): ?><tr><td colspan="7">Nenhum frete lançado com este documento.</td></tr><?php endif; ?>
      </tbody>
      <tfoot><tr><td colspan="6">Total — <?= count($fretes) ?> frete(s)</td><td class="num"><?= moeda($fatura['total_fretes'], false) ?></td></tr></tfoot>
    </table>
    <?php $totais($fatura); $assinaturas(); $rodape(); ?>
  </div>

<?php elseif ($modelo === 'vendedor'):
    $grupos = [];
    foreach ($fretes as $f) {
        $grupos[$f['vendedor'] ?? 'Sem vendedor'][] = $f;
    }
    ksort($grupos, SORT_NATURAL | SORT_FLAG_CASE); ?>
  <div class="folha">
    <?php $topo($fatura, 'Relatório de fretes da fatura — por vendedor'); $dados($fatura, $fretes); ?>
    <table class="rel">
      <thead><tr><th>Data</th><th>Nota fiscal</th><th>UF</th><th>Cobrança</th><th class="num">Transp. (R$)</th></tr></thead>
      <tbody>
      <?php foreach ($grupos as $vendedor => $lista): ?>
        <tr class="grupo"><td colspan="5"><?= e($vendedor) ?></td></tr>
        <?php foreach ($lista as $f): ?>
          <tr>
            <td><?= data_br($f['data']) ?></td>
            <td><?= e($f['nota_fiscal']) ?></td>
            <td><?= e($f['uf'] ?? '') ?></td>
            <td class="rel-nota"><?= $cobranca($f) ?></td>
            <td class="num"><?= $valorTransp($f) ?></td>
          </tr>
        <?php endforeach; ?>
        <tr class="subtotal"><td colspan="4">Subtotal <?= e($vendedor) ?> — <?= count($lista) ?> frete(s)</td>
          <td class="num"><?= moeda(array_sum(array_column($lista, 'valor_transp')), false) ?></td></tr>
      <?php endforeach; ?>
      </tbody>
      <tfoot><tr><td colspan="4">Total — <?= count($fretes) ?> frete(s), <?= count($grupos) ?> vendedor(es)</td><td class="num"><?= moeda($fatura['total_fretes'], false) ?></td></tr></tfoot>
    </table>
    <?php $totais($fatura); $assinaturas(); $rodape(); ?>
  </div>

<?php else:   // protocolo: a mesma capa duas vezes, para recortar ?>
  <div class="folha folha-meia">
    <?php foreach (['Via do financeiro', 'Via de quem lançou'] as $via): ?>
      <div class="via">
        <?php $topo($fatura, 'Protocolo de envio de fatura ao financeiro', $via); $dados($fatura, $fretes); ?>
        <div class="rel-nota mb-1">Notas fiscais (valor da transportadora):</div>
        <div class="lista-nf">
          <?php foreach (array_slice($fretes, 0, 60) as $f): ?>
            <span><?= e($f['nota_fiscal']) ?> — <?= $valorTransp($f) ?></span>
          <?php endforeach; ?>
          <?php if (count($fretes) > 60): ?><span><strong>+ <?= count($fretes) - 60 ?> NF(s)</strong> — ver modelo detalhado</span><?php endif; ?>
        </div>
        <?php $totais($fatura); $assinaturas(); ?>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
</section>
<?php endforeach; ?>
