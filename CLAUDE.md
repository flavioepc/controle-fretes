# CLAUDE.md

Este arquivo orienta o Claude Code (claude.ai/code) ao trabalhar com o código deste repositório.

Sempre responda e comente em português brasileiro.

## O que é este projeto

"Controle de Fretes Fabreck" — uma aplicação web em PHP 8.3 + MySQL 8 + Bootstrap 5 que substitui uma
planilha (`CONTROLE_FRETE`) usada para controlar custos de frete e o saldo de crédito de cada vendedor. Faz
parte da intranet Fabreck: o usuário entra pelo **portal** (login único) e escolhe o sistema a partir dali;
este repositório é o sistema `fretes`. Sem framework, sem Composer, sem etapa de build — PHP puro com um
núcleo mínimo feito à mão.

Comentários, textos de interface, mensagens de commit e a maioria dos identificadores deste código estão em
**português** — mantenha código novo consistente com isso (nomes de variáveis/métodos, mensagens de
validação, etc.).

## Rodando localmente (XAMPP)

1. Inicie o Apache e o MySQL no XAMPP Control Panel.
2. O banco local é um **espelho da produção** (copiado em 03/11/2026 10:32: 10.411 fretes, mesmos usuários,
   saldos e rotinas). Entre com o seu login real da intranet; os logins de teste (`admin@fabreck.local` etc.)
   estão desativados, como em produção. **Não rode `database/instalar_banco.bat`** — ele apaga o espelho e volta
   para a carga antiga com usuários de teste (pede para digitar APAGAR). Para mudanças de estrutura use
   `atualizar_banco.bat`. Backup do banco local anterior: `database/backup_local_antes_espelho_*.sql`.
3. Abra `http://localhost/controle-fretes/`.

Para atualizar o espelho de novo: `mysqldump` dos dois bancos no `intranet_mysql8` (ver "Produção"), remover
`/*!80016 DEFAULT ENCRYPTION='N' */` e trocar `utf8mb4_0900_ai_ci` por `utf8mb4_unicode_ci` (o MariaDB do XAMPP
não aceita), e importar no XAMPP. O caminho contrário (local → produção) nunca.

`config/config.local.php` (fica fora do Git; modelo em `config/config.local.exemplo.php`) sobrescreve só o que
muda. O padrão do `config.php` é `debug => false` (como produção); no notebook, para ver os erros completos:
```php
<?php
return ['debug' => true, 'db' => ['usuario' => 'root', 'senha' => '']];
```

Não há suíte de testes, linter ou comando de build neste repositório — não existe `composer.json`/
`package.json`. Verifique as mudanças testando a página no navegador.

## Fluxo de trabalho do banco de dados — importante

- Instalação do zero (apaga dados): `database/instalar_banco.bat`, que roda `01_portal.sql`, `02_fretes.sql`,
  `03_rotinas.sql`, `04_carga_planilha.sql`, `05_usuarios_teste.sql` nessa ordem.
- **Uma vez que existam dados reais, mudanças de estrutura chegam como novos arquivos em
  `database/atualizacoes/`** (numerados, ex.: `001_trava_aprovacao_credito.sql`) aplicados rodando
  `database/atualizar_banco.bat`. Cada arquivo de atualização precisa ser idempotente (seguro de rodar mais
  de uma vez) — siga o padrão dos arquivos que já existem ali. Aplicadas até agora (local e produção):
  `001_trava_aprovacao_credito`, `002_portal_apps_e_usuarios`, `003_perfil_operador`, `004_isencao_frete`,
  `005_pedido_erp_terceiros` (produção em 03/11/2026: 287 fretes de terceiros, R$ 105.191,60; saldo total
  inalterado em R$ 52.237,69). A tela de impressão da fatura foi publicada no mesmo dia.
  `006_impressao_faturas` (histórico de impressão) aplicada em produção em 03/11/2026.
  `007_estorno_movimentos` (estorno de pagamento/transferência) aplicada em produção em 03/11/2026
  (`~/deploy_20261103h`; saldos por vendedor idênticos antes/depois; saldo total na hora R$ 52.260,26, já com
  os lançamentos feitos em produção depois do corte).
  `008_fatura_numero_repetido` (tira a chave única transportadora + nº de `faturas`) aplicada no local e em
  produção em 09/11/2026 (`~/deploy_20261109`), junto com a separação da fatura 815 (EXPRESSO ESPERANÇA 7000,
  de 19/05, paga — ficou com 68 fretes) dos 32 fretes digitados em 05–06/11, que foram para a fatura 1776
  (7000, aberta, R$ 1.350,00); saldos por vendedor idênticos, saldo total na hora R$ 51.117,37.
  `009_alteracao_faturas` (tabela `faturas_alteracoes`, histórico de "Alterar dados" da fatura) aplicada no local
  e em produção em 18/11/2026 (`~/deploy_20261118`; saldos por vendedor idênticos, saldo total na hora
  R$ 56.340,59). Correção de conhecimento errado (só código) publicada em produção no mesmo dia
  (`~/deploy_20261118b`).
  `010_permissoes_perfil` (tabelas `permissoes_perfil` e `permissoes_historico`, painel de permissões) aplicada no
  local e em produção em 18/11/2026 (`~/deploy_20261118c`; saldos por vendedor idênticos, saldo total na hora
  R$ 56.340,59). Em produção o painel começa no padrão (tabela vazia).
- MySQL 8 não tem `ADD COLUMN IF NOT EXISTS`: para coluna/índice/FK novos crie uma procedure temporária que
  consulta `information_schema` e só então faz o `ALTER` (ver `tmp_004_colunas` na 004). Atualização que mexe
  em dado histórico precisa deixar os saldos de `vw_saldo_vendedor` idênticos — confira antes/depois.
- O XAMPP local é **MariaDB 10.4**; produção é **MySQL 8**. SQL novo precisa funcionar nos dois.
- Regras de negócio que precisam ser atômicas/livres de condição de corrida ficam em procedures/functions do
  MySQL definidas em `database/03_rotinas.sql` (ex.: `sp_decidir_solicitacao`, `sp_expirar_creditos`,
  `sp_resgatar_credito`, `sp_registrar_pagamento`, `sp_transferir_credito`, `fn_saldo_em`). O PHP chama essas
  rotinas com `CALL sp_xxx(...)` em vez de reimplementar a lógica em PHP, justamente para evitar condições de
  corrida (ex.: dois gestores aprovando o mesmo pedido de crédito ao mesmo tempo). Uma procedure sinaliza
  violação de regra de negócio com `SIGNAL SQLSTATE '45000'`; os repositórios capturam `PDOException` e
  checam `$e->errorInfo[0] === '45000'` para transformar isso numa mensagem ao usuário em vez de um erro 500
  (ver `CreditoRepository::decidir`).
- Principais views de leitura: `vw_saldo_vendedor`, `vw_saldo_gestor`, `vw_extrato`, `vw_faturas` — prefira
  consultar essas views a recalcular saldos em PHP.
- Planilhas: `tools/importar_planilhas.py <pasta> <saida>` reconstrói fretes/faturas/movimentos a partir das
  planilhas mensais (gera `importacao.sql`, `relatorio_importacao.xlsx`, `saldos_esperados.csv`; NF que o
  Excel exibia como data é recuperada pelo número serial). `tools/gerar_carga.py` é a versão antiga, de uma
  planilha só. **Desde o corte de 03/11/2026 a planilha não é mais usada** — não reimporte em produção.

## Produção

Servidor `192.168.3.198` (Ubuntu 22.04, usuário `rhadmin`), tudo em Docker em `~/intranet`
(`docker-compose.yml`): `intranet_web` (Apache + PHP 8.3, porta 80, portal na raiz e WebFolha em `/WebFolha/`)
e `intranet_mysql8` (MySQL 8, `fabreck_portal` + `fabreck_fretes`, sem porta exposta). Senhas no
`~/intranet/.env`; o PHP lê a conexão do `www/intranet/config/config.local.php` do servidor (nunca sobrescrever).
No mesmo servidor rodam também o rh-system (MySQL 5.7 `rh_mysql`) e o phpMyAdmin (porta 8080) — não mexer.

- Estado em 03/11/2026: produção tem o ano inteiro (jan–nov/2026, 10.411 fretes, saldo R$ 52.237,69) e 17
  usuários ativos, com as atualizações 001–007 aplicadas (a 004 reclassificou 2.164 fretes como isentos,
  R$ 594.363,28 assumidos pela empresa, sem alterar nenhum saldo). O espelho local foi copiado antes da 003 e
  da 004 (aplicadas nele depois), então não tem a usuária Regiane nem os lançamentos posteriores ao corte. A
  produção é a fonte da verdade: a partir
  do corte os lançamentos acontecem lá, então o espelho local envelhece. Nunca copie dump do XAMPP para lá: o
  MariaDB grava a coluna gerada `fretes.diferenca` nos INSERTs e o MySQL 8 recusa.
- Banco: comandos via `docker exec -e MYSQL_PWD="$MYSQL8_ROOT_SENHA" intranet_mysql8 mysql -uroot ...`
  (depois de `set -a; . ./.env; set +a` em `~/intranet`). Mudança de estrutura: arquivo novo em
  `database/atualizacoes/` e `bash ~/intranet/www/intranet/database/atualizar_producao.sh` no servidor.
  Antes de qualquer escrita, faça backup em `~/backups` e confira que termina com "Dump completed".
- Código: o volume `www/intranet` é montado no contêiner (sem reinício). Publique só os arquivos alterados —
  compare `md5sum` local × servidor, envie para uma pasta de preparação (`~/deploy_AAAAMMDD/`), guarde os
  atuais num `tar` em `~/backups` e copie. O Claude não tem permissão para fazer o deploy em produção sozinho:
  prepare a pasta e passe o comando para o usuário rodar. Padrão usado (ex.: `~/deploy_20261103c/aplicar.sh`):
  um `aplicar.sh` que (1) faz backup do banco e `tar` dos arquivos que serão trocados, (2) tira um retrato
  dos saldos por vendedor, aplica a atualização SQL e aborta com ALERTA se algum saldo mudar, (3) copia os
  arquivos rodando `php -l` em cada `.php` e (4) testa com `curl` login (200) e páginas internas (302). Peça
  ao usuário o print da saída.
- Acesso a partir do notebook: `plink`/`pscp` do PuTTY (host key `SHA256:2ecuZpXPQbeZLkQoDieU6LqTHnWokTmB4dDHAc3D5sE`).
  Mande comandos num arquivo (`plink -m script.sh`, com quebra de linha LF) — o PowerShell estraga aspas
  passadas em linha. Nunca grave a senha em arquivo do projeto.
- Rotinas do servidor (crontab do `rhadmin`): `~/intranet/backup_intranet.sh` diário (guarda 15 dias) e
  `CALL sp_expirar_creditos(...)` em 01/01 00:05.
- Primeiro admin, se faltar: `tools/criar_admin.php "Nome" email` (gera senha provisória; copie `tools/` antes,
  ela não é publicada no servidor).

## Ciclo de vida da requisição

Ponto de entrada único: toda requisição passa por `public/index.php` (ver `public/.htaccess` / `.htaccess`,
que roteiam tudo para lá; `app/`, `config/` e `database/` ficam fora do alcance da raiz web).

1. `public/index.php` requer `app/bootstrap.php`, que: registra um autoloader simples estilo PSR-4
   (`App\Foo\Bar` → `app/Foo/Bar.php`), carrega `app/helpers.php` (funções globais), carrega
   `config/config.php` (combinado com o opcional `config/config.local.php`), calcula `BASE_URL` a partir de
   `SCRIPT_NAME` (para o mesmo código rodar tanto na subpasta do XAMPP quanto na raiz de um vhost de
   servidor), envia os cabeçalhos de segurança (`X-Frame-Options: SAMEORIGIN`, `nosniff`, `Referrer-Policy`
   e HSTS quando HTTPS) e inicia a sessão via `App\Core\Sessao` — exceto em CLI (scripts de `tools/`, que
   podem dar `require app/bootstrap.php` para usar `Database` e os repositórios). HTTPS é detectado por
   `https_ativo()`, que também considera `X-Forwarded-Proto` (Cloudflare Tunnel/proxy) e liga o cookie `Secure`.
2. `public/index.php` monta um `App\Core\Router`, registra as rotas com `$r->get()/$r->post()` e então chama
   `$r->despachar(...)` dentro de um `try/catch (Throwable)`: o erro vai para o log (`error_log`); com `debug`
   desligado o usuário vê só uma mensagem genérica, com `debug` ligado vê o erro completo.
3. `Router::despachar` normaliza o caminho, remove o `BASE_URL`, localiza a rota, executa os middlewares na
   ordem, valida o CSRF em POST, e então chama `[Controller, método]`.
4. Middlewares disponíveis: `'auth'` (precisa estar logado), `'fretes'` (precisa ter acesso ao sistema
   `fretes`), `'pode:chave'` / `'pode:a|b'` (permissão do painel de permissões — ver "Permissões por perfil";
   é o que as rotas de fretes usam) e `'fretes:admin,financeiro'` (lista fixa de perfis; não é mais usado nas
   rotas de fretes). Dentro de controller/view use `Permissoes::pode('chave')` / `Permissoes::exigir('chave')`
   (ex.: `FreteController::excluir`).
5. Os controllers chamam um `*Repository` específico da funcionalidade (SQL puro via `App\Core\Database`,
   nunca um ORM) e renderizam uma view com `App\Core\View::render($view, $dados, $layout)`, que executa
   `app/Views/{$view}.php` dentro de `app/Views/layouts/{$layout}.php` (o conteúdo da página é injetado como
   `$conteudo`).

## Modelo de multi-tenancy / autorização

O login é único para todo o portal (tabela no banco `portal`); quais sistemas/perfis o usuário tem acesso é
carregado uma vez no login em `$_SESSION['sistemas']` (ver `App\Core\Auth::carregarSistemas`). Dentro do
sistema `fretes` existem 5 perfis: `admin`, `financeiro`, `gestor`, `operador` e `vendedor`. O `operador`
(desde 03/11/2026, atualização 003 — ex.: Regiane) lança e edita fretes e pede uso de crédito em nome de
qualquer vendedor, sem vínculo com gestor/vendedor, e (desde 18/11/2026) transfere crédito entre vendedores;
por padrão não aprova pedidos, não exclui fretes e não acessa faturas, pagamento/estorno, créditos expirados,
cadastros nem configurações. `Escopo::veTudo()` (vê todos os vendedores) inclui o operador, mas isso é
**visibilidade**, não permissão — para liberar tela/ação use `Permissoes::pode()` (`Escopo::veFaturas()` agora
é só `Permissoes::pode('faturas.ver')`). Perfil novo = `Permissoes::PERFIS` (e `PERFIS_RESTRITOS` se ele não vê
todos os vendedores), métodos do `Escopo` e `sistemas.perfis` (via arquivo em `atualizacoes/`).

### Permissões por perfil (painel `fretes/permissoes`, atualização 010)

`App\Fretes\Permissoes` é a fonte única do que cada perfil pode **fazer**: `CATALOGO` lista 16 permissões
(chave => grupo, rótulo, explicação, perfis com acesso por padrão, opções) e o padrão reproduz o acesso que
existia antes do painel (+ transferência para o operador). A tabela `permissoes_perfil` guarda **só o que o admin
mudou** (sem linha = vale o padrão do código); `permissoes_historico` registra cada mudança (perfil, permissão,
como ficou, usuário). Lido do banco a cada requisição (cache estático) — mudar no painel vale na hora.
- Travas (`Permissoes::trava`): opção `global` = ação que vale para todos os vendedores (fretes, faturas,
  pagamentos/transferências/estorno, resgate de expirados, cadastros, configurações, permissões) fica travada em
  "não" para gestor e vendedor, que só enxergam a equipe/ele mesmo; `nunca` (vendedor não aprova crédito);
  `sempre` (admin nunca perde `permissoes`). Células travadas nem são enviadas e são ignoradas no `salvar`.
- Rotas: `'pode:chave'` no `public/index.php`; menu (`layouts/app.php`, última coluna = permissões, qualquer
  uma); botões nas views (`lista.php` excluir/imprimir, `fatura.php` conferir/corrigir/imprimir, `movimentos.php`
  cada formulário e o estorno, `credito.php` formulário de pedido, `creditos-expirados.php` resgatar, links de
  correção no dashboard/extrato).
- Crédito: `Escopo::podeDecidir` exige `credito.decidir` e então quem vê tudo decide qualquer pedido, gestor só a
  equipe; `podeSolicitarPara` exige `credito.solicitar` (financeiro, se liberado, pede para qualquer vendedor);
  `CreditoRepository::pendentesParaMim` (contador do menu) segue a mesma regra.
- Permissão nova: chave no `CATALOGO` (com o padrão de cada perfil), rota com `'pode:chave'`, botão/menu com
  `Permissoes::pode()`. Não precisa de atualização de banco.
- Ao mexer em acesso, compare antes/depois: o retrato por perfil (GET de cada tela, POST sem CSRF → 403 = bloqueado,
  menu e botões) foi o teste usado em 18/11/2026; gestor e operador não existem no espelho local — crie usuários
  temporários no portal e ligue o gestor em `gestores.usuario_id`.

`App\Fretes\Escopo` é o controle de visibilidade/autorização do sistema de fretes — construído uma vez por
requisição a partir de `Auth::perfil('fretes')` mais o registro de `gestor`/`vendedor` vinculado ao usuário
atual. Os repositórios recebem um `Escopo` e chamam `$escopo->filtroVendedor('coluna')` para obter de volta
`[condiçãoSql, params]` a ser combinado com AND na cláusula WHERE — é aqui que a visibilidade de linhas por
perfil é aplicada, no SQL, não só na view. Outros métodos de `Escopo` (`podeDecidir`, `podeSolicitarPara`,
`podeCancelar`, `veTudo`, `semVinculo`) controlam ações específicas. Ao adicionar uma nova funcionalidade em
fretes que lista/filtra dados por vendedor, reaproveite `Escopo::filtroVendedor` em vez de escrever checagens
de perfil improvisadas.

## Saldo do vendedor: o que entra e o que não entra

- Frete **cobrado do cliente**: `diferenca = valor_cliente − valor_transp` vai para o saldo do vendedor.
  Cliente = 0 sem isenção = frete grátis **por conta do vendedor** (debita o saldo; pode usar uma solicitação
  de frete grátis aprovada).
- Frete **isento** (`fretes.isencao_id` preenchido, atualização 004): o cliente não paga e a **empresa** assume.
  `valor_cliente` fica 0 e a coluna gerada `diferenca` vale 0
  (`IF(isencao_id IS NULL, valor_cliente - valor_transp, valor_transp * 0)`), então não mexe no saldo.
  Motivos em `motivos_isencao` (pedido acima do valor mínimo, empresa do grupo, primeira compra, garantia de
  mercadoria, conserto, devolução, frete de terceiros (administrador) — id 8, atualização 005 —, outro). O
  administrador da empresa, Donizete, está cadastrado como **vendedor** `DONIZETE`; os fretes dele (produtos
  que ele envia) são isentos pelo motivo 8. O motivo com `exige_valor_pedido` exige o nº do pedido no ERP
  (`fretes.numero_pedido`, digitado à mão — não há integração com o ERP) e
  `fretes.valor_pedido >= configuracoes.frete_isento_pedido_minimo` (R$ 10.000,00, editável pelo admin em
  Configurações) — exceto se o motivo não mudou numa edição, porque o histórico importado não tem nº nem valor
  do pedido. A busca da lista de fretes também procura no nº do pedido. Isento não pode usar solicitação de frete grátis. Antes da 004 isso era lançado como "cliente =
  transportadora" com o motivo na observação; a 004 reclassificou esse histórico pela observação (regex) sem
  mudar saldos. Fretes zerados sem observação ficaram como estavam.
  - Formulário (`fretes/form.php` + `frete-form.js`): rádio `cobranca` = `cliente` | `isento`; isento mostra
    `isencao_id`, `numero_pedido` e `valor_pedido` e esconde `valor_cliente`. A validação fica em `FreteController::validar()`.
  - Lista de fretes: filtro `situacao` = `isento`, `isento_<id>` (por motivo) e `gratis_vendedor`
    (`isencao_id IS NULL AND valor_cliente = 0`), mais o total "Isentos (empresa assumiu)" (ver
    `FreteRepository::where()/listar()`).
  - Efeito colateral: o total "cobrado do cliente" do dashboard caiu depois da 004, porque o valor fictício
    dos isentos virou 0.
- Movimentos (`movimentos_credito`): pagamento de saldo negativo (`sp_registrar_pagamento`) e transferência entre
  vendedores (`sp_transferir_credito`, que trava os dois vendedores em ordem de id e confere o disponível da
  origem com SIGNAL), na tela `fretes/movimentos` (`MovimentoController` + `MovimentoRepository`; permissões
  `movimentos.pagamento`, `movimentos.transferencia` e `movimentos.estorno` — padrão admin e financeiro, e o
  operador também transfere; cada formulário aparece conforme a permissão); além de uso de crédito, expiração/resgate e ajustes. Regras do controller: pagamento só para
  quem tem saldo negativo e até o valor devido; transferência até o disponível, destino ativo e diferente da
  origem, motivo de 5 a 200 caracteres; data válida e não futura. A tela tem dois formulários — o erro volta
  com `_erros['_form']` indicando qual deles reabrir.
- Estorno (atualização 007, `sp_estornar_movimento`, botão "Estornar" em "Últimos lançamentos", POST
  `fretes/movimentos/estornar`, permissão `movimentos.estorno`, padrão admin e financeiro): nunca apaga — grava movimento `tipo = 'estorno'` com o
  valor ao contrário, na data do dia (`CURDATE()`), com `estorno_de_id` apontando o original (índice único: não
  estorna duas vezes). Transferência gera dois estornos (um por lado, ligados por `transferencia_par_id`) e pode
  ser estornada pelo id de qualquer lado; só passa se quem recebeu ainda tiver o valor **disponível** (SIGNAL
  com o valor). Pagamento estornado volta o vendedor ao negativo, sem conferência. Motivo obrigatório (5 a 200),
  vai na `obs`. Estorno não se estorna — se errar, lance de novo.

## Telas do sistema de fretes (menu em `layouts/app.php`)

Perfis abaixo = **padrão** do `Permissoes::CATALOGO`; o admin pode mudar no painel de permissões.

| Tela | Rota | Perfis (padrão) |
|---|---|---|
| Dashboard | `fretes` | todos |
| Fretes (lançamento/edição/lista) | `fretes/lancamentos` | admin, financeiro, operador (excluir: só admin) |
| Faturas | `fretes/faturas` | admin, financeiro |
| Imprimir faturas (lote) | `fretes/impressao` | admin, financeiro, operador |
| Impressão da fatura | `fretes/faturas/imprimir?id=` ou `?ids=1,2,3` `&modelo=` | admin, financeiro, operador |
| Uso de crédito | `fretes/credito` | todos (decidir: admin, gestor) |
| Extrato | `fretes/extrato` | todos (filtrado pelo `Escopo`) |
| Pagamentos e transf. (e estorno) | `fretes/movimentos` | admin, financeiro; operador só a transferência |
| Créditos expirados | `fretes/creditos-expirados` | admin, financeiro, gestor (ações: admin, financeiro) |
| Cadastros | `fretes/cadastros` | admin |
| Configurações | `fretes/configuracoes` | admin |
| Permissões | `fretes/permissoes` | admin (sempre) |

Impressão da fatura (`FaturaController::imprimir`, view `fretes/fatura-impressao.php` no layout
`layouts/impressao.php`, `css/impressao.css`, `js/impressao.js`): o operador lança os fretes com o nº do
documento, imprime pelo ícone de impressora na lista de fretes (ou pelo botão na tela da fatura) e manda ao
financeiro com o boleto. Modelos `detalhado`, `vendedor` (subtotais) e `protocolo` (capa em 2 vias numa folha
A4). O valor do boleto e o vencimento digitados na barra só entram no papel (JS mostra CONFERE/DIVERGENTE) — não
são gravados; quem grava é a conferência do financeiro. A barra tem `.nao-imprimir`.
Alterar dados de fatura enviada p/ pagto ou paga (atualização 009, `FaturaController::alterar`, POST
`fretes/faturas/alterar`, admin e financeiro): bloco "Alterar dados" (collapse) na tela da fatura, só nesses dois
status — aberta/conferida/divergente continuam com "Corrigir conferência". Corrige vencimento, boleto, desconto,
conferente, observação, data de envio e, se paga, pago em / recebido em / recebido por; **não muda status nem
fretes**. O boleto continua tendo que bater com fretes − desconto (senão erro, não vira divergente). Campo que já
tinha valor não pode ser apagado, mas campo vazio pode continuar vazio — as 1.709 faturas pagas vindas da
planilha não têm `pago_em` nem `valor_boleto`. Motivo obrigatório (5 a 200). `FaturaRepository::alterarDados`
trava a linha (`FOR UPDATE`), grava só o que mudou e insere em `faturas_alteracoes` o motivo e o `detalhe`
("Campo: antes → depois", uma linha por campo; `ALTERAVEIS` lista rótulo e tipo). A tela mostra a tabela
"Histórico de alterações e correções" (`FaturaRepository::alteracoes`, nome do portal).
Conhecimento errado (o "conhecimento" é o nº do documento da fatura; 18/11/2026, sem atualização de banco — usa
`faturas_alteracoes`; admin e financeiro; qualquer status **menos paga**):
- Trocar nº (`FaturaController::trocarConhecimento` → `FaturaRepository::trocarNumero`, POST
  `fretes/faturas/conhecimento`): muda `faturas.numero_documento`, os fretes vão junto, status não muda. Recusa se
  já existe outro conhecimento **em andamento** (aberta/conferida/divergente) da mesma transportadora com o nº novo
  — aí os fretes devem ser retirados para ele.
- Retirar selecionados (`retirarFretes`, POST `fretes/faturas/retirar`, checkboxes na lista de fretes com
  `form="formRetirar"` + modal, `js/fatura.js`): muda só `fretes.fatura_id` dos marcados — para outro nº da mesma
  transportadora (achado/criado por `FreteRepository::faturaPara`, que ficou pública para isso) ou `NULL` (sem
  conhecimento, aguardando). Valores e saldos não mudam. Fatura de origem ou destino que estava `conferida` ou
  `enviada_pagto` volta a `divergente` (o total mudou; conferir de novo). Histórico gravado nas duas faturas.
  Trava a transportadora e depois a fatura (`travarParaCorrecao`, mesma ordem de `faturaPara`).
- Corrigir valor ou outros dados de um lançamento continua sendo pela edição do frete (link na NF).
Nº do documento repetido (atualização 008): a transportadora reaproveita números, então `faturas` não tem
mais chave única em (transportadora, nº). `FreteRepository::faturaPara` só junta o frete a uma fatura com o mesmo
nº que esteja `aberta`/`conferida`/`divergente`; se só houver enviada/paga, cria outra. Numa edição sem trocar
transportadora/nº o frete continua na fatura dele.
Impressão em lote (menu "Imprimir faturas", `FaturaController::impressao` + `fretes/impressao-lote.php` +
`js/impressao-lote.js`): lista as faturas com fretes **digitados** no período (`fretes.criado_em`, padrão hoje) e,
por padrão, só os lançados pelo usuário logado (`fretes.criado_por`); marca várias e abre
`imprimir?ids=...`. A view `fatura-impressao.php` recebe sempre `$lote`; cada fatura é uma
`section.bloco-fatura` com os seus campos de boleto/vencimento e começa numa folha nova (`break-before: page`).
Os fretes importados das planilhas têm `criado_por` NULL e `criado_em` = 03/11/2026.
Histórico de impressão (atualização 006, tabela `faturas_impressoes`: fatura, usuário do portal, modelo, data):
o `js/impressao.js` faz POST em `fretes/faturas/impressa` (token no cabeçalho `X-CSRF-Token`, lido do
`meta[name=csrf-token]` do layout de impressão) no evento `beforeprint` — vale para o botão e para o Ctrl+P; se a
pessoa cancelar a janela, conta do mesmo jeito. Na seleção, "pendente" = nunca impressa ou com frete digitado
depois da última impressão (`FaturaRepository::paraImpressao`); só as pendentes vêm marcadas.
`FaturaRepository::comUltimaImpressao()` acrescenta qtd/última/quem (nome do portal) a qualquer lista de faturas.
Ao testar com `Invoke-WebRequest -WebSession`, lembre que o PowerShell guarda os `-Headers` na sessão e reenvia
o token CSRF nas chamadas seguintes.

## Dois bancos de dados

`App\Core\Database::get($nome)` mantém uma conexão PDO por banco lógico: `'portal'` (login compartilhado,
usuários, sistemas/permissões, log de acessos) e `'fretes'` (padrão — tabelas, views e procedures deste
sistema). Sempre passe parâmetros via `Database::todos()/um()` ou `prepare()->execute()` — nunca interpole
dado do usuário direto no SQL.

## Convenções a seguir

- Todo formulário POST inclui o campo CSRF de `Csrf::campo()`; `Router::despachar` já valida automaticamente
  em toda rota POST, então rotas POST novas não precisam de configuração manual, só do campo oculto na view.
- Toda saída dinâmica nas views passa por `e()` (escapa HTML) — nunca exiba dado de usuário/banco sem
  escapar.
- Dinheiro: converta a entrada do usuário com `ler_moeda()` (aceita o formato brasileiro `1.234,56`), formate
  para exibição com `moeda()`, e para repopular campo de formulário com `campo_moeda()`. Datas: valide como
  `Y-m-d` seguindo o padrão baseado em `checkdate` usado em `FreteController::data()`/`CreditoController`,
  exiba com `data_br()`.
- Erros de validação seguem um padrão repetido nos controllers: monte os arrays `$dados`/`$erros`, em caso de
  erro guarde `$_SESSION['_erros']` e `$_SESSION['_antigo'] = $_POST`, mostre uma mensagem flash de erro,
  redirecione de volta ao formulário (padrão PRG) — a view do formulário lê `antigo('campo')` para repopular
  e mostra `$erros['campo']`.
- `asset('js/foo.js')` / `asset('css/foo.css')` resolvem assets locais/vendorizados em `public/assets/` com
  uma query string `?v=filemtime` para evitar cache antigo — use isso em vez de montar o caminho do asset na
  mão.
- Os arquivos JS em `public/assets/js/` usam `// @ts-check` (JS puro com checagem de tipos via JSDoc, sem
  bundler/build). `public/assets/vendor/` guarda Bootstrap 5, Bootstrap Icons, Chart.js e Tom Select
  vendorizados localmente (a aplicação precisa funcionar offline/sem CDN).
- Novas classes de Repository ficam em `app/Fretes/` e são instanciadas por requisição dentro do controller
  (sem container de DI); recebem `Escopo` no construtor quando suas consultas precisam de filtro por perfil
  (ver `CreditoRepository`), ou constroem isso implicitamente via os repositórios de dashboard/frete nos
  demais casos.
