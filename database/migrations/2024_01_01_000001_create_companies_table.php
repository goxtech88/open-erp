<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->string('cuit', 13)->unique();
            $table->string('fiscal_address')->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 30)->nullable();
            // AFIP WebServices
            $table->text('afip_cert')->nullable();   // contenido del .crt en base64
            $table->text('afip_key')->nullable();    // contenido del .key en base64
            $table->string('afip_mode', 20)->default('homologacion'); // homologacion | produccion
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
