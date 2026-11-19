<?php
use App\Core\Csrf;

$erro = fn (string $c) => $erros[$c] ?? null;
$cls = fn (string $c) => isset($erros[$c]) ? ' is-invalid' : '';
?>
<div class="d-flex flex-wrap align-items-end justify-content-between gap-2 mb-3">
  <div>
    <h1 class="h4 mb-0">Cadastros</h1>
    <p class="text-body-secondary small mb-0">Empresas, transportadoras, gestores e vendedores do sistema de fretes.</p>
  </div>
</div>

<nav class="abas-status mb-3" aria-label="Tipo de cadastro">
  <?php foreach (['empresas' => 'Empresas', 'transportadoras' => 'Transportadoras',
                  'gestores' => 'Gestores', 'vendedores' => 'Vendedores'] as $k => $rot): ?>
    <a class="btn btn-sm <?= $aba === $k ? 'btn-secondary' : 'btn-outline-secondary' ?>"
       href="<?= url('fretes/cadastros?aba=' . $k) ?>" <?= $aba === $k ? 'aria-current="page"' : '' ?>><?= e($rot) ?></a>
  <?php endforeach; ?>
</nav>

<?php if ($aba === 'empresas' || $aba === 'transportadoras'):
  $rotaBase = "fretes/cadastros/$aba";
  $rotuloNovo = $aba === 'empresas' ? 'Nova empresa' : 'Nova transportadora';
  $modalId = $aba === 'empresas' ? 'modalEmpresa' : 'modalTransportadora';
?>
  <div class="d-flex justify-content-end mb-2">
    <button class="btn btn-primary btn-sm" type="button" data-bs-toggle="modal" data-bs-target="#<?= $modalId ?>">
      <i class="bi bi-plus-lg"></i> <?= $rotuloNovo ?></button>
  </div>
  <div class="card">
    <?php if (!$linhas): ?>
      <div class="vazio"><i class="bi bi-inbox"></i>Nenhum cadastro ainda.</div>
    <?php else: ?>
      <div class="table-responsive-cards">
        <table class="table table-sm table-hover align-middle mb-0 tabela-cards">
          <thead><tr><th>Nome</th><th>CNPJ</th><th>Status</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($linhas as $l): ?>
            <tr>
              <td data-rotulo="Nome" class="fw-medium"><?= e($l['nome']) ?></td>
              <td data-rotulo="CNPJ"><?= e(cnpj_br($l['cnpj'])) ?></td>
              <td data-rotulo="Status">
                <span class="badge rounded-pill text-bg-<?= $l['ativo'] ? 'success' : 'secondary' ?>"><?= $l['ativo'] ? 'Ativo' : 'Inativo' ?></span>
              </td>
              <td class="text-end text-nowrap acoes">
                <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="modal" data-bs-target="#<?= $modalId ?>"
                        data-id="<?= (int) $l['id'] ?>" data-nome="<?= e($l['nome']) ?>" data-cnpj="<?= e($l['cnpj'] ?? '') ?>">
                  <i class="bi bi-pencil"></i><span class="d-sm-none ms-1">Editar</span></button>
                <form method="post" action="<?= url("$rotaBase/alternar") ?>" class="d-inline">
                  <?= Csrf::campo() ?>
                  <input type="hidden" name="id" value="<?= (int) $l['id'] ?>">
                  <button class="btn btn-sm btn-outline-<?= $l['ativo'] ? 'danger' : 'success' ?>" type="submit">
                    <i class="bi bi-toggle-<?= $l['ativo'] ? 'on' : 'off' ?>"></i><span class="d-sm-none ms-1"><?= $l['ativo'] ? 'Inativar' : 'Ativar' ?></span></button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

  <div class="modal fade" id="<?= $modalId ?>" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <form class="modal-content" method="post" action="<?= url("$rotaBase/salvar") ?>" novalidate>
        <?= Csrf::campo() ?>
        <input type="hidden" name="id" value="<?= e(antigo('id')) ?>">
        <div class="modal-header">
          <h2 class="modal-title h5 modal-titulo-acao"><?= $rotuloNovo ?></h2>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label" for="<?= $aba ?>Nome">Nome <span class="text-danger">*</span></label>
            <input class="form-control<?= $cls('nome') ?>" id="<?= $aba ?>Nome" name="nome" maxlength="80"
                   value="<?= e(antigo('nome')) ?>" required>
            <div class="invalid-feedback"><?= e($erro('nome')) ?></div>
          </div>
          <div class="mb-0">
            <label class="form-label" for="<?= $aba ?>Cnpj">CNPJ</label>
            <input class="form-control<?= $cls('cnpj') ?>" id="<?= $aba ?>Cnpj" name="cnpj" maxlength="18"
                   value="<?= e(antigo('cnpj')) ?>" placeholder="Opcional, só números">
            <div class="invalid-feedback"><?= e($erro('cnpj')) ?></div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary">Salvar</button>
        </div>
      </form>
    </div>
  </div>
  <?php if ($erros): ?>
    <script>document.addEventListener('DOMContentLoaded', () => new bootstrap.Modal(document.getElementById('<?= $modalId ?>')).show());</script>
  <?php endif; ?>

<?php elseif ($aba === 'gestores'): ?>

  <div class="d-flex justify-content-end mb-2">
    <button class="btn btn-primary btn-sm" type="button" data-bs-toggle="modal" data-bs-target="#modalGestor">
      <i class="bi bi-plus-lg"></i> Novo gestor</button>
  </div>
  <div class="card">
    <?php if (!$linhas): ?>
      <div class="vazio"><i class="bi bi-inbox"></i>Nenhum gestor cadastrado.</div>
    <?php else: ?>
      <div class="table-responsive-cards">
        <table class="table table-sm table-hover align-middle mb-0 tabela-cards">
          <thead><tr><th>Nome</th><th>Login vinculado</th><th class="text-end">Vendedores</th><th>Status</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($linhas as $l): ?>
            <tr>
              <td data-rotulo="Nome" class="fw-medium"><?= e($l['nome']) ?></td>
              <td data-rotulo="Login">
                <?php if ($l['usuario_nome']): ?>
                  <?= e($l['usuario_nome']) ?>
                  <?php if ($l['usuario_ativo'] === false): ?><span class="badge text-bg-secondary fw-normal ms-1">login inativo</span><?php endif; ?>
                  <div class="text-body-secondary" style="font-size:.75rem"><?= e($l['usuario_email']) ?></div>
                <?php else: ?>
                  <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="modal" data-bs-target="#modalAcesso"
                          data-id="<?= (int) $l['id'] ?>" data-nome="<?= e($l['nome']) ?>"
                          data-acao="<?= e(url('fretes/cadastros/' . $aba . '/criar-acesso')) ?>">
                    <i class="bi bi-person-plus"></i> Criar acesso</button>
                <?php endif; ?>
              </td>
              <td data-rotulo="Vendedores" class="text-end"><?= (int) $l['qtd_vendedores'] ?></td>
              <td data-rotulo="Status">
                <span class="badge rounded-pill text-bg-<?= $l['ativo'] ? 'success' : 'secondary' ?>"><?= $l['ativo'] ? 'Ativo' : 'Inativo' ?></span>
              </td>
              <td class="text-end text-nowrap acoes">
                <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="modal" data-bs-target="#modalGestor"
                        data-id="<?= (int) $l['id'] ?>" data-nome="<?= e($l['nome']) ?>" data-usuario_id="<?= (int) ($l['usuario_id'] ?? 0) ?>">
                  <i class="bi bi-pencil"></i><span class="d-sm-none ms-1">Editar</span></button>
                <form method="post" action="<?= url('fretes/cadastros/gestores/alternar') ?>" class="d-inline">
                  <?= Csrf::campo() ?>
                  <input type="hidden" name="id" value="<?= (int) $l['id'] ?>">
                  <button class="btn btn-sm btn-outline-<?= $l['ativo'] ? 'danger' : 'success' ?>" type="submit">
                    <i class="bi bi-toggle-<?= $l['ativo'] ? 'on' : 'off' ?>"></i><span class="d-sm-none ms-1"><?= $l['ativo'] ? 'Inativar' : 'Ativar' ?></span></button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

  <div class="modal fade" id="modalGestor" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <form class="modal-content" method="post" action="<?= url('fretes/cadastros/gestores/salvar') ?>" novalidate>
        <?= Csrf::campo() ?>
        <input type="hidden" name="id" value="<?= e(antigo('id')) ?>">
        <div class="modal-header">
          <h2 class="modal-title h5 modal-titulo-acao">Novo gestor</h2>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label" for="gestorNome">Nome <span class="text-danger">*</span></label>
            <input class="form-control<?= $cls('nome') ?>" id="gestorNome" name="nome" maxlength="80"
                   value="<?= e(antigo('nome')) ?>" required>
            <div class="invalid-feedback"><?= e($erro('nome')) ?></div>
          </div>
          <div class="mb-0">
            <label class="form-label" for="gestorUsuario">Login vinculado</label>
            <select class="form-select<?= $cls('usuario_id') ?>" id="gestorUsuario" name="usuario_id">
              <option value="">Nenhum (sem login ainda)</option>
              <?php foreach ($usuarios as $u): ?>
                <option value="<?= (int) $u['id'] ?>" <?= (string) antigo('usuario_id') === (string) $u['id'] ? 'selected' : '' ?>>
                  <?= e($u['nome']) ?> (<?= e($u['email']) ?>)</option>
              <?php endforeach; ?>
            </select>
            <div class="invalid-feedback"><?= e($erro('usuario_id')) ?></div>
            <div class="form-text">Quem faz login no portal e aprova as solicitações de crédito da equipe.</div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary">Salvar</button>
        </div>
      </form>
    </div>
  </div>
  <?php if ($erros): ?>
    <script>document.addEventListener('DOMContentLoaded', () => new bootstrap.Modal(document.getElementById('modalGestor')).show());</script>
  <?php endif; ?>

<?php else: /* vendedores */ ?>

  <div class="d-flex justify-content-end mb-2">
    <button class="btn btn-primary btn-sm" type="button" data-bs-toggle="modal" data-bs-target="#modalVendedor">
      <i class="bi bi-plus-lg"></i> Novo vendedor</button>
  </div>
  <div class="card">
    <?php if (!$linhas): ?>
      <div class="vazio"><i class="bi bi-inbox"></i>Nenhum vendedor cadastrado.</div>
    <?php else: ?>
      <div class="table-responsive-cards">
        <table class="table table-sm table-hover align-middle mb-0 tabela-cards">
          <thead><tr><th>Nome</th><th>Gestor</th><th>Login vinculado</th><th>Crédito expira</th><th>Status</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($linhas as $l):
            $expiraTexto = $l['credito_expira'] === null ? 'segue a regra geral' : ((int) $l['credito_expira'] ? 'sempre' : 'nunca');
          ?>
            <tr>
              <td data-rotulo="Nome" class="fw-medium"><?= e($l['nome']) ?></td>
              <td data-rotulo="Gestor"><?= e($l['gestor']) ?></td>
              <td data-rotulo="Login">
                <?php if ($l['usuario_nome']): ?>
                  <?= e($l['usuario_nome']) ?>
                  <?php if ($l['usuario_ativo'] === false): ?><span class="badge text-bg-secondary fw-normal ms-1">login inativo</span><?php endif; ?>
                  <div class="text-body-secondary" style="font-size:.75rem"><?= e($l['usuario_email']) ?></div>
                <?php else: ?>
                  <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="modal" data-bs-target="#modalAcesso"
                          data-id="<?= (int) $l['id'] ?>" data-nome="<?= e($l['nome']) ?>"
                          data-acao="<?= e(url('fretes/cadastros/' . $aba . '/criar-acesso')) ?>">
                    <i class="bi bi-person-plus"></i> Criar acesso</button>
                <?php endif; ?>
              </td>
              <td data-rotulo="Crédito expira" class="small"><?= e($expiraTexto) ?></td>
              <td data-rotulo="Status">
                <span class="badge rounded-pill text-bg-<?= $l['ativo'] ? 'success' : 'secondary' ?>"><?= $l['ativo'] ? 'Ativo' : 'Inativo' ?></span>
              </td>
              <td class="text-end text-nowrap acoes">
                <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="modal" data-bs-target="#modalVendedor"
                        data-id="<?= (int) $l['id'] ?>" data-nome="<?= e($l['nome']) ?>" data-gestor_id="<?= (int) $l['gestor_id'] ?>"
                        data-usuario_id="<?= (int) ($l['usuario_id'] ?? 0) ?>"
                        data-credito_expira="<?= $l['credito_expira'] === null ? '' : (int) $l['credito_expira'] ?>">
                  <i class="bi bi-pencil"></i><span class="d-sm-none ms-1">Editar</span></button>
                <form method="post" action="<?= url('fretes/cadastros/vendedores/alternar') ?>" class="d-inline">
                  <?= Csrf::campo() ?>
                  <input type="hidden" name="id" value="<?= (int) $l['id'] ?>">
                  <button class="btn btn-sm btn-outline-<?= $l['ativo'] ? 'danger' : 'success' ?>" type="submit">
                    <i class="bi bi-toggle-<?= $l['ativo'] ? 'on' : 'off' ?>"></i><span class="d-sm-none ms-1"><?= $l['ativo'] ? 'Inativar' : 'Ativar' ?></span></button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

  <div class="modal fade" id="modalVendedor" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <form class="modal-content" method="post" action="<?= url('fretes/cadastros/vendedores/salvar') ?>" novalidate>
        <?= Csrf::campo() ?>
        <input type="hidden" name="id" value="<?= e(antigo('id')) ?>">
        <div class="modal-header">
          <h2 class="modal-title h5 modal-titulo-acao">Novo vendedor</h2>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label" for="vendedorNome">Nome <span class="text-danger">*</span></label>
            <input class="form-control<?= $cls('nome') ?>" id="vendedorNome" name="nome" maxlength="80"
                   value="<?= e(antigo('nome')) ?>" required>
            <div class="invalid-feedback"><?= e($erro('nome')) ?></div>
          </div>
          <div class="mb-3">
            <label class="form-label" for="vendedorGestor">Gestor <span class="text-danger">*</span></label>
            <select class="form-select<?= $cls('gestor_id') ?>" id="vendedorGestor" name="gestor_id" required>
              <option value="">Escolha o gestor…</option>
              <?php foreach ($gestoresAtivos as $g): ?>
                <option value="<?= (int) $g['id'] ?>" <?= (string) antigo('gestor_id') === (string) $g['id'] ? 'selected' : '' ?>><?= e($g['nome']) ?></option>
              <?php endforeach; ?>
            </select>
            <div class="invalid-feedback"><?= e($erro('gestor_id')) ?></div>
          </div>
          <div class="mb-3">
            <label class="form-label" for="vendedorUsuario">Login vinculado</label>
            <select class="form-select<?= $cls('usuario_id') ?>" id="vendedorUsuario" name="usuario_id">
              <option value="">Nenhum (sem login ainda)</option>
              <?php foreach ($usuarios as $u): ?>
                <option value="<?= (int) $u['id'] ?>" <?= (string) antigo('usuario_id') === (string) $u['id'] ? 'selected' : '' ?>>
                  <?= e($u['nome']) ?> (<?= e($u['email']) ?>)</option>
              <?php endforeach; ?>
            </select>
            <div class="invalid-feedback"><?= e($erro('usuario_id')) ?></div>
          </div>
          <div class="mb-0">
            <span class="form-label d-block">Expiração do crédito na virada do ano</span>
            <div class="btn-group w-100" role="group" aria-label="Expiração do crédito">
              <input type="radio" class="btn-check" name="credito_expira" id="expiraGeral" value="" <?= antigo('credito_expira', '') === '' ? 'checked' : '' ?>>
              <label class="btn btn-outline-secondary" for="expiraGeral">Regra geral</label>
              <input type="radio" class="btn-check" name="credito_expira" id="expiraSempre" value="1" <?= antigo('credito_expira') === '1' ? 'checked' : '' ?>>
              <label class="btn btn-outline-secondary" for="expiraSempre">Sempre expira</label>
              <input type="radio" class="btn-check" name="credito_expira" id="expiraNunca" value="0" <?= antigo('credito_expira') === '0' ? 'checked' : '' ?>>
              <label class="btn btn-outline-secondary" for="expiraNunca">Nunca expira</label>
            </div>
            <div class="form-text">"Regra geral" segue a chave <code>credito_expira_virada_ano</code> das Configurações.</div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary">Salvar</button>
        </div>
      </form>
    </div>
  </div>
  <?php if ($erros): ?>
    <script>document.addEventListener('DOMContentLoaded', () => new bootstrap.Modal(document.getElementById('modalVendedor')).show());</script>
  <?php endif; ?>

<?php endif; ?>

<div class="modal fade" id="modalAcesso" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form class="modal-content" method="post" action="" novalidate>
      <?= Csrf::campo() ?>
      <input type="hidden" name="id" value="">
      <div class="modal-header">
        <h2 class="modal-title h5">Criar acesso <span class="text-body-secondary fw-normal acesso-nome"></span></h2>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
      </div>
      <div class="modal-body">
        <label class="form-label" for="acessoEmail">E-mail da pessoa (será o login) <span class="text-danger">*</span></label>
        <input class="form-control" id="acessoEmail" name="email" type="email" maxlength="150" required inputmode="email">
        <div class="form-text">
          Cria o usuário na intranet com acesso só ao Controle de Fretes, no perfil
          <strong><?= $aba === 'gestores' ? 'gestor' : 'vendedor' ?></strong>, e gera uma senha provisória.
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="submit" class="btn btn-primary">Criar acesso</button>
      </div>
    </form>
  </div>
</div>