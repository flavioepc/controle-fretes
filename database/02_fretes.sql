SET NAMES utf8mb4;
-- =====================================================================
--  CONTROLE DE FRETES FABRECK
--  Compatível com MySQL 8.0+ e MariaDB 10.6+
--
--  Regra central:
--    SALDO DO VENDEDOR = Σ diferença dos fretes (cliente − transportadora)
--                      + Σ movimentos de crédito (saldo inicial, transferências,
--                        uso aprovado, expiração, resgate, pagamento de negativo)
--
--  Usuários ficam em fabreck_portal.usuarios (colunas *_usuario_id / *_por).
-- =====================================================================

CREATE DATABASE IF NOT EXISTS fabreck_fretes
  DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE fabreck_fretes;

-- ---------------------------------------------------------------------
-- Configurações gerais (chave liga/desliga de expiração etc.)
-- ---------------------------------------------------------------------
CREATE TABLE configuracoes (
  chave         VARCHAR(60)  PRIMARY KEY,
  valor         VARCHAR(255) NOT NULL,
  descricao     VARCHAR(255) NULL,
  atualizado_por INT UNSIGNED NULL,
  atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT INTO configuracoes (chave, valor, descricao) VALUES
  ('credito_expira_virada_ano', '0',
   '1 = na virada do ano o saldo POSITIVO de cada vendedor expira e o ano começa zerado'),
  ('alerta_vencimento_dias', '3',
   'Faturas que vencem em até N dias aparecem como alerta no dashboard');

-- ---------------------------------------------------------------------
-- Cadastros
-- ---------------------------------------------------------------------
CREATE TABLE empresas (                    -- empresas do grupo (AJOTAVE, ALZILAN...)
  id    SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nome  VARCHAR(80) NOT NULL,
  cnpj  CHAR(14) NULL,
  ativo TINYINT(1) NOT NULL DEFAULT 1,
  UNIQUE KEY uk_empresas_nome (nome)
) ENGINE=InnoDB;

CREATE TABLE transportadoras (
  id    SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nome  VARCHAR(80) NOT NULL,
  cnpj  CHAR(14) NULL,
  ativo TINYINT(1) NOT NULL DEFAULT 1,
  UNIQUE KEY uk_transp_nome (nome)
) ENGINE=InnoDB;

CREATE TABLE gestores (
  id         SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nome       VARCHAR(80) NOT NULL,
  usuario_id INT UNSIGNED NULL,            -- login do gestor no portal (aprova solicitações)
  ativo      TINYINT(1) NOT NULL DEFAULT 1,
  UNIQUE KEY uk_gestores_nome (nome)
) ENGINE=InnoDB;

CREATE TABLE vendedores (
  id             SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nome           VARCHAR(80) NOT NULL,
  gestor_id      SMALLINT UNSIGNED NOT NULL,
  usuario_id     INT UNSIGNED NULL,
  -- NULL = segue a chave geral; 1 = expira sempre; 0 = nunca expira (exceção)
  credito_expira TINYINT(1) NULL DEFAULT NULL,
  ativo          TINYINT(1) NOT NULL DEFAULT 1,
  UNIQUE KEY uk_vendedores_nome (nome),
  CONSTRAINT fk_vend_gestor FOREIGN KEY (gestor_id) REFERENCES gestores(id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Faturas / boletos das transportadoras (antiga aba "Validação")
--   aberta -> conferida (soma dos fretes bate) | divergente
--          -> enviada_pagto ("passei p/ pagto") -> paga
-- ---------------------------------------------------------------------
CREATE TABLE faturas (
  id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  transportadora_id  SMALLINT UNSIGNED NOT NULL,
  empresa_id         SMALLINT UNSIGNED NULL,
  numero_documento   VARCHAR(40) NOT NULL,
  data_entrada       DATE NOT NULL,
  vencimento         DATE NULL,
  valor_boleto       DECIMAL(12,2) NULL,     -- valor impresso no boleto (confere com Σ fretes)
  desconto           DECIMAL(12,2) NOT NULL DEFAULT 0, -- "fatura com desconto"
  status             ENUM('aberta','conferida','divergente','enviada_pagto','paga')
                     NOT NULL DEFAULT 'aberta',
  responsavel        VARCHAR(60) NULL,       -- quem validou (REGIANE, TALITA...)
  enviado_pagto_em   DATE NULL,              -- "Passei p/ pagto"
  recebido_por       VARCHAR(60) NULL,       -- RCTO: quem recebeu no financeiro
  recebido_em        DATE NULL,
  pago_em            DATE NULL,
  obs                VARCHAR(255) NULL,
  criado_por         INT UNSIGNED NULL,
  criado_em          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em      DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_fatura (transportadora_id, numero_documento),
  KEY idx_fatura_venc (status, vencimento),
  CONSTRAINT fk_fat_transp  FOREIGN KEY (transportadora_id) REFERENCES transportadoras(id),
  CONSTRAINT fk_fat_empresa FOREIGN KEY (empresa_id) REFERENCES empresas(id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Solicitações de uso de crédito (vendedor pede, gestor aprova)
--   tipo 'frete_gratis': aprovação RESERVA o valor; quando o frete é lançado
--     e vinculado à solicitação, ela vira 'utilizada' e o próprio frete
--     (cliente paga menos que a transportadora) consome o crédito.
--   tipo 'outro': aprovação gera direto um movimento de débito.
-- ---------------------------------------------------------------------
CREATE TABLE solicitacoes_credito (
  id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  vendedor_id      SMALLINT UNSIGNED NOT NULL,
  gestor_id        SMALLINT UNSIGNED NOT NULL,   -- quem deve aprovar
  tipo             ENUM('frete_gratis','outro') NOT NULL DEFAULT 'frete_gratis',
  valor            DECIMAL(12,2) NOT NULL,
  cliente          VARCHAR(120) NULL,            -- cliente/prospect da negociação
  justificativa    VARCHAR(500) NOT NULL,
  status           ENUM('pendente','aprovada','recusada','utilizada','cancelada')
                   NOT NULL DEFAULT 'pendente',
  validade         DATE NULL,                    -- reserva vale até (opcional)
  solicitado_por   INT UNSIGNED NULL,
  solicitado_em    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  decidido_por     INT UNSIGNED NULL,
  decidido_em      DATETIME NULL,
  motivo_decisao   VARCHAR(255) NULL,
  KEY idx_sol_gestor (gestor_id, status),
  KEY idx_sol_vend (vendedor_id, status),
  CONSTRAINT chk_sol_valor CHECK (valor > 0),
  CONSTRAINT fk_sol_vend   FOREIGN KEY (vendedor_id) REFERENCES vendedores(id),
  CONSTRAINT fk_sol_gestor FOREIGN KEY (gestor_id)   REFERENCES gestores(id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Fretes (antiga aba "Valores")
-- ---------------------------------------------------------------------
CREATE TABLE fretes (
  id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  data              DATE NOT NULL,
  nota_fiscal       VARCHAR(60) NOT NULL,        -- aceita "16414/16415"
  empresa_id        SMALLINT UNSIGNED NULL,      -- NULL só em dados importados com pendência
  transportadora_id SMALLINT UNSIGNED NOT NULL,
  vendedor_id       SMALLINT UNSIGNED NULL,      -- NULL = frete sem vendedor (pendência; fica fora do saldo)
  gestor_id         SMALLINT UNSIGNED NULL,      -- gestor NA DATA do frete (histórico)
  uf                CHAR(2) NULL,
  valor_cliente     DECIMAL(12,2) NOT NULL DEFAULT 0,
  valor_transp      DECIMAL(12,2) NULL,          -- NULL = aguardando fatura (fora do saldo)
  diferenca         DECIMAL(12,2) AS (valor_cliente - valor_transp) STORED,
  fatura_id         INT UNSIGNED NULL,
  solicitacao_id    INT UNSIGNED NULL,           -- frete grátis aprovado que este frete usou
  obs               VARCHAR(255) NULL,
  criado_por        INT UNSIGNED NULL,
  criado_em         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em     DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_fretes_data (data),
  KEY idx_fretes_vend (vendedor_id, data),
  KEY idx_fretes_gestor (gestor_id, data),
  KEY idx_fretes_nf (nota_fiscal),
  KEY idx_fretes_fatura (fatura_id),
  CONSTRAINT fk_fr_empresa FOREIGN KEY (empresa_id) REFERENCES empresas(id),
  CONSTRAINT fk_fr_transp  FOREIGN KEY (transportadora_id) REFERENCES transportadoras(id),
  CONSTRAINT fk_fr_vend    FOREIGN KEY (vendedor_id) REFERENCES vendedores(id),
  CONSTRAINT fk_fr_gestor  FOREIGN KEY (gestor_id) REFERENCES gestores(id),
  CONSTRAINT fk_fr_fatura  FOREIGN KEY (fatura_id) REFERENCES faturas(id) ON DELETE SET NULL,
  CONSTRAINT fk_fr_sol     FOREIGN KEY (solicitacao_id) REFERENCES solicitacoes_credito(id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Créditos expirados na virada do ano (tabela de resgate)
-- ---------------------------------------------------------------------
CREATE TABLE creditos_expirados (
  id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  vendedor_id      SMALLINT UNSIGNED NOT NULL,
  ano_referencia   SMALLINT UNSIGNED NOT NULL,   -- ano cujo saldo expirou
  valor            DECIMAL(12,2) NOT NULL,
  expirado_em      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  status           ENUM('expirado','resgatado_parcial','resgatado') NOT NULL DEFAULT 'expirado',
  valor_resgatado  DECIMAL(12,2) NOT NULL DEFAULT 0,
  UNIQUE KEY uk_exp_vend_ano (vendedor_id, ano_referencia),  -- impede expirar 2x o mesmo ano
  CONSTRAINT fk_exp_vend FOREIGN KEY (vendedor_id) REFERENCES vendedores(id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Pagamentos de saldo negativo (descontado do vendedor em outro sistema)
-- ---------------------------------------------------------------------
CREATE TABLE pagamentos_vendedor (
  id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  vendedor_id        SMALLINT UNSIGNED NOT NULL,
  valor              DECIMAL(12,2) NOT NULL,
  data_pagamento     DATE NOT NULL,
  forma              VARCHAR(60) NOT NULL DEFAULT 'Desconto em outro sistema',
  referencia_externa VARCHAR(80) NULL,           -- nº do lançamento no outro sistema
  obs                VARCHAR(255) NULL,
  criado_por         INT UNSIGNED NULL,
  criado_em          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT chk_pag_valor CHECK (valor > 0),
  CONSTRAINT fk_pag_vend FOREIGN KEY (vendedor_id) REFERENCES vendedores(id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Movimentos de crédito: tudo que mexe no saldo e NÃO é um frete.
-- valor com sinal: positivo credita, negativo debita.
-- ---------------------------------------------------------------------
CREATE TABLE movimentos_credito (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  vendedor_id     SMALLINT UNSIGNED NOT NULL,
  data_ref        DATE NOT NULL,
  tipo            ENUM('saldo_inicial',      -- saldo trazido das planilhas
                       'transferencia',      -- entre vendedores (par +/-)
                       'uso_credito',        -- solicitação tipo 'outro' aprovada
                       'despesa',            -- ex.: custo de boleto
                       'expiracao',          -- virada do ano (negativo)
                       'resgate_expirado',   -- devolução de crédito expirado (positivo)
                       'pagamento_negativo', -- vendedor pagou o negativo (positivo)
                       'ajuste') NOT NULL,
  valor           DECIMAL(12,2) NOT NULL,
  solicitacao_id  INT UNSIGNED NULL,
  expirado_id     INT UNSIGNED NULL,
  pagamento_id    INT UNSIGNED NULL,
  transferencia_par_id INT UNSIGNED NULL,       -- id do movimento oposto da transferência
  obs             VARCHAR(255) NULL,
  criado_por      INT UNSIGNED NULL,
  criado_em       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_mov_vend (vendedor_id, data_ref),
  CONSTRAINT chk_mov_valor CHECK (valor <> 0),
  CONSTRAINT fk_mov_vend FOREIGN KEY (vendedor_id) REFERENCES vendedores(id),
  CONSTRAINT fk_mov_sol  FOREIGN KEY (solicitacao_id) REFERENCES solicitacoes_credito(id),
  CONSTRAINT fk_mov_exp  FOREIGN KEY (expirado_id) REFERENCES creditos_expirados(id),
  CONSTRAINT fk_mov_pag  FOREIGN KEY (pagamento_id) REFERENCES pagamentos_vendedor(id)
) ENGINE=InnoDB;

-- =====================================================================
-- VIEWS
-- =====================================================================

-- Extrato unificado (fretes + movimentos) — base do saldo e da tela de extrato
CREATE OR REPLACE VIEW vw_extrato AS
SELECT f.vendedor_id, f.data AS data_ref, 'frete' AS origem, f.id AS origem_id,
       CONCAT('NF ', f.nota_fiscal, ' · ', t.nome) AS descricao, f.diferenca AS valor
  FROM fretes f JOIN transportadoras t ON t.id = f.transportadora_id
 WHERE f.valor_transp IS NOT NULL
UNION ALL
SELECT m.vendedor_id, m.data_ref, m.tipo, m.id, COALESCE(m.obs, m.tipo), m.valor
  FROM movimentos_credito m;

-- Saldo por vendedor
CREATE OR REPLACE VIEW vw_saldo_vendedor AS
SELECT v.id AS vendedor_id, v.nome AS vendedor, g.id AS gestor_id, g.nome AS gestor, v.ativo,
       COALESCE(fr.total, 0)                               AS credito_fretes,
       COALESCE(mv.total, 0)                               AS movimentos,
       COALESCE(fr.total, 0) + COALESCE(mv.total, 0)       AS saldo,
       COALESCE(rs.total, 0)                               AS reservado,
       COALESCE(fr.total, 0) + COALESCE(mv.total, 0) - COALESCE(rs.total, 0) AS disponivel,
       COALESCE(ag.qtd, 0)                                 AS fretes_aguardando_fatura
  FROM vendedores v
  JOIN gestores g ON g.id = v.gestor_id
  LEFT JOIN (SELECT vendedor_id, SUM(diferenca) total FROM fretes
              WHERE valor_transp IS NOT NULL GROUP BY vendedor_id) fr ON fr.vendedor_id = v.id
  LEFT JOIN (SELECT vendedor_id, SUM(valor) total FROM movimentos_credito
              GROUP BY vendedor_id) mv ON mv.vendedor_id = v.id
  LEFT JOIN (SELECT vendedor_id, SUM(valor) total FROM solicitacoes_credito
              WHERE status = 'aprovada' AND tipo = 'frete_gratis'
              GROUP BY vendedor_id) rs ON rs.vendedor_id = v.id
  LEFT JOIN (SELECT vendedor_id, COUNT(*) qtd FROM fretes
              WHERE valor_transp IS NULL GROUP BY vendedor_id) ag ON ag.vendedor_id = v.id;

-- Saldo por gestor (soma da equipe atual)
CREATE OR REPLACE VIEW vw_saldo_gestor AS
SELECT gestor_id, gestor,
       COUNT(*)                         AS vendedores,
       SUM(saldo)                       AS saldo,
       SUM(CASE WHEN saldo > 0 THEN saldo ELSE 0 END) AS saldo_positivo,
       SUM(CASE WHEN saldo < 0 THEN saldo ELSE 0 END) AS saldo_negativo,
       SUM(reservado)                   AS reservado
  FROM vw_saldo_vendedor
 GROUP BY gestor_id, gestor;

-- Faturas com o total calculado dos fretes vinculados
CREATE OR REPLACE VIEW vw_faturas AS
SELECT fa.*, t.nome AS transportadora, e.nome AS empresa,
       COALESCE(x.qtd_itens, 0)                      AS qtd_itens,
       COALESCE(x.total_transp, 0)                   AS total_fretes,
       COALESCE(x.total_transp, 0) - fa.desconto     AS total_a_pagar,
       CASE WHEN fa.valor_boleto IS NULL THEN NULL
            ELSE fa.valor_boleto - (COALESCE(x.total_transp, 0) - fa.desconto) END AS divergencia,
       CASE WHEN fa.status <> 'paga' AND fa.vencimento < CURDATE() THEN 1 ELSE 0 END AS vencida
  FROM faturas fa
  JOIN transportadoras t ON t.id = fa.transportadora_id
  LEFT JOIN empresas e ON e.id = fa.empresa_id
  LEFT JOIN (SELECT fatura_id, COUNT(*) qtd_itens, SUM(valor_transp) total_transp
               FROM fretes WHERE fatura_id IS NOT NULL GROUP BY fatura_id) x ON x.fatura_id = fa.id;
