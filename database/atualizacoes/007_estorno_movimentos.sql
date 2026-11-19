SET NAMES utf8mb4;
-- 03/11/2026 — Estorno de pagamento de saldo negativo e de transferência de crédito (admin e financeiro).
-- Pode rodar mais de uma vez. Não mexe em nenhum lançamento existente (nenhum saldo muda).
--
--  * o estorno NÃO apaga nada: grava um movimento novo do tipo 'estorno', com o valor ao contrário,
--    na data do estorno, apontando para o lançamento original (movimentos_credito.estorno_de_id)
--  * transferência estornada = dois estornos (um para cada lado), ligados entre si por transferencia_par_id
--  * o índice único em estorno_de_id impede estornar duas vezes o mesmo lançamento
USE fabreck_fretes;

DROP PROCEDURE IF EXISTS tmp_007_colunas;
DELIMITER $$
CREATE PROCEDURE tmp_007_colunas()
BEGIN
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'movimentos_credito' AND COLUMN_NAME = 'tipo'
                  AND COLUMN_TYPE LIKE '%''estorno''%') THEN
    ALTER TABLE movimentos_credito MODIFY COLUMN tipo
      ENUM('saldo_inicial','transferencia','uso_credito','despesa','expiracao','resgate_expirado',
           'pagamento_negativo','ajuste','estorno') NOT NULL;
  END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'movimentos_credito' AND COLUMN_NAME = 'estorno_de_id') THEN
    ALTER TABLE movimentos_credito
      ADD COLUMN estorno_de_id INT UNSIGNED NULL AFTER transferencia_par_id,
      ADD UNIQUE KEY uk_mov_estorno (estorno_de_id),
      ADD CONSTRAINT fk_mov_estorno FOREIGN KEY (estorno_de_id) REFERENCES movimentos_credito(id);
  END IF;
END $$
DELIMITER ;
CALL tmp_007_colunas();
DROP PROCEDURE tmp_007_colunas;

-- ---------------------------------------------------------------------
-- Estorna um pagamento de saldo negativo ou uma transferência (pelo id de qualquer um dos dois lados).
--   * pagamento: o valor volta a ser devido (o saldo do vendedor desce de novo)
--   * transferência: o valor sai de quem recebeu e volta para quem enviou — só se quem recebeu
--     ainda tiver esse valor DISPONÍVEL (mesma regra da transferência)
-- ---------------------------------------------------------------------
DELIMITER $$
DROP PROCEDURE IF EXISTS sp_estornar_movimento $$
CREATE PROCEDURE sp_estornar_movimento(IN p_movimento INT UNSIGNED, IN p_usuario INT UNSIGNED,
                                       IN p_motivo VARCHAR(200))
BEGIN
  DECLARE v_vend SMALLINT UNSIGNED;
  DECLARE v_tipo VARCHAR(30);
  DECLARE v_valor DECIMAL(12,2);
  DECLARE v_data DATE;
  DECLARE v_par INT UNSIGNED;
  DECLARE v_pag INT UNSIGNED;
  DECLARE v_par_vend SMALLINT UNSIGNED;
  DECLARE v_par_valor DECIMAL(12,2);
  DECLARE v_saida INT UNSIGNED;
  DECLARE v_entrada INT UNSIGNED;
  DECLARE v_origem SMALLINT UNSIGNED;
  DECLARE v_destino SMALLINT UNSIGNED;
  DECLARE v_trava SMALLINT UNSIGNED;
  DECLARE v_disp DECIMAL(12,2);
  DECLARE v_est_origem INT UNSIGNED;
  DECLARE v_est_destino INT UNSIGNED;
  DECLARE v_msg VARCHAR(128);
  DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK; RESIGNAL; END;

  START TRANSACTION;
  SELECT vendedor_id, tipo, valor, data_ref, transferencia_par_id, pagamento_id
    INTO v_vend, v_tipo, v_valor, v_data, v_par, v_pag
    FROM movimentos_credito WHERE id = p_movimento FOR UPDATE;

  IF v_vend IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Lançamento não encontrado';
  END IF;
  IF v_tipo NOT IN ('pagamento_negativo', 'transferencia') THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Só pagamentos e transferências podem ser estornados';
  END IF;
  IF EXISTS (SELECT 1 FROM movimentos_credito WHERE estorno_de_id IN (p_movimento, COALESCE(v_par, 0))) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Este lançamento já foi estornado';
  END IF;

  IF v_tipo = 'pagamento_negativo' THEN
    SELECT id INTO v_trava FROM vendedores WHERE id = v_vend FOR UPDATE;
    INSERT INTO movimentos_credito (vendedor_id, data_ref, tipo, valor, pagamento_id, estorno_de_id, obs, criado_por)
    VALUES (v_vend, CURDATE(), 'estorno', -v_valor, v_pag, p_movimento,
            LEFT(CONCAT('Estorno do pagamento de ', DATE_FORMAT(v_data, '%d/%m/%Y'), ' — ', p_motivo), 255),
            p_usuario);
  ELSE
    SELECT vendedor_id, valor INTO v_par_vend, v_par_valor
      FROM movimentos_credito WHERE id = v_par AND tipo = 'transferencia' FOR UPDATE;
    IF v_par_vend IS NULL OR v_par_valor <> -v_valor THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Transferência sem o lançamento do outro vendedor; corrija no banco';
    END IF;
    IF v_valor < 0 THEN          -- clicou no lado de quem enviou
      SET v_saida = p_movimento, v_origem = v_vend, v_entrada = v_par, v_destino = v_par_vend, v_valor = -v_valor;
    ELSE
      SET v_saida = v_par, v_origem = v_par_vend, v_entrada = p_movimento, v_destino = v_vend;
    END IF;

    -- trava os dois na ordem do id (como sp_transferir_credito) e confere o disponível de quem recebeu
    SELECT id INTO v_trava FROM vendedores WHERE id = LEAST(v_origem, v_destino) FOR UPDATE;
    SELECT id INTO v_trava FROM vendedores WHERE id = GREATEST(v_origem, v_destino) FOR UPDATE;
    SELECT disponivel INTO v_disp FROM vw_saldo_vendedor WHERE vendedor_id = v_destino;
    IF v_disp < v_valor THEN
      SET v_msg = CONCAT('Quem recebeu só tem R$ ', FORMAT(GREATEST(v_disp, 0), 2, 'pt_BR'),
                         ' disponível para devolver');
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_msg;
    END IF;

    INSERT INTO movimentos_credito (vendedor_id, data_ref, tipo, valor, estorno_de_id, obs, criado_por)
    SELECT v_origem, CURDATE(), 'estorno', v_valor, v_saida,
           LEFT(CONCAT('Estorno da transferência para ', nome, ' de ', DATE_FORMAT(v_data, '%d/%m/%Y'),
                       ' — ', p_motivo), 255), p_usuario
      FROM vendedores WHERE id = v_destino;
    SET v_est_origem = LAST_INSERT_ID();
    INSERT INTO movimentos_credito (vendedor_id, data_ref, tipo, valor, estorno_de_id, transferencia_par_id, obs, criado_por)
    SELECT v_destino, CURDATE(), 'estorno', -v_valor, v_entrada, v_est_origem,
           LEFT(CONCAT('Estorno da transferência de ', nome, ' de ', DATE_FORMAT(v_data, '%d/%m/%Y'),
                       ' — ', p_motivo), 255), p_usuario
      FROM vendedores WHERE id = v_origem;
    SET v_est_destino = LAST_INSERT_ID();
    UPDATE movimentos_credito SET transferencia_par_id = v_est_destino WHERE id = v_est_origem;
  END IF;
  COMMIT;
END $$
DELIMITER ;
