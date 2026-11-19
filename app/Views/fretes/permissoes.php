<?php
use App\Core\Csrf;
use App\Fretes\Permissoes;

/** @var array $historico */
$grupos = [];
foreach (Permissoes::CATALOGO as $chave => [$grupo]) {
    $grupos[$grupo][] = $chave;
}
$diferentes = 0;
foreach (Permissoes::CATALOGO as $chave => $_) {
    foreach (Permissoes::PERFIS as $perfil => $__) {
        if (Permissoes::trava($perfil, $chave) === null
            && Permissoes::perfilPode($perfil, $chave) !== Permissoes::padrao($perfil, $chave)) {
            $diferentes++;
        }
    }
}
?>
<div class="d-flex flex-wrap align-items-end justify-content-between gap-2 mb-3">
  <div>
    <h1 class="h4 mb-0">Permissões</h1>
    <p class="text-body-secondary small mb-0">O que cada perfil pode fazer no sistema de fretes. Vale na hora, para todos os
      usuários do perfil.</p>
  </div>
  <?php if ($diferentes): ?>
    <form method="post" action="<?= url('fretes/permissoes/restaurar') ?>"
          onsubmit="return confirm('Voltar todas as permissões ao padrão do sistema?');">
      <?= Csrf::campo() ?>
      <button class="btn btn-outline-secondary btn-sm" type="submit"><i class="bi bi-arrow-counterclockwise"></i>
        Voltar ao padrão (<?= $diferentes ?> diferente(s))</button>
    </form>
  <?php endif; ?>
</div>

<div class="alert alert-light border small d-flex gap-2">
  <i class="bi bi-info-circle fs-5"></i>
  <div>A permissão libera a <strong>tela ou ação</strong>; quais vendedores cada um enxerga não muda: gestor vê só a equipe e
    vendedor só ele mesmo. Por isso as ações que valem para todos os vendedores ficam <i class="bi bi-lock"></i> travadas para
    gestor e vendedor. O administrador nunca perde o acesso a este painel. Dashboard e extrato abrem para todos os perfis.
    <span class="badge text-bg-warning">diferente do padrão</span> marca o que já foi mudado aqui.</div>
</div>

<form method="post" action="<?= url('fretes/permissoes/salvar') ?>" id="formPermissoes">
  <?= Csrf::campo() ?>
  <div class="card">
    <div class="table-responsive">
      <table class="table table-sm align-middle mb-0 tabela-permissoes">
        <thead class="sticky-top">
          <tr>
            <th>Permissão</th>
            <?php foreach (Permissoes::PERFIS as $perfil => $nome): ?>
              <th class="text-center" style="width:7.5rem">
                <?= e($nome) ?>
                <div><button class="btn btn-link btn-sm p-0 small marcar-coluna" type="button" data-perfil="<?= $perfil ?>"
                             title="Marcar/desmarcar tudo que é editável para <?= e($nome) ?>">todas</button></div>
              </th>
            <?php endforeach; ?>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($grupos as $grupo => $chaves): ?>
          <tr><th colspan="<?= count(Permissoes::PERFIS) + 1 ?>" class="small text-uppercase text-body-secondary bg-body-tertiary"><?= e($grupo) ?></th></tr>
          <?php foreach ($chaves as $chave): [, $rotulo, $explica] = Permissoes::CATALOGO[$chave]; ?>
            <tr>
              <td>
                <div class="fw-medium"><?= e($rotulo) ?></div>
                <div class="small text-body-secondary"><?= e($explica) ?></div>
              </td>
              <?php foreach (Permissoes::PERFIS as $perfil => $nome):
                  $trava = Permissoes::trava($perfil, $chave);
                  $ligado = Permissoes::perfilPode($perfil, $chave);
                  $mudado = $trava === null && $ligado !== Permissoes::padrao($perfil, $chave); ?>
                <td class="text-center<?= $mudado ? ' table-warning' : '' ?>">
                  <?php if ($trava !== null): ?>
                    <span class="text-body-secondary" title="<?= $trava ? 'Sempre liberado' : 'Não se aplica a este perfil' ?>">
                      <i class="bi <?= $trava ? 'bi-check-lg' : 'bi-dash-lg' ?>"></i> <i class="bi bi-lock small"></i></span>
                  <?php else: ?>
                    <input class="form-check-input perm" type="checkbox" name="p[<?= $perfil ?>][<?= e($chave) ?>]" value="1"
                           data-perfil="<?= $perfil ?>" data-original="<?= $ligado ? '1' : '0' ?>" <?= $ligado ? 'checked' : '' ?>
                           aria-label="<?= e($rotulo) ?> — <?= e($nome) ?>">
                    <?php if ($mudado): ?><div class="small text-warning-emphasis" style="font-size:.7rem">padrão: <?= Permissoes::padrao($perfil, $chave) ? 'sim' : 'não' ?></div><?php endif; ?>
                  <?php endif; ?>
                </td>
              <?php endforeach; ?>
            </tr>
          <?php endforeach; ?>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div class="card-footer bg-transparent d-flex flex-wrap align-items-center justify-content-end gap-2 barra-salvar">
      <span class="small text-body-secondary me-auto" id="resumoPermissoes" aria-live="polite"></span>
      <button class="btn btn-outline-secondary" type="reset">Desfazer</button>
      <button class="btn btn-primary" type="submit" id="btnSalvarPermissoes"><i class="bi bi-check2"></i> Salvar permissões</button>
    </div>
  </div>
</form>

<?php if ($historico): ?>
  <h2 class="titulo-secao mt-4">Últimas mudanças</h2>
  <div class="card">
    <div class="table-responsive-cards">
      <table class="table table-sm align-middle mb-0 tabela-cards">
        <thead><tr><th>Quando</th><th>Quem</th><th>Perfil</th><th>Permissão</th><th>Ficou</th></tr></thead>
        <tbody>
        <?php foreach ($historico as $h): ?>
          <tr>
            <td data-rotulo="Quando" class="text-nowrap"><?= e(date('d/m/Y H:i', strtotime($h['alterado_em']))) ?></td>
            <td data-rotulo="Quem"><?= e($h['usuario'] ?? '—') ?></td>
            <td data-rotulo="Perfil"><?= e(Permissoes::PERFIS[$h['perfil']] ?? $h['perfil']) ?></td>
            <td data-rotulo="Permissão"><?= e(Permissoes::CATALOGO[$h['permissao']][1] ?? $h['permissao']) ?></td>
            <td data-rotulo="Ficou">
              <?= (int) $h['permitido']
                  ? '<span class="badge text-bg-success">liberado</span>'
                  : '<span class="badge text-bg-secondary">bloqueado</span>' ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>
