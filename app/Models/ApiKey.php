<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ApiKey extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_id', 'name', 'token', 'token_prefix',
        'permissions', 'rate_limit', 'expires_at', 'is_active',
    ];

    protected $casts = [
        'permissions'  => 'array',
        'is_active'    => 'boolean',
        'expires_at'   => 'datetime',
        'last_used_at' => 'datetime',
    ];

    protected $hidden = ['token'];

    // ── Relaciones ────────────────────────────────────────
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    // ── Métodos estáticos ─────────────────────────────────
    /**
     * Genera un nuevo token seguro y lo devuelve en claro (solo una vez).
     * Guarda el hash SHA-256 en la base de datos.
     */
    public static function generateToken(): array
    {
        $plaintext = 'gx_' . Str::random(40);
        $hashed    = hash('sha256', $plaintext);
        $prefix    = substr($plaintext, 0, 8);

        return compact('plaintext', 'hashed', 'prefix');
    }

    /**
     * Busca una ApiKey por el token en texto plano (hace el hash internamente).
     */
    public static function findByToken(string $plaintext): ?self
    {
        return static::where('token', hash('sha256', $plaintext))
            ->where('is_active', true)
            ->first();
    }

    // ── Helpers ───────────────────────────────────────────
    public function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->permissions ?? [])
            || in_array('*', $this->permissions ?? []);
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function markUsed(): void
    {
        $this->updateQuietly(['last_used_at' => now()]);
    }

    // ── Scopes ────────────────────────────────────────────
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where(fn($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }
}
