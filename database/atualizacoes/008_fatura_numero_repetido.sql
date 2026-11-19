SET NAMES utf8mb4;
-- 09/11/2026 — A transportadora pode repetir o nº do documento (ex.: EXPRESSO ESPERANÇA 7000 de 19/05/2026,
-- já paga, e outra 7000 em 06/11/2026). Com a chave única (transportadora, nº) os fretes novos caíam
-- na fatura antiga e paga. Agora o nº deixa de ser único: o lançamento reaproveita só a fatura ainda
-- em andamento (aberta/conferida/divergente) e cria outra quando a que existe já foi enviada/paga
-- (ver FreteRepository::faturaPara). Pode rodar mais de uma vez. Não mexe em dados (nenhum saldo muda).
USE fabreck_fretes;

DROP PROCEDURE IF EXISTS tmp_008_indices;
DELIMITER $$
CREATE PROCEDURE tmp_008_indices()
BEGIN
  -- primeiro o índice comum (a FK fk_fat_transp precisa de um índice começando por transportadora_id)
  IF NOT EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'faturas' AND INDEX_NAME = 'idx_fatura_transp_num') THEN
    ALTER TABLE faturas ADD KEY idx_fatura_transp_num (transportadora_id, numero_documento);
  END IF;
  IF EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'faturas' AND INDEX_NAME = 'uk_fatura') THEN
    ALTER TABLE faturas DROP INDEX uk_fatura;
  END IF;
END $$
DELIMITER ;
CALL tmp_008_indices();
DROP PROCEDURE tmp_008_indices;
