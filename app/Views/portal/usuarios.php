<?php
use App\Core\Auth;
use App\Core\Csrf;

$erro = fn (string $c) => $erros[$c] ?? null;
$cls = fn (string $c) => isset($erros[$c]) ? ' is-invalid' : '';
$rotuloPerfil = fn (string $p) => $p === 'usuario' ? 'Liberado' : ucfirst($p);
$antigoAcesso = is_array(antigo('acesso', null)) ? antigo('acesso') : [];
?>
<div class="d-flex flex-wrap align-items-end justify-content-between gap-2 mb-3">
  <div>
    <h1 class="h4 mb-0">Usuários e acessos</h1>
    <p class="text-body-secondary small mb-0">Quem entra na intranet e quais aplicativos cada pessoa vê.</p>
  </div>
  <button class="btn btn-primary btn-sm" type="button" data-bs-toggle="modal" data-bs-target="#modalUsuario">
    <i class="bi bi-person-plus"></i> Novo usuário</button>
</div>

<div class="card">
  <?php if (!$usuarios): ?>
    <div class="vazio"><i class="bi bi-inbox"></i>Nenhum usuário cadastrado.</div>
  <?php else: ?>
    <div class="table-responsive-cards">
      <table class="table table-sm table-hover align-middle mb-0 tabela-cards">
        <thead><tr><th>Usuário</th><th>Aplicativos</th><th>Último acesso</th><th>Status</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($usuarios as $u):
            $acessosJson = [];
            foreach ($u['acessos'] as $slug => $a) {
                $acessosJson[$slug] = $a['perfil'];
            } ?>
          <tr id="u<?= (int) $u['id'] ?>">
            <td data-rotulo="Usuário">
              <div class="fw-medium"><?= e($u['nome']) ?>
                <?php if ((int) $u['id'] === Auth::id()): ?><span class="badge text-bg-light border fw-normal ms-1">você</span><?php endif; ?>
              </div>
              <div class="text-body-secondary" style="font-size:.78rem"><?= e($u['email']) ?></div>
            </td>
            <td data-rotulo="Aplicativos">
              <?php if (!$u['acessos']): ?>
                <span class="text-body-secondary small">nenhum</span>
              <?php endif; ?>
              <?php foreach ($u['acessos'] as $a): ?>
                <span class="badge text-bg-light border fw-normal mb-1"><?= e($a['nome']) ?>
                  <?php if ($a['perfil'] !== 'usuario'): ?><span class="text-body-secondary">· <?= e($rotuloPerfil($a['perfil'])) ?></span><?php endif; ?>
                </span>
              <?php endforeach; ?>
            </td>
            <td data-rotulo="Último acesso" class="small text-nowrap">
              <?= $u['ultimo_login'] ? e(date('d/m/Y H:i', strtotime($u['ultimo_login']))) : '<span class="text-body-secondary">nunca</span>' ?>
              <?php if ((int) $u['trocar_senha']): ?>
                <div><span class="badge text-bg-warning fw-normal">senha provisória</span></div>
              <?php endif; ?>
            </td>
            <td data-rotulo="Status">
              <span class="badge rounded-pill text-bg-<?= $u['ativo'] ? 'success' : 'secondary' ?>"><?= $u['ativo'] ? 'Ativo' : 'Inativo' ?></span>
            </td>
            <td class="text-end text-nowrap acoes">
              <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="modal" data-bs-target="#modalUsuario"
                      data-id="<?= (int) $u['id'] ?>" data-nome="<?= e($u['nome']) ?>" data-email="<?= e($u['email']) ?>"
                      data-ativo="<?= (int) $u['ativo'] ?>" data-acessos="<?= e(json_encode($acessosJson)) ?>" title="Editar">
                <i class="bi bi-pencil"></i><span class="d-sm-none ms-1">Editar</span></button>
              <form method="post" action="<?= url('usuarios/redefinir-senha') ?>" class="d-inline"
                    onsubmit="return confirm('Gerar uma nova senha provisória para <?= e(addslashes($u['nome'])) ?>? A senha atual deixa de funcionar.');">
                <?= Csrf::campo() ?>
                <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                <button class="btn btn-sm btn-outline-secondary" type="submit" title="Gerar senha provisória">
                  <i class="bi bi-key"></i><span class="d-sm-none ms-1">Nova senha</span></button>
              </form>
              <?php if ((int) $u['id'] !== Auth::id()): ?>
                <form method="post" action="<?= url('usuarios/alternar') ?>" class="d-inline">
                  <?= Csrf::campo() ?>
                  <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                  <button class="btn btn-sm btn-outline-<?= $u['ativo'] ? 'danger' : 'success' ?>" type="submit"
                          title="<?= $u['ativo'] ? 'Inativar' : 'Ativar' ?>">
                    <i class="bi bi-toggle-<?= $u['ativo'] ? 'on' : 'off' ?>"></i><span class="d-sm-none ms-1"><?= $u['ativo'] ? 'Inativar' : 'Ativar' ?></span></button>
                </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<p class="text-body-secondary small mt-3 mb-0">
  <i class="bi bi-info-circle"></i>
  Apps com login próprio (como o WebFolha) só aparecem no portal para quem tiver acesso liberado aqui;
  o usuário e a senha de dentro do app continuam sendo cadastrados no próprio app.
</p>

<div class="modal fade" id="modalUsuario" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
    <form class="modal-content" method="post" action="<?= url('usuarios/salvar') ?>" novalidate>
      <?= Csrf::campo() ?>
      <input type="hidden" name="id" value="<?= e(antigo('id')) ?>">
      <div class="modal-header">
        <h2 class="modal-title h5 modal-titulo-acao">Novo usuário</h2>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
      </div>
      <div class="modal-body">
        <?php if ($erro('acesso')): ?>
          <div class="alert alert-danger py-2 small"><?= e($erro('acesso')) ?></div>
        <?php endif; ?>
        <div class="mb-3">
          <label class="form-label" for="usuarioNome">Nome <span class="text-danger">*</span></label>
          <input class="form-control<?= $cls('nome') ?>" id="usuarioNome" name="nome" maxlength="100"
                 value="<?= e(antigo('nome')) ?>" required>
          <div class="invalid-feedback"><?= e($erro('nome')) ?></div>
        </div>
        <div class="mb-3">
          <label class="form-label" for="usuarioEmail">E-mail (login) <span class="text-danger">*</span></label>
          <input class="form-control<?= $cls('email') ?>" id="usuarioEmail" name="email" type="email" maxlength="150"
                 value="<?= e(antigo('email')) ?>" required inputmode="email">
          <div class="invalid-feedback"><?= e($erro('email')) ?></div>
        </div>
        <div class="form-check form-switch mb-3">
          <input class="form-check-input" type="checkbox" role="switch" id="usuarioAtivo" name="ativo" value="1"
                 <?= antigo('ativo', '1') ? 'checked' : '' ?>>
          <label class="form-check-label" for="usuarioAtivo">Ativo (pode entrar na intranet)</label>
        </div>

        <h3 class="h6 mb-2">Aplicativos liberados</h3>
        <div class="list-group">
          <?php foreach ($sistemas as $s): ?>
            <div class="list-group-item d-flex flex-wrap align-items-center justify-content-between gap-2">
              <label class="fw-medium small mb-0" for="acesso_<?= e($s['slug']) ?>"><?= e($s['nome']) ?></label>
              <select class="form-select form-select-sm w-auto" id="acesso_<?= e($s['slug']) ?>"
                      name="acesso[<?= e($s['slug']) ?>]" data-sistema="<?= e($s['slug']) ?>">
                <option value="">Sem acesso</option>
                <?php foreach ($s['perfis'] as $p): ?>
                  <option value="<?= e($p) ?>" <?= ($antigoAcesso[$s['slug']] ?? '') === $p ? 'selected' : '' ?>><?= e($rotuloPerfil($p)) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          <?php endforeach; ?>
        </div>
        <p class="form-text mb-0 texto-novo">Uma senha provisória será gerada e mostrada depois de salvar.</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="submit" class="btn btn-primary">Salvar</button>
      </div>
    </form>
  </div>
</div>
<?php if ($erros): ?>
  <script>document.addEventListener('DOMContentLoaded', () => new bootstrap.Modal(document.getElementById('modalUsuario')).show());</script>
<?php endif; ?>
