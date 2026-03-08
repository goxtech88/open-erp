<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApiWebhook extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_id', 'name', 'url', 'secret',
        'events', 'timeout', 'retries', 'is_active',
    ];

    protected $casts = [
        'events'    => 'array',
        'is_active' => 'boolean',
        'last_triggered_at' => 'datetime',
    ];

    protected $hidden = ['secret'];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** Genera la firma HMAC-SHA256 para el payload */
    public function sign(string $payload): string
    {
        return 'sha256=' . hash_hmac('sha256', $payload, $this->secret ?? '');
    }

    /** ¿Este webhook escucha este evento? */
    public function listensTo(string $event): bool
    {
        return in_array($event, $this->events ?? []) || in_array('*', $this->events ?? []);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
