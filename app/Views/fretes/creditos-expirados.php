<?php
/** @var App\Fretes\Escopo $escopo */
use App\Core\Csrf;

$statusInfo = [
    'expirado' => ['Expirado', 'warning', 'bi-hourglass-bottom'],
    'resgatado_parcial' => ['Resgatado em parte', 'info', 'bi-arrow-counterclockwise'],
    'resgatado' => ['Resgatado', 'success', 'bi-check-circle'],
];
$abas = ['pendentes' => 'Disponíveis para resgate', 'resgatado' => 'Resgatados', 'todos' => 'Todos'];
$podeResgatar = $escopo->veTudo() && App\Fretes\Permissoes::pode('expirados.acoes');

// anos candidatos para processar na mão: os últimos 6 fechados, mais recente primeiro
$anosCandidatos = range($anoAtual - 1, $anoAtual - 6);
?>
<div class="d-flex flex-wrap align-items-end justify-content-between gap-2 mb-3">
  <div>
    <h1 class="h4 mb-0">Créditos expirados</h1>
    <p class="text-body-secondary small mb-0">Saldo positivo expirado na virada do ano — pode ser resgatado no todo ou em parte.</p>
  </div>
</div>

<div class="alert alert-<?= $chaveGeralAtiva ? 'info' : 'secondary' ?> d-flex flex-wrap align-items-center justify-content-between gap-2">
  <span><i class="bi bi-info-circle"></i>
    Regra geral: o crédito <strong><?= $chaveGeralAtiva ? 'expira' : 'não expira' ?></strong> na virada do ano
    (cada vendedor pode ter uma exceção própria em Cadastros → Vendedores).</span>
  <?php if ($podeResgatar): ?>
    <a class="btn btn-sm btn-outline-secondary" href="<?= url('fretes/configuracoes') ?>">Alterar em Configurações</a>
  <?php endif; ?>
</div>

<?php if ($podeResgatar): ?>
  <div class="card mb-3">
    <div class="card-header bg-transparent"><h2 class="h6 mb-0">Processar virada do ano manualmente</h2></div>
    <div class="card-body">
      <p class="small text-body-secondary">
        Normalmente roda sozinho via cron em 01/01. Pode rodar na mão aqui também — é seguro repetir,
        o mesmo ano nunca expira duas vezes.
      </p>
      <form class="row g-2 align-items-end" method="post" action="<?= url('fretes/creditos-expirados/expirar') ?>" id="formExpirar">
        <?= Csrf::campo() ?>
        <div class="col-auto">
          <label class="form-label small mb-1" for="anoExpirar">Ano a expirar</label>
          <select class="form-select form-select-sm" id="anoExpirar" name="ano">
            <?php foreach ($anosCandidatos as $ano): ?>
              <option value="<?= $ano ?>">
                <?= $ano ?><?= in_array($ano, $anosJaProcessados, true) ? ' (já processado)' : '' ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-auto">
          <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalExpirar">
            <i class="bi bi-calendar-check"></i> Processar</button>
        </div>
      </form>
    </div>
  </div>
<?php endif; ?>

<div class="row g-2 mb-3">
  <div class="col-6 col-sm-4">
    <div class="card card-indicador"><div class="card-body py-2">
      <div class="indicador-rotulo">Registros</div>
      <div class="fs-5 fw-semibold numero"><?= number_format((int) $resumo['qtd'], 0, ',', '.') ?></div>
    </div></div>
  </div>
  <div class="col-6 col-sm-4">
    <div class="card card-indicador"><div class="card-body py-2">
      <div class="indicador-rotulo">Total expirado</div>
      <div class="fs-5 fw-semibold numero"><?= moeda($resumo['total']) ?></div>
    </div></div>
  </div>
  <div class="col-12 col-sm-4">
    <div class="card card-indicador card-destaque"><div class="card-body py-2">
      <div class="indicador-rotulo">Disponível para resgate</div>
      <div class="fs-5 fw-semibold numero"><?= moeda($resumo['disponivel']) ?></div>
    </div></div>
  </div>
</div>

<nav class="abas-status mb-3" aria-label="Filtrar por situação">
  <?php foreach ($abas as $k => $rot): ?>
    <a class="btn btn-sm <?= $status === $k ? 'btn-secondary' : 'btn-outline-secondary' ?>"
       href="<?= url('fretes/creditos-expirados?status=' . $k) ?>" <?= $status === $k ? 'aria-current="page"' : '' ?>><?= e($rot) ?></a>
  <?php endforeach; ?>
</nav>

<div class="card">
  <?php if (!$lista): ?>
    <div class="vazio"><i class="bi bi-inbox"></i>Nenhum registro nesta situação.</div>
  <?php else: ?>
    <div class="table-responsive-cards">
      <table class="table table-sm table-hover align-middle mb-0 tabela-cards">
        <thead><tr>
          <th>Vendedor</th><th>Ano</th><th>Expirado em</th>
          <th class="text-end">Valor</th><th class="text-end">Resgatado</th><th class="text-end">Disponível</th>
          <th>Status</th><th></th>
        </tr></thead>
        <tbody>
        <?php foreach ($lista as $ce): [$stRot, $stCor, $stIco] = $statusInfo[$ce['status']]; ?>
          <tr>
            <td data-rotulo="Vendedor">
              <span class="fw-medium"><?= e($ce['vendedor']) ?></span>
              <div class="text-body-secondary" style="font-size:.75rem"><?= e($ce['gestor']) ?></div>
            </td>
            <td data-rotulo="Ano"><?= (int) $ce['ano_referencia'] ?></td>
            <td data-rotulo="Expirado em" class="text-nowrap"><?= data_br($ce['expirado_em']) ?></td>
            <td data-rotulo="Valor" class="text-end numero text-nowrap"><?= moeda($ce['valor']) ?></td>
            <td data-rotulo="Resgatado" class="text-end numero text-nowrap"><?= moeda($ce['valor_resgatado']) ?></td>
            <td data-rotulo="Disponível" class="text-end numero text-nowrap fw-medium"><?= moeda($ce['disponivel_resgate']) ?></td>
            <td data-rotulo="Status"><span class="badge rounded-pill text-bg-<?= $stCor ?>"><i class="bi <?= $stIco ?>"></i> <?= $stRot ?></span></td>
            <td class="text-end text-nowrap acoes">
              <?php if ($podeResgatar && (float) $ce['disponivel_resgate'] > 0.004): ?>
                <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalResgatar"
                        data-id="<?= (int) $ce['id'] ?>" data-vendedor="<?= e($ce['vendedor']) ?>"
                        data-disponivel="<?= e($ce['disponivel_resgate']) ?>">
                  <i class="bi bi-arrow-counterclockwise"></i> Resgatar</button>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php if ($podeResgatar): ?>
<!-- Modal: resgatar -->
<div class="modal fade" id="modalResgatar" tabindex="-1" aria-labelledby="modalResgatarTitulo" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form class="modal-content" method="post" action="<?= url('fretes/creditos-expirados/resgatar') ?>">
      <?= Csrf::campo() ?>
      <input type="hidden" name="id" id="resgatarId">
      <div class="modal-header">
        <h2 class="modal-title h5" id="modalResgatarTitulo">Resgatar crédito expirado</h2>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
      </div>
      <div class="modal-body">
        <p class="mb-2">Vendedor: <strong id="resgatarVendedor"></strong></p>
        <p class="small text-body-secondary mb-3">Disponível para resgate: <strong id="resgatarDisponivel" class="numero"></strong></p>
        <label class="form-label" for="resgatarValor">Valor a resgatar <span class="text-danger">*</span></label>
        <div class="input-group mb-3">
          <span class="input-group-text">R$</span>
          <input class="form-control text-end campo-moeda" id="resgatarValor" name="valor" inputmode="decimal"
                 placeholder="0,00" required autocomplete="off">
        </div>
        <label class="form-label" for="resgatarMotivo">Motivo (opcional)</label>
        <textarea class="form-control" id="resgatarMotivo" name="motivo" rows="2" maxlength="200"
                  placeholder="Ex.: negociação com o vendedor para manter o relacionamento com o cliente"></textarea>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="submit" class="btn btn-primary">Confirmar resgate</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal: confirmar expiração manual -->
<div class="modal fade" id="modalExpirar" tabindex="-1" aria-labelledby="modalExpirarTitulo" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h2 class="modal-title h5" id="modalExpirarTitulo">Confirmar processamento</h2>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
      </div>
      <div class="modal-body">
        Expirar o saldo positivo de <strong id="expirarAnoTexto"></strong> para todos os vendedores sujeitos à regra?
        Essa ação não pode ser desfeita (mas é seguro repetir sem dobrar o valor).
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-primary" id="confirmarExpirar">Confirmar</button>
      </div>
    </div>
  </div>
</div>
<script>
  document.getElementById('modalResgatar').addEventListener('show.bs.modal', function (ev) {
    var b = ev.relatedTarget;
    document.getElementById('resgatarId').value = b.getAttribute('data-id');
    document.getElementById('resgatarVendedor').textContent = b.getAttribute('data-vendedor');
    var disp = parseFloat(b.getAttribute('data-disponivel'));
    document.getElementById('resgatarDisponivel').textContent =
      disp.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
  });
  document.getElementById('modalExpirar').addEventListener('show.bs.modal', function () {
    document.getElementById('expirarAnoTexto').textContent = document.getElementById('anoExpirar').value;
  });
  document.getElementById('confirmarExpirar').addEventListener('click', function () {
    document.getElementById('formExpirar').submit();
  });
</script>
<?php endif; ?>
