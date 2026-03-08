<?php

namespace App\Services\Cache;

use Illuminate\Support\Facades\Cache;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * GxCacheService - Servicio de cache para Open.ERP by Goxtech Labs
 *
 * Resuelve el problema de queries lentas en:
 *  - Listados de stock con 1000+ artículos
 *  - Valoración de inventario (KPI)
 *  - Reportes de movimientos
 *  - Rankings de clientes/proveedores
 *
 * Estrategia:
 *  - Cache en Redis con TTL configurable por tipo de dato
 *  - Invalidación automática al modificar artículos/stock
 *  - Prefijo 'gx_erp:' para todos los keys
 */
class GxCacheService
{
    // TTL en segundos por tipo de consulta
    const TTL_STOCK_LIST      = 300;   //  5 min  - listado de artículos
    const TTL_STOCK_KPI       = 900;   // 15 min  - valoración de inventario
    const TTL_CUSTOMERS       = 600;   // 10 min  - directorio clientes
    const TTL_INVOICE_SUMMARY = 180;   //  3 min  - resumen de facturas
    const TTL_DASHBOARD       = 120;   //  2 min  - kpis del dashboard
    const TTL_AFIP_CONFIG     = 3600;  // 60 min  - configuración AFIP

    /**
     * Obtiene lista de artículos con stock, usando cache Redis.
     * Evita N+1 queries cuando hay 1000+ artículos.
     */
    public static function stockList(int $companyId, array $filters = [], int $page = 1): mixed
    {
        $key = self::key("stock:list:{$companyId}:" . md5(serialize($filters)) . ":p{$page}");

        return Cache::remember($key, self::TTL_STOCK_LIST, function () use ($companyId, $filters) {
            return \App\Models\Article::query()
                ->where('company_id', $companyId)
                ->when($filters['category_id'] ?? null, fn(Builder $q, $v) => $q->where('category_id', $v))
                ->when($filters['search'] ?? null, fn(Builder $q, $v) => $q->where('name', 'ilike', "%{$v}%"))
                ->when($filters['low_stock'] ?? false, fn(Builder $q) => $q->whereColumn('stock', '<', 'min_stock'))
                ->with(['category']) // evita N+1
                ->orderBy('name')
                ->paginate(50);
        });
    }

    /**
     * KPI: Valoración de inventario (costo total del stock).
     * Query pesada - cache 15 min.
     * Ejemplo: 5000 artículos × precio_costo = valor_inventario_total
     */
    public static function inventoryValuation(int $companyId): array
    {
        $key = self::key("kpi:inventory_valuation:{$companyId}");

        return Cache::remember($key, self::TTL_STOCK_KPI, function () use ($companyId) {
            $result = \App\Models\Article::query()
                ->where('company_id', $companyId)
                ->selectRaw('
                    COUNT(*) as total_articles,
                    SUM(stock) as total_units,
                    SUM(stock * cost_price) as total_value,
                    SUM(stock * sale_price) as potential_revenue,
                    COUNT(CASE WHEN stock <= min_stock THEN 1 END) as low_stock_count,
                    COUNT(CASE WHEN stock = 0 THEN 1 END) as out_of_stock_count
                ')
                ->first();

            return [
                'total_articles'    => (int) ($result->total_articles ?? 0),
                'total_units'       => (float) ($result->total_units ?? 0),
                'total_value'       => (float) ($result->total_value ?? 0),
                'potential_revenue' => (float) ($result->potential_revenue ?? 0),
                'low_stock_count'   => (int) ($result->low_stock_count ?? 0),
                'out_of_stock_count'=> (int) ($result->out_of_stock_count ?? 0),
                'margin_value'      => (float) (($result->potential_revenue ?? 0) - ($result->total_value ?? 0)),
                'cached_at'         => now()->toIso8601String(),
            ];
        });
    }

    /**
     * KPI: Resumen del dashboard (ventas, cobros, artículos críticos).
     */
    public static function dashboardKpis(int $companyId): array
    {
        $key = self::key("kpi:dashboard:{$companyId}");

        return Cache::remember($key, self::TTL_DASHBOARD, function () use ($companyId) {
            $today    = now()->startOfDay();
            $month    = now()->startOfMonth();

            // Total ventas del mes
            $salesMonth = \App\Models\Invoice::query()
                ->where('company_id', $companyId)
                ->where('type', 'sale')
                ->where('issued_at', '>=', $month)
                ->sum('total');

            // Total ventas hoy
            $salesToday = \App\Models\Invoice::query()
                ->where('company_id', $companyId)
                ->where('type', 'sale')
                ->where('issued_at', '>=', $today)
                ->sum('total');

            // Artículos con stock crítico
            $criticalStock = \App\Models\Article::query()
                ->where('company_id', $companyId)
                ->whereColumn('stock', '<', 'min_stock')
                ->count();

            return compact('salesMonth', 'salesToday', 'criticalStock');
        });
    }

    /**
     * Invalida el cache de stock de una empresa.
     * Llamar después de crear/editar/eliminar artículos o movimientos.
     */
    public static function invalidateStock(int $companyId): void
    {
        // Redis permite invalidar por patrón con tags o flush selectivo
        // Laravel cache tags (requiere driver redis o memcached)
        Cache::tags(["gx_stock_{$companyId}"])->flush();

        // Fallback: invalidar keys específicos conocidos
        Cache::forget(self::key("kpi:inventory_valuation:{$companyId}"));
        Cache::forget(self::key("kpi:dashboard:{$companyId}"));
    }

    /**
     * Invalida TODO el cache de una empresa (cuando hay cambios masivos).
     */
    public static function invalidateAll(int $companyId): void
    {
        $prefixes = ['stock:list', 'kpi:inventory_valuation', 'kpi:dashboard', 'customers'];
        foreach ($prefixes as $prefix) {
            Cache::forget(self::key("{$prefix}:{$companyId}"));
        }
    }

    /**
     * Genera el key de cache con el prefijo gx_erp estándar.
     */
    private static function key(string $suffix): string
    {
        return "gx_erp:{$suffix}";
    }
}
