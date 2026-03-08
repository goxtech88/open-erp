<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * GET /api/v1/health
 * Health check del sistema — sin autenticación requerida.
 */
class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $checks = [];

        // ── PostgreSQL ──────────────────────────────────────
        try {
            DB::statement('SELECT 1');
            $checks['postgres'] = 'ok';
        } catch (\Throwable) {
            $checks['postgres'] = 'error';
        }

        // ── Redis ───────────────────────────────────────────
        try {
            Cache::store('redis')->put('gx_health_check', true, 10);
            $checks['redis'] = Cache::store('redis')->get('gx_health_check') ? 'ok' : 'error';
        } catch (\Throwable) {
            $checks['redis'] = 'error';
        }

        $allOk  = ! in_array('error', $checks);
        $status = $allOk ? 200 : 503;

        return response()->json([
            'status'  => $allOk ? 'healthy' : 'degraded',
            'version' => config('app.version', '1.0.0'),
            'app'     => 'Open.ERP by Goxtech Labs',
            'checks'  => $checks,
            'time'    => now()->toIso8601String(),
        ], $status);
    }
}
