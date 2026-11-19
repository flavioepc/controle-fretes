<?php /** @var string $conteudo */ ?>
<!doctype html>
<html lang="pt-BR" data-bs-theme="light">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($titulo ?? '') ?> · <?= e(config('app_nome')) ?></title>
  <script>(function () { try { var t = localStorage.getItem('tema'); if (!t) t = matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'; document.documentElement.setAttribute('data-bs-theme', t); } catch (e) {} })();</script>
  <link rel="stylesheet" href="<?= asset('vendor/bootstrap/bootstrap.min.css') ?>">
  <link rel="stylesheet" href="<?= asset('vendor/bootstrap-icons/bootstrap-icons.min.css') ?>">
  <link rel="stylesheet" href="<?= asset('css/app.css') ?>">
</head>
<body class="tela-auth">
  <main class="tela-auth-caixa">
    <?= $conteudo ?>
  </main>
  <script src="<?= asset('vendor/bootstrap/bootstrap.bundle.min.js') ?>"></script>
</body>
</html>
