#!/bin/sh
set -e

cd /var/www/html

# El disco del free tier de Render no es persistente: cada arranque (deploy
# nuevo o el contenedor despertando tras dormirse por inactividad) parte de
# un SQLite vacío. Por eso se migra y se siembra siempre, no solo la primera
# vez — es la forma de que el demo se "autocure" con datos frescos.
mkdir -p database
touch database/database.sqlite

# APP_KEY nuevo en cada arranque: como la base es efímera, no hay datos
# cifrados viejos que deban seguir siendo legibles con una key anterior.
export APP_KEY="base64:$(php -r 'echo base64_encode(random_bytes(32));')"

php artisan migrate --force
php artisan db:seed --force

# migrate/seed corren como root (el contenedor no cambia de usuario) y de
# paso crean subcarpetas de cache (p. ej. al cachear roles y permisos): esas
# quedan de root y le tapan el chown del build a PHP-FPM, que sirve como
# www-data. Se re-aplica acá, ya con todo lo que el arranque pudo crear.
chown -R www-data:www-data storage bootstrap/cache database

: "${PORT:=10000}"
envsubst '${PORT}' < /etc/nginx/templates/default.conf.template > /etc/nginx/conf.d/default.conf

php-fpm -D

exec nginx -g 'daemon off;'
