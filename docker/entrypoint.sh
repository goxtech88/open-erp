#!/bin/sh
# ==============================================================
# Entrypoint del contenedor ERP Laravel
# Se ejecuta cada vez que el contenedor arranca
# ==============================================================
set -e

echo "🚀 Iniciando ERP Laravel..."

# Esperar a que PostgreSQL esté disponible
echo "⏳ Esperando PostgreSQL en ${DB_HOST}:${DB_PORT}..."
while ! nc -z "${DB_HOST:-postgres}" "${DB_PORT:-5432}"; do
  sleep 1
done
echo "✅ PostgreSQL disponible"

# Generar APP_KEY si no existe
if [ -z "$APP_KEY" ]; then
  echo "🔑 Generando APP_KEY..."
  php artisan key:generate --force
fi

# Ejecutar migraciones (--force permite en producción)
echo "📦 Ejecutando migraciones..."
php artisan migrate --force

# Ejecutar seeders SOLO si la base está vacía (primer deploy)
if php artisan db:table users 2>/dev/null | grep -q "Records: 0"; then
  echo "🌱 Ejecutando seeders iniciales..."
  php artisan db:seed --force
fi

# Limpiar y reconstruir cachés de producción
echo "⚡ Optimizando Laravel para producción..."
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# Asegurar permisos del storage
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true

echo "✅ ERP Laravel listo"

# Ejecutar el comando principal (php-fpm)
exec "$@"
