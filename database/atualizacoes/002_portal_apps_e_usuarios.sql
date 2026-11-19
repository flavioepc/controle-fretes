SET NAMES utf8mb4;
-- 28/10/2026 — Portal com vários aplicativos e tela "Usuários e acessos".
--  * sistemas.externo: o app roda fora deste código (ex.: WebFolha), o card leva direto à URL
--  * sistemas.perfis:  perfis possíveis do app, separados por vírgula (aparecem na tela de acessos)
--  * cards novos: WebFolha (folha de pagamento) e Usuários e acessos (administração do portal)
-- Pode rodar mais de uma vez.
USE fabreck_portal;

DELIMITER $$
DROP PROCEDURE IF EXISTS tmp_add_coluna $$
CREATE PROCEDURE tmp_add_coluna(IN p_tabela VARCHAR(64), IN p_coluna VARCHAR(64), IN p_definicao VARCHAR(255))
BEGIN
  IF NOT EXISTS (SELECT 1 FROM information_schema.columns
                  WHERE table_schema = DATABASE() AND table_name = p_tabela AND column_name = p_coluna) THEN
    SET @ddl = CONCAT('ALTER TABLE ', p_tabela, ' ADD COLUMN ', p_coluna, ' ', p_definicao);
    PREPARE st FROM @ddl;
    EXECUTE st;
    DEALLOCATE PREPARE st;
  END IF;
END $$
DELIMITER ;

CALL tmp_add_coluna('sistemas', 'externo', 'TINYINT(1) NOT NULL DEFAULT 0 AFTER url');
CALL tmp_add_coluna('sistemas', 'perfis', 'VARCHAR(255) NOT NULL DEFAULT ''usuario'' AFTER icone');
DROP PROCEDURE tmp_add_coluna;

UPDATE sistemas SET perfis = 'admin,financeiro,gestor,vendedor' WHERE slug = 'fretes';

INSERT INTO sistemas (slug, nome, descricao, url, externo, icone, perfis, ordem) VALUES
  ('webfolha', 'WebFolha', 'Folha de pagamento, contratos e holerites (login próprio do sistema)',
   '/WebFolha/', 1, 'bi-cash-coin', 'usuario', 2),
  ('usuarios', 'Usuários e acessos', 'Cadastro de usuários da intranet e liberação dos aplicativos',
   'usuarios', 0, 'bi-person-gear', 'admin', 90)
ON DUPLICATE KEY UPDATE nome = VALUES(nome), descricao = VALUES(descricao), url = VALUES(url),
  externo = VALUES(externo), icone = VALUES(icone), perfis = VALUES(perfis);

-- Quem já é admin do sistema de fretes passa a administrar também os usuários do portal
INSERT IGNORE INTO usuario_sistema (usuario_id, sistema_id, perfil)
SELECT us.usuario_id, (SELECT id FROM sistemas WHERE slug = 'usuarios'), 'admin'
  FROM usuario_sistema us JOIN sistemas s ON s.id = us.sistema_id
 WHERE s.slug = 'fretes' AND us.perfil = 'admin';
