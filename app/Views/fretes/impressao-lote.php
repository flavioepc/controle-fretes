<?php
/** @var array $f  @var array $faturas  @var array $opcoes */
$rotuloStatus = [
    'aberta' => 'Aberta', 'conferida' => 'Conferida', 'divergente' => 'Divergente',
    'enviada_pagto' => 'Enviada p/ pagto', 'paga' => 'Paga',
];
$corStatus = [
    'aberta' => 'secondary', 'conferida' => 'info', 'divergente' => 'danger',
    'enviada_pagto' => 'primary', 'paga' => 'success',
];
$totalGeral = array_sum(array_column($faturas, 'total_a_pagar'));
?>
<div class="d-flex flex-wrap align-items-end justify-content-between gap-2 mb-3">
  <div>
    <h1 class="h4 mb-0">Imprimir faturas</h1>
    <p class="text-body-secondary small mb-0">Faturas com fretes digitados de <?= data_br($f['de']) ?> a <?= data_br($f['ate']) ?>
      <?= $f['meus'] ? 'por você' : 'por qualquer usuário' ?> · <?= count($faturas) ?> fatura(s)</p>
  </div>
</div>

<form class="mb-3" method="get" action="<?= url('fretes/impressao') ?>">
  <div class="card card-body py-2 px-3 barra-filtros">
    <div class="row g-2 align-items-end">
      <div class="col-6 col-md-2 col-xl-auto">
        <label class="form-label small mb-1" for="de">Digitados de</label>
        <input class="form-control form-control-sm" type="date" id="de" name="de" value="<?= e($f['de']) ?>">
      </div>
      <div class="col-6 col-md-2 col-xl-auto">
        <label class="form-label small mb-1" for="ate">Até</label>
        <input class="form-control form-control-sm" type="date" id="ate" name="ate" value="<?= e($f['ate']) ?>">
      </div>
      <div class="col-12 col-md-3 col-xl-auto">
        <label class="form-label small mb-1" for="quem">Lançados por</label>
        <select class="form-select form-select-sm" id="quem" name="quem">
          <option value="meus" <?= $f['meus'] ? 'selected' : '' ?>>Mim</option>
          <option value="todos" <?= $f['meus'] ? '' : 'selected' ?>>Qualquer usuário</option>
        </select>
      </div>
      <div class="col-12 col-md-3 col-xl-auto">
        <label class="form-label small mb-1" for="situacao">Impressão</label>
        <select class="form-select form-select-sm" id="situacao" name="situacao">
          <?php foreach (['pendentes' => 'Pendentes (falta imprimir)', 'impressas' => 'Já impressas', 'todas' => 'Todas'] as $k => $v): ?>
            <option value="<?= $k ?>" <?= $f['situacao'] === $k ? 'selected' : '' ?>><?= e($v) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-12 col-md-3 col-xl">
        <label class="form-label small mb-1" for="transportadora">Transportadora</label>
        <select class="form-select form-select-sm busca-select" id="transportadora" name="transportadora" data-placeholder="Todas">
          <option value="">Todas</option>
          <?php foreach ($opcoes['transportadoras'] as $o): ?>
            <option value="<?= $o['id'] ?>" <?= $f['transportadora'] === (int) $o['id'] ? 'selected' : '' ?>><?= e($o['nome']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-12 col-md-2 col-xl-auto d-flex gap-2">
        <button class="btn btn-primary btn-sm flex-grow-1" type="submit"><i class="bi bi-search"></i> Filtrar</button>
        <a class="btn btn-outline-secondary btn-sm" href="<?= url('fretes/impressao') ?>" aria-label="Voltar para hoje"><i class="bi bi-x-lg"></i></a>
      </div>
    </div>
  </div>
</form>

<?php if (!$faturas): ?>
  <div class="card"><div class="vazio"><i class="bi bi-printer"></i>
    <?= $f['situacao'] === 'pendentes' ? 'Nenhuma fatura falta imprimir nesse período.' : 'Nenhuma fatura com fretes digitados nesse período.' ?>
    <div class="small mt-1">Só aparecem fretes lançados com o nº do documento (fatura/boleto).
      <?php if ($f['situacao'] === 'pendentes'): ?>
        <a href="<?= url_com(['situacao' => 'todas'], 'fretes/impressao') ?>">Ver também as já impressas</a>.
      <?php endif; ?></div></div></div>
<?php else: ?>
  <form id="formLote" method="get" action="<?= url('fretes/faturas/imprimir') ?>" target="_blank">
    <input type="hidden" name="ids" id="idsLote">
    <div class="card">
      <div class="card-header bg-transparent d-flex flex-wrap align-items-center gap-2">
        <div class="form-check mb-0 me-auto">
          <input class="form-check-input" type="checkbox" id="marcarTodas">
          <label class="form-check-label" for="marcarTodas">Marcar todas</label>
        </div>
        <span class="small text-body-secondary" id="resumoLote" aria-live="polite"></span>
        <select class="form-select form-select-sm w-auto" name="modelo" aria-label="Modelo de impressão">
          <option value="detalhado">Modelo detalhado</option>
          <option value="vendedor">Modelo por vendedor</option>
          <option value="protocolo">Protocolo (2 vias)</option>
        </select>
        <button class="btn btn-primary btn-sm" type="submit" id="btnLote"><i class="bi bi-printer"></i> Imprimir selecionadas</button>
      </div>
      <div class="table-responsive-cards">
        <table class="table table-sm table-hover align-middle mb-0 tabela-cards">
          <thead><tr>
            <th style="width:2rem"></th><th>Transportadora</th><th>Documento</th><th>Empresa</th>
            <th class="text-end">Fretes</th><th class="text-end">Total a pagar</th><th>Situação</th><th>Impressão</th><th></th>
          </tr></thead>
          <tbody>
          <?php foreach ($faturas as $fa): ?>
            <tr>
              <td><input class="form-check-input sel-fatura" type="checkbox" value="<?= (int) $fa['id'] ?>" <?= $fa['pendente'] ? 'checked' : '' ?>
                         data-total="<?= e($fa['total_a_pagar']) ?>" aria-label="Selecionar fatura <?= e($fa['numero_documento']) ?>"></td>
              <td data-rotulo="Transportadora"><?= e($fa['transportadora']) ?></td>
              <td data-rotulo="Documento" class="fw-medium"><?= e($fa['numero_documento']) ?>
                <div class="text-body-secondary" style="font-size:.75rem">último lançamento <?= e(date('d/m H:i', strtotime($fa['ultimo_lancamento']))) ?></div></td>
              <td data-rotulo="Empresa"><?= e($fa['empresa'] ?? '—') ?></td>
              <td data-rotulo="Fretes" class="text-end numero" title="Digitados no período / total na fatura">
                <?= (int) $fa['qtd_periodo'] ?><?= (int) $fa['qtd_periodo'] !== (int) $fa['qtd_itens'] ? ' <span class="text-body-secondary">de ' . (int) $fa['qtd_itens'] . '</span>' : '' ?></td>
              <td data-rotulo="Total a pagar" class="text-end numero text-nowrap"><?= moeda($fa['total_a_pagar']) ?></td>
              <td data-rotulo="Situação"><span class="badge rounded-pill text-bg-<?= $corStatus[$fa['status']] ?? 'secondary' ?>"><?= e($rotuloStatus[$fa['status']] ?? $fa['status']) ?></span></td>
              <td data-rotulo="Impressão" class="small">
                <?php if (!$fa['qtd_impressoes']): ?>
                  <span class="badge text-bg-warning">falta imprimir</span>
                <?php else: ?>
                  <?php if ($fa['pendente']): ?><span class="badge text-bg-warning" title="Entrou frete nesta fatura depois da última impressão">reimprimir: frete novo</span><br><?php endif; ?>
                  <span class="text-body-secondary"><i class="bi bi-printer-fill"></i> <?= (int) $fa['qtd_impressoes'] ?>x · <?= e(date('d/m H:i', strtotime($fa['ultima_impressao']))) ?>
                    <?= $fa['ultima_impressao_por'] ? '· ' . e($fa['ultima_impressao_por']) : '' ?></span>
                <?php endif; ?>
              </td>
              <td class="text-end"><a class="btn btn-link btn-sm" href="<?= url('fretes/faturas/imprimir?id=' . (int) $fa['id']) ?>" target="_blank"
                     title="Imprimir só esta" aria-label="Imprimir só a fatura <?= e($fa['numero_documento']) ?>"><i class="bi bi-printer"></i></a></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
          <tfoot><tr class="fw-semibold">
            <td colspan="5">Total de todas</td>
            <td class="text-end numero text-nowrap"><?= moeda($totalGeral) ?></td><td colspan="3"></td>
          </tr></tfoot>
        </table>
      </div>
    </div>
    <?php if (count($faturas) >= 300): ?>
      <div class="alert alert-warning mt-2 small">Mostrando as primeiras 300 faturas. Diminua o período.</div>
    <?php endif; ?>
  </form>
<?php endif; ?>
