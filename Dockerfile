# Imagen única para el demo público gratuito: frontend estático + API en el
# mismo origen (evita el problema de cookies cross-domain de Sanctum) y sin
# servicios aparte (SQLite en vez de MySQL, cache de archivo en vez de Redis,
# Pusher en vez de Reverb). El stack "real" para desarrollo sigue siendo el
# de gestion-turnos-back/Dockerfile + docker-compose.yml; esta imagen es solo
# para desplegar una demo de portfolio, no para producción con datos reales.

# ---- Etapa 1: build del frontend ----
FROM node:20-alpine AS frontend-build
WORKDIR /app
COPY gestion-turnos-front/package*.json ./
RUN npm ci
COPY gestion-turnos-front/ ./

# La key y el cluster de Pusher son públicos por diseño (viajan al navegador
# a propósito); el secret nunca aparece acá, solo como variable de entorno
# del backend en Render.
ARG VITE_API_BASE_URL=/api
ARG VITE_BROADCASTER=pusher
ARG VITE_PUSHER_APP_KEY=a8d63d96a2a175b464d3
ARG VITE_PUSHER_APP_CLUSTER=sa1
ENV VITE_API_BASE_URL=$VITE_API_BASE_URL \
    VITE_BROADCASTER=$VITE_BROADCASTER \
    VITE_PUSHER_APP_KEY=$VITE_PUSHER_APP_KEY \
    VITE_PUSHER_APP_CLUSTER=$VITE_PUSHER_APP_CLUSTER
RUN npm run build

# ---- Etapa 2: backend (PHP-FPM) + nginx sirviendo todo en un solo proceso ----
FROM php:8.4-fpm

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git zip unzip libpng-dev libonig-dev libxml2-dev libzip-dev \
        sqlite3 libsqlite3-dev nginx gettext-base \
    && docker-php-ext-install pdo_sqlite mbstring exif pcntl bcmath gd zip \
    && apt-get clean && rm -rf /var/lib/apt/lists/* \
    # Sin esto, PHP-FPM descarta las variables de entorno que Render inyecta
    # (PUSHER_APP_SECRET, etc.) y solo las ve la CLI, nunca las peticiones web.
    && echo "clear_env = no" >> /usr/local/etc/php-fpm.d/www.conf

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
COPY gestion-turnos-back/ /var/www/html/
RUN composer install --no-interaction --prefer-dist --optimize-autoloader --no-dev

COPY --from=frontend-build /app/dist /var/www/frontend-dist

COPY docker/render/nginx.conf.template /etc/nginx/templates/default.conf.template
COPY docker/render/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh \
    # PHP-FPM corre como www-data (ver www.conf del paquete oficial); sin
    # esto, storage/ y bootstrap/cache/ quedan de root y cada escritura
    # (logs, sesiones, cache de archivo) falla con "Permission denied".
    && chown -R www-data:www-data /var/www/html /var/www/frontend-dist

# Config estática y sin secretos: lo que sí es secreto (Pusher, dominios)
# se define como variable de entorno en Render, nunca acá.
ENV APP_NAME=GestionTurnos \
    APP_ENV=demo \
    APP_DEBUG=false \
    LOG_CHANNEL=stack \
    LOG_LEVEL=error \
    DB_CONNECTION=sqlite \
    DB_DATABASE=/var/www/html/database/database.sqlite \
    CACHE_STORE=file \
    SESSION_DRIVER=file \
    SESSION_SECURE_COOKIE=true \
    SESSION_SAME_SITE=lax \
    QUEUE_CONNECTION=sync \
    SEED_PASSWORD=password123 \
    CONTROL_TOWER_RETRASO_MINUTOS=20

EXPOSE 10000
ENTRYPOINT ["/entrypoint.sh"]
