<?php

namespace App\Modules\Integrations\Contracts;

/**
 * Interface base para todos los drivers de integración Open.ERP
 * Patrón Strategy: cada integración implementa esta interface
 */
interface IntegrationDriver
{
    /** Nombre único del driver (ej: 'tiendanube', 'mercadolibre') */
    public function name(): string;

    /** Nombre legible para mostrar en UI */
    public function label(): string;

    /** Ícono SVG o emoji para la card de UI */
    public function icon(): string;

    /** Configura el driver con las credenciales de la empresa */
    public function configure(array $config): static;

    /** Verifica que las credenciales son válidas (test de conexión) */
    public function testConnection(): bool;

    /** Sincroniza stock desde Open.ERP → plataforma externa */
    public function syncStock(array $articles): array;

    /** Sincroniza pedidos desde plataforma externa → Open.ERP */
    public function importOrders(): array;

    /** Retorna los campos de configuración requeridos (para el form UI) */
    public function configFields(): array;

    /** ¿Es edición CE o PRO? */
    public function requiresPro(): bool;
}
