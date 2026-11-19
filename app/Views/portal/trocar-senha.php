<?php use App\Core\Csrf; ?>
<div class="row justify-content-center">
  <div class="col-12 col-sm-10 col-md-7 col-lg-5">
    <h1 class="h4 mb-1"><?= $obrigatoria ? 'Cadastre uma nova senha' : 'Alterar senha' ?></h1>
    <p class="text-body-secondary small mb-3">
      <?= $obrigatoria
          ? 'Você entrou com uma senha provisória. Escolha uma senha só sua para continuar.'
          : 'Use pelo menos 8 caracteres, com letras e números.' ?>
    </p>

    <div class="card">
      <div class="card-body p-4">
        <?php if ($erro): ?>
          <div class="alert alert-danger py-2 small d-flex gap-2 align-items-center" role="alert">
            <i class="bi bi-exclamation-circle"></i><span><?= e($erro) ?></span>
          </div>
        <?php endif; ?>
        <form method="post" action="<?= url('trocar-senha') ?>" novalidate>
          <?= Csrf::campo() ?>
          <div class="mb-3">
            <label class="form-label small fw-medium" for="senhaAtual"><?= $obrigatoria ? 'Senha provisória' : 'Senha atual' ?></label>
            <input class="form-control" type="password" id="senhaAtual" name="senha_atual" autocomplete="current-password" required autofocus>
          </div>
          <div class="mb-3">
            <label class="form-label small fw-medium" for="senhaNova">Nova senha</label>
            <input class="form-control" type="password" id="senhaNova" name="senha_nova" autocomplete="new-password" minlength="8" required>
            <div class="form-text">Pelo menos 8 caracteres, com letras e números.</div>
          </div>
          <div class="mb-4">
            <label class="form-label small fw-medium" for="senhaConfirma">Repita a nova senha</label>
            <input class="form-control" type="password" id="senhaConfirma" name="senha_confirma" autocomplete="new-password" minlength="8" required>
          </div>
          <div class="d-flex gap-2 justify-content-end">
            <?php if (!$obrigatoria): ?>
              <a class="btn btn-outline-secondary" href="<?= url('/') ?>">Cancelar</a>
            <?php endif; ?>
            <button class="btn btn-primary" type="submit">Salvar nova senha</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
