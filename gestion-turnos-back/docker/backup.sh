#!/usr/bin/env bash
#
# Backup de la base de datos. Los datos viven en un volumen de Docker:
# si el volumen se corrompe o alguien corre `docker compose down -v`,
# sin backup se pierde todo.
#
# Uso:   bash docker/backup.sh [carpeta_destino]
# Cron:  0 2 * * *  cd /ruta/al/proyecto && bash docker/backup.sh >> backup.log 2>&1
#
set -euo pipefail

DESTINO="${1:-./backups}"
RETENCION_DIAS=30
CONTENEDOR="gestion-turnos-mysql"

cd "$(dirname "$0")/.."

# Credenciales desde .env: nunca hardcodeadas en el script.
DB_DATABASE=$(grep -E '^DB_DATABASE=' .env | cut -d= -f2-)
DB_USERNAME=$(grep -E '^DB_USERNAME=' .env | cut -d= -f2-)
DB_PASSWORD=$(grep -E '^DB_PASSWORD=' .env | cut -d= -f2-)

if ! docker ps --format '{{.Names}}' | grep -q "^${CONTENEDOR}$"; then
  echo "ERROR: el contenedor ${CONTENEDOR} no está corriendo." >&2
  exit 1
fi

mkdir -p "$DESTINO"
ARCHIVO="${DESTINO}/gestion_turnos_$(date +%Y%m%d_%H%M%S).sql.gz"

echo "Respaldando ${DB_DATABASE} -> ${ARCHIVO}"

# --single-transaction: backup consistente sin bloquear la operación clínica.
docker exec -e MYSQL_PWD="$DB_PASSWORD" "$CONTENEDOR" \
  mysqldump \
    --user="$DB_USERNAME" \
    --single-transaction \
    --no-tablespaces \
    --routines \
    --triggers \
    --default-character-set=utf8mb4 \
    "$DB_DATABASE" | gzip > "$ARCHIVO"

# Un dump vacío o truncado es peor que ninguno: se detecta acá.
if [ ! -s "$ARCHIVO" ] || [ "$(stat -c%s "$ARCHIVO" 2>/dev/null || stat -f%z "$ARCHIVO")" -lt 1024 ]; then
  echo "ERROR: el backup salió vacío o demasiado chico. Se elimina." >&2
  rm -f "$ARCHIVO"
  exit 1
fi

echo "OK: $(du -h "$ARCHIVO" | cut -f1)"

# Rotación: no acumular indefinidamente.
find "$DESTINO" -name 'gestion_turnos_*.sql.gz' -type f -mtime +${RETENCION_DIAS} -delete 2>/dev/null || true

echo "Backups disponibles: $(find "$DESTINO" -name 'gestion_turnos_*.sql.gz' | wc -l)"
echo
echo "Para restaurar:"
echo "  gunzip -c ${ARCHIVO} | docker exec -i ${CONTENEDOR} mysql -u${DB_USERNAME} -p'<password>' ${DB_DATABASE}"
