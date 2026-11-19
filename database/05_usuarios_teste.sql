SET NAMES utf8mb4;
-- =====================================================================
--  USUÁRIOS DE TESTE (só para desenvolvimento — NÃO use em produção)
--    admin@fabreck.local   / Admin@123  -> admin (vê tudo)
--    rafael@fabreck.local  / Teste@123  -> gestor RAFAEL (vê a equipe)
--    mateus@fabreck.local  / Teste@123  -> vendedor MATEUS (vê só ele)
-- =====================================================================
USE fabreck_portal;

INSERT INTO usuarios (nome, email, senha_hash, trocar_senha) VALUES
  ('Administrador', 'admin@fabreck.local',  '$2y$10$6tVwj3XFjHWeELGedHvjSOVRrTPt.7XUT1hshVvJTmQOZNzc.M7Gi', 0),
  ('Rafael',        'rafael@fabreck.local', '$2y$10$n7DdJHBXbPldsVS7MDaFsOn4qQi8G4FN7bp7MSNFHMVNdWgxr45Um', 0),
  ('Mateus',        'mateus@fabreck.local', '$2y$10$n7DdJHBXbPldsVS7MDaFsOn4qQi8G4FN7bp7MSNFHMVNdWgxr45Um', 0)
ON DUPLICATE KEY UPDATE senha_hash = VALUES(senha_hash);

INSERT IGNORE INTO usuario_sistema (usuario_id, sistema_id, perfil)
SELECT u.id, s.id, x.perfil
  FROM (SELECT 'admin@fabreck.local' email, 'admin' perfil
        UNION ALL SELECT 'rafael@fabreck.local', 'gestor'
        UNION ALL SELECT 'mateus@fabreck.local', 'vendedor') x
  JOIN usuarios u ON u.email = x.email
  JOIN sistemas s ON s.slug = 'fretes';

-- vincula os logins aos cadastros do sistema de fretes
UPDATE fabreck_fretes.gestores   SET usuario_id = (SELECT id FROM usuarios WHERE email = 'rafael@fabreck.local') WHERE nome = 'RAFAEL';
UPDATE fabreck_fretes.vendedores SET usuario_id = (SELECT id FROM usuarios WHERE email = 'mateus@fabreck.local') WHERE nome = 'MATEUS';

-- uma solicitação pendente para aparecer no dashboard
INSERT INTO fabreck_fretes.solicitacoes_credito (vendedor_id, gestor_id, tipo, valor, cliente, justificativa)
SELECT v.id, v.gestor_id, 'frete_gratis', 350.00, 'Cliente Exemplo Ltda', 'Frete grátis no primeiro pedido'
  FROM fabreck_fretes.vendedores v WHERE v.nome = 'MATEUS'
   AND NOT EXISTS (SELECT 1 FROM fabreck_fretes.solicitacoes_credito);
