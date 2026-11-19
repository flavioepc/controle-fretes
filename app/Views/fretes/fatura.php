<?php
use App\Core\Csrf;
use App\Fretes\Permissoes;

$rotuloStatus = [
    'aberta' => 'Aberta', 'conferida' => 'Conferida', 'divergente' => 'Divergente',
    'enviada_pagto' => 'Enviada p/ pagto', 'paga' => 'Paga',
];
$corStatus = [
    'aberta' => 'secondary', 'conferida' => 'info', 'divergente' => 'danger',
    'enviada_pagto' => 'primary', 'paga' => 'success',
];
// o que aparece depende do status da fatura e do painel de permissões
$permConferir = Permissoes::pode('faturas.conferir');
$permCorrigir = Permissoes::pode('faturas.corrigir');
$podeConferir = $permConferir && in_array($fatura['status'], ['aberta', 'conferida', 'divergente'], true);
$podeEnviar = $permConferir && $fatura['status'] === 'conferida';
$podePagar = $permConferir && $fatura['status'] === 'enviada_pagto';
$podeAlterar = $permCorrigir && in_array($fatura['status'], ['enviada_pagto', 'paga'], true);   // corrigir depois de enviada (com histórico)
$abrirAlterar = ($erros['_form'] ?? null) === 'alterar';
/** valor do formulário "Alterar dados": o digitado, se voltou com erro, senão o gravado */
$alt = fn (string $c, string $padrao) => $abrirAlterar ? (string) antigo($c, $padrao) : $padrao;
/** o que já foi preenchido não pode ser apagado (as faturas vindas da planilha têm esses campos vazios) */
$obrig = fn (string $c) => !empty($fatura[$c]) ? ' <span class="text-danger">*</span>' : '';
/** conhecimento errado: trocar o nº ou retirar fretes (qualquer status, menos paga) */
$podeCorrigirConhecimento = $permCorrigir && $fatura['status'] !== 'paga';
$marcar = $podeCorrigirConhecimento && $fretes;
$formErro = $erros['_form'] ?? null;
$marcados = $formErro === 'retirar' ? array_map('intval', (array) antigo('fretes', [])) : [];
$erro = fn (string $c) => $erros[$c] ?? null;
$cls = fn (string $c) => isset($erros[$c]) ? ' is-invalid' : '';
?>
<div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3">
  <div>
    <a class="small text-decoration-none" href="<?= url('fretes/faturas') ?>"><i class="bi bi-arrow-left"></i> Voltar para faturas</a>
    <h1 class="h4 mb-0 mt-1">Fatura <?= e($fatura['numero_documento']) ?>
      <span class="badge rounded-pill text-bg-<?= $corStatus[$fatura['status']] ?? 'secondary' ?>"><?= e($rotuloStatus[$fatura['status']] ?? $fatura['status']) ?></span>
    </h1>
    <p class="text-body-secondary small mb-0"><?= e($fatura['transportadora']) ?><?= $fatura['empresa'] ? ' · ' . e($fatura['empresa']) : '' ?>
      · entrada em <?= data_br($fatura['data_entrada']) ?></p>
    <p class="text-body-secondary small mb-0"><i class="bi bi-printer"></i>
      <?= $fatura['qtd_impressoes']
          ? 'Impressa ' . $fatura['qtd_impressoes'] . 'x · última em ' . e(date('d/m/Y H:i', strtotime($fatura['ultima_impressao'])))
            . ($fatura['ultima_impressao_por'] ? ' por ' . e($fatura['ultima_impressao_por']) : '')
          : 'Relatório ainda não impresso' ?></p>
  </div>
  <?php if (Permissoes::pode('faturas.imprimir')): ?>
    <a class="btn btn-outline-secondary" href="<?= url('fretes/faturas/imprimir?id=' . (int) $fatura['id']) ?>" target="_blank">
      <i class="bi bi-printer"></i> Imprimir</a>
  <?php endif; ?>
</div>

<div class="row g-2 mb-3">
  <div class="col-6 col-lg-3">
    <div class="card card-indicador"><div class="card-body py-2">
      <div class="indicador-rotulo"><i class="bi bi-receipt"></i> Total dos fretes</div>
      <div class="fs-5 fw-semibold numero"><?= moeda($fatura['total_fretes']) ?></div>
      <div class="indicador-nota"><?= (int) $fatura['qtd_itens'] ?> item(ns)</div>
    </div></div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="card card-indicador card-destaque"><div class="card-body py-2">
      <div class="indicador-rotulo"><i class="bi bi-cash-stack"></i> Total a pagar</div>
      <div class="fs-5 fw-semibold numero"><?= moeda($fatura['total_a_pagar']) ?></div>
      <div class="indicador-nota">fretes − desconto (<?= moeda($fatura['desconto']) ?>)</div>
    </div></div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="card card-indicador"><div class="card-body py-2">
      <div class="indicador-rotulo"><i class="bi bi-file-earmark-text"></i> Valor do boleto</div>
      <div class="fs-5 fw-semibold numero"><?= $fatura['valor_boleto'] === null ? '—' : moeda($fatura['valor_boleto']) ?></div>
    </div></div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="card card-indicador"><div class="card-body py-2">
      <div class="indicador-rotulo"><i class="bi bi-calendar-event"></i> Vencimento</div>
      <div class="fs-5 fw-semibold numero <?= (int) $fatura['vencida'] ? 'status-critico-texto' : '' ?>">
        <?= data_br($fatura['vencimento']) ?></div>
      <?php if ((int) $fatura['vencida']): ?><div class="indicador-nota status-critico-texto">vencida</div><?php endif; ?>
    </div></div>
  </div>
</div>

<?php if ($fatura['divergencia'] !== null && abs((float) $fatura['divergencia']) > 0.01): ?>
  <div class="alert alert-danger d-flex gap-2"><i class="bi bi-exclamation-diamond-fill fs-5"></i>
    <div>Boleto não confere com os fretes vinculados: diferença de
      <strong class="numero"><?= moeda($fatura['divergencia']) ?></strong>
      (boleto <?= $fatura['divergencia'] > 0 ? 'maior' : 'menor' ?> que o calculado).</div>
  </div>
<?php endif; ?>

<div class="row g-3">
  <div class="col-12 col-lg-7">
    <div class="card h-100">
      <div class="card-header bg-transparent"><h2 class="h6 mb-0">Fretes vinculados</h2></div>
      <?php if (!$fretes): ?>
        <div class="vazio py-4"><i class="bi bi-inbox"></i>Nenhum frete vinculado a esta fatura.</div>
      <?php else: ?>
        <div class="table-responsive-cards">
          <table class="table table-sm align-middle mb-0 tabela-cards">
            <thead><tr>
              <?php if ($marcar): ?>
                <th style="width:2rem"><input class="form-check-input" type="checkbox" id="marcarFretes" aria-label="Marcar todos os fretes"></th>
              <?php endif; ?>
              <th>NF / Data</th><th>Vendedor</th><th class="text-end">Cliente</th><th class="text-end">Transp.</th></tr></thead>
            <tbody>
            <?php foreach ($fretes as $fr): ?>
              <tr>
                <?php if ($marcar): ?>
                  <td><input class="form-check-input sel-frete" type="checkbox" name="fretes[]" value="<?= (int) $fr['id'] ?>" form="formRetirar"
                             data-nf="<?= e($fr['nota_fiscal']) ?>" data-valor="<?= e($fr['valor_transp'] ?? 0) ?>"
                             <?= in_array((int) $fr['id'], $marcados, true) ? 'checked' : '' ?> aria-label="Marcar NF <?= e($fr['nota_fiscal']) ?>"></td>
                <?php endif; ?>
                <td data-rotulo="NF">
                  <a href="<?= url('fretes/lancamentos/editar?id=' . (int) $fr['id']) ?>"><?= e($fr['nota_fiscal']) ?></a>
                  <span class="text-body-secondary small ms-1"><?= data_br($fr['data']) ?></span>
                </td>
                <td data-rotulo="Vendedor"><?= e($fr['vendedor'] ?? 'sem vendedor') ?></td>
                <td data-rotulo="Cliente" class="text-end numero text-nowrap"><?= moeda($fr['valor_cliente'], false) ?></td>
                <td data-rotulo="Transportadora (R$)" class="text-end numero text-nowrap fw-medium <?= classe_sinal($fr['diferenca']) ?>">
                  <?= $fr['valor_transp'] === null ? 'aguardando' : moeda($fr['valor_transp'], false) ?></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot><tr class="fw-semibold">
              <td colspan="<?= $marcar ? 3 : 2 ?>">Total</td>
              <td class="text-end numero"><?= moeda(array_sum(array_column($fretes, 'valor_cliente')), false) ?></td>
              <td class="text-end numero"><?= moeda($fatura['total_fretes'], false) ?></td>
            </tr></tfoot>
          </table>
        </div>
        <?php if ($marcar): ?>
          <div class="card-footer bg-transparent d-flex flex-wrap align-items-center justify-content-between gap-2">
            <span class="small text-body-secondary" id="resumoRetirar" aria-live="polite">Lançamento no conhecimento errado?
              Marque e retire. Para mudar o valor, clique na NF.</span>
            <button class="btn btn-outline-danger btn-sm" type="button" id="btnRetirar" data-bs-toggle="modal" data-bs-target="#modalRetirar"
                    <?= $marcados ? '' : 'disabled' ?>><i class="bi bi-box-arrow-right"></i> Retirar selecionados</button>
          </div>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>

  <div class="col-12 col-lg-5">
    <?php if ($podeConferir): ?>
      <div class="card mb-3">
        <div class="card-header bg-transparent"><h2 class="h6 mb-0"><?= $fatura['status'] === 'aberta' ? 'Conferir fatura' : 'Corrigir conferência' ?></h2></div>
        <form method="post" action="<?= url('fretes/faturas/conferir') ?>" novalidate>
          <?= Csrf::campo() ?>
          <input type="hidden" name="id" value="<?= (int) $fatura['id'] ?>">
          <div class="card-body">
            <div class="row g-3">
              <div class="col-6">
                <label class="form-label" for="vencimento">Vencimento</label>
                <input type="date" class="form-control<?= $cls('vencimento') ?>" id="vencimento" name="vencimento"
                       value="<?= e(antigo('vencimento', $fatura['vencimento'] ?? '')) ?>">
                <div class="invalid-feedback"><?= e($erro('vencimento')) ?></div>
              </div>
              <div class="col-6">
                <label class="form-label" for="desconto">Desconto</label>
                <div class="input-group">
                  <span class="input-group-text">R$</span>
                  <input class="form-control text-end campo-moeda<?= $cls('desconto') ?>" id="desconto" name="desconto"
                         inputmode="decimal" value="<?= e(antigo('desconto', campo_moeda($fatura['desconto']))) ?>" placeholder="0,00">
                  <div class="invalid-feedback"><?= e($erro('desconto')) ?></div>
                </div>
              </div>
              <div class="col-6">
                <label class="form-label" for="valor_boleto">Valor do boleto</label>
                <div class="input-group">
                  <span class="input-group-text">R$</span>
                  <input class="form-control text-end campo-moeda<?= $cls('valor_boleto') ?>" id="valor_boleto" name="valor_boleto"
                         inputmode="decimal" value="<?= e(antigo('valor_boleto', campo_moeda($fatura['valor_boleto']))) ?>" placeholder="0,00">
                  <div class="invalid-feedback"><?= e($erro('valor_boleto')) ?></div>
                </div>
                <div class="form-text">Se informado e não bater com o total a pagar, a fatura vira divergente automaticamente.</div>
              </div>
              <div class="col-6">
                <label class="form-label" for="responsavel">Conferido por <span class="text-danger">*</span></label>
                <input class="form-control<?= $cls('responsavel') ?>" id="responsavel" name="responsavel" maxlength="60"
                       value="<?= e(antigo('responsavel', $fatura['responsavel'] ?? '')) ?>" required>
                <div class="invalid-feedback"><?= e($erro('responsavel')) ?></div>
              </div>
              <div class="col-12">
                <span class="form-label d-block">Resultado</span>
                <div class="btn-group w-100" role="group" aria-label="Resultado da conferência">
                  <input type="radio" class="btn-check" name="status" id="statusConferida" value="conferida"
                         <?= antigo('status', $fatura['status'] === 'divergente' ? 'divergente' : 'conferida') !== 'divergente' ? 'checked' : '' ?>>
                  <label class="btn btn-outline-success" for="statusConferida"><i class="bi bi-check-circle"></i> Confere</label>
                  <input type="radio" class="btn-check" name="status" id="statusDivergente" value="divergente"
                         <?= antigo('status', $fatura['status'] === 'divergente' ? 'divergente' : 'conferida') === 'divergente' ? 'checked' : '' ?>>
                  <label class="btn btn-outline-danger" for="statusDivergente"><i class="bi bi-exclamation-triangle"></i> Divergente</label>
                </div>
              </div>
              <div class="col-12">
                <label class="form-label" for="obs">Observação<span class="text-danger" id="obsObrig"><?= $fatura['status'] === 'divergente' ? ' *' : '' ?></span></label>
                <textarea class="form-control<?= $cls('obs') ?>" id="obs" name="obs" rows="2" maxlength="255"
                          placeholder="Ex.: boleto veio com desconto não lançado"><?= e(antigo('obs', $fatura['obs'] ?? '')) ?></textarea>
                <div class="invalid-feedback"><?= e($erro('obs')) ?></div>
              </div>
            </div>
          </div>
          <div class="card-footer bg-transparent d-flex justify-content-end">
            <button class="btn btn-primary" type="submit"><i class="bi bi-check2"></i> Salvar conferência</button>
          </div>
        </form>
      </div>
    <?php endif; ?>

    <?php if ($podeEnviar): ?>
      <div class="card mb-3">
        <div class="card-body d-flex flex-wrap align-items-center justify-content-between gap-2">
          <div>
            <div class="fw-medium">Fatura conferida</div>
            <div class="small text-body-secondary">Pronta para seguir para o pagamento.</div>
          </div>
          <form method="post" action="<?= url('fretes/faturas/enviar') ?>">
            <?= Csrf::campo() ?>
            <input type="hidden" name="id" value="<?= (int) $fatura['id'] ?>">
            <button class="btn btn-primary" type="submit"><i class="bi bi-send"></i> Enviar para pagamento</button>
          </form>
        </div>
      </div>
    <?php endif; ?>

    <?php if ($podePagar): ?>
      <div class="card mb-3">
        <div class="card-header bg-transparent"><h2 class="h6 mb-0">Marcar como paga</h2></div>
        <form method="post" action="<?= url('fretes/faturas/pagar') ?>" novalidate>
          <?= Csrf::campo() ?>
          <input type="hidden" name="id" value="<?= (int) $fatura['id'] ?>">
          <div class="card-body">
            <div class="row g-3">
              <div class="col-6">
                <label class="form-label" for="pago_em">Pago em</label>
                <input type="date" class="form-control" id="pago_em" name="pago_em" value="<?= e(date('Y-m-d')) ?>">
              </div>
              <div class="col-6">
                <label class="form-label" for="recebido_em">Recebido (RCTO) em</label>
                <input type="date" class="form-control" id="recebido_em" name="recebido_em">
              </div>
              <div class="col-12">
                <label class="form-label" for="recebido_por">Recebido por</label>
                <input class="form-control" id="recebido_por" name="recebido_por" maxlength="60" placeholder="Quem recebeu no financeiro">
              </div>
            </div>
          </div>
          <div class="card-footer bg-transparent d-flex justify-content-end">
            <button class="btn btn-success" type="submit"><i class="bi bi-check2-circle"></i> Marcar como paga</button>
          </div>
        </form>
      </div>
    <?php endif; ?>

    <?php if ($fatura['status'] === 'paga'): ?>
      <div class="card">
        <div class="card-body">
          <div class="fw-medium mb-2"><i class="bi bi-check2-circle text-success"></i> Fatura paga</div>
          <dl class="row small mb-0">
            <dt class="col-6">Pago em</dt><dd class="col-6"><?= data_br($fatura['pago_em']) ?></dd>
            <?php if ($fatura['recebido_por']): ?>
              <dt class="col-6">Recebido por</dt><dd class="col-6"><?= e($fatura['recebido_por']) ?></dd>
            <?php endif; ?>
            <?php if ($fatura['recebido_em']): ?>
              <dt class="col-6">Recebido em</dt><dd class="col-6"><?= data_br($fatura['recebido_em']) ?></dd>
            <?php endif; ?>
          </dl>
        </div>
      </div>
    <?php endif; ?>

    <?php if ($podeCorrigirConhecimento): ?>
      <div class="card mt-3" id="corrigir-conhecimento">
        <div class="card-body d-flex flex-wrap align-items-center justify-content-between gap-2">
          <div>
            <div class="fw-medium">Conhecimento errado?</div>
            <div class="small text-body-secondary">Troque o nº do conhecimento (os fretes vão junto) ou retire
              lançamentos marcando na lista ao lado.</div>
          </div>
          <button class="btn btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#formConhecimento"
                  aria-expanded="<?= $formErro === 'conhecimento' ? 'true' : 'false' ?>" aria-controls="formConhecimento">
            <i class="bi bi-123"></i> Trocar nº</button>
        </div>
        <form class="collapse<?= $formErro === 'conhecimento' ? ' show' : '' ?> border-top" id="formConhecimento" method="post"
              action="<?= url('fretes/faturas/conhecimento') ?>" novalidate>
          <?= Csrf::campo() ?>
          <input type="hidden" name="id" value="<?= (int) $fatura['id'] ?>">
          <div class="card-body">
            <div class="row g-3">
              <div class="col-12 col-sm-5">
                <label class="form-label" for="con_numero">Nº correto <span class="text-danger">*</span></label>
                <input class="form-control<?= $cls('numero') ?>" id="con_numero" name="numero" maxlength="40" autocomplete="off"
                       value="<?= e($formErro === 'conhecimento' ? antigo('numero') : '') ?>" placeholder="atual: <?= e($fatura['numero_documento']) ?>">
                <div class="invalid-feedback"><?= e($erro('numero')) ?></div>
              </div>
              <div class="col-12 col-sm-7">
                <label class="form-label" for="con_motivo">Motivo <span class="text-danger">*</span></label>
                <input class="form-control<?= $cls('motivo_numero') ?>" id="con_motivo" name="motivo" maxlength="200"
                       value="<?= e($formErro === 'conhecimento' ? antigo('motivo') : '') ?>" placeholder="Ex.: transportadora mandou com o nº errado">
                <div class="invalid-feedback"><?= e($erro('motivo_numero')) ?></div>
              </div>
            </div>
          </div>
          <div class="card-footer bg-transparent d-flex justify-content-end gap-2">
            <button class="btn btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#formConhecimento">Cancelar</button>
            <button class="btn btn-primary" type="submit"><i class="bi bi-check2"></i> Trocar nº</button>
          </div>
        </form>
      </div>
    <?php endif; ?>

    <?php if ($podeAlterar): ?>
      <div class="card mt-3" id="alterar">
        <div class="card-body d-flex flex-wrap align-items-center justify-content-between gap-2">
          <div>
            <div class="fw-medium">Corrigir dados da fatura</div>
            <div class="small text-body-secondary">Vencimento, boleto, desconto<?= $fatura['status'] === 'paga' ? ', dados do pagamento' : '' ?>…
              O status e os fretes não mudam.</div>
          </div>
          <button class="btn btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#formAlterar"
                  aria-expanded="<?= $abrirAlterar ? 'true' : 'false' ?>" aria-controls="formAlterar">
            <i class="bi bi-pencil-square"></i> Alterar dados</button>
        </div>
        <form class="collapse<?= $abrirAlterar ? ' show' : '' ?> border-top" id="formAlterar" method="post"
              action="<?= url('fretes/faturas/alterar') ?>" novalidate>
          <?= Csrf::campo() ?>
          <input type="hidden" name="id" value="<?= (int) $fatura['id'] ?>">
          <div class="card-body">
            <div class="row g-3">
              <div class="col-6">
                <label class="form-label" for="alt_vencimento">Vencimento</label>
                <input type="date" class="form-control<?= $cls('vencimento') ?>" id="alt_vencimento" name="vencimento"
                       value="<?= e($alt('vencimento', $fatura['vencimento'] ?? '')) ?>">
                <div class="invalid-feedback"><?= e($erro('vencimento')) ?></div>
              </div>
              <div class="col-6">
                <label class="form-label" for="alt_enviado">Enviada p/ pagto em<?= $obrig('enviado_pagto_em') ?></label>
                <input type="date" class="form-control<?= $cls('enviado_pagto_em') ?>" id="alt_enviado" name="enviado_pagto_em"
                       max="<?= date('Y-m-d') ?>" value="<?= e($alt('enviado_pagto_em', $fatura['enviado_pagto_em'] ?? '')) ?>">
                <div class="invalid-feedback"><?= e($erro('enviado_pagto_em')) ?></div>
              </div>
              <div class="col-6">
                <label class="form-label" for="alt_boleto">Valor do boleto</label>
                <div class="input-group">
                  <span class="input-group-text">R$</span>
                  <input class="form-control text-end campo-moeda<?= $cls('valor_boleto') ?>" id="alt_boleto" name="valor_boleto"
                         inputmode="decimal" value="<?= e($alt('valor_boleto', campo_moeda($fatura['valor_boleto']))) ?>" placeholder="0,00">
                  <div class="invalid-feedback"><?= e($erro('valor_boleto')) ?></div>
                </div>
              </div>
              <div class="col-6">
                <label class="form-label" for="alt_desconto">Desconto</label>
                <div class="input-group">
                  <span class="input-group-text">R$</span>
                  <input class="form-control text-end campo-moeda<?= $cls('desconto') ?>" id="alt_desconto" name="desconto"
                         inputmode="decimal" value="<?= e($alt('desconto', campo_moeda($fatura['desconto']))) ?>" placeholder="0,00">
                  <div class="invalid-feedback"><?= e($erro('desconto')) ?></div>
                </div>
              </div>
              <div class="col-12 form-text mt-1">O boleto precisa bater com o total dos fretes (<?= moeda($fatura['total_fretes']) ?>) menos o desconto.</div>
              <div class="col-12">
                <label class="form-label" for="alt_responsavel">Conferido por<?= $obrig('responsavel') ?></label>
                <input class="form-control<?= $cls('responsavel') ?>" id="alt_responsavel" name="responsavel" maxlength="60"
                       value="<?= e($alt('responsavel', $fatura['responsavel'] ?? '')) ?>">
                <div class="invalid-feedback"><?= e($erro('responsavel')) ?></div>
              </div>
              <?php if ($fatura['status'] === 'paga'): ?>
                <div class="col-6">
                  <label class="form-label" for="alt_pago">Pago em<?= $obrig('pago_em') ?></label>
                  <input type="date" class="form-control<?= $cls('pago_em') ?>" id="alt_pago" name="pago_em"
                         max="<?= date('Y-m-d') ?>" value="<?= e($alt('pago_em', $fatura['pago_em'] ?? '')) ?>">
                  <div class="invalid-feedback"><?= e($erro('pago_em')) ?></div>
                </div>
                <div class="col-6">
                  <label class="form-label" for="alt_recebido_em">Recebido (RCTO) em</label>
                  <input type="date" class="form-control<?= $cls('recebido_em') ?>" id="alt_recebido_em" name="recebido_em"
                         max="<?= date('Y-m-d') ?>" value="<?= e($alt('recebido_em', $fatura['recebido_em'] ?? '')) ?>">
                  <div class="invalid-feedback"><?= e($erro('recebido_em')) ?></div>
                </div>
                <div class="col-12">
                  <label class="form-label" for="alt_recebido_por">Recebido por</label>
                  <input class="form-control<?= $cls('recebido_por') ?>" id="alt_recebido_por" name="recebido_por" maxlength="60"
                         value="<?= e($alt('recebido_por', $fatura['recebido_por'] ?? '')) ?>">
                  <div class="invalid-feedback"><?= e($erro('recebido_por')) ?></div>
                </div>
              <?php endif; ?>
              <div class="col-12">
                <label class="form-label" for="alt_obs">Observação</label>
                <textarea class="form-control<?= $cls('obs') ?>" id="alt_obs" name="obs" rows="2" maxlength="255"><?= e($alt('obs', $fatura['obs'] ?? '')) ?></textarea>
                <div class="invalid-feedback"><?= e($erro('obs')) ?></div>
              </div>
              <div class="col-12">
                <label class="form-label" for="alt_motivo">Motivo da alteração <span class="text-danger">*</span></label>
                <input class="form-control<?= $cls('motivo') ?>" id="alt_motivo" name="motivo" maxlength="200"
                       value="<?= e($alt('motivo', '')) ?>" placeholder="Ex.: transportadora prorrogou o vencimento do boleto">
                <div class="invalid-feedback"><?= e($erro('motivo')) ?></div>
              </div>
            </div>
          </div>
          <div class="card-footer bg-transparent d-flex justify-content-end gap-2">
            <button class="btn btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#formAlterar">Cancelar</button>
            <button class="btn btn-primary" type="submit"><i class="bi bi-check2"></i> Salvar alteração</button>
          </div>
        </form>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php if ($marcar): ?>
<!-- Modal: retirar fretes do conhecimento (os checkboxes da lista usam form="formRetirar") -->
<div class="modal fade" id="modalRetirar" tabindex="-1" aria-labelledby="modalRetirarTitulo" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form class="modal-content" id="formRetirar" method="post" action="<?= url('fretes/faturas/retirar') ?>" novalidate>
      <?= Csrf::campo() ?>
      <input type="hidden" name="id" value="<?= (int) $fatura['id'] ?>">
      <div class="modal-header">
        <h2 class="modal-title h5" id="modalRetirarTitulo">Retirar do conhecimento <?= e($fatura['numero_documento']) ?></h2>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
      </div>
      <div class="modal-body">
        <p class="fw-medium mb-1" id="retirarResumo"></p>
        <p class="small text-body-secondary">Os valores e o saldo dos vendedores não mudam. Se esta fatura (ou a de destino)
          já estiver conferida ou enviada para pagamento, ela volta a <strong>divergente</strong> para ser conferida de novo.</p>
        <label class="form-label" for="ret_destino">Mover para o conhecimento nº</label>
        <input class="form-control<?= $cls('destino') ?>" id="ret_destino" name="destino" maxlength="40" autocomplete="off"
               value="<?= e($formErro === 'retirar' ? antigo('destino') : '') ?>" placeholder="em branco = fica sem conhecimento (aguardando)">
        <div class="invalid-feedback"><?= e($erro('destino')) ?></div>
        <div class="form-text mb-3">Da mesma transportadora (<?= e($fatura['transportadora']) ?>). Se não existir em andamento,
          é criado um novo, aberto.</div>
        <label class="form-label" for="ret_motivo">Motivo <span class="text-danger">*</span></label>
        <input class="form-control<?= $cls('motivo_retirar') ?>" id="ret_motivo" name="motivo" maxlength="200"
               value="<?= e($formErro === 'retirar' ? antigo('motivo') : '') ?>" placeholder="Ex.: NF pertence ao conhecimento 12345">
        <div class="invalid-feedback"><?= e($erro('motivo_retirar')) ?></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="submit" class="btn btn-danger"><i class="bi bi-box-arrow-right"></i> Retirar</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<?php if ($alteracoes): ?>
  <h2 class="titulo-secao mt-4">Histórico de alterações e correções</h2>
  <div class="card">
    <div class="table-responsive-cards">
      <table class="table table-sm align-middle mb-0 tabela-cards">
        <thead><tr><th>Quando</th><th>Quem</th><th>Motivo</th><th>O que mudou</th></tr></thead>
        <tbody>
        <?php foreach ($alteracoes as $a): ?>
          <tr>
            <td data-rotulo="Quando" class="text-nowrap"><?= e(date('d/m/Y H:i', strtotime($a['alterada_em']))) ?></td>
            <td data-rotulo="Quem"><?= e($a['usuario'] ?? '—') ?></td>
            <td data-rotulo="Motivo"><?= e($a['motivo']) ?></td>
            <td data-rotulo="O que mudou" class="small"><?= nl2br(e($a['detalhe'])) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>
