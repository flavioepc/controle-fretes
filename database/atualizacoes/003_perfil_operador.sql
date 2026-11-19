SET NAMES utf8mb4;
-- 03/11/2026 — Perfil "operador" no Controle de Fretes: quem lança os fretes para gestores e financeiro
-- (ex.: Regiane). Vê todos os vendedores, lança/edita fretes e pede uso de crédito em nome de qualquer
-- vendedor; não aprova pedidos, não exclui fretes e não acessa faturas, cadastros nem configurações.
-- Não precisa de vínculo com gestor nem com vendedor. Pode rodar mais de uma vez.
USE fabreck_portal;

UPDATE sistemas SET perfis = 'admin,financeiro,gestor,operador,vendedor' WHERE slug = 'fretes';
