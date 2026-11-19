SET NAMES utf8mb4;
-- 03/11/2026 — Frete isento de cobrança do cliente (a EMPRESA assume, não o vendedor) e
-- conferência de saldo na transferência de crédito. Pode rodar mais de uma vez.
--
--  * motivos_isencao: por que o cliente não pagou (pedido acima de R$ 10 mil, empresa do grupo...)
--  * fretes.isencao_id: preenchido = isento; o valor cobrado do cliente fica 0 e a diferença
--    também (não mexe no saldo do vendedor). Até aqui isso era feito lançando "cliente = transportadora".
--  * fretes.valor_pedido: valor do pedido/NF, exigido quando o motivo depende dele (acima de R$ 10 mil)
--  * configuracoes.frete_isento_pedido_minimo: o valor da política (padrão 10.000,00)
--  * frete grátis por conta do VENDEDOR continua como antes: cobrado do cliente = 0, debita o saldo.
USE fabreck_fretes;

CREATE TABLE IF NOT EXISTS motivos_isencao (
  id                  SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nome                VARCHAR(60) NOT NULL,
  exige_valor_pedido  TINYINT(1) NOT NULL DEFAULT 0,   -- 1 = valor do pedido >= frete_isento_pedido_minimo
  ativo               TINYINT(1) NOT NULL DEFAULT 1,
  ordem               SMALLINT NOT NULL DEFAULT 0,
  UNIQUE KEY uk_motivo_nome (nome)
) ENGINE=InnoDB;

INSERT IGNORE INTO motivos_isencao (id, nome, exige_valor_pedido, ordem) VALUES
  (1, 'Pedido acima do valor mínimo', 1, 1),
  (2, 'Empresa do grupo', 0, 2),
  (3, 'Primeira compra', 0, 3),
  (4, 'Garantia', 0, 4),
  (5, 'Conserto', 0, 5),
  (6, 'Devolução', 0, 6),
  (7, 'Outro (explicar na observação)', 0, 99);

INSERT IGNORE INTO configuracoes (chave, valor, descricao) VALUES
  ('frete_isento_pedido_minimo', '10000.00',
   'Pedido com valor igual ou acima deste não paga frete (a empresa assume)');

-- colunas novas (MySQL 8 não tem ADD COLUMN IF NOT EXISTS: confere no information_schema)
DROP PROCEDURE IF EXISTS tmp_004_colunas;
DELIMITER $$
CREATE PROCEDURE tmp_004_colunas()
BEGIN
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'fretes' AND COLUMN_NAME = 'isencao_id') THEN
    ALTER TABLE fretes
      ADD COLUMN isencao_id SMALLINT UNSIGNED NULL AFTER valor_transp,
      ADD COLUMN valor_pedido DECIMAL(12,2) NULL AFTER isencao_id,
      ADD KEY idx_fretes_isencao (isencao_id, data),
      ADD CONSTRAINT fk_fr_isencao FOREIGN KEY (isencao_id) REFERENCES motivos_isencao(id);
  END IF;
END $$
DELIMITER ;
CALL tmp_004_colunas();
DROP PROCEDURE tmp_004_colunas;

-- isento: diferença 0 (continua NULL enquanto aguarda o valor da transportadora)
ALTER TABLE fretes MODIFY COLUMN diferenca DECIMAL(12,2)
  AS (IF(isencao_id IS NULL, valor_cliente - valor_transp, valor_transp * 0)) STORED;

-- histórico: os lançados "cliente = transportadora" com o motivo escrito na observação viram isentos.
-- A diferença deles já era 0, então nenhum saldo muda. Só mexe no que veio antes desta atualização.
UPDATE fretes SET isencao_id = CASE
         WHEN LOWER(obs) REGEXP '^(acima|aciima)( de)? 10' THEN 1
         WHEN LOWER(obs) REGEXP '^grupo'                   THEN 2
         WHEN LOWER(obs) REGEXP '^1 (compra|compar)'       THEN 3
         WHEN LOWER(obs) REGEXP '^garantia'                THEN 4
         WHEN LOWER(obs) REGEXP '^conserto'                THEN 5
         WHEN LOWER(obs) REGEXP '^devolu'                  THEN 6
       END,
       valor_cliente = 0
 WHERE isencao_id IS NULL AND solicitacao_id IS NULL AND criado_em < '2026-11-04'
   AND valor_transp IS NOT NULL AND valor_cliente = valor_transp
   AND LOWER(obs) REGEXP '^((acima|aciima)( de)? 10|grupo|1 (compra|compar)|garantia|conserto|devolu)';

-- transferência de crédito: trava os dois vendedores e só transfere o que a origem tem DISPONÍVEL
DELIMITER $$
DROP PROCEDURE IF EXISTS sp_transferir_credito $$
CREATE PROCEDURE sp_transferir_credito(IN p_origem SMALLINT UNSIGNED, IN p_destino SMALLINT UNSIGNED,
                                       IN p_valor DECIMAL(12,2), IN p_data DATE,
                                       IN p_usuario INT UNSIGNED, IN p_obs VARCHAR(200))
BEGIN
  DECLARE v_saida INT UNSIGNED;
  DECLARE v_entrada INT UNSIGNED;
  DECLARE v_menor SMALLINT UNSIGNED;
  DECLARE v_maior SMALLINT UNSIGNED;
  DECLARE v_disp DECIMAL(12,2);
  DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK; RESIGNAL; END;

  IF p_origem = p_destino OR p_valor <= 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Transferência inválida';
  END IF;

  START TRANSACTION;
  -- trava os dois (sempre na ordem do id, para duas transferências cruzadas não se travarem)
  SELECT id INTO v_menor FROM vendedores WHERE id = LEAST(p_origem, p_destino) FOR UPDATE;
  SELECT id INTO v_maior FROM vendedores WHERE id = GREATEST(p_origem, p_destino) FOR UPDATE;
  IF v_menor IS NULL OR v_maior IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Vendedor não encontrado';
  END IF;
  SELECT disponivel INTO v_disp FROM vw_saldo_vendedor WHERE vendedor_id = p_origem;
  IF v_disp < p_valor THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Saldo disponível insuficiente para transferir';
  END IF;

  -- mesmo texto das transferências trazidas das planilhas: "Transferência para X" / "Transferência de Y"
  INSERT INTO movimentos_credito (vendedor_id, data_ref, tipo, valor, obs, criado_por)
  SELECT p_origem, p_data, 'transferencia', -p_valor,
         LEFT(CONCAT('Transferência para ', nome, COALESCE(CONCAT(' — ', p_obs), '')), 255), p_usuario
    FROM vendedores WHERE id = p_destino;
  SET v_saida = LAST_INSERT_ID();
  INSERT INTO movimentos_credito (vendedor_id, data_ref, tipo, valor, transferencia_par_id, obs, criado_por)
  SELECT p_destino, p_data, 'transferencia', p_valor, v_saida,
         LEFT(CONCAT('Transferência de ', nome, COALESCE(CONCAT(' — ', p_obs), '')), 255), p_usuario
    FROM vendedores WHERE id = p_origem;
  SET v_entrada = LAST_INSERT_ID();
  UPDATE movimentos_credito SET transferencia_par_id = v_entrada WHERE id = v_saida;
  COMMIT;
END $$
DELIMITER ;
