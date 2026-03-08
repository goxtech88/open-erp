<?php

namespace App\Jobs;

use App\Models\ApiWebhook;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Job: Envía un webhook a un endpoint externo (async via Redis Queue)
 *
 * Dispatch: SendWebhookJob::dispatch($webhook, $event, $payload)
 * Queue: 'webhooks' (baja prioridad, 3 reintentos)
 */
class SendWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $timeout = 30;

    // Backoff exponencial: 1 min, 5 min, 30 min
    public function backoff(): array
    {
        return [60, 300, 1800];
    }

    public function __construct(
        private readonly ApiWebhook $webhook,
        private readonly string     $event,
        private readonly array      $payload,
    ) {
        $this->onQueue('webhooks');
    }

    public function handle(): void
    {
        $payloadJson = json_encode([
            'event'      => $this->event,
            'data'       => $this->payload,
            'timestamp'  => now()->toIso8601String(),
            'source'     => 'Open.ERP Goxtech Labs',
        ]);

        $signature = $this->webhook->sign($payloadJson);

        try {
            $response = Http::timeout($this->webhook->timeout)
                ->withHeaders([
                    'Content-Type'        => 'application/json',
                    'X-GxERP-Event'       => $this->event,
                    'X-GxERP-Signature'   => $signature,
                    'X-GxERP-Delivery'    => uniqid('gx_', true),
                ])
                ->post($this->webhook->url, json_decode($payloadJson, true));

            $this->webhook->updateQuietly(['last_triggered_at' => now()]);

            if (! $response->successful()) {
                throw new \RuntimeException(
                    "Webhook falló: HTTP {$response->status()} → {$this->webhook->url}"
                );
            }

        } catch (\Throwable $e) {
            Log::warning("GxERP Webhook error [{$this->event}] → {$this->webhook->url}: {$e->getMessage()}");

            if ($this->attempts() >= $this->tries) {
                Log::error("GxERP Webhook abandonado tras {$this->tries} intentos: {$this->webhook->url}");
            }

            throw $e; // Laravel reencola automáticamente con backoff
        }
    }
}
