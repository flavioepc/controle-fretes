SET NAMES utf8mb4;
-- 03/11/2026 — Frete isento: nº do pedido do ERP e motivo "Frete de terceiros (administrador)".
-- Pode rodar mais de uma vez. Não muda saldo: só mexe em fretes cuja diferença já é 0.
--
--  * fretes.numero_pedido: nº do pedido no ERP, exigido (com o valor) no motivo "Pedido acima do valor mínimo"
--  * motivo 8: produtos enviados pelo administrador (Donizete, cadastrado como vendedor)
--  * histórico: fretes zerados do Donizete viram "terceiros" e algumas observações com erro de digitação
--    que a 004 não pegou ("gripo", "1 cpmpra", "acim a 10 mil"...) ganham o motivo certo
USE fabreck_fretes;

INSERT IGNORE INTO motivos_isencao (id, nome, exige_valor_pedido, ordem) VALUES
  (8, 'Frete de terceiros (administrador)', 0, 7);

DROP PROCEDURE IF EXISTS tmp_005_colunas;
DELIMITER $$
CREATE PROCEDURE tmp_005_colunas()
BEGIN
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'fretes' AND COLUMN_NAME = 'numero_pedido') THEN
    ALTER TABLE fretes
      ADD COLUMN numero_pedido VARCHAR(30) NULL AFTER valor_pedido,
      ADD KEY idx_fretes_pedido (numero_pedido);
  END IF;
END $$
DELIMITER ;
CALL tmp_005_colunas();
DROP PROCEDURE tmp_005_colunas;

-- histórico (mesmas travas da 004: só zerados "cliente = transportadora", antes do corte, sem crédito usado)
UPDATE fretes f
  LEFT JOIN vendedores v ON v.id = f.vendedor_id
   SET f.isencao_id = CASE
         WHEN LOWER(f.obs) REGEXP 'conserto|consero'            THEN 5
         WHEN LOWER(f.obs) REGEXP '^doniz' OR v.nome = 'DONIZETE' THEN 8
         WHEN LOWER(f.obs) REGEXP '^acim a 10'                  THEN 1
         WHEN LOWER(f.obs) REGEXP '^gripo'                      THEN 2
         WHEN LOWER(f.obs) REGEXP '^1 ?(cpmpra|compra)'         THEN 3
         WHEN LOWER(f.obs) REGEXP '^com garantia'               THEN 4
       END,
       f.valor_cliente = 0
 WHERE f.isencao_id IS NULL AND f.solicitacao_id IS NULL AND f.criado_em < '2026-11-04'
   AND f.valor_transp IS NOT NULL AND f.valor_cliente = f.valor_transp
   AND (v.nome = 'DONIZETE'
        OR LOWER(f.obs) REGEXP '^(doniz|acim a 10|gripo|1 ?(cpmpra|compra)|com garantia|nf conserto|consero)');
