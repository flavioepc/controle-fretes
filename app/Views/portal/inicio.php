<?php
$hora = (int) date('H');
$saudacao = $hora < 12 ? 'Bom dia' : ($hora < 18 ? 'Boa tarde' : 'Boa noite');
$primeiroNome = explode(' ', $usuario['nome'] ?? '')[0];
?>
<div class="container-xxl px-0">
  <div class="mb-4">
    <h1 class="h3 mb-1"><?= e($saudacao) ?>, <?= e($primeiroNome) ?></h1>
    <p class="text-body-secondary mb-0">Escolha o sistema que deseja acessar.</p>
  </div>

  <?php if (!$sistemas): ?>
    <div class="alert alert-info">Seu usuário ainda não tem acesso a nenhum sistema. Fale com o administrador.</div>
  <?php else: ?>
    <div class="row g-3">
      <?php foreach ($sistemas as $s): ?>
        <div class="col-12 col-sm-6 col-xl-4">
          <a class="card cartao-sistema h-100 text-decoration-none" href="<?= e(url_sistema($s)) ?>">
            <div class="card-body d-flex gap-3 align-items-start">
              <span class="cartao-sistema-icone"><i class="bi <?= e($s['icone'] ?: 'bi-app') ?>"></i></span>
              <div class="flex-grow-1">
                <h2 class="h6 mb-1 text-body"><?= e($s['nome']) ?></h2>
                <p class="small text-body-secondary mb-2"><?= e($s['descricao']) ?></p>
                <?php if (!empty($s['externo'])): ?>
                  <span class="badge text-bg-light border fw-normal"><i class="bi bi-key me-1"></i>Login próprio do app</span>
                <?php elseif ($s['perfil'] !== 'usuario'): ?>
                  <span class="badge text-bg-light border fw-normal">Perfil: <?= e(ucfirst($s['perfil'])) ?></span>
                <?php endif; ?>
              </div>
              <i class="bi bi-arrow-right text-body-secondary"></i>
            </div>
          </a>
        </div>
      <?php endforeach; ?>

      <div class="col-12 col-sm-6 col-xl-4">
        <div class="card h-100 border-dashed bg-transparent">
          <div class="card-body d-flex gap-3 align-items-center text-body-secondary">
            <span class="cartao-sistema-icone opacity-50"><i class="bi bi-plus-lg"></i></span>
            <div class="small">Novos sistemas da intranet aparecerão aqui.</div>
          </div>
        </div>
      </div>
    </div>
  <?php endif; ?>
</div>
