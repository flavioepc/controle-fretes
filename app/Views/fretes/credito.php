<?php
/** @var App\Fretes\Escopo $escopo */
use App\Core\Csrf;

$statusInfo = [
    'pendente'  => ['Aguardando aprovação', 'warning',   'bi-hourglass-split'],
    'aprovada'  => ['Aprovado · reservado', 'primary',   'bi-check-circle'],
    'utilizada' => ['Utilizado',            'success',   'bi-bag-check'],
    'recusada'  => ['Recusado',             'danger',    'bi-x-circle'],
    'cancelada' => ['Cancelado',            'secondary', 'bi-slash-circle'],
];
$abas = ['abertas' => 'Em aberto', 'pendente' => 'Pendentes', 'aprovada' => 'Aprovados',
         'utilizada' => 'Utilizados', 'recusada' => 'Recusados', 'cancelada' => 'Cancelados', 'todas' => 'Todos'];
$tipoTexto = ['frete_gratis' => 'Frete grátis', 'outro' => 'Outro uso'];
$ehVendedor = $escopo->perfil === 'vendedor';
$meuSaldo = $ehVendedor ? ($vendedores[0] ?? null) : null;
// botão e formulário de pedido: precisa da permissão (o saldo do vendedor aparece de qualquer jeito)
$podePedir = $vendedores && App\Fretes\Permissoes::pode('credito.solicitar');
$erro = fn (string $c) => $erros[$c] ?? null;
$cls = fn (string $c) => isset($erros[$c]) ? ' is-invalid' : '';
$abrirForm = !empty($erros);
?>
<div class="d-flex flex-wrap align-items-end justify-content-between gap-2 mb-3">
  <div>
    <h1 class="h4 mb-0">Uso de crédito</h1>
    <p class="text-body-secondary small mb-0">
      <?= $ehVendedor ? 'Peça frete grátis para fechar uma venda ou outro uso do seu crédito.'
                     : 'Pedidos de frete grátis e de uso do crédito dos vendedores.' ?></p>
  </div>
  <?php if ($podePedir): ?>
    <button class="btn btn-primary btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#novo"
            aria-expanded="<?= $abrirForm ? 'true' : 'false' ?>" aria-controls="novo">
      <i class="bi bi-plus-lg"></i> Novo pedido</button>
  <?php endif; ?>
</div>

<?php if ($escopo->semVinculo()): ?>
  <div class="alert alert-warning">Seu login ainda não está vinculado a um cadastro. Peça ao administrador.</div>
<?php endif; ?>

<?php if ($meuSaldo): ?>
  <div class="row g-2 mb-3">
    <?php foreach ([['Seu saldo', $meuSaldo['saldo'], 'bi-wallet2'],
                    ['Reservado', $meuSaldo['reservado'], 'bi-bookmark-check'],
                    ['Disponível para pedir', $meuSaldo['disponivel'], 'bi-piggy-bank']] as $i => [$rot, $val, $ico]): ?>
      <div class="col-12 col-sm-4">
        <div class="card card-indicador <?= $i === 2 ? 'card-destaque' : '' ?>"><div class="card-body py-2">
          <div class="indicador-rotulo"><i class="bi <?= $ico ?>"></i> <?= $rot ?></div>
          <div class="fs-4 fw-semibold numero <?= classe_sinal($val) ?>"><?= moeda($val) ?></div>
        </div></div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<!-- Novo pedido -->
<?php if ($podePedir): ?>
<div class="collapse <?= $abrirForm ? 'show' : '' ?> mb-3" id="novo">
  <form class="card" method="post" action="<?= url('fretes/credito/solicitar') ?>" id="formCredito" novalidate>
    <?= Csrf::campo() ?>
    <div class="card-body">
      <h2 class="h6 mb-3">Novo pedido de crédito</h2>
      <div class="row g-3">
        <?php if (count($vendedores) === 1): $v = $vendedores[0]; ?>
          <input type="hidden" name="vendedor_id" id="vendedor_id" value="<?= (int) $v['id'] ?>"
                 data-disponivel="<?= e($v['disponivel']) ?>">
        <?php else: ?>
          <div class="col-12 col-md-6">
            <label class="form-label" for="vendedor_id">Vendedor <span class="text-danger">*</span></label>
            <select class="form-select busca-select<?= $cls('vendedor_id') ?>" id="vendedor_id" name="vendedor_id" required>
              <option value="">Digite para buscar…</option>
              <?php foreach ($vendedores as $v): ?>
                <option value="<?= (int) $v['id'] ?>" data-disponivel="<?= e($v['disponivel']) ?>"
                        <?= (string) antigo('vendedor_id') === (string) $v['id'] ? 'selected' : '' ?>>
                  <?= e($v['nome']) ?><?= $escopo->veTudo() ? ' (' . e($v['gestor']) . ')' : '' ?> — disponível <?= moeda($v['disponivel']) ?>
                </option>
              <?php endforeach; ?>
            </select>
            <div class="invalid-feedback d-block"><?= e($erro('vendedor_id')) ?></div>
          </div>
        <?php endif; ?>

        <div class="col-12 <?= count($vendedores) === 1 ? '' : 'col-md-6' ?>">
          <span class="form-label d-block">Tipo de uso</span>
          <div class="btn-group w-100" role="group" aria-label="Tipo de uso">
            <input type="radio" class="btn-check" name="tipo" id="tipoFrete" value="frete_gratis"
                   <?= antigo('tipo', 'frete_gratis') !== 'outro' ? 'checked' : '' ?>>
            <label class="btn btn-outline-primary" for="tipoFrete"><i class="bi bi-gift"></i> Frete grátis</label>
            <input type="radio" class="btn-check" name="tipo" id="tipoOutro" value="outro"
                   <?= antigo('tipo') === 'outro' ? 'checked' : '' ?>>
            <label class="btn btn-outline-primary" for="tipoOutro"><i class="bi bi-cash-coin"></i> Outro uso</label>
          </div>
          <div class="form-text" id="ajudaTipo">Frete grátis: o valor fica reservado e é baixado quando o frete for lançado.</div>
        </div>

        <div class="col-6 col-md-3">
          <label class="form-label" for="valor">Valor <span class="text-danger">*</span></label>
          <div class="input-group">
            <span class="input-group-text">R$</span>
            <input class="form-control text-end campo-moeda<?= $cls('valor') ?>" id="valor" name="valor" inputmode="decimal"
                   value="<?= e(antigo('valor')) ?>" placeholder="0,00" required autocomplete="off">
            <div class="invalid-feedback"><?= e($erro('valor')) ?></div>
          </div>
          <div class="form-text" id="ajudaValor">&nbsp;</div>
        </div>
        <div class="col-6 col-md-3">
          <label class="form-label" for="validade">Válido até</label>
          <input type="date" class="form-control<?= $cls('validade') ?>" id="validade" name="validade"
                 value="<?= e(antigo('validade')) ?>" min="<?= date('Y-m-d') ?>">
          <div class="invalid-feedback"><?= e($erro('validade')) ?></div>
        </div>
        <div class="col-12 col-md-6">
          <label class="form-label" for="cliente">Cliente <span class="text-danger" id="clienteObrig">*</span></label>
          <input class="form-control<?= $cls('cliente') ?>" id="cliente" name="cliente" maxlength="120"
                 value="<?= e(antigo('cliente')) ?>" placeholder="Cliente ou prospect da negociação">
          <div class="invalid-feedback"><?= e($erro('cliente')) ?></div>
        </div>
        <div class="col-12">
          <label class="form-label" for="justificativa">Justificativa <span class="text-danger">*</span></label>
          <textarea class="form-control<?= $cls('justificativa') ?>" id="justificativa" name="justificativa" rows="2"
                    maxlength="500" required placeholder="Ex.: primeiro pedido do cliente, concorrente ofereceu frete grátis"><?= e(antigo('justificativa')) ?></textarea>
          <div class="invalid-feedback"><?= e($erro('justificativa')) ?></div>
        </div>
      </div>
    </div>
    <div class="card-footer bg-transparent d-flex justify-content-end gap-2">
      <button class="btn btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#novo">Cancelar</button>
      <button class="btn btn-primary" type="submit"><i class="bi bi-send"></i> Enviar para aprovação</button>
    </div>
  </form>
</div>
<?php endif; ?>

<!-- Aguardando a decisão deste usuário -->
<?php if ($aguardandoMim): ?>
  <h2 class="titulo-secao">Aguardando sua aprovação <span><?= count($aguardandoMim) ?> pedido(s)</span></h2>
  <div class="row g-3 mb-4">
    <?php foreach ($aguardandoMim as $s): $faltaSaldo = (float) $s['valor'] > (float) $s['disponivel']; ?>
      <div class="col-12 col-lg-6">
        <div class="card h-100 cartao-pedido pendente">
          <div class="card-body">
            <div class="d-flex justify-content-between gap-2 mb-2">
              <div class="min-w-0">
                <div class="fw-semibold text-truncate"><?= e($s['vendedor']) ?></div>
                <div class="small text-body-secondary">#<?= (int) $s['id'] ?> · <?= e($tipoTexto[$s['tipo']]) ?>
                  · <?= e(date('d/m H:i', strtotime($s['solicitado_em']))) ?></div>
              </div>
              <div class="text-end">
                <div class="fs-4 fw-semibold numero text-nowrap"><?= moeda($s['valor']) ?></div>
                <div class="small text-nowrap <?= $faltaSaldo ? 'status-critico-texto' : 'text-body-secondary' ?>">
                  <?php if ($faltaSaldo): ?><i class="bi bi-exclamation-triangle-fill"></i><?php endif; ?>
                  disponível <?= moeda($s['disponivel']) ?></div>
              </div>
            </div>
            <?php if ($s['cliente']): ?><div class="small"><i class="bi bi-building text-body-secondary"></i> <?= e($s['cliente']) ?></div><?php endif; ?>
            <p class="small mb-0 mt-1 text-body-secondary"><?= e($s['justificativa']) ?></p>
            <?php if ($s['validade']): ?><div class="small mt-1"><i class="bi bi-calendar"></i> válido até <?= data_br($s['validade']) ?></div><?php endif; ?>
          </div>
          <div class="card-footer bg-transparent d-flex gap-2">
            <button type="button" class="btn btn-outline-danger btn-sm flex-grow-1" data-bs-toggle="modal" data-bs-target="#modalRecusar"
                    data-id="<?= (int) $s['id'] ?>" data-resumo="#<?= (int) $s['id'] ?> · <?= e($s['vendedor']) ?> · <?= moeda($s['valor']) ?>">
              <i class="bi bi-x-lg"></i> Recusar</button>
            <form method="post" action="<?= url('fretes/credito/decidir') ?>" class="flex-grow-1 d-flex">
              <?= Csrf::campo() ?>
              <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
              <input type="hidden" name="acao" value="aprovar">
              <button class="btn btn-success btn-sm flex-grow-1" type="submit" <?= $faltaSaldo ? 'disabled title="Saldo disponível insuficiente"' : '' ?>>
                <i class="bi bi-check-lg"></i> Aprovar</button>
            </form>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<!-- Histórico -->
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
  <h2 class="titulo-secao mb-0"><?= $ehVendedor ? 'Seus pedidos' : 'Pedidos' ?></h2>
  <nav class="abas-status" aria-label="Filtrar por situação">
    <?php foreach ($abas as $k => $rot): ?>
      <a class="btn btn-sm <?= $status === $k ? 'btn-secondary' : 'btn-outline-secondary' ?>"
         href="<?= url('fretes/credito?status=' . $k) ?>" <?= $status === $k ? 'aria-current="page"' : '' ?>><?= $rot ?></a>
    <?php endforeach; ?>
  </nav>
</div>

<div class="card">
  <?php if (!$lista): ?>
    <div class="vazio"><i class="bi bi-inbox"></i>Nenhum pedido nesta situação.</div>
  <?php else: ?>
    <ul class="list-group list-group-flush">
      <?php foreach ($lista as $s): [$stRot, $stCor, $stIco] = $statusInfo[$s['status']]; ?>
        <li class="list-group-item lista-pedido">
          <div class="d-flex flex-wrap gap-2 align-items-start">
            <div class="flex-grow-1 min-w-0">
              <div class="d-flex flex-wrap align-items-center gap-2">
                <span class="fw-medium"><?= e($s['vendedor']) ?></span>
                <span class="badge rounded-pill text-bg-<?= $stCor ?>"><i class="bi <?= $stIco ?>"></i> <?= $stRot ?></span>
                <span class="small text-body-secondary">#<?= (int) $s['id'] ?> · <?= e($tipoTexto[$s['tipo']]) ?></span>
              </div>
              <div class="small text-body-secondary mt-1">
                <?= $s['cliente'] ? e($s['cliente']) . ' · ' : '' ?><?= e($s['justificativa']) ?>
              </div>
              <div class="small mt-1 text-body-secondary">
                Pedido em <?= e(date('d/m/Y H:i', strtotime($s['solicitado_em']))) ?>
                <?php if ($s['decidido_em']): ?> · decidido em <?= e(date('d/m/Y H:i', strtotime($s['decidido_em']))) ?><?php endif; ?>
                <?php if ($s['motivo_decisao']): ?> · “<?= e($s['motivo_decisao']) ?>”<?php endif; ?>
                <?php if ($s['frete_id']): ?> · usado na NF <strong><?= e($s['frete_nf']) ?></strong><?php endif; ?>
                <?php if ($s['validade'] && $s['status'] === 'aprovada'): ?>
                  · <span class="<?= $s['validade'] < date('Y-m-d') ? 'status-critico-texto' : '' ?>">válido até <?= data_br($s['validade']) ?></span>
                <?php endif; ?>
              </div>
            </div>
            <div class="text-end ms-auto">
              <div class="fw-semibold numero text-nowrap"><?= moeda($s['valor']) ?></div>
              <?php if ($escopo->podeCancelar($s) && !($s['tipo'] === 'outro' && $s['status'] !== 'pendente') && !$s['frete_id']): ?>
                <button type="button" class="btn btn-link btn-sm text-danger p-0 mt-1" data-bs-toggle="modal" data-bs-target="#modalCancelar"
                        data-id="<?= (int) $s['id'] ?>" data-resumo="#<?= (int) $s['id'] ?> · <?= e($s['vendedor']) ?> · <?= moeda($s['valor']) ?>">
                  Cancelar</button>
              <?php endif; ?>
            </div>
          </div>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</div>

<!-- Modais (sem alert/confirm do navegador) -->
<?php foreach (['Recusar' => ['decidir', 'Recusar pedido', 'Motivo da recusa', true, 'danger'],
                'Cancelar' => ['cancelar', 'Cancelar pedido', 'Motivo (opcional)', false, 'danger']] as $nome => [$rota, $tit, $rotMotivo, $obrig, $cor]): ?>
<div class="modal fade" id="modal<?= $nome ?>" tabindex="-1" aria-labelledby="modal<?= $nome ?>Titulo" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form class="modal-content" method="post" action="<?= url('fretes/credito/' . $rota) ?>">
      <?= Csrf::campo() ?>
      <input type="hidden" name="id" class="campo-id">
      <?php if ($rota === 'decidir'): ?><input type="hidden" name="acao" value="recusar"><?php endif; ?>
      <div class="modal-header">
        <h2 class="modal-title h5" id="modal<?= $nome ?>Titulo"><?= $tit ?></h2>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
      </div>
      <div class="modal-body">
        <p class="campo-resumo fw-medium"></p>
        <label class="form-label" for="motivo<?= $nome ?>"><?= $rotMotivo ?></label>
        <textarea class="form-control" id="motivo<?= $nome ?>" name="motivo" rows="2" maxlength="255" <?= $obrig ? 'required' : '' ?>></textarea>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Voltar</button>
        <button type="submit" class="btn btn-<?= $cor ?>"><?= $tit ?></button>
      </div>
    </form>
  </div>
</div>
<?php endforeach; ?>
