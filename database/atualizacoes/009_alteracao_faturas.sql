SET NAMES utf8mb4;
-- 18/11/2026 — Histórico de alteração dos dados de faturas já enviadas para pagamento ou pagas
-- (vencimento, boleto, desconto, conferente, observação e dados do pagamento).
-- Uma linha por alteração, com o motivo e o que mudou (antes → depois). Pode rodar mais de uma vez;
-- não mexe em fretes nem em saldo.
USE fabreck_fretes;

CREATE TABLE IF NOT EXISTS faturas_alteracoes (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  fatura_id    INT UNSIGNED NOT NULL,
  usuario_id   INT UNSIGNED NULL,               -- portal.usuarios.id (outro banco, sem FK)
  motivo       VARCHAR(200) NOT NULL,
  detalhe      TEXT NOT NULL,                   -- uma linha por campo: "Vencimento: 11/11/2026 → 25/11/2026"
  alterada_em  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_fa_fatura (fatura_id, alterada_em),
  CONSTRAINT fk_fa_fatura FOREIGN KEY (fatura_id) REFERENCES faturas(id) ON DELETE CASCADE
) ENGINE=InnoDB;
