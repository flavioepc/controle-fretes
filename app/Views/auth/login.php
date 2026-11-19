<?php use App\Core\Csrf; ?>
<div class="text-center mb-4">
  <span class="marca-icone marca-icone-lg mb-3"><i class="bi bi-grid-3x3-gap-fill"></i></span>
  <h1 class="h4 mb-1">Intranet Fabreck</h1>
  <p class="text-body-secondary small mb-0">Entre com seu e-mail corporativo</p>
</div>

<div class="card shadow-sm border-0">
  <div class="card-body p-4">
    <?php if ($erro): ?>
      <div class="alert alert-danger py-2 small d-flex gap-2 align-items-center" role="alert">
        <i class="bi bi-exclamation-circle"></i><span><?= e($erro) ?></span>
      </div>
    <?php endif; ?>

    <form method="post" action="<?= url('login') ?>" novalidate>
      <?= Csrf::campo() ?>
      <div class="mb-3">
        <label for="email" class="form-label small fw-medium">E-mail</label>
        <input type="email" class="form-control form-control-lg" id="email" name="email"
               value="<?= e($email) ?>" autocomplete="username" required autofocus inputmode="email">
      </div>
      <div class="mb-4">
        <label for="senha" class="form-label small fw-medium">Senha</label>
        <div class="input-group input-group-lg">
          <input type="password" class="form-control" id="senha" name="senha" autocomplete="current-password" required>
          <button class="btn btn-outline-secondary" type="button" id="verSenha" aria-label="Mostrar senha">
            <i class="bi bi-eye"></i>
          </button>
        </div>
      </div>
      <button class="btn btn-primary btn-lg w-100" type="submit">Entrar</button>
    </form>
  </div>
</div>
<p class="text-center text-body-secondary small mt-4 mb-0">Esqueceu a senha? Fale com o administrador.</p>

<script>
  document.getElementById('verSenha').addEventListener('click', function () {
    var c = document.getElementById('senha'), mostrar = c.type === 'password';
    c.type = mostrar ? 'text' : 'password';
    this.innerHTML = mostrar ? '<i class="bi bi-eye-slash"></i>' : '<i class="bi bi-eye"></i>';
    this.setAttribute('aria-label', mostrar ? 'Ocultar senha' : 'Mostrar senha');
  });
</script>
