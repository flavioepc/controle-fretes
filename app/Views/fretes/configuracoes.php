<?php
use App\Core\Csrf;

$expira = $config['credito_expira_virada_ano'] ?? null;
$alerta = $config['alerta_vencimento_dias'] ?? null;
$pedidoMinimo = $config['frete_isento_pedido_minimo'] ?? null;
$erro = fn (string $c) => $erros[$c] ?? null;
$cls = fn (string $c) => isset($erros[$c]) ? ' is-invalid' : '';
$quemAtualizou = function (?array $c): string {
    if (!$c || !$c['atualizado_por_nome']) {
        return '';
    }
    return 'Atualizado por ' . $c['atualizado_por_nome'] . ' em ' . date('d/m/Y H:i', strtotime($c['atualizado_em']));
};
?>
<div class="d-flex flex-wrap align-items-end justify-content-between gap-2 mb-3">
  <div>
    <h1 class="h4 mb-0">Configurações</h1>
    <p class="text-body-secondary small mb-0">Chaves gerais do sistema de fretes.</p>
  </div>
</div>

<form method="post" action="<?= url('fretes/configuracoes/salvar') ?>" novalidate>
  <?= Csrf::campo() ?>

  <div class="card mb-3">
    <div class="card-body">
      <div class="form-check form-switch">
        <input class="form-check-input" type="checkbox" role="switch" id="credito_expira_virada_ano"
               name="credito_expira_virada_ano" value="1"
               <?= (string) antigo('credito_expira_virada_ano', (string) ($expira['valor'] ?? '0')) === '1' ? 'checked' : '' ?>>
        <label class="form-check-label fw-medium" for="credito_expira_virada_ano">
          Expirar o saldo de crédito na virada do ano</label>
      </div>
      <p class="small text-body-secondary mt-2 mb-0">
        Quando ligado, o saldo <strong>positivo</strong> de cada vendedor expira em 31/12 e o ano começa zerado
        (o saldo negativo nunca expira). Vale como regra geral: cada vendedor pode ter uma exceção própria em
        <em>Cadastros → Vendedores</em> ("sempre expira" / "nunca expira"), cadastrada à parte desta chave.
      </p>
      <?php if ($txt = $quemAtualizou($expira)): ?>
        <p class="small text-body-secondary mb-0 mt-2"><?= e($txt) ?></p>
      <?php endif; ?>
    </div>
  </div>

  <div class="card mb-3">
    <div class="card-body">
      <label class="form-label fw-medium" for="alerta_vencimento_dias">Alerta de vencimento de faturas</label>
      <div class="row g-2 align-items-center">
        <div class="col-auto">
          <input type="number" class="form-control<?= $cls('alerta_vencimento_dias') ?>" style="width: 6rem"
                 id="alerta_vencimento_dias" name="alerta_vencimento_dias" min="0" max="365" required
                 value="<?= e(antigo('alerta_vencimento_dias', (string) ($alerta['valor'] ?? ''))) ?>">
        </div>
        <div class="col-auto text-body-secondary small">dia(s)</div>
      </div>
      <div class="invalid-feedback<?= $erro('alerta_vencimento_dias') ? ' d-block' : '' ?>"><?= e($erro('alerta_vencimento_dias')) ?></div>
      <p class="small text-body-secondary mt-2 mb-0">
        Faturas que vencem em até esse número de dias aparecem como alerta no dashboard.</p>
      <?php if ($txt = $quemAtualizou($alerta)): ?>
        <p class="small text-body-secondary mb-0 mt-2"><?= e($txt) ?></p>
      <?php endif; ?>
    </div>
  </div>

  <div class="card mb-3">
    <div class="card-body">
      <label class="form-label fw-medium" for="frete_isento_pedido_minimo">Pedido mínimo para frete isento</label>
      <div class="input-group" style="max-width: 14rem">
        <span class="input-group-text">R$</span>
        <input class="form-control text-end<?= $cls('frete_isento_pedido_minimo') ?>" id="frete_isento_pedido_minimo"
               name="frete_isento_pedido_minimo" inputmode="decimal" required
               value="<?= e(antigo('frete_isento_pedido_minimo', campo_moeda($pedidoMinimo['valor'] ?? null))) ?>">
      </div>
      <div class="invalid-feedback<?= $erro('frete_isento_pedido_minimo') ? ' d-block' : '' ?>"><?= e($erro('frete_isento_pedido_minimo')) ?></div>
      <p class="small text-body-secondary mt-2 mb-0">
        Política da empresa: pedido com valor igual ou acima deste não paga frete. Ao lançar um frete isento pelo
        motivo "Pedido acima do valor mínimo", o sistema exige o nº e o valor do pedido (do ERP) e confere com este limite.
        O frete isento não entra no saldo do vendedor.</p>
      <?php if ($txt = $quemAtualizou($pedidoMinimo)): ?>
        <p class="small text-body-secondary mb-0 mt-2"><?= e($txt) ?></p>
      <?php endif; ?>
    </div>
  </div>

  <div class="d-flex justify-content-end">
    <button class="btn btn-primary" type="submit"><i class="bi bi-check2"></i> Salvar configurações</button>
  </div>
</form>
