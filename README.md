# Controle de Fretes Fabreck

Plataforma web (PHP 8.3 + MySQL 8 + Bootstrap 5) que substitui a planilha `CONTROLE_FRETE`.
Faz parte da intranet Fabreck: o usuário entra pelo **portal** (login único) e escolhe o sistema.

## Regras de negócio

| Regra | Como funciona |
|---|---|
| **Saldo do vendedor** | Σ (valor cobrado do cliente − valor da transportadora) dos fretes **+** movimentos de crédito. Frete sem valor da transportadora fica como "aguardando fatura" e **não entra** no saldo. |
| **Uso de crédito** | O vendedor solicita, o **gestor aprova**. O tipo *frete grátis* **reserva** o valor (saldo disponível = saldo − reservas). Quando o frete é lançado e vinculado, a solicitação vira *utilizada*. O tipo *outro* debita na hora da aprovação. Não se aprova acima do saldo disponível. |
| **Expiração** | Há uma chave geral `credito_expira_virada_ano` (liga/desliga) e uma exceção por vendedor (`vendedores.credito_expira`: NULL segue a chave geral, 1 expira sempre, 0 nunca expira). Na virada do ano, o saldo **positivo** expira, vai para `creditos_expirados` e o ano começa zerado. As reservas abertas são canceladas. |
| **Resgate** | Um crédito expirado pode ser resgatado no todo ou em parte (`sp_resgatar_credito`), com motivo registrado. |
| **Saldo negativo** | Não expira. O desconto é feito em outro sistema, e aqui se registra o pagamento (`sp_registrar_pagamento`), que zera o negativo. Não aceita valor maior que o devido. |
| **Transferência** | Move crédito entre vendedores, gerando um par de movimentos vinculados (`sp_transferir_credito`). |
| **Faturas** | Fluxo aberta → conferida/divergente → enviada p/ pagto → paga. Total a pagar = Σ fretes vinculados − desconto da fatura. |

## Banco de dados

Rode os arquivos na ordem abaixo:

```bash
mysql -u root -p < database/01_portal.sql          # usuários, sistemas, permissões (compartilhado)
mysql -u root -p < database/02_fretes.sql          # tabelas e views do sistema de fretes
mysql -u root -p < database/03_rotinas.sql         # procedures de negócio
mysql -u root -p < database/04_carga_planilha.sql  # histórico importado da planilha
```

Reconstrução de fretes, faturas e créditos a partir das planilhas mensais (`tools/importar_planilhas.py`):
**não use mais** — o corte foi em 03/11/2026 e o script apaga e refaz essas tabelas, o que perderia tudo o que
for lançado no sistema. A carga final de produção veio das planilhas de **janeiro a novembro/2026**:
10.411 fretes, 1.755 faturas, 165 movimentos, saldo total R$ 52.237,69.

### Virada do ano
O cron do servidor chama `CALL sp_expirar_creditos(YEAR(CURDATE())-1, NULL)` em 01/01 às 00:05 (ver Produção).
A rotina pode rodar mais de uma vez sem risco: o mesmo ano nunca expira duas vezes.

### Principais views
- `vw_saldo_vendedor`: saldo, reservado, disponível e fretes aguardando fatura
- `vw_saldo_gestor`: totais por equipe (positivo e negativo separados)
- `vw_extrato`: fretes e movimentos numa linha do tempo, por vendedor
- `vw_faturas`: total calculado, desconto, divergência com o boleto e se está vencida

## Produção (servidor 192.168.3.198, Ubuntu 22.04 + Docker)

Em produção desde 03/11/2026 — a partir daí os lançamentos são feitos **só no sistema**, não na planilha.

Tudo fica em `~/intranet` (usuário `rhadmin`), definido no `docker-compose.yml`:
- `intranet_web` — Apache + PHP 8.3 na porta 80. Portal na raiz (`www/intranet/public`), WebFolha em `/WebFolha/`
- `intranet_mysql8` — MySQL 8 com `fabreck_portal` e `fabreck_fretes`, sem porta aberta para a rede
- Senhas no `~/intranet/.env` (fora do Git); conexão do PHP em `www/intranet/config/config.local.php`
- Cron do `rhadmin`: `backup_intranet.sh` todo dia (guarda 15 dias em `~/backups`) e a expiração da virada do ano

Publicar código: copiar os arquivos alterados para `~/intranet/www/intranet/` (o volume é montado no contêiner,
não precisa reiniciar). Nunca sobrescreva o `config/config.local.php` do servidor.
Mudança de estrutura do banco: arquivo novo em `database/atualizacoes/` e, no servidor,
`bash ~/intranet/www/intranet/database/atualizar_producao.sh`.
Primeiro admin, se um dia faltar: `docker exec intranet_web php /var/www/intranet/tools/criar_admin.php "Nome" email`
(copie a pasta `tools/` para o servidor antes).

**Acesso externo (vendedores no celular):** Cloudflare Tunnel (HTTPS sem abrir portas) ou VPN Tailscale.
O PHP já reconhece o HTTPS repassado pelo túnel (`X-Forwarded-Proto`) para marcar o cookie como `Secure`.

## Rodando no notebook (XAMPP)
1. No XAMPP Control Panel, inicie o **Apache** e o **MySQL**.
2. Só na primeira vez: rode `database/instalar_banco.bat` (APAGA e recria os bancos com a carga da planilha e os
   usuários de teste; pede para digitar APAGAR). Com dados que importam, use `database/atualizar_banco.bat`.
3. Abra **http://localhost/controle-fretes/**
4. Para ver os erros completos na tela, crie `config/config.local.php` com `return ['debug' => true];`
   (o padrão agora é `debug` desligado, como em produção).

| Usuário de teste | Senha | Perfil | Vê |
|---|---|---|---|
| admin@fabreck.local | Admin@123 | admin | tudo, inclusive faturas |
| rafael@fabreck.local | Teste@123 | gestor RAFAEL | a equipe dele |
| mateus@fabreck.local | Teste@123 | vendedor MATEUS | só o próprio saldo |

Se o seu MySQL tiver senha, crie `config/config.local.php` (ele não vai para o Git):
```php
<?php
return ['db' => ['usuario' => 'root', 'senha' => 'sua-senha']];
```

## Estrutura
```
.htaccess            manda tudo para public/ (app, config e database ficam inacessíveis)
public/
  index.php          ponto de entrada e lista de rotas
  assets/css/app.css estilos (tema claro/escuro, cores dos gráficos)
  assets/js/         app.js (tema) e dashboard.js (gráficos), com // @ts-check
  assets/vendor/     Bootstrap 5.3.3, Bootstrap Icons, Chart.js 4.4.1 e Tom Select 2.3.1 locais (funcionam sem internet)
app/
  bootstrap.php      autoload, config, sessão
  helpers.php        e(), url(), moeda(), data_br(), flash()...
  Core/              Router, Database (PDO), Auth, Sessao, Csrf, View
  Controllers/       AuthController, PortalController, Fretes/ Dashboard, Frete e Credito Controller
  Fretes/            Escopo (quem vê/decide o quê), Filtros, Dashboard/Frete/Credito Repository
  Views/             layouts/, auth/, portal/, fretes/, erros/
config/config.php    configuração padrão (+ config.local.php opcional; modelo em config.local.exemplo.php)
database/            SQL, carga da planilha, instalar_banco.bat, atualizar_banco.bat, atualizacoes/,
                     atualizar_producao.sh (servidor)
tools/               importar_planilhas.py, gerar_carga.py, criar_admin.php
```

## Segurança já incluída
- Senhas com `password_hash` (bcrypt); bloqueio após 5 tentativas erradas em 15 minutos
- Sessão com cookie `HttpOnly` e `SameSite`, novo id a cada login, expira após 2 h sem uso
- Cookie `Secure` e HSTS quando o acesso é HTTPS, inclusive atrás do Cloudflare Tunnel (`X-Forwarded-Proto`)
- Cabeçalhos `X-Frame-Options`, `X-Content-Type-Options` e `Referrer-Policy`; com `debug` desligado os erros
  vão para o log do PHP e o usuário vê só uma mensagem genérica
- Token CSRF em todo formulário POST
- Toda consulta usa prepared statements (PDO) e toda saída passa por `e()` (anti-XSS)
- O escopo por perfil é aplicado no SQL, não só na tela

## Telas prontas
- **Dashboard** (`/fretes`) — todos os perfis, cada um no seu escopo
- **Fretes** (`/fretes/lancamentos`) — admin e financeiro
  - lista com busca (NF, documento, obs.), filtros por vendedor/transportadora/empresa/situação, totais e paginação
  - lançar/editar com diferença calculada na hora, campos de busca para vendedor e transportadora
  - nº do documento encontra a fatura ou cria uma nova "aberta"
  - aviso de NF repetida (pede confirmação; na edição só se a NF mudar)
  - vínculo com frete grátis aprovado: a solicitação vira "utilizada" e a reserva sai do saldo
  - "Salvar e lançar outro" mantém data, empresa, transportadora e documento (vários itens da mesma fatura)
  - excluir (só admin), com confirmação

- **Uso de crédito** (`/fretes/credito`) — todos os perfis
  - vendedor: vê saldo / reservado / disponível, pede frete grátis ou outro uso, acompanha e cancela
  - gestor: recebe os pedidos da equipe (contador vermelho no menu), aprova ou recusa com motivo, pede em nome de um vendedor
  - admin: vê e decide tudo (ex.: equipe AUTONOMO); financeiro só consulta
  - frete grátis aprovado = valor reservado; "outro uso" aprovado = debitado na hora
  - não aprova acima do saldo disponível (conferido no banco, com trava contra duas aprovações simultâneas)
- **Extrato** (`/fretes/extrato`) — fretes e movimentos numa linha do tempo, por vendedor
- **Faturas** (`/fretes/faturas`) — admin e financeiro: conferência, envio para pagamento e baixa
- **Créditos expirados** (`/fretes/creditos-expirados`) — consulta, resgate e expiração manual
- **Cadastros** (`/fretes/cadastros`) — admin: empresas, transportadoras, gestores e vendedores, e criação do login
- **Configurações** (`/fretes/configuracoes`) — admin: expiração na virada do ano, alerta de vencimento
- **Usuários e acessos** (`/usuarios`) — admin do portal: usuários da intranet e aplicativos liberados

## Atualizando o banco SEM perder dados
Depois que houver dados reais, **não rode mais o `instalar_banco.bat`** (ele apaga tudo).
Mudanças de estrutura chegam como arquivos em `database/atualizacoes/` — rode **`database/atualizar_banco.bat`**.
Cada atualização pode ser rodada mais de uma vez sem problema.
