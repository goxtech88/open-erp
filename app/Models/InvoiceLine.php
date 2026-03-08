<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_id', 'article_id',
        'description', 'quantity',
        'unit_price', 'vat_rate',
        'subtotal', 'sort_order',
    ];

    protected $casts = [
        'quantity'   => 'decimal:4',
        'unit_price' => 'decimal:2',
        'vat_rate'   => 'decimal:2',
        'subtotal'   => 'decimal:2',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    /** Calcula subtotal = quantity * unit_price */
    public function calcSubtotal(): void
    {
        $this->subtotal = round($this->quantity * $this->unit_price, 2);
    }
}
