<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();

            // Tipo: venta (sale) o compra (purchase)
            $table->string('type', 10);                    // sale | purchase

            // Datos del comprobante AFIP
            $table->string('invoice_code', 5);             // A | B | C | M | X
            $table->unsignedSmallInteger('pos_number')->default(1);  // Punto de venta
            $table->unsignedInteger('number')->default(0); // Nro de comprobante

            $table->date('date');

            // Tercero: cliente (ventas) o proveedor (compras)
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();

            // Estado
            $table->string('status', 20)->default('draft'); // draft | authorized | cancelled

            // Totales
            $table->decimal('net', 14, 2)->default(0);
            $table->decimal('vat', 14, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0);

            // AFIP CAE
            $table->string('cae', 20)->nullable();
            $table->date('cae_expiry')->nullable();

            $table->text('notes')->nullable();
            $table->timestamps();

            // Índices
            $table->index(['company_id', 'type', 'status']);
            $table->index(['company_id', 'date']);
            $table->unique(['company_id', 'type', 'invoice_code', 'pos_number', 'number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
