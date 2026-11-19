<?php
/** @var App\Fretes\Escopo $escopo */
$origemInfo = [
    'frete'              => ['Frete',              'bi-truck',                 'secondary'],
    'saldo_inicial'       => ['Saldo inicial',       'bi-flag',                  'secondary'],
    'transferencia'       => ['Transferência',       'bi-arrow-left-right',      'info'],
    'uso_credito'         => ['Uso de crédito',      'bi-cash-coin',             'primary'],
    'despesa'             => ['Despesa',             'bi-receipt',               'danger'],
    'expiracao'           => ['Expiração',           'bi-hourglass-bottom',      'warning'],
    'resgate_expirado'    => ['Resgate',             'bi-arrow-counterclockwise','success'],
    'pagamento_negativo'  => ['Pagamento',           'bi-wallet2',               'success'],
    'ajuste'              => ['Ajuste',              'bi-sliders',               'secondary'],
    'estorno'             => ['Estorno',             'bi-arrow-return-left',     'danger'],
];
$ehVendedor = $escopo->perfil === 'vendedor';
$vendedorEscolhido = null;
foreach ($vendedores as $v) {
    if ((int) $v['id'] === (int) $vendedorId) {
        $vendedorEscolhido = $v;
        break;
    }
}
?>
<div class="d-flex flex-wrap align-items-end justify-content-between gap-2 mb-3">
  <div>
    <h1 class="h4 mb-0">Extrato</h1>
    <p class="text-body-secondary small mb-0">Fretes e movimentos de crédito numa linha do tempo.</p>
  </div>
</div>

<?php if ($escopo->semVinculo()): ?>
  <div class="alert alert-warning d-flex gap-2"><i class="bi bi-link-45deg fs-5"></i>
    <div>Seu login ainda não está vinculado a um cadastro de <?= e($escopo->perfil) ?>. Peça ao administrador para fazer o vínculo.</div>
  </div>
<?php elseif (!$vendedores): ?>
  <div class="vazio"><i class="bi bi-inbox"></i>Nenhum vendedor disponível para consulta.</div>
<?php else: ?>

<form class="mb-3" method="get" action="<?= url('fretes/extrato') ?>">
  <div class="card card-body py-2 px-3 barra-filtros">
    <div class="row g-2 align-items-end">
      <?php if (!$ehVendedor && count($vendedores) > 1): ?>
        <div class="col-12 col-md-5 col-xl-4">
          <label class="form-label small mb-1" for="vendedor">Vendedor</label>
          <select class="form-select form-select-sm busca-select" id="vendedor" name="vendedor" data-placeholder="Escolha um vendedor" required>
            <option value="">Escolha um vendedor…</option>
            <?php $g = null; foreach ($vendedores as $o):
                if ($g !== $o['gestor']): if ($g !== null) echo '</optgroup>'; $g = $o['gestor']; ?>
                <optgroup label="<?= e($g) ?>">
            <?php endif; ?>
              <option value="<?= (int) $o['id'] ?>" <?= $vendedorId === (int) $o['id'] ? 'selected' : '' ?>><?= e($o['nome']) ?></option>
            <?php endforeach; if ($g !== null) echo '</optgroup>'; ?>
          </select>
        </div>
      <?php endif; ?>
      <div class="col-6 col-md-2 col-xl-auto">
        <label class="form-label small mb-1" for="de">De</label>
        <input class="form-control form-control-sm" type="date" id="de" name="de" value="<?= e($de) ?>">
      </div>
      <div class="col-6 col-md-2 col-xl-auto">
        <label class="form-label small mb-1" for="ate">Até</label>
        <input class="form-control form-control-sm" type="date" id="ate" name="ate" value="<?= e($ate) ?>">
      </div>
      <div class="col-12 col-md-2 col-xl-auto d-flex gap-2">
        <button class="btn btn-primary btn-sm flex-grow-1" type="submit"><i class="bi bi-search"></i> Filtrar</button>
      </div>
    </div>
  </div>
</form>

<?php if (!$vendedorId): ?>
  <div class="vazio"><i class="bi bi-person-lines-fill"></i>Escolha um vendedor para ver o extrato.</div>
<?php else: ?>

  <?php if ($saldo): ?>
    <div class="row g-2 mb-3">
      <?php foreach ([['Saldo atual', $saldo['saldo'], 'bi-wallet2', false],
                      ['Reservado', $saldo['reservado'], 'bi-bookmark-check', false],
                      ['Disponível', $saldo['disponivel'], 'bi-piggy-bank', true]] as [$rot, $val, $ico, $destaque]): ?>
        <div class="col-6 col-lg-3">
          <div class="card card-indicador <?= $destaque ? 'card-destaque' : '' ?>"><div class="card-body py-2">
            <div class="indicador-rotulo"><i class="bi <?= $ico ?>"></i> <?= $rot ?></div>
            <div class="fs-5 fw-semibold numero <?= classe_sinal($val) ?>"><?= moeda($val) ?></div>
          </div></div>
        </div>
      <?php endforeach; ?>
      <div class="col-6 col-lg-3">
        <div class="card card-indicador"><div class="card-body py-2">
          <div class="indicador-rotulo"><i class="bi bi-hourglass-split"></i> Aguardando fatura</div>
          <div class="fs-5 fw-semibold numero"><?= number_format((float) $saldo['fretes_aguardando_fatura'], 0, ',', '.') ?></div>
        </div></div>
      </div>
    </div>
  <?php endif; ?>

  <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
    <h2 class="titulo-secao mb-0">
      <?= $ehVendedor ? 'Seu extrato' : e(($vendedorEscolhido['nome'] ?? '') . ' — extrato') ?>
    </h2>
    <span class="small text-body-secondary">
      Saldo em <?= data_br($de) ?> (antes do período): <strong class="numero <?= classe_sinal($extrato['saldo_anterior']) ?>"><?= moeda($extrato['saldo_anterior']) ?></strong>
    </span>
  </div>

  <div class="card">
    <?php if (!$extrato['linhas']): ?>
      <div class="vazio"><i class="bi bi-inbox"></i>Nenhum lançamento neste período.</div>
    <?php else: ?>
      <div class="table-responsive-cards">
        <table class="table table-sm table-hover align-middle mb-0 tabela-cards">
          <thead><tr>
            <th>Data</th><th>Origem</th><th>Descrição</th>
            <th class="text-end">Valor</th><th class="text-end">Saldo</th>
          </tr></thead>
          <tbody>
          <?php foreach ($extrato['linhas'] as $l): [$rot, $ico, $cor] = $origemInfo[$l['origem']] ?? [$l['origem'], 'bi-dot', 'secondary']; ?>
            <tr>
              <td data-rotulo="Data" class="text-nowrap"><?= data_br($l['data_ref']) ?></td>
              <td data-rotulo="Origem">
                <span class="badge rounded-pill text-bg-<?= $cor ?>"><i class="bi <?= $ico ?>"></i> <?= e($rot) ?></span>
              </td>
              <td data-rotulo="Descrição">
                <?php if ($l['origem'] === 'frete' && $escopo->veTudo() && App\Fretes\Permissoes::pode('fretes.lancar')): ?>
                  <a href="<?= url('fretes/lancamentos/editar?id=' . (int) $l['origem_id']) ?>"><?= e($l['descricao']) ?></a>
                <?php else: ?>
                  <?= e($l['descricao']) ?>
                <?php endif; ?>
              </td>
              <td data-rotulo="Valor" class="text-end numero text-nowrap fw-medium <?= classe_sinal($l['valor']) ?>"><?= moeda($l['valor']) ?></td>
              <td data-rotulo="Saldo" class="text-end numero text-nowrap <?= classe_sinal($l['saldo_apos']) ?>"><?= moeda($l['saldo_apos']) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

<?php endif; ?>
<?php endif; ?>
