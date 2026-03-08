<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    use HasFactory;

    // Tipos de comprobante
    const TYPE_SALE     = 'sale';
    const TYPE_PURCHASE = 'purchase';

    // Estados
    const STATUS_DRAFT      = 'draft';
    const STATUS_AUTHORIZED = 'authorized';
    const STATUS_CANCELLED  = 'cancelled';

    // Alícuotas IVA AFIP
    const VAT_RATES = [0, 10.5, 21, 27];

    protected $fillable = [
        'company_id', 'type',
        'invoice_code', 'pos_number', 'number',
        'date',
        'customer_id', 'supplier_id',
        'status',
        'net', 'vat', 'total',
        'cae', 'cae_expiry',
        'notes',
    ];

    protected $casts = [
        'date'       => 'date',
        'cae_expiry' => 'date',
        'net'        => 'decimal:2',
        'vat'        => 'decimal:2',
        'total'      => 'decimal:2',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class)->orderBy('sort_order');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /** Número de factura con formato: B-0001-00000001 */
    public function getFormattedNumberAttribute(): string
    {
        return sprintf(
            '%s-%04d-%08d',
            $this->invoice_code,
            $this->pos_number,
            $this->number
        );
    }

    public function isSale(): bool
    {
        return $this->type === self::TYPE_SALE;
    }

    public function isPurchase(): bool
    {
        return $this->type === self::TYPE_PURCHASE;
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isAuthorized(): bool
    {
        return $this->status === self::STATUS_AUTHORIZED;
    }

    /** Recalcula net, vat y total a partir de las líneas. */
    public function recalcTotals(): void
    {
        $this->net   = $this->lines->sum('subtotal');
        $this->vat   = $this->lines->sum(fn ($l) => $l->subtotal * ($l->vat_rate / 100));
        $this->total = $this->net + $this->vat;
    }
}
