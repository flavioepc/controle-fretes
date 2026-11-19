<?php
/**
 * @var App\Fretes\Escopo $escopo
 * @var App\Fretes\Filtros $filtros
 */
use App\Fretes\Filtros;

$rotuloStatus = [
    'aberta' => 'Aberta', 'conferida' => 'Conferida', 'divergente' => 'Divergente',
    'enviada_pagto' => 'Enviada p/ pagto', 'paga' => 'Paga',
];
$corStatus = [
    'aberta' => 'secondary', 'conferida' => 'info', 'divergente' => 'danger',
    'enviada_pagto' => 'primary', 'paga' => 'success',
];
$periodoTexto = data_br($filtros->de) . ' a ' . data_br($filtros->ate);
$nomeEscopo = match ($escopo->perfil) {
    'gestor'   => 'Saldos da sua equipe',
    'vendedor' => 'Seu saldo',
    default    => 'Saldos de todos os vendedores',
};
$ehVendedor = $escopo->perfil === 'vendedor';
?>

<div class="d-flex flex-wrap align-items-end justify-content-between gap-2 mb-3">
  <div>
    <h1 class="h4 mb-0">Dashboard de Fretes</h1>
    <p class="text-body-secondary small mb-0"><?= e($nomeEscopo) ?> · movimento de <?= e($periodoTexto) ?></p>
  </div>
  <button class="btn btn-outline-secondary btn-sm d-md-none" type="button" data-bs-toggle="collapse"
          data-bs-target="#barraFiltros" aria-expanded="false" aria-controls="barraFiltros">
    <i class="bi bi-funnel"></i> Filtros<?= $filtros->ativos() ? ' <span class="badge text-bg-primary">•</span>' : '' ?>
  </button>
</div>

<?php if ($escopo->semVinculo()): ?>
  <div class="alert alert-warning d-flex gap-2"><i class="bi bi-link-45deg fs-5"></i>
    <div>Seu login ainda não está vinculado a um cadastro de <?= e($escopo->perfil) ?>. Peça ao administrador para fazer o vínculo.</div>
  </div>
<?php endif; ?>

<!-- Filtros: uma linha acima de tudo; no celular ficam recolhidos -->
<form class="collapse d-md-block mb-3" id="barraFiltros" method="get" action="<?= url('fretes') ?>">
  <div class="card card-body py-2 px-3 barra-filtros">
    <div class="row g-2 align-items-end">
      <div class="col-12 col-md-auto">
        <label class="form-label small mb-1" for="fPeriodo">Período</label>
        <select class="form-select form-select-sm" id="fPeriodo" name="periodo">
          <option value="">Personalizado</option>
          <?php foreach (Filtros::ATALHOS as $k => $v): ?>
            <option value="<?= $k ?>" <?= $filtros->atalho === $k ? 'selected' : '' ?>><?= e($v) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-6 col-md-auto">
        <label class="form-label small mb-1" for="fDe">De</label>
        <input class="form-control form-control-sm" type="date" id="fDe" name="de" value="<?= e($filtros->de) ?>">
      </div>
      <div class="col-6 col-md-auto">
        <label class="form-label small mb-1" for="fAte">Até</label>
        <input class="form-control form-control-sm" type="date" id="fAte" name="ate" value="<?= e($filtros->ate) ?>">
      </div>
      <div class="col-12 col-md">
        <label class="form-label small mb-1" for="fEmpresa">Empresa</label>
        <select class="form-select form-select-sm" id="fEmpresa" name="empresa">
          <option value="">Todas</option>
          <?php foreach ($opcoes['empresas'] as $o): ?>
            <option value="<?= $o['id'] ?>" <?= $filtros->empresaId === (int) $o['id'] ? 'selected' : '' ?>><?= e($o['nome']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php if ($opcoes['gestores']): ?>
        <div class="col-12 col-md">
          <label class="form-label small mb-1" for="fGestor">Gestor</label>
          <select class="form-select form-select-sm" id="fGestor" name="gestor">
            <option value="">Todos</option>
            <?php foreach ($opcoes['gestores'] as $o): ?>
              <option value="<?= $o['id'] ?>" <?= $filtros->gestorId === (int) $o['id'] ? 'selected' : '' ?>><?= e($o['nome']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      <?php endif; ?>
      <div class="col-12 col-md-auto d-flex gap-2">
        <button class="btn btn-primary btn-sm flex-grow-1" type="submit"><i class="bi bi-check2"></i> Aplicar</button>
        <a class="btn btn-outline-secondary btn-sm" href="<?= url('fretes') ?>" title="Limpar filtros" aria-label="Limpar filtros"><i class="bi bi-x-lg"></i></a>
      </div>
    </div>
  </div>
</form>

<!-- Pendências que pedem ação -->
<?php if ($pendencias['solicitacoes'] || $pendencias['fretes_sem_vendedor'] || ($saldos['aguardando_fatura'] ?? 0)): ?>
  <div class="d-flex flex-wrap gap-2 mb-3">
    <?php if ($pendencias['solicitacoes']): ?>
      <a class="pill-pendencia text-decoration-none" href="<?= url('fretes/credito?status=pendente') ?>"><i class="bi bi-hand-thumbs-up"></i>
        <?= $pendencias['solicitacoes'] ?> solicitação(ões) de crédito aguardando aprovação · <?= moeda($pendencias['solicitacoes_valor']) ?>
        <i class="bi bi-arrow-right"></i></a>
    <?php endif; ?>
    <?php
      // quem lança fretes corrige pela lista de fretes; o período cobre todo o histórico importado
      $podeCorrigir = App\Fretes\Permissoes::pode('fretes.lancar');
      $linkSituacao = fn (string $s) => url('fretes/lancamentos?' . http_build_query(
          ['situacao' => $s, 'de' => (date('Y') - 1) . '-01-01', 'ate' => date('Y-m-d')]));
    ?>
    <?php if ($saldos['aguardando_fatura'] ?? 0): ?>
      <<?= $podeCorrigir ? 'a href="' . e($linkSituacao('aguardando')) . '" class="pill-pendencia text-decoration-none"' : 'span class="pill-pendencia"' ?>>
        <i class="bi bi-hourglass-split"></i>
        <?= (int) $saldos['aguardando_fatura'] ?> frete(s) aguardando valor da transportadora (fora do saldo)
        <?php if ($podeCorrigir): ?><i class="bi bi-arrow-right"></i><?php endif; ?></<?= $podeCorrigir ? 'a' : 'span' ?>>
    <?php endif; ?>
    <?php if ($pendencias['fretes_sem_vendedor']): ?>
      <<?= $podeCorrigir ? 'a href="' . e($linkSituacao('sem_vendedor')) . '" class="pill-pendencia text-decoration-none"' : 'span class="pill-pendencia"' ?>>
        <i class="bi bi-person-exclamation"></i>
        <?= $pendencias['fretes_sem_vendedor'] ?> frete(s) sem vendedor
        <?php if ($podeCorrigir): ?><i class="bi bi-arrow-right"></i><?php endif; ?></<?= $podeCorrigir ? 'a' : 'span' ?>>
    <?php endif; ?>
  </div>
<?php endif; ?>

<!-- Saldos (posição atual, não dependem do período) -->
<h2 class="titulo-secao">Saldo de crédito <span>posição atual</span></h2>
<div class="row g-3 mb-4">
  <div class="col-6 col-xl-3">
    <div class="card card-indicador h-100"><div class="card-body">
      <div class="indicador-rotulo"><i class="bi bi-piggy-bank"></i> <?= $ehVendedor ? 'Seu crédito' : 'Crédito dos vendedores' ?></div>
      <div class="indicador-valor"><?= moeda($saldos['credito'] ?? 0) ?></div>
      <div class="indicador-nota"><?= $ehVendedor ? 'Disponível: ' . moeda(max(0, (float) ($saldos['liquido'] ?? 0) - (float) ($saldos['reservado'] ?? 0))) : (int) ($saldos['vendedores_com_credito'] ?? 0) . ' vendedor(es) com saldo positivo' ?></div>
    </div></div>
  </div>
  <div class="col-6 col-xl-3">
    <div class="card card-indicador h-100"><div class="card-body">
      <div class="indicador-rotulo"><i class="bi bi-arrow-down-circle"></i> Saldo negativo a descontar</div>
      <div class="indicador-valor <?= classe_sinal($saldos['negativo'] ?? 0) ?>"><?= moeda($saldos['negativo'] ?? 0) ?></div>
      <div class="indicador-nota"><?= $ehVendedor ? 'Descontado em outro sistema' : (int) ($saldos['vendedores_negativos'] ?? 0) . ' vendedor(es) negativos' ?></div>
    </div></div>
  </div>
  <div class="col-6 col-xl-3">
    <div class="card card-indicador h-100"><div class="card-body">
      <div class="indicador-rotulo"><i class="bi bi-bookmark-check"></i> Reservado (frete grátis)</div>
      <div class="indicador-valor"><?= moeda($saldos['reservado'] ?? 0) ?></div>
      <div class="indicador-nota">Aprovado e ainda não utilizado</div>
    </div></div>
  </div>
  <div class="col-6 col-xl-3">
    <div class="card card-indicador card-destaque h-100"><div class="card-body">
      <div class="indicador-rotulo"><i class="bi bi-wallet2"></i> Saldo líquido</div>
      <div class="indicador-valor <?= classe_sinal($saldos['liquido'] ?? 0) ?>"><?= moeda($saldos['liquido'] ?? 0) ?></div>
      <div class="indicador-nota">Crédito − negativos</div>
    </div></div>
  </div>
</div>

<!-- Movimento no período -->
<h2 class="titulo-secao">Fretes no período <span><?= e($periodoTexto) ?></span></h2>
<div class="row g-3 mb-4">
  <div class="col-6 col-xl-3">
    <div class="card card-indicador h-100"><div class="card-body">
      <div class="indicador-rotulo"><i class="bi bi-truck"></i> Fretes lançados</div>
      <div class="indicador-valor"><?= number_format((int) ($periodo['fretes'] ?? 0), 0, ',', '.') ?></div>
      <div class="indicador-nota"><?= (int) ($periodo['geraram_credito'] ?? 0) ?> geraram crédito · <?= (int) ($periodo['consumiram_credito'] ?? 0) ?> consumiram</div>
    </div></div>
  </div>
  <div class="col-6 col-xl-3">
    <div class="card card-indicador h-100"><div class="card-body">
      <div class="indicador-rotulo"><i class="bi bi-person-check"></i> Cobrado dos clientes</div>
      <div class="indicador-valor"><?= moeda($periodo['cobrado_cliente'] ?? 0) ?></div>
    </div></div>
  </div>
  <div class="col-6 col-xl-3">
    <div class="card card-indicador h-100"><div class="card-body">
      <div class="indicador-rotulo"><i class="bi bi-building"></i> Cobrado pelas transportadoras</div>
      <div class="indicador-valor"><?= moeda($periodo['pago_transp'] ?? 0) ?></div>
    </div></div>
  </div>
  <div class="col-6 col-xl-3">
    <div class="card card-indicador h-100"><div class="card-body">
      <div class="indicador-rotulo"><i class="bi bi-plus-slash-minus"></i> Crédito gerado no período</div>
      <div class="indicador-valor <?= classe_sinal($periodo['credito_gerado'] ?? 0) ?>"><?= moeda($periodo['credito_gerado'] ?? 0) ?></div>
      <div class="indicador-nota">Cliente − transportadora</div>
    </div></div>
  </div>
</div>

<?php if ($faturas): $ap = $faturas['aPagar']; ?>
<!-- Faturas das transportadoras -->
<h2 class="titulo-secao">Faturas das transportadoras</h2>
<div class="row g-3 mb-4">
  <div class="col-6 col-xl-3">
    <div class="card card-indicador h-100"><div class="card-body">
      <div class="indicador-rotulo"><i class="bi bi-receipt"></i> A pagar</div>
      <div class="indicador-valor"><?= moeda($ap['total']) ?></div>
      <div class="indicador-nota"><?= (int) $ap['qtd'] ?> fatura(s) em aberto</div>
    </div></div>
  </div>
  <div class="col-6 col-xl-3">
    <div class="card card-indicador h-100"><div class="card-body">
      <div class="indicador-rotulo"><i class="bi bi-check2-circle"></i> Pagas no período</div>
      <div class="indicador-valor"><?= moeda($faturas['pagas']['total']) ?></div>
      <div class="indicador-nota"><?= (int) $faturas['pagas']['qtd'] ?> fatura(s)</div>
    </div></div>
  </div>
  <div class="col-6 col-xl-3">
    <div class="card card-indicador h-100"><div class="card-body">
      <div class="indicador-rotulo"><i class="bi bi-calendar-event"></i> Vencimento</div>
      <div class="d-flex flex-column gap-1 mt-1">
        <span class="status-linha"><i class="bi bi-exclamation-triangle-fill status-critico"></i><strong><?= (int) $ap['vencidas'] ?></strong> vencida(s)</span>
        <span class="status-linha"><i class="bi bi-clock-fill status-alerta"></i><strong><?= (int) $ap['vencendo'] ?></strong> vence(m) em até <?= (int) $faturas['dias'] ?> dias</span>
      </div>
    </div></div>
  </div>
  <div class="col-6 col-xl-3">
    <div class="card card-indicador h-100"><div class="card-body">
      <div class="indicador-rotulo"><i class="bi bi-exclamation-diamond"></i> Divergentes</div>
      <div class="indicador-valor"><?= (int) $ap['divergentes'] ?></div>
      <div class="indicador-nota">Boleto não confere com os fretes</div>
    </div></div>
  </div>
</div>
<?php endif; ?>

<!-- Gráficos -->
<div class="row g-3 mb-4">
  <div class="col-12 <?= $ehVendedor ? '' : 'col-lg-7' ?>">
    <div class="card h-100"><div class="card-body">
      <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-start gap-2 mb-2">
        <div>
          <h2 class="h6 mb-0">Cliente × transportadora</h2>
          <p class="small text-body-secondary mb-0">Valores de frete por <?= $serie['semanal'] ? 'semana' : 'dia' ?> no período</p>
        </div>
        <ul class="legenda list-unstyled mb-0" aria-hidden="true">
          <li><span class="legenda-linha s1"></span>Cobrado do cliente</li>
          <li><span class="legenda-linha s2"></span>Cobrado pela transportadora</li>
        </ul>
      </div>
      <?php if ($serie['linhas']): ?>
        <div class="grafico-area"><canvas id="graficoSerie" role="img"
             aria-label="Gráfico de linhas comparando o valor cobrado do cliente e o cobrado pela transportadora"></canvas></div>
        <details class="mt-2 small">
          <summary class="text-body-secondary">Ver como tabela</summary>
          <div class="table-responsive mt-2" style="max-height: 260px">
            <table class="table table-sm tabela-numeros mb-0">
              <thead><tr><th><?= $serie['semanal'] ? 'Semana de' : 'Dia' ?></th><th class="text-end">Cliente</th><th class="text-end">Transportadora</th><th class="text-end">Diferença</th></tr></thead>
              <tbody>
              <?php foreach ($serie['linhas'] as $l): ?>
                <tr><td><?= data_br($l['dia']) ?></td><td class="text-end"><?= moeda($l['cliente'], false) ?></td>
                    <td class="text-end"><?= moeda($l['transp'], false) ?></td>
                    <td class="text-end <?= classe_sinal($l['diferenca']) ?>"><?= moeda($l['diferenca'], false) ?></td></tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </details>
      <?php else: ?>
        <div class="vazio"><i class="bi bi-inbox"></i>Nenhum frete no período selecionado.</div>
      <?php endif; ?>
    </div></div>
  </div>

  <?php if (!$ehVendedor): ?>
  <div class="col-12 col-lg-5">
    <div class="card h-100"><div class="card-body">
      <h2 class="h6 mb-0"><?= e($grupo['titulo']) ?></h2>
      <p class="small text-body-secondary mb-2">
        <span class="chave-sinal positivo"></span>crédito
        <span class="chave-sinal negativo ms-2"></span>negativo · posição atual
      </p>
      <?php if ($grupo['linhas']): ?>
        <div class="grafico-area" style="height: <?= max(220, count($grupo['linhas']) * 30 + 40) ?>px">
          <canvas id="graficoGrupo" role="img" aria-label="<?= e($grupo['titulo']) ?> em barras horizontais"></canvas>
        </div>
      <?php else: ?>
        <div class="vazio"><i class="bi bi-inbox"></i>Sem saldos para mostrar.</div>
      <?php endif; ?>
    </div></div>
  </div>
  <?php endif; ?>
</div>

<!-- Rankings e faturas -->
<div class="row g-3 mb-4">
  <?php if (!$ehVendedor): ?>
  <?php foreach (['credito' => ['Maiores créditos', 'bi-trophy'], 'negativo' => ['Saldos negativos', 'bi-arrow-down-circle']] as $tipo => [$tit, $ico]): ?>
    <div class="col-12 col-md-6">
      <div class="card h-100">
        <div class="card-header bg-transparent d-flex align-items-center gap-2">
          <i class="bi <?= $ico ?> text-body-secondary"></i><h2 class="h6 mb-0"><?= $tit ?></h2>
        </div>
        <?php if ($ranking[$tipo]): ?>
          <ul class="list-group list-group-flush lista-ranking">
            <?php foreach ($ranking[$tipo] as $r): ?>
              <li class="list-group-item d-flex align-items-center gap-2">
                <span class="avatar avatar-sm"><?= e(iniciais($r['vendedor'])) ?></span>
                <div class="flex-grow-1 min-w-0">
                  <div class="text-truncate small fw-medium"><?= e($r['vendedor']) ?></div>
                  <div class="text-body-secondary text-truncate" style="font-size:.75rem"><?= e($r['gestor']) ?>
                    <?= (float) $r['reservado'] > 0 ? ' · reservado ' . moeda($r['reservado']) : '' ?></div>
                </div>
                <span class="small fw-semibold numero text-nowrap <?= classe_sinal($r['saldo']) ?>"><?= moeda($r['saldo']) ?></span>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php else: ?>
          <div class="vazio py-4"><i class="bi bi-check2-circle"></i>Nenhum vendedor aqui.</div>
        <?php endif; ?>
      </div>
    </div>
  <?php endforeach; endif; ?>

  <?php if ($faturas): ?>
    <div class="col-12">
      <div class="card h-100">
        <div class="card-header bg-transparent d-flex align-items-center gap-2">
          <i class="bi bi-calendar-event text-body-secondary"></i><h2 class="h6 mb-0">Próximos vencimentos</h2>
        </div>
        <?php if ($faturas['proximas']): ?>
          <div class="table-responsive-cards">
            <table class="table table-sm align-middle mb-0 tabela-cards">
              <thead><tr><th>Transportadora</th><th>Documento</th><th>Vencimento</th><th>Status</th><th class="text-end">Valor</th></tr></thead>
              <tbody>
                <?php foreach ($faturas['proximas'] as $fa): ?>
                  <tr>
                    <td data-rotulo="Transportadora"><span class="fw-medium"><?= e($fa['transportadora']) ?></span>
                      <div class="text-body-secondary" style="font-size:.75rem"><?= e($fa['empresa'] ?? '—') ?></div></td>
                    <td data-rotulo="Documento" class="text-nowrap"><?= e($fa['numero_documento']) ?></td>
                    <td data-rotulo="Vencimento" class="text-nowrap">
                      <?php if ((int) $fa['vencida']): ?><i class="bi bi-exclamation-triangle-fill status-critico" title="Vencida"></i><?php endif; ?>
                      <?= data_br($fa['vencimento']) ?><?= (int) $fa['vencida'] ? ' <span class="small status-critico-texto">vencida</span>' : '' ?></td>
                    <td data-rotulo="Status"><span class="badge rounded-pill text-bg-<?= $corStatus[$fa['status']] ?? 'secondary' ?>"><?= e($rotuloStatus[$fa['status']] ?? $fa['status']) ?></span></td>
                    <td data-rotulo="Valor" class="text-end numero fw-medium text-nowrap"><?= moeda($fa['total_a_pagar']) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php else: ?>
          <div class="vazio py-4"><i class="bi bi-check2-circle"></i>Nenhuma fatura em aberto.</div>
        <?php endif; ?>
      </div>
    </div>
  <?php endif; ?>
</div>

<script id="dadosGraficos" type="application/json"><?= json_encode($graficos, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?></script>
