SET NAMES utf8mb4;
-- =====================================================================
--  ROTINAS DE NEGÓCIO — Controle de Fretes Fabreck
--  Cada rotina roda numa transação e falha com mensagem clara (SIGNAL)
--  quando uma regra é violada. O PHP chama com:  CALL sp_xxx(...)
-- =====================================================================
USE fabreck_fretes;

DELIMITER $$

-- ---------------------------------------------------------------------
-- Saldo de um vendedor numa data (inclusive)
-- ---------------------------------------------------------------------
DROP FUNCTION IF EXISTS fn_saldo_em $$
CREATE FUNCTION fn_saldo_em(p_vendedor SMALLINT UNSIGNED, p_data DATE)
RETURNS DECIMAL(12,2) READS SQL DATA
BEGIN
  RETURN COALESCE((SELECT SUM(diferenca) FROM fretes
                    WHERE vendedor_id = p_vendedor AND valor_transp IS NOT NULL
                      AND data <= p_data), 0)
       + COALESCE((SELECT SUM(valor) FROM movimentos_credito
                    WHERE vendedor_id = p_vendedor AND data_ref <= p_data), 0);
END $$

-- ---------------------------------------------------------------------
-- Virada do ano: expira o saldo POSITIVO de quem tem expiração ativa.
--   * chave geral: configuracoes.credito_expira_virada_ano
--   * exceção por vendedor: vendedores.credito_expira (NULL segue a geral)
--   * saldo negativo NÃO expira (continua para desconto)
--   * reservas de frete grátis ainda abertas são canceladas
--   * pode rodar mais de uma vez: o mesmo ano nunca expira duas vezes
-- Agendar no cron em 01/01 00:05:  CALL sp_expirar_creditos(YEAR(CURDATE())-1, NULL);
-- ---------------------------------------------------------------------
DROP PROCEDURE IF EXISTS sp_expirar_creditos $$
CREATE PROCEDURE sp_expirar_creditos(IN p_ano SMALLINT UNSIGNED, IN p_usuario INT UNSIGNED)
BEGIN
  DECLARE v_geral TINYINT DEFAULT 0;
  DECLARE v_fim DATE;
  DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK; RESIGNAL; END;

  SET v_fim = MAKEDATE(p_ano, 1) + INTERVAL 1 YEAR - INTERVAL 1 DAY;   -- 31/12/p_ano
  SELECT CAST(valor AS UNSIGNED) INTO v_geral
    FROM configuracoes WHERE chave = 'credito_expira_virada_ano';

  START TRANSACTION;

  INSERT IGNORE INTO creditos_expirados (vendedor_id, ano_referencia, valor)
  SELECT v.id, p_ano, fn_saldo_em(v.id, v_fim)
    FROM vendedores v
   WHERE COALESCE(v.credito_expira, v_geral) = 1
     AND fn_saldo_em(v.id, v_fim) > 0;

  INSERT INTO movimentos_credito (vendedor_id, data_ref, tipo, valor, expirado_id, obs, criado_por)
  SELECT ce.vendedor_id, v_fim, 'expiracao', -ce.valor, ce.id,
         CONCAT('Saldo de ', p_ano, ' expirado na virada do ano'), p_usuario
    FROM creditos_expirados ce
   WHERE ce.ano_referencia = p_ano
     AND NOT EXISTS (SELECT 1 FROM movimentos_credito m
                      WHERE m.expirado_id = ce.id AND m.tipo = 'expiracao');

  UPDATE solicitacoes_credito s
    JOIN creditos_expirados ce ON ce.vendedor_id = s.vendedor_id AND ce.ano_referencia = p_ano
     SET s.status = 'cancelada',
         s.motivo_decisao = CONCAT('Cancelada: crédito de ', p_ano, ' expirou')
   WHERE s.status = 'aprovada' AND s.tipo = 'frete_gratis';

  COMMIT;
  SELECT COUNT(*) AS vendedores_expirados, COALESCE(SUM(valor),0) AS total_expirado
    FROM creditos_expirados WHERE ano_referencia = p_ano;
END $$

-- ---------------------------------------------------------------------
-- Resgate (total ou parcial) de um crédito expirado
-- ---------------------------------------------------------------------
DROP PROCEDURE IF EXISTS sp_resgatar_credito $$
CREATE PROCEDURE sp_resgatar_credito(IN p_expirado_id INT UNSIGNED, IN p_valor DECIMAL(12,2),
                                     IN p_usuario INT UNSIGNED, IN p_motivo VARCHAR(200))
BEGIN
  DECLARE v_vend SMALLINT UNSIGNED;
  DECLARE v_resto DECIMAL(12,2);
  DECLARE v_ano SMALLINT UNSIGNED;
  DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK; RESIGNAL; END;

  START TRANSACTION;
  SELECT vendedor_id, valor - valor_resgatado, ano_referencia INTO v_vend, v_resto, v_ano
    FROM creditos_expirados WHERE id = p_expirado_id FOR UPDATE;

  IF v_vend IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Crédito expirado não encontrado';
  END IF;
  IF p_valor <= 0 OR p_valor > v_resto THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Valor de resgate maior que o disponível para resgate';
  END IF;

  INSERT INTO movimentos_credito (vendedor_id, data_ref, tipo, valor, expirado_id, obs, criado_por)
  VALUES (v_vend, CURDATE(), 'resgate_expirado', p_valor, p_expirado_id,
          CONCAT('Resgate do crédito expirado de ', v_ano, COALESCE(CONCAT(' — ', p_motivo), '')),
          p_usuario);

  UPDATE creditos_expirados
     SET valor_resgatado = valor_resgatado + p_valor,
         status = IF(valor_resgatado + p_valor >= valor, 'resgatado', 'resgatado_parcial')
   WHERE id = p_expirado_id;
  COMMIT;
END $$

-- ---------------------------------------------------------------------
-- Pagamento do saldo negativo (desconto feito em outro sistema)
-- Não deixa pagar mais do que o negativo — o saldo vai no máximo a zero.
-- ---------------------------------------------------------------------
DROP PROCEDURE IF EXISTS sp_registrar_pagamento $$
CREATE PROCEDURE sp_registrar_pagamento(IN p_vendedor SMALLINT UNSIGNED, IN p_valor DECIMAL(12,2),
                                        IN p_data DATE, IN p_referencia VARCHAR(80),
                                        IN p_usuario INT UNSIGNED, IN p_obs VARCHAR(255))
BEGIN
  DECLARE v_saldo DECIMAL(12,2);
  DECLARE v_existe SMALLINT UNSIGNED;
  DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK; RESIGNAL; END;

  START TRANSACTION;
  SELECT id INTO v_existe FROM vendedores WHERE id = p_vendedor FOR UPDATE;  -- trava o vendedor
  IF v_existe IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Vendedor não encontrado';
  END IF;
  SET v_saldo = fn_saldo_em(p_vendedor, '9999-12-31');

  IF v_saldo >= 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Vendedor não possui saldo negativo';
  END IF;
  IF p_valor <= 0 OR p_valor > -v_saldo THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Valor maior que o saldo negativo do vendedor';
  END IF;

  INSERT INTO pagamentos_vendedor (vendedor_id, valor, data_pagamento, referencia_externa, obs, criado_por)
  VALUES (p_vendedor, p_valor, p_data, p_referencia, p_obs, p_usuario);

  INSERT INTO movimentos_credito (vendedor_id, data_ref, tipo, valor, pagamento_id, obs, criado_por)
  VALUES (p_vendedor, p_data, 'pagamento_negativo', p_valor, LAST_INSERT_ID(),
          CONCAT('Pagamento de saldo negativo', COALESCE(CONCAT(' — ref. ', p_referencia), '')),
          p_usuario);
  COMMIT;
END $$

-- ---------------------------------------------------------------------
-- Gestor aprova ou recusa uma solicitação de crédito
--   p_aprovar = 1 aprova, 0 recusa
--   Só aprova se o vendedor tiver saldo DISPONÍVEL (saldo − reservas)
-- ---------------------------------------------------------------------
DROP PROCEDURE IF EXISTS sp_decidir_solicitacao $$
CREATE PROCEDURE sp_decidir_solicitacao(IN p_id INT UNSIGNED, IN p_aprovar TINYINT,
                                        IN p_usuario INT UNSIGNED, IN p_motivo VARCHAR(255))
BEGIN
  DECLARE v_vend SMALLINT UNSIGNED;
  DECLARE v_tipo VARCHAR(20);
  DECLARE v_valor DECIMAL(12,2);
  DECLARE v_status VARCHAR(20);
  DECLARE v_disp DECIMAL(12,2);
  DECLARE v_trava SMALLINT UNSIGNED;
  DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK; RESIGNAL; END;

  START TRANSACTION;
  SELECT vendedor_id, tipo, valor, status INTO v_vend, v_tipo, v_valor, v_status
    FROM solicitacoes_credito WHERE id = p_id FOR UPDATE;
  -- trava o vendedor: duas aprovações simultâneas para ele esperam uma pela outra
  SELECT id INTO v_trava FROM vendedores WHERE id = v_vend FOR UPDATE;

  IF v_status IS NULL OR v_status <> 'pendente' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Solicitação não está pendente';
  END IF;

  IF p_aprovar = 1 THEN
    SELECT disponivel INTO v_disp FROM vw_saldo_vendedor WHERE vendedor_id = v_vend;
    IF v_disp < v_valor THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Saldo disponível insuficiente para aprovar';
    END IF;

    UPDATE solicitacoes_credito
       SET status = 'aprovada', decidido_por = p_usuario, decidido_em = NOW(), motivo_decisao = p_motivo
     WHERE id = p_id;

    IF v_tipo = 'outro' THEN       -- uso direto: debita já e encerra a solicitação
      INSERT INTO movimentos_credito (vendedor_id, data_ref, tipo, valor, solicitacao_id, obs, criado_por)
      VALUES (v_vend, CURDATE(), 'uso_credito', -v_valor, p_id,
              CONCAT('Uso de crédito aprovado (solicitação #', p_id, ')'), p_usuario);
      UPDATE solicitacoes_credito SET status = 'utilizada' WHERE id = p_id;
    END IF;
  ELSE
    UPDATE solicitacoes_credito
       SET status = 'recusada', decidido_por = p_usuario, decidido_em = NOW(), motivo_decisao = p_motivo
     WHERE id = p_id;
  END IF;
  COMMIT;
END $$

-- ---------------------------------------------------------------------
-- Transferência de crédito entre vendedores (gera par de movimentos)
-- ---------------------------------------------------------------------
DROP PROCEDURE IF EXISTS sp_transferir_credito $$
CREATE PROCEDURE sp_transferir_credito(IN p_origem SMALLINT UNSIGNED, IN p_destino SMALLINT UNSIGNED,
                                       IN p_valor DECIMAL(12,2), IN p_data DATE,
                                       IN p_usuario INT UNSIGNED, IN p_obs VARCHAR(200))
BEGIN
  DECLARE v_saida INT UNSIGNED;
  DECLARE v_entrada INT UNSIGNED;
  DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK; RESIGNAL; END;

  IF p_origem = p_destino OR p_valor <= 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Transferência inválida';
  END IF;

  START TRANSACTION;
  INSERT INTO movimentos_credito (vendedor_id, data_ref, tipo, valor, obs, criado_por)
  VALUES (p_origem, p_data, 'transferencia', -p_valor, COALESCE(p_obs, 'Transferência enviada'), p_usuario);
  SET v_saida = LAST_INSERT_ID();
  INSERT INTO movimentos_credito (vendedor_id, data_ref, tipo, valor, transferencia_par_id, obs, criado_por)
  VALUES (p_destino, p_data, 'transferencia', p_valor, v_saida, COALESCE(p_obs, 'Transferência recebida'), p_usuario);
  SET v_entrada = LAST_INSERT_ID();
  UPDATE movimentos_credito SET transferencia_par_id = v_entrada WHERE id = v_saida;
  COMMIT;
END $$

DELIMITER ;
