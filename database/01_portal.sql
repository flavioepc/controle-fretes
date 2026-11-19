SET NAMES utf8mb4;
-- =====================================================================
--  PORTAL FABRECK — banco compartilhado por todos os sistemas da intranet
--  Login único: o usuário entra no portal e escolhe o sistema que acessa.
--  Compatível com MySQL 8.0+ e MariaDB 10.6+
-- =====================================================================

CREATE DATABASE IF NOT EXISTS fabreck_portal
  DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE fabreck_portal;

-- Usuários (um cadastro só para todos os sistemas)
CREATE TABLE usuarios (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nome          VARCHAR(100) NOT NULL,
  email         VARCHAR(150) NOT NULL,
  senha_hash    VARCHAR(255) NOT NULL,          -- password_hash() do PHP
  ativo         TINYINT(1)   NOT NULL DEFAULT 1,
  trocar_senha  TINYINT(1)   NOT NULL DEFAULT 1, -- força troca no 1º acesso
  ultimo_login  DATETIME     NULL,
  criado_em     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em DATETIME     NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_usuarios_email (email)
) ENGINE=InnoDB;

-- Sistemas exibidos na página inicial do portal
CREATE TABLE sistemas (
  id        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug      VARCHAR(40)  NOT NULL,              -- 'fretes', 'estoque'...
  nome      VARCHAR(80)  NOT NULL,
  descricao VARCHAR(255) NULL,
  url       VARCHAR(255) NOT NULL,              -- '/fretes/' ou subdomínio
  icone     VARCHAR(50)  NULL,                  -- classe Bootstrap Icons
  ordem     SMALLINT     NOT NULL DEFAULT 0,
  ativo     TINYINT(1)   NOT NULL DEFAULT 1,
  UNIQUE KEY uk_sistemas_slug (slug)
) ENGINE=InnoDB;

-- Quem acessa o quê, e com qual perfil dentro de cada sistema
CREATE TABLE usuario_sistema (
  usuario_id INT UNSIGNED NOT NULL,
  sistema_id INT UNSIGNED NOT NULL,
  perfil     VARCHAR(30)  NOT NULL,             -- fretes: admin | financeiro | gestor | vendedor
  PRIMARY KEY (usuario_id, sistema_id),
  CONSTRAINT fk_us_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
  CONSTRAINT fk_us_sistema FOREIGN KEY (sistema_id) REFERENCES sistemas(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Trilha de auditoria comum (login, alterações sensíveis)
CREATE TABLE log_acessos (
  id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT UNSIGNED NULL,
  sistema    VARCHAR(40)  NULL,
  acao       VARCHAR(60)  NOT NULL,             -- 'login', 'login_falhou', 'logout'...
  detalhe    VARCHAR(255) NULL,
  ip         VARCHAR(45)  NULL,
  criado_em  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_log_usuario (usuario_id, criado_em)
) ENGINE=InnoDB;

INSERT INTO sistemas (slug, nome, descricao, url, icone, ordem) VALUES
  ('fretes', 'Controle de Fretes', 'Fretes, faturas de transportadoras e crédito dos vendedores',
   '/fretes/', 'bi-truck', 1);
