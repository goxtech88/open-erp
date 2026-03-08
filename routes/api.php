<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\StockController;
use App\Http\Controllers\Api\V1\CustomerController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Middleware\ApiKeyAuth;

/*
|--------------------------------------------------------------------------
| Open.ERP API Routes — /api/v1/
| Autenticación: Authorization: Bearer gx_<token>
|--------------------------------------------------------------------------
*/

// ── Sin autenticación ──────────────────────────────────────
Route::get('/v1/health', HealthController::class);
Route::get('/v1/docs', fn() => response()->json([
    'name'    => 'Open.ERP API v1',
    'version' => '1.0.0',
    'base'    => '/api/v1/',
    'auth'    => 'Authorization: Bearer gx_<tu_token>',
    'docs'    => 'https://erp.goxtechlabs.com.ar/api-manager',
    'endpoints' => [
        'GET  /v1/health'              => 'Estado del sistema (sin auth)',
        'GET  /v1/stock'               => 'Listado de artículos con stock [read]',
        'GET  /v1/stock/{id}'          => 'Artículo individual [read]',
        'PATCH /v1/stock/{id}'         => 'Actualizar stock/precio [write]',
        'GET  /v1/stock/kpi/valuation' => 'Valoración de inventario KPI [read]',
        'GET  /v1/customers'           => 'Listado de clientes [read]',
        'GET  /v1/customers/{id}'      => 'Cliente individual [read]',
        'POST /v1/customers'           => 'Crear cliente [write]',
        'PUT  /v1/customers/{id}'      => 'Actualizar cliente [write]',
    ],
]));

// ── Con autenticación (API Key Bearer) ────────────────────
Route::middleware([ApiKeyAuth::class . ':read'])->group(function () {

    // Stock / Artículos
    Route::get('/v1/stock',               [StockController::class, 'index']);
    Route::get('/v1/stock/kpi/valuation', [StockController::class, 'valuation']);
    Route::get('/v1/stock/{id}',          [StockController::class, 'show']);

    // Clientes
    Route::get('/v1/customers',      [CustomerController::class, 'index']);
    Route::get('/v1/customers/{id}', [CustomerController::class, 'show']);
});

Route::middleware([ApiKeyAuth::class . ':write'])->group(function () {

    // Stock — requiere permiso write
    Route::patch('/v1/stock/{id}', [StockController::class, 'update']);

    // Clientes — requiere permiso write
    Route::post('/v1/customers',       [CustomerController::class, 'store']);
    Route::put('/v1/customers/{id}',   [CustomerController::class, 'update']);
});
