<?php
use App\Core\Csrf;

$situacoes = [
    '' => 'Todas', 'aguardando' => 'Aguardando valor da transportadora', 'sem_fatura' => 'Sem fatura',
    'sem_vendedor' => 'Sem vendedor', 'credito' => 'Geraram crédito', 'debito' => 'Consumiram crédito',
    'gratis_vendedor' => 'Frete grátis do vendedor (cliente R$ 0)', 'isento' => 'Isentos — empresa assumiu',
];
$motivosIsencao = [];
foreach ($opcoes['motivos'] as $m) {
    $motivosIsencao['isento_' . $m['id']] = $m['nome'];
}
$podeExcluir = App\Fretes\Permissoes::pode('fretes.excluir');
$podeImprimir = App\Fretes\Permissoes::pode('faturas.imprimir');
$filtrosAtivos = $f['vendedor'] || $f['transportadora'] || $f['empresa'] || $f['busca'] !== '' || $f['situacao'] !== '';
?>
<div class="d-flex flex-wrap align-items-end justify-content-between gap-2 mb-3">
  <div>
    <h1 class="h4 mb-0">Fretes</h1>
    <p class="text-body-secondary small mb-0"><?= data_br($f['de']) ?> a <?= data_br($f['ate']) ?> ·
      <?= number_format($res['total'], 0, ',', '.') ?> lançamento(s)</p>
  </div>
  <div class="d-flex gap-2">
    <button class="btn btn-outline-secondary btn-sm d-md-none" type="button" data-bs-toggle="collapse"
            data-bs-target="#filtrosFretes" aria-expanded="false" aria-controls="filtrosFretes">
      <i class="bi bi-funnel"></i> Filtros<?= $filtrosAtivos ? ' <span class="badge text-bg-primary">•</span>' : '' ?>
    </button>
    <a class="btn btn-primary btn-sm" href="<?= url('fretes/lancamentos/novo') ?>"><i class="bi bi-plus-lg"></i> Lançar frete</a>
  </div>
</div>

<form class="collapse d-md-block mb-3" id="filtrosFretes" method="get" action="<?= url('fretes/lancamentos') ?>">
  <div class="card card-body py-2 px-3 barra-filtros">
    <div class="row g-2 align-items-end">
      <div class="col-12 col-md-3 col-xl-2">
        <label class="form-label small mb-1" for="busca">Buscar</label>
        <input class="form-control form-control-sm" id="busca" name="busca" value="<?= e($f['busca']) ?>"
               placeholder="NF, documento, pedido, obs." inputmode="search">
      </div>
      <div class="col-6 col-md-2 col-xl-auto">
        <label class="form-label small mb-1" for="de">De</label>
        <input class="form-control form-control-sm" type="date" id="de" name="de" value="<?= e($f['de']) ?>">
      </div>
      <div class="col-6 col-md-2 col-xl-auto">
        <label class="form-label small mb-1" for="ate">Até</label>
        <input class="form-control form-control-sm" type="date" id="ate" name="ate" value="<?= e($f['ate']) ?>">
      </div>
      <div class="col-12 col-md-5 col-xl">
        <label class="form-label small mb-1" for="vendedor">Vendedor</label>
        <select class="form-select form-select-sm busca-select" id="vendedor" name="vendedor" data-placeholder="Todos">
          <option value="">Todos</option>
          <?php $g = null; foreach ($opcoes['vendedores'] as $o):
              if ($g !== $o['gestor']): if ($g !== null) echo '</optgroup>'; $g = $o['gestor']; ?>
              <optgroup label="<?= e($g) ?>">
          <?php endif; ?>
            <option value="<?= $o['id'] ?>" <?= $f['vendedor'] === (int) $o['id'] ? 'selected' : '' ?>><?= e($o['nome']) ?></option>
          <?php endforeach; if ($g !== null) echo '</optgroup>'; ?>
        </select>
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
      <div class="col-6 col-md-3 col-xl-auto">
        <label class="form-label small mb-1" for="situacao">Situação</label>
        <select class="form-select form-select-sm" id="situacao" name="situacao">
          <?php foreach ($situacoes as $k => $v): ?>
            <option value="<?= $k ?>" <?= $f['situacao'] === $k ? 'selected' : '' ?>><?= e($v) ?></option>
          <?php endforeach; ?>
          <optgroup label="Isentos por motivo">
            <?php foreach ($motivosIsencao as $k => $v): ?>
              <option value="<?= $k ?>" <?= $f['situacao'] === $k ? 'selected' : '' ?>><?= e($v) ?></option>
            <?php endforeach; ?>
          </optgroup>
        </select>
      </div>
      <div class="col-12 col-md-2 col-xl-auto d-flex gap-2">
        <button class="btn btn-primary btn-sm flex-grow-1" type="submit"><i class="bi bi-search"></i> Filtrar</button>
        <a class="btn btn-outline-secondary btn-sm" href="<?= url('fretes/lancamentos') ?>" aria-label="Limpar filtros"><i class="bi bi-x-lg"></i></a>
      </div>
    </div>
  </div>
</form>

<!-- Totais do filtro -->
<div class="row g-2 mb-3">
  <?php foreach ([['Cobrado dos clientes', $res['somas']['cliente'], ''],
                  ['Cobrado pelas transportadoras', $res['somas']['transp'], ''],
                  ['Diferença (crédito)', $res['somas']['dif'], classe_sinal($res['somas']['dif'])],
                  ['Isentos (empresa assumiu)', $res['somas']['isento'], '']] as [$rot, $val, $cls]): ?>
    <div class="col-6 col-lg-3">
      <div class="card card-indicador"><div class="card-body py-2">
        <div class="indicador-rotulo"><?= e($rot) ?></div>
        <div class="fs-5 fw-semibold numero <?= $cls ?>"><?= moeda($val) ?></div>
      </div></div>
    </div>
  <?php endforeach; ?>
</div>

<div class="card">
  <?php if (!$res['linhas']): ?>
    <div class="vazio"><i class="bi bi-inbox"></i>Nenhum frete encontrado com esses filtros.</div>
  <?php else: ?>
    <div class="table-responsive-cards">
      <table class="table table-sm table-hover align-middle mb-0 tabela-cards tabela-fretes">
        <thead><tr>
          <th>NF / Data</th><th>Vendedor</th><th>Transportadora</th><th>Documento</th>
          <th class="text-end">Cliente</th><th class="text-end">Transp.</th><th class="text-end">Diferença</th><th></th>
        </tr></thead>
        <tbody>
        <?php foreach ($res['linhas'] as $l): ?>
          <tr id="f<?= (int) $l['id'] ?>">
            <td data-rotulo="NF">
              <span class="fw-medium"><?= e($l['nota_fiscal']) ?></span>
              <span class="text-body-secondary small ms-1"><?= data_br($l['data']) ?></span>
              <div class="text-body-secondary" style="font-size:.75rem">
                <?= e($l['empresa'] ?? 'sem empresa') ?><?= $l['uf'] ? ' · ' . e($l['uf']) : '' ?>
                <?= $l['solicitacao_id'] ? ' · <span class="text-primary-emphasis"><i class="bi bi-gift"></i> frete grátis</span>' : '' ?>
                <?= $l['isencao_id'] ? ' · <span class="text-success-emphasis" title="O cliente não pagou; a empresa assumiu o frete"><i class="bi bi-shield-check"></i> isento: ' . e($l['motivo_isencao']) . '</span>' : '' ?>
                <?= $l['numero_pedido'] ? ' · pedido ' . e($l['numero_pedido']) . ($l['valor_pedido'] !== null ? ' (' . moeda($l['valor_pedido']) . ')' : '') : '' ?>
              </div>
            </td>
            <td data-rotulo="Vendedor">
              <?php if ($l['vendedor']): ?>
                <?= e($l['vendedor']) ?><div class="text-body-secondary d-none d-sm-block" style="font-size:.75rem"><?= e($l['gestor']) ?></div>
              <?php else: ?><span class="badge text-bg-warning">sem vendedor</span><?php endif; ?>
            </td>
            <td data-rotulo="Transportadora"><?= e($l['transportadora']) ?></td>
            <td data-rotulo="Documento" class="text-nowrap"><?= e($l['numero_documento'] ?? '—') ?>
              <?php if ($l['fatura_id'] && $podeImprimir): ?>
                <a class="ms-1" href="<?= url('fretes/faturas/imprimir?id=' . (int) $l['fatura_id']) ?>" target="_blank"
                   title="Imprimir relatório da fatura para o financeiro" aria-label="Imprimir fatura <?= e($l['numero_documento']) ?>"><i class="bi bi-printer"></i></a>
              <?php endif; ?></td>
            <td data-rotulo="Cliente" class="text-end numero text-nowrap"><?= moeda($l['valor_cliente'], false) ?></td>
            <td data-rotulo="Transportadora (R$)" class="text-end numero text-nowrap">
              <?= $l['valor_transp'] === null ? '<span class="badge text-bg-light border fw-normal" title="Fora do saldo até informar">aguardando</span>' : moeda($l['valor_transp'], false) ?>
            </td>
            <td data-rotulo="Diferença" class="text-end numero text-nowrap fw-medium <?= classe_sinal($l['diferenca']) ?>">
              <?= $l['diferenca'] === null ? '—' : moeda($l['diferenca'], false) ?>
            </td>
            <td class="text-end text-nowrap acoes">
              <a class="btn btn-sm btn-outline-secondary" href="<?= url('fretes/lancamentos/editar?id=' . (int) $l['id']) ?>"
                 aria-label="Editar frete NF <?= e($l['nota_fiscal']) ?>"><i class="bi bi-pencil"></i><span class="d-sm-none ms-1">Editar</span></a>
              <?php if ($podeExcluir): ?>
                <button class="btn btn-sm btn-outline-danger" type="button" data-bs-toggle="modal" data-bs-target="#modalExcluir"
                        data-id="<?= (int) $l['id'] ?>" data-nf="<?= e($l['nota_fiscal']) ?>" aria-label="Excluir frete NF <?= e($l['nota_fiscal']) ?>">
                  <i class="bi bi-trash"></i></button>
              <?php endif; ?>
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
            <a class="page-link" href="<?= e(url_com(['pagina' => $paginaAtual - 1], 'fretes/lancamentos')) ?>" aria-label="Anterior">‹</a></li>
          <?php for ($i = max(1, $paginaAtual - 2); $i <= min($paginas, $paginaAtual + 2); $i++): ?>
            <li class="page-item <?= $i === $paginaAtual ? 'active' : '' ?>">
              <a class="page-link" href="<?= e(url_com(['pagina' => $i], 'fretes/lancamentos')) ?>"><?= $i ?></a></li>
          <?php endfor; ?>
          <li class="page-item <?= $paginaAtual >= $paginas ? 'disabled' : '' ?>">
            <a class="page-link" href="<?= e(url_com(['pagina' => $paginaAtual + 1], 'fretes/lancamentos')) ?>" aria-label="Próxima">›</a></li>
        </ul>
      </nav>
    <?php endif; ?>
  <?php endif; ?>
</div>

<?php if ($podeExcluir): ?>
<div class="modal fade" id="modalExcluir" tabindex="-1" aria-labelledby="modalExcluirTitulo" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form class="modal-content" method="post" action="<?= url('fretes/lancamentos/excluir') ?>">
      <?= Csrf::campo() ?>
      <input type="hidden" name="id" id="excluirId">
      <div class="modal-header"><h2 class="modal-title h5" id="modalExcluirTitulo">Excluir frete</h2>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button></div>
      <div class="modal-body">Excluir o frete da NF <strong id="excluirNf"></strong>? O saldo do vendedor será recalculado.
        Esta ação não pode ser desfeita.</div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="submit" class="btn btn-danger">Excluir</button>
      </div>
    </form>
  </div>
</div>
<script>
  document.getElementById('modalExcluir').addEventListener('show.bs.modal', function (ev) {
    var b = ev.relatedTarget;
    document.getElementById('excluirId').value = b.getAttribute('data-id');
    document.getElementById('excluirNf').textContent = b.getAttribute('data-nf');
  });
</script>
<?php endif; ?>
