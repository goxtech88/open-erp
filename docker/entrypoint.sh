#!/bin/sh
# ==============================================================
# Entrypoint del contenedor Open.ERP Laravel
# ==============================================================
set -e

echo "🚀 Iniciando Open.ERP Laravel..."

# Si vendor está vacío (primer arranque con volumen), copiarlo desde build
if [ ! -f "/var/www/html/vendor/autoload.php" ]; then
  echo "📦 Instalando dependencias de Composer..."
  if [ -f "/app/vendor/autoload.php" ]; then
    cp -r /app/vendor/* /var/www/html/vendor/ 2>/dev/null || true
  else
    cd /var/www/html
    composer install --optimize-autoloader --no-dev --no-interaction --prefer-dist 2>&1 || {
      echo "⚠️ Composer install falló, intentando con --no-scripts..."
      composer install --optimize-autoloader --no-dev --no-interaction --prefer-dist --no-scripts
    }
  fi
fi

# Esperar a que PostgreSQL esté disponible
echo "⏳ Esperando PostgreSQL en ${DB_HOST}:${DB_PORT}..."
while ! nc -z "${DB_HOST:-gx_postgres}" "${DB_PORT:-5432}"; do
  sleep 1
done
echo "✅ PostgreSQL disponible"

# Generar APP_KEY si no existe
if [ -z "$APP_KEY" ] || [ "$APP_KEY" = "" ]; then
  echo "🔑 Generando APP_KEY..."
  php artisan key:generate --force 2>&1 || echo "⚠️ key:generate falló"
fi

# Ejecutar migraciones
echo "📦 Ejecutando migraciones..."
php artisan migrate --force 2>&1 || echo "⚠️ Migraciones fallaron"

# Seed si la tabla users está vacía
USER_COUNT=$(php artisan tinker --execute="echo \App\Models\User::count();" 2>/dev/null || echo "0")
if [ "$USER_COUNT" = "0" ]; then
  echo "🌱 Sembrando datos iniciales..."
  php artisan db:seed --force 2>&1 || echo "⚠️ Seed falló"
fi

# En modo local/dev, limpiar cachés para desarrollo ágil
if [ "$APP_ENV" = "local" ] || [ "$APP_ENV" = "development" ]; then
  echo "🔧 Modo desarrollo: limpiando cachés..."
  php artisan config:clear 2>/dev/null || true
  php artisan route:clear 2>/dev/null || true
  php artisan view:clear 2>/dev/null || true
else
  echo "⚡ Optimizando Laravel para producción..."
  php artisan config:cache
  php artisan route:cache
  php artisan view:cache
  php artisan event:cache
fi

# Asegurar permisos del storage
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true

echo "✅ Open.ERP listo — http://localhost:8000"
echo "📧 Admin: admin@erp.local | 🔑 Pass: password"

# Ejecutar el comando principal (php-fpm)
exec "$@"
