<?php
use App\Core\Csrf;

$formErro = $erros['_form'] ?? null;
/** erros e valores digitados só voltam para o formulário que foi enviado (os dois têm "valor", "data"...) */
$erro = fn (string $form, string $c) => $formErro === $form ? ($erros[$c] ?? null) : null;
$cls = fn (string $form, string $c) => $erro($form, $c) ? ' is-invalid' : '';
$ant = fn (string $form, string $c, string $padrao = '') => $formErro === $form ? (string) antigo($c, $padrao) : $padrao;

$negativos = array_filter($vendedores, fn ($v) => (float) $v['saldo'] < 0);
$comCredito = array_filter($vendedores, fn ($v) => $v['ativo'] && (float) $v['disponivel'] > 0);
$ativos = array_filter($vendedores, fn ($v) => (bool) $v['ativo']);
$opcao = fn (array $v, string $info, string $sel) =>
    '<option value="' . (int) $v['id'] . '" data-saldo="' . e($v['saldo']) . '" data-disponivel="' . e($v['disponivel']) . '"'
    . ((string) $sel === (string) $v['id'] ? ' selected' : '') . '>'
    . e($v['nome']) . ' (' . e($v['gestor']) . ')' . (!$v['ativo'] ? ' · inativo' : '') . ' — ' . $info . '</option>';
?>
<div class="d-flex flex-wrap align-items-end justify-content-between gap-2 mb-3">
  <div>
    <h1 class="h4 mb-0">Pagamentos e transferências</h1>
    <p class="text-body-secondary small mb-0">Baixa de saldo negativo pago pelo vendedor e transferência de crédito entre vendedores.</p>
  </div>
</div>

<div class="row g-3 mb-4">
  <?php if ($pode['pagamento']): ?>
  <!-- Pagamento de saldo negativo -->
  <div class="col-12 col-lg-6">
    <form class="card h-100" method="post" action="<?= url('fretes/movimentos/pagamento') ?>" id="pagamento" novalidate>
      <?= Csrf::campo() ?>
      <div class="card-body">
        <h2 class="h6 mb-1"><i class="bi bi-cash-coin"></i> Registrar pagamento de saldo negativo</h2>
        <p class="small text-body-secondary">O vendedor pagou ou teve o valor descontado (ex.: na folha). O saldo sobe até, no máximo, zero.</p>
        <?php if (!$negativos): ?>
          <div class="vazio py-3"><i class="bi bi-emoji-smile"></i>Nenhum vendedor com saldo negativo.</div>
        <?php else: ?>
        <div class="row g-3">
          <div class="col-12">
            <label class="form-label" for="pag_vendedor">Vendedor <span class="text-danger">*</span></label>
            <select class="form-select busca-select<?= $cls('pagamento', 'vendedor_id') ?>" id="pag_vendedor" name="vendedor_id" required>
              <option value="">Digite para buscar…</option>
              <?php foreach ($negativos as $v): ?>
                <?= $opcao($v, 'deve ' . moeda(-(float) $v['saldo']), $ant('pagamento', 'vendedor_id')) ?>
              <?php endforeach; ?>
            </select>
            <div class="invalid-feedback d-block"><?= e($erro('pagamento', 'vendedor_id')) ?></div>
          </div>
          <div class="col-6">
            <label class="form-label" for="pag_valor">Valor pago <span class="text-danger">*</span></label>
            <div class="input-group">
              <span class="input-group-text">R$</span>
              <input class="form-control text-end campo-moeda<?= $cls('pagamento', 'valor') ?>" id="pag_valor" name="valor"
                     inputmode="decimal" value="<?= e($ant('pagamento', 'valor')) ?>" placeholder="0,00" required autocomplete="off">
              <div class="invalid-feedback"><?= e($erro('pagamento', 'valor')) ?></div>
            </div>
            <div class="form-text" id="pag_ajuda">&nbsp;</div>
          </div>
          <div class="col-6">
            <label class="form-label" for="pag_data">Data do pagamento <span class="text-danger">*</span></label>
            <input type="date" class="form-control<?= $cls('pagamento', 'data') ?>" id="pag_data" name="data" required
                   max="<?= date('Y-m-d') ?>" value="<?= e($ant('pagamento', 'data', date('Y-m-d'))) ?>">
            <div class="invalid-feedback"><?= e($erro('pagamento', 'data')) ?></div>
          </div>
          <div class="col-12 col-sm-6">
            <label class="form-label" for="pag_ref">Referência</label>
            <input class="form-control<?= $cls('pagamento', 'referencia') ?>" id="pag_ref" name="referencia" maxlength="80"
                   value="<?= e($ant('pagamento', 'referencia')) ?>" placeholder="Ex.: folha 11/2026, nº do recibo">
            <div class="invalid-feedback"><?= e($erro('pagamento', 'referencia')) ?></div>
          </div>
          <div class="col-12 col-sm-6">
            <label class="form-label" for="pag_obs">Observação</label>
            <input class="form-control<?= $cls('pagamento', 'obs') ?>" id="pag_obs" name="obs" maxlength="255"
                   value="<?= e($ant('pagamento', 'obs')) ?>">
            <div class="invalid-feedback"><?= e($erro('pagamento', 'obs')) ?></div>
          </div>
        </div>
        <?php endif; ?>
      </div>
      <?php if ($negativos): ?>
        <div class="card-footer bg-transparent d-flex justify-content-end">
          <button class="btn btn-primary" type="submit"><i class="bi bi-check2"></i> Registrar pagamento</button>
        </div>
      <?php endif; ?>
    </form>
  </div>
  <?php endif; ?>

  <?php if ($pode['transferencia']): ?>
  <!-- Transferência de crédito -->
  <div class="col-12 col-lg-6">
    <form class="card h-100" method="post" action="<?= url('fretes/movimentos/transferencia') ?>" id="transferencia" novalidate>
      <?= Csrf::campo() ?>
      <div class="card-body">
        <h2 class="h6 mb-1"><i class="bi bi-arrow-left-right"></i> Transferir crédito entre vendedores</h2>
        <p class="small text-body-secondary">Sai do saldo disponível de quem envia e entra no saldo de quem recebe, na mesma data.</p>
        <div class="row g-3">
          <div class="col-12 col-sm-6">
            <label class="form-label" for="tr_origem">De (quem envia) <span class="text-danger">*</span></label>
            <select class="form-select busca-select<?= $cls('transferencia', 'origem_id') ?>" id="tr_origem" name="origem_id" required>
              <option value="">Digite para buscar…</option>
              <?php foreach ($comCredito as $v): ?>
                <?= $opcao($v, 'disponível ' . moeda($v['disponivel']), $ant('transferencia', 'origem_id')) ?>
              <?php endforeach; ?>
            </select>
            <div class="invalid-feedback d-block"><?= e($erro('transferencia', 'origem_id')) ?></div>
          </div>
          <div class="col-12 col-sm-6">
            <label class="form-label" for="tr_destino">Para (quem recebe) <span class="text-danger">*</span></label>
            <select class="form-select busca-select<?= $cls('transferencia', 'destino_id') ?>" id="tr_destino" name="destino_id" required>
              <option value="">Digite para buscar…</option>
              <?php foreach ($ativos as $v): ?>
                <?= $opcao($v, 'saldo ' . moeda($v['saldo']), $ant('transferencia', 'destino_id')) ?>
              <?php endforeach; ?>
            </select>
            <div class="invalid-feedback d-block"><?= e($erro('transferencia', 'destino_id')) ?></div>
          </div>
          <div class="col-6">
            <label class="form-label" for="tr_valor">Valor <span class="text-danger">*</span></label>
            <div class="input-group">
              <span class="input-group-text">R$</span>
              <input class="form-control text-end campo-moeda<?= $cls('transferencia', 'valor') ?>" id="tr_valor" name="valor"
                     inputmode="decimal" value="<?= e($ant('transferencia', 'valor')) ?>" placeholder="0,00" required autocomplete="off">
              <div class="invalid-feedback"><?= e($erro('transferencia', 'valor')) ?></div>
            </div>
            <div class="form-text" id="tr_ajuda">&nbsp;</div>
          </div>
          <div class="col-6">
            <label class="form-label" for="tr_data">Data <span class="text-danger">*</span></label>
            <input type="date" class="form-control<?= $cls('transferencia', 'data') ?>" id="tr_data" name="data" required
                   max="<?= date('Y-m-d') ?>" value="<?= e($ant('transferencia', 'data', date('Y-m-d'))) ?>">
            <div class="invalid-feedback"><?= e($erro('transferencia', 'data')) ?></div>
          </div>
          <div class="col-12">
            <label class="form-label" for="tr_obs">Motivo <span class="text-danger">*</span></label>
            <input class="form-control<?= $cls('transferencia', 'obs') ?>" id="tr_obs" name="obs" maxlength="200" required
                   value="<?= e($ant('transferencia', 'obs')) ?>" placeholder="Ex.: venda dividida entre os dois vendedores">
            <div class="invalid-feedback"><?= e($erro('transferencia', 'obs')) ?></div>
          </div>
        </div>
      </div>
      <div class="card-footer bg-transparent d-flex justify-content-end">
        <button class="btn btn-primary" type="submit"><i class="bi bi-arrow-left-right"></i> Transferir</button>
      </div>
    </form>
  </div>
  <?php endif; ?>
</div>

<h2 class="titulo-secao" id="recentes">Últimos lançamentos</h2>
<div class="card">
  <?php if (!$recentes): ?>
    <div class="vazio"><i class="bi bi-inbox"></i>Nenhum pagamento ou transferência ainda.</div>
  <?php else: ?>
    <div class="table-responsive-cards">
      <table class="table table-sm table-hover align-middle mb-0 tabela-cards">
        <thead><tr><th>Data</th><th>Tipo</th><th>Vendedor</th><th>Detalhe</th><th class="text-end">Valor</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($recentes as $m):
            $pag = $m['tipo'] === 'pagamento_negativo';
            $estornado = (bool) $m['estorno_id'];
            $quem = $pag ? e($m['vendedor']) : e($m['vendedor']) . ' <i class="bi bi-arrow-right"></i> ' . e($m['contraparte'] ?? '?'); ?>
          <tr>
            <td data-rotulo="Data" class="text-nowrap"><?= data_br($m['data_ref']) ?></td>
            <td data-rotulo="Tipo">
              <span class="badge rounded-pill <?= $pag ? 'text-bg-success' : 'text-bg-primary' ?>">
                <i class="bi <?= $pag ? 'bi-cash-coin' : 'bi-arrow-left-right' ?>"></i> <?= $pag ? 'Pagamento' : 'Transferência' ?></span>
              <?php if ($estornado): ?><span class="badge rounded-pill text-bg-danger"><i class="bi bi-arrow-return-left"></i> Estornado</span><?php endif; ?>
            </td>
            <td data-rotulo="Vendedor"><?= $quem ?></td>
            <td data-rotulo="Detalhe" class="small text-body-secondary">
              <?= e($m['obs']) ?>
              <?php if ($m['criado_por_nome']): ?><div style="font-size:.75rem">por <?= e($m['criado_por_nome']) ?> em <?= e(date('d/m/Y H:i', strtotime($m['criado_em']))) ?></div><?php endif; ?>
              <?php if ($estornado): ?>
                <div class="text-danger-emphasis" style="font-size:.75rem"><?= e($m['estorno_obs']) ?>
                  · <?= e(date('d/m/Y H:i', strtotime($m['estornado_em']))) ?><?= $m['estornado_por_nome'] ? ' por ' . e($m['estornado_por_nome']) : '' ?></div>
              <?php endif; ?>
            </td>
            <td data-rotulo="Valor" class="text-end numero text-nowrap fw-medium<?= $estornado ? ' text-decoration-line-through text-body-secondary' : '' ?>"><?= moeda(abs((float) $m['valor'])) ?></td>
            <td class="text-end text-nowrap acoes">
              <?php if (!$estornado && $pode['estorno']): ?>
                <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#modalEstornar"
                        data-id="<?= (int) $m['id'] ?>"
                        data-resumo="<?= e(($pag ? 'Pagamento de ' : 'Transferência de ') . moeda(abs((float) $m['valor'])) . ' em ' . data_br($m['data_ref'])
                                         . ' — ' . ($pag ? $m['vendedor'] : $m['vendedor'] . ' → ' . ($m['contraparte'] ?? '?'))) ?>"
                        data-efeito="<?= e($pag
                            ? "O valor volta a ser devido por {$m['vendedor']} (o saldo dele desce de novo)."
                            : 'O valor sai do saldo de ' . ($m['contraparte'] ?? '?') . " e volta para {$m['vendedor']}. Só é feito se "
                              . ($m['contraparte'] ?? '?') . ' ainda tiver esse valor disponível.') ?>">
                  <i class="bi bi-arrow-return-left"></i> Estornar</button>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php if ($pode['estorno']): ?>
<!-- Modal: estornar pagamento ou transferência -->
<div class="modal fade" id="modalEstornar" tabindex="-1" aria-labelledby="modalEstornarTitulo" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form class="modal-content" method="post" action="<?= url('fretes/movimentos/estornar') ?>">
      <?= Csrf::campo() ?>
      <input type="hidden" name="movimento_id" class="campo-id">
      <div class="modal-header">
        <h2 class="modal-title h5" id="modalEstornarTitulo">Estornar lançamento</h2>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
      </div>
      <div class="modal-body">
        <p class="fw-medium mb-1 campo-resumo"></p>
        <p class="small text-body-secondary campo-efeito"></p>
        <p class="small text-body-secondary">O lançamento original continua no extrato; o estorno entra com a data de hoje, com o
          valor ao contrário. Não dá para desfazer o estorno (se precisar, lance de novo).</p>
        <label class="form-label" for="estornoMotivo">Motivo do estorno <span class="text-danger">*</span></label>
        <textarea class="form-control" id="estornoMotivo" name="motivo" rows="2" minlength="5" maxlength="200" required
                  placeholder="Ex.: pagamento lançado no vendedor errado"></textarea>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="submit" class="btn btn-danger"><i class="bi bi-arrow-return-left"></i> Confirmar estorno</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>
