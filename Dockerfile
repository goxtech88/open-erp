# ==============================================================
# Stage 1: Build de dependencias con Composer
# ==============================================================
FROM composer:2.7 AS composer-build

WORKDIR /app

# Copiar composer.json y composer.lock si existe (wildcard lo hace opcional)
COPY composer.json composer.loc[k] ./

RUN composer install \
    --optimize-autoloader \
    --no-dev \
    --no-interaction \
    --no-scripts \
    --prefer-dist

COPY . .

RUN composer dump-autoload --optimize --no-dev


# ==============================================================
# Stage 2: Imagen final de producción
# ==============================================================
FROM php:8.2-fpm-alpine AS production

# Instalar dependencias del sistema
RUN apk add --no-cache \
    libpng-dev \
    libzip-dev \
    postgresql-dev \
    libxml2-dev \
    oniguruma-dev \
    curl \
    zip \
    unzip \
    libsoap \
    openssl \
    nginx \
    supervisor

# Instalar extensiones PHP necesarias
RUN docker-php-ext-install \
    pdo \
    pdo_pgsql \
    pgsql \
    zip \
    gd \
    mbstring \
    bcmath \
    xml \
    soap \
    opcache

# Copiar configuración PHP optimizada
COPY docker/php/php.ini /usr/local/etc/php/conf.d/99-production.ini
COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/98-opcache.ini

WORKDIR /var/www/html

# Copiar vendor desde el stage de build
COPY --from=composer-build /app/vendor ./vendor

# Copiar el resto de la aplicación
COPY . .

# Crear directorios necesarios y ajustar permisos
RUN mkdir -p storage/logs storage/framework/cache storage/framework/sessions \
    storage/framework/views bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache \
    && chown -R www-data:www-data /var/www/html

# Optimizar Laravel para producción
RUN php artisan config:cache \
    && php artisan route:cache \
    && php artisan view:cache \
    && php artisan event:cache

EXPOSE 9000

CMD ["php-fpm"]
