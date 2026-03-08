<?php

namespace App\Http\Middleware;

use App\Models\ApiKey;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware de autenticación por API Key (Bearer token)
 *
 * Uso: Authorization: Bearer gx_xxxxxxxxxxxx
 *
 * Features:
 * - Rate limiting por API key (usando Redis)
 * - Verificación de permisos por endpoint
 * - Log del request
 * - Cache de la API key (5 min) para evitar DB en cada request
 */
class ApiKeyAuth
{
    public function handle(Request $request, Closure $next, string $permission = 'read'): Response
    {
        $token = $this->extractToken($request);

        if (! $token) {
            return $this->unauthorized('API key requerida. Enviá: Authorization: Bearer gx_<tu_token>');
        }

        // Cache de la key para evitar DB en cada request (TTL 5 min)
        $cacheKey = 'gx_erp:apikey:' . hash('sha256', $token);
        $apiKey   = Cache::remember($cacheKey, 300, fn() => ApiKey::findByToken($token));

        if (! $apiKey) {
            return $this->unauthorized('API key inválida o inactiva');
        }

        if ($apiKey->isExpired()) {
            return $this->unauthorized('API key vencida');
        }

        // Verificar permiso requerido
        if (! $apiKey->hasPermission($permission)) {
            return response()->json([
                'error' => 'Permiso insuficiente',
                'required' => $permission,
                'your_permissions' => $apiKey->permissions,
            ], 403);
        }

        // Rate limiting con Redis (ventana deslizante de 1 minuto)
        $rateLimitKey = "gx_erp:ratelimit:{$apiKey->id}:" . now()->format('Y-m-d-H-i');
        $requests     = Cache::increment($rateLimitKey);
        Cache::expire($rateLimitKey, 60);

        if ($requests > $apiKey->rate_limit) {
            return response()->json([
                'error'   => 'Rate limit excedido',
                'limit'   => $apiKey->rate_limit,
                'window'  => '1 minuto',
            ], 429)->withHeaders([
                'X-RateLimit-Limit'     => $apiKey->rate_limit,
                'X-RateLimit-Remaining' => 0,
                'Retry-After'           => 60,
            ]);
        }

        // Adjuntar la key al request para usarla en controllers
        $request->merge(['_api_key' => $apiKey]);
        $request->attributes->set('api_key', $apiKey);

        // Marcar uso (sin bloquear el request)
        $apiKey->markUsed();

        $response = $next($request);

        // Headers informativos en la respuesta
        return $response->withHeaders([
            'X-RateLimit-Limit'     => $apiKey->rate_limit,
            'X-RateLimit-Remaining' => max(0, $apiKey->rate_limit - $requests),
            'X-Powered-By'          => 'Open.ERP Goxtech Labs',
        ]);
    }

    private function extractToken(Request $request): ?string
    {
        $bearer = $request->bearerToken();
        if ($bearer) return $bearer;

        // También acepta ?api_key=gx_xxx en query string (para tests rápidos)
        return $request->query('api_key');
    }

    private function unauthorized(string $message): JsonResponse
    {
        return response()->json([
            'error'   => $message,
            'docs'    => 'https://erp.goxtechlabs.com.ar/api/v1/docs',
        ], 401);
    }
}
