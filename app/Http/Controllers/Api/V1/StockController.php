<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Services\Cache\GxCacheService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API v1 — Stock y Artículos
 * Ruta base: /api/v1/stock
 */
class StockController extends Controller
{
    /**
     * GET /api/v1/stock
     * Lista artículos con stock. Cache Redis 5 min.
     */
    public function index(Request $request): JsonResponse
    {
        $companyId = $request->attributes->get('api_key')->company_id;

        $filters = $request->only(['search', 'category_id', 'low_stock']);
        $page    = (int) $request->get('page', 1);

        // GxCacheService cachea esta query pesada en Redis (TTL 5 min)
        $stock = GxCacheService::stockList($companyId, $filters, $page);

        return response()->json([
            'data'  => $stock->items(),
            'meta'  => [
                'total'        => $stock->total(),
                'per_page'     => $stock->perPage(),
                'current_page' => $stock->currentPage(),
                'last_page'    => $stock->lastPage(),
            ],
            'cache' => 'redis-5min',
        ]);
    }

    /**
     * GET /api/v1/stock/{id}
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $companyId = $request->attributes->get('api_key')->company_id;

        $article = Article::where('company_id', $companyId)->findOrFail($id);

        return response()->json(['data' => $article]);
    }

    /**
     * PATCH /api/v1/stock/{id}
     * Actualiza el stock de un artículo e invalida el cache.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'stock'    => 'sometimes|numeric|min:0',
            'price'    => 'sometimes|numeric|min:0',
            'is_active'=> 'sometimes|boolean',
        ]);

        $companyId = $request->attributes->get('api_key')->company_id;
        $article   = Article::where('company_id', $companyId)->findOrFail($id);

        $article->update($request->only(['stock', 'price', 'cost_price', 'is_active']));

        // Invalidar cache de stock para que la próxima consulta sea fresca
        GxCacheService::invalidateStock($companyId);

        return response()->json(['data' => $article->fresh()]);
    }

    /**
     * GET /api/v1/stock/kpi/valuation
     * KPI de valoración de inventario. Cache Redis 15 min.
     */
    public function valuation(Request $request): JsonResponse
    {
        $companyId = $request->attributes->get('api_key')->company_id;

        $valuation = GxCacheService::inventoryValuation($companyId);

        return response()->json([
            'data'  => $valuation,
            'cache' => 'redis-15min',
        ]);
    }
}
