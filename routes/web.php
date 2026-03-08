<?php

use App\Http\Controllers\CustomerController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\ArticleController;
use App\Http\Controllers\InvoiceController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| ERP Routes — Todos los grupos requieren autenticación.
|--------------------------------------------------------------------------
*/

Route::get('/', fn () => redirect()->route('dashboard'));

/* ── Auth (generadas por Breeze) ─────────────────────────────────────── */
require __DIR__ . '/auth.php';

/* ── Rutas protegidas ─────────────────────────────────────────────────── */
Route::middleware(['auth', 'verified'])->group(function () {

    // Dashboard
    Route::get('/dashboard', fn () => view('dashboard.index'))->name('dashboard');

    // Clientes
    Route::resource('customers', CustomerController::class)
        ->except(['show']);

    // Proveedores
    Route::resource('suppliers', SupplierController::class)
        ->except(['show']);

    // Artículos
    Route::resource('articles', ArticleController::class)
        ->except(['show']);

    // Ventas (type=sale)
    Route::prefix('sales')->name('invoices.')->group(function () {
        Route::get('/',                       [InvoiceController::class, 'index'])->defaults('type', 'sale')->name('sales');
        Route::get('/create',                 [InvoiceController::class, 'create'])->defaults('type', 'sale')->name('sales.create');
        Route::post('/',                      [InvoiceController::class, 'store'])->defaults('type', 'sale')->name('sales.store');
        Route::get('/{invoice}/edit',         [InvoiceController::class, 'edit'])->defaults('type', 'sale')->name('sales.edit');
        Route::put('/{invoice}',              [InvoiceController::class, 'update'])->defaults('type', 'sale')->name('sales.update');
        Route::delete('/{invoice}',           [InvoiceController::class, 'destroy'])->defaults('type', 'sale')->name('sales.destroy');
        Route::post('/{invoice}/authorize',   [InvoiceController::class, 'authorize'])->defaults('type', 'sale')->name('sales.authorize');
    });

    // Compras (type=purchase)
    Route::prefix('purchases')->name('invoices.')->group(function () {
        Route::get('/',                       [InvoiceController::class, 'index'])->defaults('type', 'purchase')->name('purchases');
        Route::get('/create',                 [InvoiceController::class, 'create'])->defaults('type', 'purchase')->name('purchases.create');
        Route::post('/',                      [InvoiceController::class, 'store'])->defaults('type', 'purchase')->name('purchases.store');
        Route::get('/{invoice}/edit',         [InvoiceController::class, 'edit'])->defaults('type', 'purchase')->name('purchases.edit');
        Route::put('/{invoice}',              [InvoiceController::class, 'update'])->defaults('type', 'purchase')->name('purchases.update');
        Route::delete('/{invoice}',           [InvoiceController::class, 'destroy'])->defaults('type', 'purchase')->name('purchases.destroy');
        Route::post('/{invoice}/authorize',   [InvoiceController::class, 'authorize'])->defaults('type', 'purchase')->name('purchases.authorize');
    });
});
