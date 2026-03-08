<?php

namespace App\Modules\Integrations\Drivers;

use App\Modules\Integrations\Contracts\IntegrationDriver;
use Illuminate\Support\Facades\Http;

/**
 * Driver: WooCommerce / WordPress
 * Usa la REST API de WooCommerce (Consumer Key + Secret)
 * Docs: https://woocommerce.github.io/woocommerce-rest-api-docs/
 *
 * También importa clientes desde formularios WordPress via webhook.
 */
class WooCommerceDriver implements IntegrationDriver
{
    private string $siteUrl       = '';
    private string $consumerKey   = '';
    private string $consumerSecret= '';

    public function name(): string  { return 'woocommerce'; }
    public function label(): string { return 'WooCommerce / WordPress'; }
    public function icon(): string  { return '🌐'; }
    public function requiresPro(): bool { return true; }

    public function configure(array $config): static
    {
        $this->siteUrl        = rtrim($config['site_url'] ?? '', '/');
        $this->consumerKey    = $config['consumer_key']    ?? '';
        $this->consumerSecret = $config['consumer_secret'] ?? '';
        return $this;
    }

    public function configFields(): array
    {
        return [
            ['key' => 'site_url',        'label' => 'URL del sitio WordPress',   'type' => 'text',     'required' => true,
             'hint' => 'Ej: https://misitio.com.ar'],
            ['key' => 'consumer_key',    'label' => 'Consumer Key (WC)',          'type' => 'text',     'required' => true],
            ['key' => 'consumer_secret', 'label' => 'Consumer Secret (WC)',       'type' => 'password', 'required' => true,
             'hint' => 'Generalo en WooCommerce → Ajustes → REST API'],
            ['key' => 'sync_stock',      'label' => 'Sincronizar stock',          'type' => 'checkbox', 'default' => true],
            ['key' => 'auto_invoice',    'label' => 'Facturar pedidos completados','type' => 'checkbox', 'default' => false],
            ['key' => 'import_customers','label' => 'Importar clientes de WP',   'type' => 'checkbox', 'default' => true],
        ];
    }

    public function testConnection(): bool
    {
        try {
            $res = Http::withBasicAuth($this->consumerKey, $this->consumerSecret)
                ->get("{$this->siteUrl}/wp-json/wc/v3/system_status");
            return $res->successful();
        } catch (\Throwable) {
            return false;
        }
    }

    public function syncStock(array $articles): array
    {
        $results = ['synced' => 0, 'errors' => []];

        foreach ($articles as $article) {
            try {
                // Buscar producto por SKU en WooCommerce
                $products = $this->wc('GET', 'products', ['sku' => $article['sku']])->json();

                if (empty($products)) {
                    $results['errors'][] = "SKU {$article['sku']} no encontrado en WooCommerce";
                    continue;
                }

                $productId = $products[0]['id'];

                $this->wc('PUT', "products/{$productId}", [
                    'stock_quantity'  => max(0, (int) $article['stock']),
                    'manage_stock'    => true,
                    'regular_price'   => (string) $article['price'],
                ]);

                $results['synced']++;
            } catch (\Throwable $e) {
                $results['errors'][] = "WC Error {$article['sku']}: {$e->getMessage()}";
            }
        }

        return $results;
    }

    public function importOrders(): array
    {
        $orders = $this->wc('GET', 'orders', ['status' => 'completed', 'per_page' => 50])->json();

        return array_map(fn($order) => [
            'external_id'    => $order['id'],
            'source'         => 'woocommerce',
            'customer_name'  => trim("{$order['billing']['first_name']} {$order['billing']['last_name']}"),
            'customer_email' => $order['billing']['email'],
            'customer_cuit'  => null,
            'total'          => $order['total'],
            'items'          => array_map(fn($item) => [
                'sku'      => $item['sku'],
                'quantity' => $item['quantity'],
                'price'    => $item['price'],
            ], $order['line_items'] ?? []),
            'issued_at'      => substr($order['date_completed'] ?? $order['date_created'], 0, 10),
        ], $orders);
    }

    /**
     * Importa clientes de WordPress (usuarios con rol 'customer')
     */
    public function importCustomers(): array
    {
        return $this->wc('GET', 'customers', ['per_page' => 100, 'role' => 'customer'])
            ->json();
    }

    private function wc(string $method, string $endpoint, array $data = [])
    {
        return Http::withBasicAuth($this->consumerKey, $this->consumerSecret)
            ->{strtolower($method)}(
                "{$this->siteUrl}/wp-json/wc/v3/{$endpoint}",
                $data
            );
    }
}
