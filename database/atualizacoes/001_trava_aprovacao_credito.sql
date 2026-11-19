SET NAMES utf8mb4;
-- 27/10/2026 — aprovação de crédito trava o vendedor (evita aprovar acima do saldo
-- quando dois gestores aprovam ao mesmo tempo). Pode rodar mais de uma vez.
USE fabreck_fretes;
DELIMITER $$
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
DELIMITER ;
