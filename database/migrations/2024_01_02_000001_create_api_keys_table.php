<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('api_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');                          // nombre descriptivo (ej: "App Tienda Nube")
            $table->string('token', 64)->unique();           // token hasheado SHA256
            $table->string('token_prefix', 8);              // primeros 8 chars para identificarla en UI
            $table->json('permissions')->default('["read"]'); // ["read","write","invoices","stock"]
            $table->integer('rate_limit')->default(60);      // requests por minuto
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_keys');
    }
};
