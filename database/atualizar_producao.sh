#!/usr/bin/env bash
# ============================================================
#  Aplica as ATUALIZACOES do banco (pasta atualizacoes/) no servidor de produção, sem apagar dados.
#  Equivalente ao atualizar_banco.bat do notebook. Cada arquivo pode rodar mais de uma vez.
#  O MySQL 8 roda no contêiner intranet_mysql8 (ver ~/intranet/docker-compose.yml).
#
#  Uso no servidor (192.168.3.198, usuário rhadmin):
#     bash ~/intranet/www/intranet/database/atualizar_producao.sh
# ============================================================
set -euo pipefail
cd ~/intranet
set -a; . ./.env; set +a

for f in www/intranet/database/atualizacoes/*.sql; do
  echo "Aplicando $f ..."
  docker exec -i -e MYSQL_PWD="$MYSQL8_ROOT_SENHA" intranet_mysql8 \
    mysql -uroot --default-character-set=utf8mb4 < "$f"
done
echo "Banco atualizado. Os dados foram mantidos."
