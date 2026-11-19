<div class="text-center py-5">
  <i class="bi bi-exclamation-octagon display-5 text-body-secondary"></i>
  <h1 class="h4 mt-3"><?= e($titulo ?? 'Erro') ?></h1>
  <p class="text-body-secondary"><?= e($mensagem ?? '') ?></p>
  <a href="<?= url('/') ?>" class="btn btn-outline-primary">Voltar ao início</a>
</div>
