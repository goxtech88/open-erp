<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('integrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('driver');               // 'arca','tiendanube','mercadolibre','mercadopago','woocommerce'
            $table->string('name');                 // nombre personalizado (ej: "Mi tienda ML")
            $table->json('config')->nullable();     // credenciales encriptadas (tokens, client_id, etc.)
            $table->boolean('is_active')->default(false);
            $table->timestamp('last_sync_at')->nullable();
            $table->string('last_sync_status')->nullable(); // 'ok','error'
            $table->text('last_sync_error')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'driver', 'name']);
        });

        Schema::create('integration_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('integration_id')->constrained()->cascadeOnDelete();
            $table->string('event');               // 'sync.stock','order.imported','invoice.sent'
            $table->string('status');              // 'success','error','warning'
            $table->text('message')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('created_at');

            $table->index(['integration_id', 'created_at']);
        });

        Schema::create('factusol_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('entity');              // 'customers','articles','invoices','stock'
            $table->string('status');              // 'pending','processing','done','error'
            $table->integer('total_rows')->default(0);
            $table->integer('processed_rows')->default(0);
            $table->integer('error_rows')->default(0);
            $table->json('errors')->nullable();
            $table->string('file_path')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('factusol_imports');
        Schema::dropIfExists('integration_logs');
        Schema::dropIfExists('integrations');
    }
};
