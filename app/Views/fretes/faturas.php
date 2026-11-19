<?php
$rotuloStatus = [
    'aberta' => 'Aberta', 'conferida' => 'Conferida', 'divergente' => 'Divergente',
    'enviada_pagto' => 'Enviada p/ pagto', 'paga' => 'Paga',
];
$corStatus = [
    'aberta' => 'secondary', 'conferida' => 'info', 'divergente' => 'danger',
    'enviada_pagto' => 'primary', 'paga' => 'success',
];
// "Vencidas" e "Aberta" olham o vencimento (não pagas, antes de hoje / de hoje em diante); as demais, o status
$abas = ['vencidas' => 'Vencidas'] + $rotuloStatus + ['todas' => 'Todas'];
$filtrosAtivos = $f['transportadora'] || $f['empresa'] || $f['busca'] !== '';
?>
<div class="d-flex flex-wrap align-items-end justify-content-between gap-2 mb-3">
  <div>
    <h1 class="h4 mb-0">Faturas</h1>
    <p class="text-body-secondary small mb-0">Conferência e pagamento dos boletos das transportadoras.</p>
  </div>
  <button class="btn btn-outline-secondary btn-sm d-md-none" type="button" data-bs-toggle="collapse"
          data-bs-target="#filtrosFaturas" aria-expanded="false" aria-controls="filtrosFaturas">
    <i class="bi bi-funnel"></i> Filtros<?= $filtrosAtivos ? ' <span class="badge text-bg-primary">•</span>' : '' ?>
  </button>
</div>

<nav class="abas-status mb-3" aria-label="Filtrar por situação">
  <?php foreach ($abas as $k => $rot): ?>
    <a class="btn btn-sm <?= $f['status'] === $k ? 'btn-secondary' : 'btn-outline-secondary' ?>"
       href="<?= url('fretes/faturas?status=' . $k) ?>" <?= $f['status'] === $k ? 'aria-current="page"' : '' ?>><?= e($rot) ?></a>
  <?php endforeach; ?>
</nav>

<form class="collapse d-md-block mb-3" id="filtrosFaturas" method="get" action="<?= url('fretes/faturas') ?>">
  <input type="hidden" name="status" value="<?= e($f['status']) ?>">
  <div class="card card-body py-2 px-3 barra-filtros">
    <div class="row g-2 align-items-end">
      <div class="col-12 col-md-3">
        <label class="form-label small mb-1" for="busca">Documento</label>
        <input class="form-control form-control-sm" id="busca" name="busca" value="<?= e($f['busca']) ?>"
               placeholder="Número do boleto" inputmode="search">
      </div>
      <div class="col-12 col-md-4 col-xl">
        <label class="form-label small mb-1" for="transportadora">Transportadora</label>
        <select class="form-select form-select-sm busca-select" id="transportadora" name="transportadora" data-placeholder="Todas">
          <option value="">Todas</option>
          <?php foreach ($opcoes['transportadoras'] as $o): ?>
            <option value="<?= $o['id'] ?>" <?= $f['transportadora'] === (int) $o['id'] ? 'selected' : '' ?>><?= e($o['nome']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-6 col-md-3 col-xl-auto">
        <label class="form-label small mb-1" for="empresa">Empresa</label>
        <select class="form-select form-select-sm" id="empresa" name="empresa">
          <option value="">Todas</option>
          <?php foreach ($opcoes['empresas'] as $o): ?>
            <option value="<?= $o['id'] ?>" <?= $f['empresa'] === (int) $o['id'] ? 'selected' : '' ?>><?= e($o['nome']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-12 col-md-2 col-xl-auto d-flex gap-2">
        <button class="btn btn-primary btn-sm flex-grow-1" type="submit"><i class="bi bi-search"></i> Filtrar</button>
        <a class="btn btn-outline-secondary btn-sm" href="<?= url('fretes/faturas?status=' . $f['status']) ?>" aria-label="Limpar filtros"><i class="bi bi-x-lg"></i></a>
      </div>
    </div>
  </div>
</form>

<div class="row g-2 mb-3">
  <div class="col-6 col-sm-4">
    <div class="card card-indicador"><div class="card-body py-2">
      <div class="indicador-rotulo">Faturas</div>
      <div class="fs-5 fw-semibold numero"><?= number_format($res['total'], 0, ',', '.') ?></div>
    </div></div>
  </div>
  <div class="col-6 col-sm-4">
    <div class="card card-indicador card-destaque"><div class="card-body py-2">
      <div class="indicador-rotulo">Total a pagar</div>
      <div class="fs-5 fw-semibold numero"><?= moeda($res['somas']['total']) ?></div>
    </div></div>
  </div>
  <div class="col-12 col-sm-4">
    <div class="card card-indicador"><div class="card-body py-2">
      <div class="indicador-rotulo"><i class="bi bi-exclamation-triangle-fill status-critico"></i> Vencidas</div>
      <div class="fs-5 fw-semibold numero <?= (int) $res['somas']['vencidas'] ? 'status-critico-texto' : '' ?>">
        <?= (int) $res['somas']['vencidas'] ?></div>
    </div></div>
  </div>
</div>

<div class="card">
  <?php if (!$res['linhas']): ?>
    <div class="vazio"><i class="bi bi-inbox"></i>Nenhuma fatura encontrada com esses filtros.</div>
  <?php else: ?>
    <div class="table-responsive-cards">
      <table class="table table-sm table-hover align-middle mb-0 tabela-cards">
        <thead><tr>
          <th>Documento</th><th>Transportadora</th><th>Empresa</th><th>Vencimento</th>
          <th class="text-end">Itens</th><th class="text-end">Total a pagar</th><th>Status</th><th></th>
        </tr></thead>
        <tbody>
        <?php foreach ($res['linhas'] as $fa): ?>
          <tr>
            <td data-rotulo="Documento">
              <span class="fw-medium"><?= e($fa['numero_documento']) ?></span>
              <div class="text-body-secondary" style="font-size:.75rem">entrada <?= data_br($fa['data_entrada']) ?></div>
            </td>
            <td data-rotulo="Transportadora"><?= e($fa['transportadora']) ?></td>
            <td data-rotulo="Empresa"><?= e($fa['empresa'] ?? '—') ?></td>
            <td data-rotulo="Vencimento" class="text-nowrap">
              <?php if ((int) $fa['vencida']): ?><i class="bi bi-exclamation-triangle-fill status-critico"></i> <?php endif; ?>
              <?= data_br($fa['vencimento']) ?>
            </td>
            <td data-rotulo="Itens" class="text-end"><?= (int) $fa['qtd_itens'] ?></td>
            <td data-rotulo="Total a pagar" class="text-end numero fw-medium text-nowrap"><?= moeda($fa['total_a_pagar']) ?></td>
            <td data-rotulo="Status"><span class="badge rounded-pill text-bg-<?= $corStatus[$fa['status']] ?? 'secondary' ?>"><?= e($rotuloStatus[$fa['status']] ?? $fa['status']) ?></span></td>
            <td class="text-end text-nowrap acoes">
              <a class="btn btn-sm btn-outline-secondary" href="<?= url('fretes/faturas/ver?id=' . (int) $fa['id']) ?>">
                <i class="bi bi-eye"></i><span class="d-sm-none ms-1">Ver</span></a>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <?php if ($paginas > 1): ?>
      <nav class="card-footer bg-transparent d-flex justify-content-between align-items-center" aria-label="Páginas">
        <span class="small text-body-secondary">Página <?= $paginaAtual ?> de <?= $paginas ?></span>
        <ul class="pagination pagination-sm mb-0">
          <li class="page-item <?= $paginaAtual <= 1 ? 'disabled' : '' ?>">
            <a class="page-link" href="<?= e(url_com(['pagina' => $paginaAtual - 1], 'fretes/faturas')) ?>" aria-label="Anterior">‹</a></li>
          <?php for ($i = max(1, $paginaAtual - 2); $i <= min($paginas, $paginaAtual + 2); $i++): ?>
            <li class="page-item <?= $i === $paginaAtual ? 'active' : '' ?>">
              <a class="page-link" href="<?= e(url_com(['pagina' => $i], 'fretes/faturas')) ?>"><?= $i ?></a></li>
          <?php endfor; ?>
          <li class="page-item <?= $paginaAtual >= $paginas ? 'disabled' : '' ?>">
            <a class="page-link" href="<?= e(url_com(['pagina' => $paginaAtual + 1], 'fretes/faturas')) ?>" aria-label="Próxima">›</a></li>
        </ul>
      </nav>
    <?php endif; ?>
  <?php endif; ?>
</div>
