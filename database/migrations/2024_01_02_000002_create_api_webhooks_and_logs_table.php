<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('api_webhooks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('url');                           // endpoint destino
            $table->string('secret', 64)->nullable();        // HMAC secret para firmar payload
            $table->json('events');                          // ["invoice.created","stock.updated"]
            $table->integer('timeout')->default(10);         // segundos de timeout HTTP
            $table->integer('retries')->default(3);
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_triggered_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('api_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('api_key_id')->nullable()->constrained()->nullOnDelete();
            $table->string('method', 10);                   // GET, POST, etc.
            $table->string('endpoint');
            $table->integer('status_code');
            $table->json('request_body')->nullable();
            $table->json('response_body')->nullable();
            $table->integer('duration_ms')->nullable();      // tiempo de respuesta
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at');

            $table->index(['api_key_id', 'created_at']);
            $table->index('created_at');                     // para limpieza automática
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_logs');
        Schema::dropIfExists('api_webhooks');
    }
};
