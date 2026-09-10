# syntax=docker/dockerfile:1
#
# Imagen única reutilizada por los 3 servicios de producción (ver
# docs/DECISIONES.md DEC-065-anexo/despliegue): "web" usa el CMD por defecto
# (nginx + php-fpm vía supervisord); "reverb" y "queue-worker" sobrescriben el
# comando de arranque en la plataforma de despliegue (EasyPanel) con
# `php artisan reverb:start ...` / `php artisan queue:work ...` — mismo
# código, mismas dependencias, sin reconstruir nada aparte.

# ---- Etapa 1: dependencias de Composer, solo para exponerle vendor/ al
# build de Vite (abajo) — resources/css/filament/admin/theme.css importa
# vendor/filament/filament/resources/css/theme.css, y ese archivo a su vez
# escanea vistas Blade de Filament dentro de vendor/ para generar las clases
# de Tailwind: sin vendor/ presente, `npm run build` falla al no poder
# resolver ese import. --no-scripts evita ejecutar `artisan` (no hay PHP con
# las extensiones del proyecto en esta etapa, ni falta hace: solo necesitamos
# los archivos de los paquetes en disco, no un autoloader funcional).
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-interaction --prefer-dist --ignore-platform-reqs

# ---- Etapa 2: build de assets del POS (Vite) ----
FROM node:22-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY vite.config.js ./
COPY resources resources
COPY public public
# resources/css/filament/admin/theme.css escanea app/Filament (@source) para
# generar las clases de Tailwind que usan las páginas/recursos de Filament —
# sin este directorio el build "funciona" pero produce un CSS incompleto,
# rompiendo estilos del panel admin en silencio (no un error de build).
# storage/framework/views también es un @source de resources/css/app.css
# (vistas Blade compiladas) — normalmente vacío en un build limpio, se copia
# solo para que el directorio exista y el glob no falle.
COPY app app
COPY storage storage
COPY --from=vendor /app/vendor vendor
# Vite incrusta las variables VITE_* en el bundle en tiempo de build, no de
# arranque — deben pasarse como --build-arg al construir la imagen.
ARG VITE_REVERB_APP_KEY
ARG VITE_REVERB_HOST
ARG VITE_REVERB_PORT
ARG VITE_REVERB_SCHEME
ENV VITE_REVERB_APP_KEY=$VITE_REVERB_APP_KEY \
    VITE_REVERB_HOST=$VITE_REVERB_HOST \
    VITE_REVERB_PORT=$VITE_REVERB_PORT \
    VITE_REVERB_SCHEME=$VITE_REVERB_SCHEME
RUN npm run build

# ---- Etapa 3: imagen final (PHP-FPM + Nginx) ----
FROM php:8.3-fpm-alpine

RUN apk add --no-cache nginx supervisor postgresql-libs \
    && apk add --no-cache --virtual .build-deps postgresql-dev $PHPIZE_DEPS \
    && docker-php-ext-install pdo pdo_pgsql pgsql bcmath opcache pcntl \
    && apk del .build-deps

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
COPY . .
COPY --from=assets /app/public/build public/build

RUN composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist \
    && chown -R www-data:www-data storage bootstrap/cache

COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

EXPOSE 80

# Migraciones/storage:link NO corren aquí a propósito — son pasos manuales
# de un solo disparo (ver instrucciones de despliegue), no algo que deba
# repetirse cada vez que arrancan/reinician los 3 servicios a la vez.
CMD ["supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
