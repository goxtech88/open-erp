# 🏭 ERP Laravel - Sistema de Facturación Electrónica AFIP

[![Docker Build](https://github.com/TU_USUARIO/erp-laravel/actions/workflows/deploy.yml/badge.svg)](https://github.com/TU_USUARIO/erp-laravel/actions)
[![PHP](https://img.shields.io/badge/PHP-8.2-blue.svg)](https://php.net)
[![Laravel](https://img.shields.io/badge/Laravel-11-red.svg)](https://laravel.com)
[![PostgreSQL](https://img.shields.io/badge/PostgreSQL-16-blue.svg)](https://postgresql.org)

Sistema ERP modular con Laravel 11, PostgreSQL y facturación electrónica AFIP para Argentina.

## ✨ Funcionalidades

- 📦 Gestión de artículos, categorías y stock
- 👥 Clientes y proveedores
- 🧾 Facturas electrónicas AFIP (WSFEV1)
- 🏢 Multi-empresa
- 📊 Dashboard analítico

## 🚀 Deploy Rápido con Docker

### Desarrollo local

```bash
git clone https://github.com/TU_USUARIO/erp-laravel.git
cd erp-laravel
cp .env.example .env
docker compose up -d
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed
```
Acceder en: http://localhost:8000

### Producción (Portainer)

Ver [DEPLOY.md](DEPLOY.md) para instrucciones completas.

## 🛠️ Stack Tecnológico

| Componente | Tecnología |
|------------|-----------|
| Backend | Laravel 11 / PHP 8.2 |
| Base de datos | PostgreSQL 16 |
| Cache/Queue | Redis 7 |
| Servidor web | Nginx 1.25 |
| AFIP SDK | afipsdk/afip-php |
| Auth | Spatie Permissions |

## 📁 Estructura del Proyecto

```
erp-laravel/
├── app/
│   ├── Models/          # Company, Customer, Supplier, Article, Invoice...
│   └── Http/Controllers/
├── database/
│   └── migrations/      # Esquema completo de BD
├── docker/              # Configuraciones Docker
│   ├── nginx/
│   └── php/
├── .github/workflows/   # CI/CD GitHub Actions
├── Dockerfile
├── docker-compose.yml       # Desarrollo
└── docker-compose.prod.yml  # Producción / Portainer
```

## 🔑 Variables de Entorno

Copiar `.env.production.example` y completar los valores. Ver sección de producción.
