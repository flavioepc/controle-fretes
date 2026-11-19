SET NAMES utf8mb4;
-- 18/11/2026 — Painel de permissões por perfil (admin, financeiro, gestor, operador, vendedor).
-- O catálogo de permissões e o padrão de cada perfil ficam no código (App\Fretes\Permissoes::CATALOGO);
-- aqui só fica o que o admin mudou no painel. Sem linha = vale o padrão do código. Pode rodar mais de uma vez.
-- Não mexe em fretes, faturas nem saldo.
USE fabreck_fretes;

CREATE TABLE IF NOT EXISTS permissoes_perfil (
  perfil       VARCHAR(20) NOT NULL,
  permissao    VARCHAR(40) NOT NULL,
  permitido    TINYINT(1)  NOT NULL,
  alterado_por INT UNSIGNED NULL,                -- portal.usuarios.id (outro banco, sem FK)
  alterado_em  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (perfil, permissao)
) ENGINE=InnoDB;

-- quem ligou/desligou o quê, e quando
CREATE TABLE IF NOT EXISTS permissoes_historico (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  perfil       VARCHAR(20) NOT NULL,
  permissao    VARCHAR(40) NOT NULL,
  permitido    TINYINT(1)  NOT NULL,             -- como ficou
  usuario_id   INT UNSIGNED NULL,
  alterado_em  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_ph_data (alterado_em)
) ENGINE=InnoDB;
