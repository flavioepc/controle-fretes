<?php
use App\Core\Csrf;

$id = $frete['id'] ?? null;
/** valor: o que foi digitado antes do erro > valor salvo > padrão */
$v = function (string $campo, mixed $padrao = '') use ($frete) {
    return antigo($campo, $frete[$campo] ?? $padrao);
};
$valorMoeda = function (string $campo) use ($frete) {
    $a = antigo($campo, null);
    return $a !== null ? (string) $a : campo_moeda($frete[$campo] ?? null);
};
$erro = fn (string $c) => $erros[$c] ?? null;
$cls = fn (string $c) => isset($erros[$c]) ? ' is-invalid' : '';
$sel = fn (string $c, $valor) => (string) $v($c) === (string) $valor ? 'selected' : '';
$documento = antigo('documento', $frete['numero_documento'] ?? '');
$isento = antigo('cobranca', !empty($frete['isencao_id']) ? 'isento' : 'cliente') === 'isento';
?>
<div class="container-fluid px-0" style="max-width: 920px">
  <nav aria-label="breadcrumb" class="mb-2">
    <ol class="breadcrumb small mb-0">
      <li class="breadcrumb-item"><a href="<?= url('fretes/lancamentos') ?>">Fretes</a></li>
      <li class="breadcrumb-item active" aria-current="page"><?= $id ? 'Editar' : 'Lançar' ?></li>
    </ol>
  </nav>
  <h1 class="h4 mb-3"><?= $id ? 'Editar frete' : 'Lançar frete' ?></h1>

  <form method="post" action="<?= url('fretes/lancamentos/salvar') ?>" id="formFrete" novalidate>
    <?= Csrf::campo() ?>
    <?php if ($id): ?><input type="hidden" name="id" value="<?= (int) $id ?>"><?php endif; ?>

    <div class="card mb-3"><div class="card-body">
      <h2 class="titulo-secao mb-3">Nota e destino</h2>
      <div class="row g-3">
        <div class="col-6 col-md-3">
          <label class="form-label" for="data">Data <span class="text-danger">*</span></label>
          <input type="date" class="form-control<?= $cls('data') ?>" id="data" name="data" required
                 value="<?= e($v('data', date('Y-m-d'))) ?>">
          <div class="invalid-feedback"><?= e($erro('data')) ?></div>
        </div>
        <div class="col-6 col-md-4">
          <label class="form-label" for="nota_fiscal">Nota fiscal <span class="text-danger">*</span></label>
          <input class="form-control<?= $cls('nota_fiscal') ?>" id="nota_fiscal" name="nota_fiscal" required maxlength="60"
                 value="<?= e($v('nota_fiscal')) ?>" placeholder="Ex.: 59498 ou 16414/16415" autocomplete="off">
          <div class="invalid-feedback"><?= e($erro('nota_fiscal')) ?></div>
        </div>
        <div class="col-12 col-md-5">
          <label class="form-label" for="empresa_id">Empresa <span class="text-danger">*</span></label>
          <select class="form-select<?= $cls('empresa_id') ?>" id="empresa_id" name="empresa_id" required>
            <option value="">Escolha…</option>
            <?php foreach ($opcoes['empresas'] as $o): ?>
              <option value="<?= $o['id'] ?>" <?= $sel('empresa_id', $o['id']) ?>><?= e($o['nome']) ?></option>
            <?php endforeach; ?>
          </select>
          <div class="invalid-feedback"><?= e($erro('empresa_id')) ?></div>
        </div>

        <?php if ($duplicadas): ?>
          <div class="col-12">
            <div class="alert alert-warning mb-0">
              <div class="fw-medium mb-1"><i class="bi bi-files"></i> Já existe(m) frete(s) com esta nota fiscal:</div>
              <ul class="small mb-2">
                <?php foreach ($duplicadas as $d): ?>
                  <li><a href="<?= url('fretes/lancamentos/editar?id=' . (int) $d['id']) ?>" target="_blank"><?= data_br($d['data']) ?></a>
                    · <?= e($d['vendedor'] ?? 'sem vendedor') ?> · cliente <?= moeda($d['valor_cliente']) ?></li>
                <?php endforeach; ?>
              </ul>
              <div class="form-check">
                <input class="form-check-input" type="checkbox" value="1" id="confirmar_duplicada" name="confirmar_duplicada">
                <label class="form-check-label small" for="confirmar_duplicada">Conferi: é outro frete e quero salvar mesmo assim</label>
              </div>
            </div>
          </div>
        <?php endif; ?>

        <div class="col-12 col-md-7">
          <label class="form-label" for="vendedor_id">Vendedor <span class="text-danger">*</span></label>
          <select class="form-select busca-select<?= $cls('vendedor_id') ?>" id="vendedor_id" name="vendedor_id" required data-placeholder="Digite para buscar…">
            <option value="">Digite para buscar…</option>
            <?php $g = null; foreach ($opcoes['vendedores'] as $o):
                if ($g !== $o['gestor']): if ($g !== null) echo '</optgroup>'; $g = $o['gestor']; ?>
                <optgroup label="Gestor: <?= e($g) ?>">
            <?php endif; ?>
              <option value="<?= $o['id'] ?>" <?= $sel('vendedor_id', $o['id']) ?>><?= e($o['nome']) ?></option>
            <?php endforeach; if ($g !== null) echo '</optgroup>'; ?>
          </select>
          <div class="invalid-feedback d-block"><?= e($erro('vendedor_id')) ?></div>
        </div>
        <div class="col-12 col-md-5">
          <label class="form-label" for="uf">UF de destino</label>
          <select class="form-select<?= $cls('uf') ?>" id="uf" name="uf">
            <option value="">—</option>
            <?php foreach (UFS as $uf): ?>
              <option <?= $sel('uf', $uf) ?>><?= $uf ?></option>
            <?php endforeach; ?>
          </select>
          <div class="invalid-feedback"><?= e($erro('uf')) ?></div>
        </div>
      </div>
    </div></div>

    <div class="card mb-3"><div class="card-body">
      <h2 class="titulo-secao mb-3">Transportadora e valores</h2>
      <div class="row g-3">
        <div class="col-12 col-md-7">
          <label class="form-label" for="transportadora_id">Transportadora <span class="text-danger">*</span></label>
          <select class="form-select busca-select<?= $cls('transportadora_id') ?>" id="transportadora_id" name="transportadora_id" required data-placeholder="Digite para buscar…">
            <option value="">Digite para buscar…</option>
            <?php foreach ($opcoes['transportadoras'] as $o): ?>
              <option value="<?= $o['id'] ?>" <?= $sel('transportadora_id', $o['id']) ?>><?= e($o['nome']) ?></option>
            <?php endforeach; ?>
          </select>
          <div class="invalid-feedback d-block"><?= e($erro('transportadora_id')) ?></div>
        </div>
        <div class="col-12 col-md-5">
          <label class="form-label" for="documento">Nº do documento (fatura/boleto)</label>
          <input class="form-control<?= $cls('documento') ?>" id="documento" name="documento" maxlength="40"
                 value="<?= e($documento) ?>" autocomplete="off" placeholder="Deixe em branco se ainda não chegou">
          <div class="form-text">Se a fatura não existir, ela é criada automaticamente.</div>
          <div class="invalid-feedback"><?= e($erro('documento')) ?></div>
        </div>

        <div class="col-12">
          <span class="form-label d-block">Cobrança do frete</span>
          <div class="btn-group flex-wrap" role="group" aria-label="Cobrança do frete">
            <input type="radio" class="btn-check" name="cobranca" id="cobrancaCliente" value="cliente" <?= $isento ? '' : 'checked' ?>>
            <label class="btn btn-outline-primary" for="cobrancaCliente"><i class="bi bi-person"></i> Cobrado do cliente</label>
            <input type="radio" class="btn-check" name="cobranca" id="cobrancaIsento" value="isento" <?= $isento ? 'checked' : '' ?>>
            <label class="btn btn-outline-primary" for="cobrancaIsento"><i class="bi bi-shield-check"></i> Isento — a empresa assume</label>
          </div>
          <div class="form-text" id="ajudaCobranca">&nbsp;</div>
        </div>

        <div class="col-12 col-md-5 bloco-isento" <?= $isento ? '' : 'hidden' ?>>
          <label class="form-label" for="isencao_id">Motivo da isenção <span class="text-danger">*</span></label>
          <select class="form-select<?= $cls('isencao_id') ?>" id="isencao_id" name="isencao_id">
            <option value="">Escolha…</option>
            <?php foreach ($opcoes['motivos'] as $m):
                if (!$m['ativo'] && (int) $m['id'] !== (int) ($frete['isencao_id'] ?? 0)) continue; ?>
              <option value="<?= (int) $m['id'] ?>" data-exige="<?= (int) $m['exige_valor_pedido'] ?>" <?= $sel('isencao_id', $m['id']) ?>>
                <?= e($m['nome']) ?><?= $m['exige_valor_pedido'] ? ' (a partir de ' . moeda($opcoes['pedido_minimo']) . ')' : '' ?></option>
            <?php endforeach; ?>
          </select>
          <div class="invalid-feedback"><?= e($erro('isencao_id')) ?></div>
        </div>
        <div class="col-5 col-md-3 bloco-isento" <?= $isento ? '' : 'hidden' ?>>
          <label class="form-label" for="numero_pedido">Nº do pedido (ERP) <span class="text-danger pedido-obrig" hidden>*</span></label>
          <input class="form-control<?= $cls('numero_pedido') ?>" id="numero_pedido" name="numero_pedido" maxlength="30"
                 value="<?= e(antigo('numero_pedido', $frete['numero_pedido'] ?? '')) ?>" autocomplete="off">
          <div class="invalid-feedback"><?= e($erro('numero_pedido')) ?></div>
        </div>
        <div class="col-7 col-md-4 bloco-isento" <?= $isento ? '' : 'hidden' ?>>
          <label class="form-label" for="valor_pedido">Valor do pedido <span class="text-danger pedido-obrig" hidden>*</span></label>
          <div class="input-group">
            <span class="input-group-text">R$</span>
            <input class="form-control text-end campo-moeda<?= $cls('valor_pedido') ?>" id="valor_pedido" name="valor_pedido"
                   inputmode="decimal" value="<?= e($valorMoeda('valor_pedido')) ?>" placeholder="0,00" autocomplete="off"
                   data-minimo="<?= e($opcoes['pedido_minimo']) ?>">
            <div class="invalid-feedback"><?= e($erro('valor_pedido')) ?></div>
          </div>
          <div class="form-text" id="ajudaPedido">&nbsp;</div>
        </div>

        <div class="col-6 col-md-4 bloco-cliente" <?= $isento ? 'hidden' : '' ?>>
          <label class="form-label" for="valor_cliente">Cobrado do cliente</label>
          <div class="input-group">
            <span class="input-group-text">R$</span>
            <input class="form-control text-end campo-moeda<?= $cls('valor_cliente') ?>" id="valor_cliente" name="valor_cliente"
                   inputmode="decimal" value="<?= e($valorMoeda('valor_cliente')) ?>" placeholder="0,00" autocomplete="off">
            <div class="invalid-feedback"><?= e($erro('valor_cliente')) ?></div>
          </div>
          <div class="form-text">0 = frete grátis por conta do vendedor (sai do saldo dele).</div>
        </div>
        <div class="col-6 col-md-4">
          <label class="form-label" for="valor_transp">Cobrado pela transportadora</label>
          <div class="input-group">
            <span class="input-group-text">R$</span>
            <input class="form-control text-end campo-moeda<?= $cls('valor_transp') ?>" id="valor_transp" name="valor_transp"
                   inputmode="decimal" value="<?= e($valorMoeda('valor_transp')) ?>" placeholder="aguardando" autocomplete="off">
            <div class="invalid-feedback"><?= e($erro('valor_transp')) ?></div>
          </div>
          <div class="form-text">Em branco = aguardando fatura (fica fora do saldo).</div>
        </div>
        <div class="col-12 col-md-4">
          <div class="resultado-diferenca h-100" id="caixaDiferenca" aria-live="polite">
            <div class="small text-body-secondary">Diferença para o vendedor</div>
            <div class="fs-4 fw-semibold numero" id="diferenca">—</div>
            <div class="small" id="diferencaTexto">&nbsp;</div>
          </div>
        </div>
      </div>
    </div></div>

    <div class="card mb-3"><div class="card-body">
      <h2 class="titulo-secao mb-3">Crédito e observações</h2>
      <div class="row g-3">
        <div class="col-12 col-md-7">
          <label class="form-label" for="solicitacao_id"><i class="bi bi-gift"></i> Usa frete grátis aprovado?</label>
          <select class="form-select<?= $cls('solicitacao_id') ?>" id="solicitacao_id" name="solicitacao_id">
            <option value="">Não</option>
            <?php foreach ($solicitacoes as $s): ?>
              <option value="<?= $s['id'] ?>" data-vendedor="<?= (int) $s['vendedor_id'] ?>" data-valor="<?= e($s['valor']) ?>"
                      <?= $sel('solicitacao_id', $s['id']) ?>>
                #<?= (int) $s['id'] ?> · <?= e($s['vendedor']) ?> · <?= moeda($s['valor']) ?><?= $s['cliente'] ? ' · ' . e($s['cliente']) : '' ?>
              </option>
            <?php endforeach; ?>
          </select>
          <div class="form-text" id="ajudaSolicitacao">Aparecem só os fretes grátis aprovados do vendedor escolhido.</div>
          <div class="invalid-feedback"><?= e($erro('solicitacao_id')) ?></div>
        </div>
        <div class="col-12 col-md-5">
          <label class="form-label" for="obs">Observação</label>
          <input class="form-control<?= $cls('obs') ?>" id="obs" name="obs" maxlength="255" value="<?= e($v('obs')) ?>">
          <div class="invalid-feedback"><?= e($erro('obs')) ?></div>
        </div>
      </div>
    </div></div>

    <div class="barra-acoes">
      <a class="btn btn-outline-secondary" href="<?= url('fretes/lancamentos') ?>">Cancelar</a>
      <div class="d-flex gap-2 flex-grow-1 flex-sm-grow-0">
        <?php if (!$id): ?>
          <button class="btn btn-outline-primary flex-grow-1" type="submit" name="continuar" value="1"
                  title="Salva e abre um novo lançamento mantendo data, empresa, transportadora e documento">
            Salvar e lançar outro</button>
        <?php endif; ?>
        <button class="btn btn-primary flex-grow-1" type="submit"><i class="bi bi-check2"></i> Salvar</button>
      </div>
    </div>
  </form>
</div>
