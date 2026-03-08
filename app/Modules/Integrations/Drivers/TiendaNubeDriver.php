<?php

namespace App\Modules\Integrations\Drivers;

use App\Modules\Integrations\Contracts\IntegrationDriver;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Driver: Tienda Nube
 * Docs API: https://tiendanube.github.io/api-documentation/
 *
 * Scope: Sync stock bidireccional, importar pedidos → facturas Open.ERP
 */
class TiendaNubeDriver implements IntegrationDriver
{
    private string $accessToken = '';
    private string $storeId     = '';
    private string $baseUrl     = 'https://api.tiendanube.com/v1';

    public function name(): string  { return 'tiendanube'; }
    public function label(): string { return 'Tienda Nube'; }
    public function icon(): string  { return '🛍️'; }
    public function requiresPro(): bool { return true; }

    public function configure(array $config): static
    {
        $this->accessToken = $config['access_token'] ?? '';
        $this->storeId     = $config['store_id'] ?? '';
        return $this;
    }

    public function configFields(): array
    {
        return [
            ['key' => 'access_token', 'label' => 'Access Token',     'type' => 'password', 'required' => true],
            ['key' => 'store_id',     'label' => 'ID de la Tienda',  'type' => 'text',     'required' => true],
            ['key' => 'sync_stock',   'label' => 'Sincronizar stock', 'type' => 'checkbox', 'default'  => true],
            ['key' => 'auto_invoice', 'label' => 'Facturar pedidos pagados', 'type' => 'checkbox', 'default' => false],
        ];
    }

    public function testConnection(): bool
    {
        try {
            $response = $this->client()->get("{$this->baseUrl}/{$this->storeId}");
            return $response->successful();
        } catch (\Throwable $e) {
            Log::error("TiendaNube testConnection: {$e->getMessage()}");
            return false;
        }
    }

    /**
     * Sincroniza stock de artículos Open.ERP → Tienda Nube
     * $articles: array de ['sku' => ..., 'stock' => ..., 'price' => ...]
     */
    public function syncStock(array $articles): array
    {
        $results = ['synced' => 0, 'errors' => []];

        foreach ($articles as $article) {
            try {
                // Buscar producto por SKU en Tienda Nube
                $products = $this->client()
                    ->get("{$this->baseUrl}/{$this->storeId}/products", ['sku' => $article['sku']])
                    ->json('results', []);

                if (empty($products)) {
                    $results['errors'][] = "SKU {$article['sku']} no encontrado en Tienda Nube";
                    continue;
                }

                $productId  = $products[0]['id'];
                $variantId  = $products[0]['variants'][0]['id'] ?? null;

                if ($variantId) {
                    $this->client()->put(
                        "{$this->baseUrl}/{$this->storeId}/products/{$productId}/variants/{$variantId}",
                        ['stock' => (int) $article['stock'], 'price' => (string) $article['price']]
                    );
                    $results['synced']++;
                }
            } catch (\Throwable $e) {
                $results['errors'][] = "Error {$article['sku']}: {$e->getMessage()}";
            }
        }

        return $results;
    }

    /**
     * Importa pedidos pagados de Tienda Nube → Open.ERP
     * Retorna array de pedidos mapeados al formato de Invoice
     */
    public function importOrders(): array
    {
        $orders = $this->client()
            ->get("{$this->baseUrl}/{$this->storeId}/orders", [
                'payment_status' => 'paid',
                'per_page'       => 50,
            ])
            ->json('results', []);

        return array_map(fn($order) => $this->mapOrderToInvoice($order), $orders);
    }

    private function mapOrderToInvoice(array $order): array
    {
        return [
            'external_id'    => $order['id'],
            'source'         => 'tiendanube',
            'customer_name'  => $order['customer']['name'] ?? 'Consumidor Final',
            'customer_email' => $order['customer']['email'] ?? null,
            'customer_cuit'  => $order['customer']['identification'] ?? null,
            'total'          => $order['total'],
            'items'          => array_map(fn($item) => [
                'sku'      => $item['sku'],
                'quantity' => $item['quantity'],
                'price'    => $item['price'],
            ], $order['products'] ?? []),
            'issued_at'      => $order['paid_at'] ?? now()->toDateString(),
        ];
    }

    private function client()
    {
        return Http::withHeaders([
            'Authentication' => "bearer {$this->accessToken}",
            'User-Agent'     => 'Open.ERP Goxtech Labs (erp@goxtechlabs.com.ar)',
            'Content-Type'   => 'application/json',
        ]);
    }
}
