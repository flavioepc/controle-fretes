<?php /** @var string $conteudo */ ?>
<!doctype html>
<html lang="pt-BR" data-bs-theme="light">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="<?= e(App\Core\Csrf::token()) ?>">
  <title><?= e($titulo ?? '') ?> · <?= e(config('app_nome')) ?></title>
  <link rel="stylesheet" href="<?= asset('vendor/bootstrap/bootstrap.min.css') ?>">
  <link rel="stylesheet" href="<?= asset('vendor/bootstrap-icons/bootstrap-icons.min.css') ?>">
  <link rel="stylesheet" href="<?= asset('css/impressao.css') ?>">
</head>
<body class="impressao">
  <?= $conteudo ?>
  <script src="<?= asset('js/impressao.js') ?>"></script>
</body>
</html>
