<?php

namespace App\Modules\Integrations\Drivers;

use App\Modules\Integrations\Contracts\IntegrationDriver;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Driver: Mercado Libre + Mercado Pago
 * Docs: https://developers.mercadolibre.com.ar/
 *       https://www.mercadopago.com.ar/developers/
 *
 * Auth: OAuth 2.0 (refresh token automático)
 */
class MercadoLibreDriver implements IntegrationDriver
{
    private string $accessToken  = '';
    private string $refreshToken = '';
    private string $clientId     = '';
    private string $clientSecret = '';
    private string $sellerId     = '';
    private string $country      = 'MLA'; // MLA = Argentina

    public function name(): string  { return 'mercadolibre'; }
    public function label(): string { return 'Mercado Libre / Mercado Pago'; }
    public function icon(): string  { return '🛒'; }
    public function requiresPro(): bool { return true; }

    public function configure(array $config): static
    {
        $this->accessToken  = $config['access_token']  ?? '';
        $this->refreshToken = $config['refresh_token'] ?? '';
        $this->clientId     = $config['client_id']     ?? '';
        $this->clientSecret = $config['client_secret'] ?? '';
        $this->sellerId     = $config['seller_id']     ?? '';
        return $this;
    }

    public function configFields(): array
    {
        return [
            ['key' => 'client_id',     'label' => 'App ID (Client ID)',     'type' => 'text',     'required' => true],
            ['key' => 'client_secret', 'label' => 'Client Secret',          'type' => 'password', 'required' => true],
            ['key' => 'access_token',  'label' => 'Access Token',           'type' => 'password', 'required' => true,
             'hint' => 'Se renueva automáticamente. Obtenelo desde el Panel de Desarrolladores'],
            ['key' => 'refresh_token', 'label' => 'Refresh Token',          'type' => 'password', 'required' => true],
            ['key' => 'seller_id',     'label' => 'ID de Vendedor',         'type' => 'text',     'required' => true],
            ['key' => 'sync_stock',    'label' => 'Sincronizar stock',      'type' => 'checkbox', 'default'  => true],
            ['key' => 'mp_payments',   'label' => 'Importar cobros (MP)',   'type' => 'checkbox', 'default'  => true],
        ];
    }

    public function testConnection(): bool
    {
        try {
            $res = Http::get("https://api.mercadolibre.com/users/{$this->sellerId}", [
                'access_token' => $this->accessToken,
            ]);
            return $res->successful();
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Actualiza stock de artículos en las publicaciones de ML
     */
    public function syncStock(array $articles): array
    {
        $results = ['synced' => 0, 'errors' => []];

        foreach ($articles as $article) {
            try {
                // Buscar publicación por SKU
                $search = Http::withToken($this->accessToken)
                    ->get("https://api.mercadolibre.com/users/{$this->sellerId}/items/search", [
                        'seller_sku' => $article['sku'],
                    ])->json('results', []);

                foreach ($search as $itemId) {
                    Http::withToken($this->accessToken)
                        ->put("https://api.mercadolibre.com/items/{$itemId}", [
                            'available_quantity' => max(0, (int) $article['stock']),
                            'price'              => (float) $article['price'],
                        ]);
                    $results['synced']++;
                }
            } catch (\Throwable $e) {
                $results['errors'][] = "ML Error {$article['sku']}: {$e->getMessage()}";
            }
        }

        return $results;
    }

    /**
     * Importa ventas de ML al sistema
     */
    public function importOrders(): array
    {
        $orders = Http::withToken($this->accessToken)
            ->get("https://api.mercadolibre.com/orders/search", [
                'seller' => $this->sellerId,
                'order.status' => 'paid',
                'limit' => 50,
            ])->json('results', []);

        return array_map(fn($order) => [
            'external_id'   => $order['id'],
            'source'        => 'mercadolibre',
            'customer_name' => $order['buyer']['nickname'] ?? 'Comprador ML',
            'total'         => $order['total_amount'],
            'items'         => array_map(fn($item) => [
                'sku'      => $item['item']['seller_sku'] ?? $item['item']['id'],
                'quantity' => $item['quantity'],
                'price'    => $item['unit_price'],
            ], $order['order_items'] ?? []),
            'issued_at'     => substr($order['date_created'], 0, 10),
        ], $orders);
    }

    /**
     * Obtiene pagos de Mercado Pago para conciliación
     */
    public function getMercadoPagoPayments(string $dateFrom, string $dateTo): array
    {
        return Http::withToken($this->accessToken)
            ->get("https://api.mercadopago.com/v1/payments/search", [
                'range'          => 'date_created',
                'begin_date'     => $dateFrom . 'T00:00:00Z',
                'end_date'       => $dateTo   . 'T23:59:59Z',
                'status'         => 'approved',
                'limit'          => 100,
            ])->json('results', []);
    }
}
