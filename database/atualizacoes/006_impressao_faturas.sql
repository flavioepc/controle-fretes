SET NAMES utf8mb4;
-- 03/11/2026 — Histórico de impressão das faturas (relatório que o operador manda ao financeiro).
-- Uma linha por vez que a janela de impressão foi aberta. Pode rodar mais de uma vez; não mexe em saldo.
USE fabreck_fretes;

CREATE TABLE IF NOT EXISTS faturas_impressoes (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  fatura_id    INT UNSIGNED NOT NULL,
  usuario_id   INT UNSIGNED NULL,               -- portal.usuarios.id (outro banco, sem FK)
  modelo       VARCHAR(12) NOT NULL,            -- detalhado | vendedor | protocolo
  impressa_em  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_fi_fatura (fatura_id, impressa_em),
  CONSTRAINT fk_fi_fatura FOREIGN KEY (fatura_id) REFERENCES faturas(id) ON DELETE CASCADE
) ENGINE=InnoDB;
