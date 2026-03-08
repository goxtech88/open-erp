# ==============================================================
# Open.ERP by Goxtech Labs — Dockerfile
# PHP 8.2-FPM Alpine / PostgreSQL / Redis
# ==============================================================

# Stage 1: Dependencias PHP con Composer
FROM composer:2.7 AS composer-build

WORKDIR /app

# composer.lock es opcional — wildcard lo hace condicional
COPY composer.json composer.loc[k] ./

RUN composer install \
    --optimize-autoloader \
    --no-dev \
    --no-interaction \
    --no-scripts \
    --prefer-dist

COPY . .

# Stage 2: Imagen final PHP-FPM
FROM php:8.2-fpm-alpine AS production

# Dependencias de sistema (Alpine) — sin libsoap (no existe), sin nginx/supervisor
RUN apk add --no-cache \
    libpng-dev \
    libzip-dev \
    postgresql-dev \
    libxml2-dev \
    oniguruma-dev \
    curl \
    zip \
    unzip \
    openssl \
    netcat-openbsd

# Instalar extensiones PHP (soap requiere libxml2-dev que ya está)
# NO incluir opcache aquí — ya viene built-in en php:8.2-fpm-alpine
RUN docker-php-ext-install \
    pdo \
    pdo_pgsql \
    pgsql \
    zip \
    gd \
    mbstring \
    bcmath \
    xml \
    soap

# Activar opcache (ya viene compilado, solo hay que habilitarlo)
RUN docker-php-ext-enable opcache

# Redis via PECL
RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps

# Configuración PHP (sin extension=soap — ya está cargado por docker-php-ext-install)
COPY docker/php/php.ini /usr/local/etc/php/conf.d/99-production.ini
COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/98-opcache.ini

WORKDIR /var/www/html

# Vendor desde el stage de build
COPY --from=composer-build /app/vendor ./vendor

# Código de la aplicación
COPY . .

# Permisos de storage
RUN mkdir -p storage/logs storage/framework/cache storage/framework/sessions \
    storage/framework/views bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache \
    && chown -R www-data:www-data /var/www/html

# Directorio de logs PHP
RUN mkdir -p /var/log/php && chown www-data:www-data /var/log/php

# Entrypoint: genera APP_KEY, corre migraciones, optimiza
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 9000

ENTRYPOINT ["entrypoint.sh"]
CMD ["php-fpm"]
