<?php
/** @var string $conteudo */
use App\Core\Auth;
use App\Core\Csrf;
use App\Fretes\Permissoes;

$usuario = Auth::usuario();
$secao = $secao ?? 'portal';
$pagina = $pagina ?? '';
$perfil = Auth::perfil('fretes');
// contador de pedidos de crédito esperando a decisão deste usuário (quem pode aprovar)
$pendentesMenu = 0;
if ($secao === 'fretes' && Permissoes::pode('credito.decidir')) {
    try {
        $pendentesMenu = (new App\Fretes\CreditoRepository(new App\Fretes\Escopo()))->pendentesParaMim();
    } catch (\Throwable $e) {
        $pendentesMenu = 0;
    }
}
$contadores = ['solicitacoes' => $pendentesMenu];

// menu do sistema de fretes (itens sem rota ainda aparecem como "em breve");
// a última coluna são as permissões que mostram o item (qualquer uma; null = todos os perfis)
$menuFretes = [
    ['dashboard',    'Dashboard',          'bi-speedometer2',      'fretes',        true,  null],
    ['fretes',       'Fretes',             'bi-truck',             'fretes/lancamentos', true, ['fretes.lancar']],
    ['impressao',    'Imprimir faturas',   'bi-printer',           'fretes/impressao', true, ['faturas.imprimir']],
    ['faturas',      'Faturas',           'bi-receipt',           'fretes/faturas', true,  ['faturas.ver']],
    ['solicitacoes', 'Uso de crédito',     'bi-hand-thumbs-up',    'fretes/credito', true, null],
    ['extrato',      'Extrato',            'bi-list-columns-reverse', 'fretes/extrato', true, null],
    ['movimentos',   'Pagamentos e transf.', 'bi-arrow-left-right', 'fretes/movimentos', true,
        ['movimentos.pagamento', 'movimentos.transferencia', 'movimentos.estorno']],
    ['expirados',    'Créditos expirados', 'bi-hourglass-bottom',  'fretes/creditos-expirados', true, ['expirados.ver']],
    ['cadastros',    'Cadastros',          'bi-people',            'fretes/cadastros', true,  ['cadastros']],
    ['config',       'Configurações',      'bi-gear',              'fretes/configuracoes', true, ['configuracoes']],
    ['permissoes',   'Permissões',         'bi-shield-check',      'fretes/permissoes', true, ['permissoes']],
];
?>
<!doctype html>
<html lang="pt-BR" data-bs-theme="light">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="<?= e(Csrf::token()) ?>">
  <title><?= e($titulo ?? '') ?> · <?= e(config('app_nome')) ?></title>
  <script>/* aplica o tema antes de desenhar a página (sem "piscar") */
    (function () { try { var t = localStorage.getItem('tema'); if (!t) t = matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'; document.documentElement.setAttribute('data-bs-theme', t); } catch (e) {} })();
  </script>
  <link rel="stylesheet" href="<?= asset('vendor/bootstrap/bootstrap.min.css') ?>">
  <link rel="stylesheet" href="<?= asset('vendor/bootstrap-icons/bootstrap-icons.min.css') ?>">
<?php foreach ($estilos ?? [] as $css): ?>
  <link rel="stylesheet" href="<?= $css ?>">
<?php endforeach; ?>
  <link rel="stylesheet" href="<?= asset('css/app.css') ?>">
</head>
<body class="app">

<header class="navbar app-topo border-bottom sticky-top">
  <div class="container-fluid gap-2">
    <?php if ($secao === 'fretes'): ?>
      <button class="btn btn-link text-body d-lg-none px-1" type="button" data-bs-toggle="offcanvas"
              data-bs-target="#menuLateral" aria-controls="menuLateral" aria-label="Abrir menu">
        <i class="bi bi-list fs-4"></i>
      </button>
    <?php endif; ?>

    <a class="navbar-brand d-flex align-items-center gap-2 me-auto" href="<?= url('/') ?>">
      <span class="marca-icone"><i class="bi bi-grid-3x3-gap-fill"></i></span>
      <span class="fw-semibold">Fabreck</span>
      <?php if ($secao === 'fretes'): ?>
        <span class="text-body-secondary d-none d-sm-inline">/ Fretes</span>
      <?php endif; ?>
    </a>

    <button class="btn btn-link text-body px-2" type="button" id="alternarTema" aria-label="Alternar tema claro/escuro">
      <i class="bi bi-moon-stars"></i>
    </button>

    <div class="dropdown">
      <button class="btn btn-link text-body text-decoration-none d-flex align-items-center gap-2 px-1"
              data-bs-toggle="dropdown" aria-expanded="false">
        <span class="avatar"><?= e(iniciais($usuario['nome'] ?? '?')) ?></span>
        <span class="d-none d-md-inline small"><?= e($usuario['nome'] ?? '') ?></span>
        <i class="bi bi-chevron-down small"></i>
      </button>
      <ul class="dropdown-menu dropdown-menu-end shadow-sm">
        <li><h6 class="dropdown-header"><?= e($usuario['email'] ?? '') ?></h6></li>
        <?php if ($perfil): ?>
          <li><span class="dropdown-item-text small">Perfil em Fretes: <strong><?= e(ucfirst($perfil)) ?></strong></span></li>
        <?php endif; ?>
        <li><hr class="dropdown-divider"></li>
        <li><a class="dropdown-item" href="<?= url('/') ?>"><i class="bi bi-house me-2"></i>Início do portal</a></li>
        <?php if (Auth::perfil('usuarios') === 'admin'): ?>
          <li><a class="dropdown-item" href="<?= url('usuarios') ?>"><i class="bi bi-person-gear me-2"></i>Usuários e acessos</a></li>
        <?php endif; ?>
        <li><a class="dropdown-item" href="<?= url('trocar-senha') ?>"><i class="bi bi-shield-lock me-2"></i>Alterar senha</a></li>
        <li>
          <form method="post" action="<?= url('sair') ?>">
            <?= Csrf::campo() ?>
            <button class="dropdown-item text-danger"><i class="bi bi-box-arrow-right me-2"></i>Sair</button>
          </form>
        </li>
      </ul>
    </div>
  </div>
</header>

<div class="app-corpo">
  <?php if ($secao === 'fretes'): ?>
    <nav class="offcanvas-lg offcanvas-start app-menu border-end" tabindex="-1" id="menuLateral" aria-label="Menu do sistema de fretes">
      <div class="offcanvas-header border-bottom">
        <h5 class="offcanvas-title">Controle de Fretes</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" data-bs-target="#menuLateral" aria-label="Fechar"></button>
      </div>
      <div class="offcanvas-body flex-column p-2">
        <ul class="nav nav-pills flex-column gap-1">
          <?php foreach ($menuFretes as [$id, $rotulo, $icone, $rota, $pronto, $permissoes]):
              if ($permissoes && !Permissoes::podeAlguma($permissoes)) continue; ?>
            <li class="nav-item">
              <?php if ($pronto): ?>
                <a class="nav-link <?= $pagina === $id ? 'active' : '' ?>" href="<?= url($rota) ?>"
                   <?= $pagina === $id ? 'aria-current="page"' : '' ?>>
                  <i class="bi <?= $icone ?>"></i><span><?= e($rotulo) ?></span>
                  <?php if (!empty($contadores[$id])): ?>
                    <span class="badge rounded-pill text-bg-danger ms-auto" title="Aguardando sua aprovação"><?= (int) $contadores[$id] ?></span>
                  <?php endif; ?>
                </a>
              <?php else: ?>
                <span class="nav-link disabled" aria-disabled="true">
                  <i class="bi <?= $icone ?>"></i><span><?= e($rotulo) ?></span>
                  <span class="badge text-bg-light border ms-auto fw-normal">em breve</span>
                </span>
              <?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>
    </nav>
  <?php endif; ?>

  <main class="app-conteudo" id="conteudo">
    <?php foreach (['sucesso' => 'success', 'erro' => 'danger', 'aviso' => 'warning'] as $tipo => $cor):
        if ($msg = flash($tipo)): ?>
        <div class="alert alert-<?= $cor ?> alert-dismissible fade show" role="alert">
          <?= e($msg) ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
        </div>
    <?php endif; endforeach; ?>

    <?= $conteudo ?>
  </main>
</div>

<script src="<?= asset('vendor/bootstrap/bootstrap.bundle.min.js') ?>"></script>
<script src="<?= asset('js/app.js') ?>"></script>
<?php foreach ($scripts ?? [] as $s): ?>
  <script src="<?= $s ?>"></script>
<?php endforeach; ?>
</body>
</html>
