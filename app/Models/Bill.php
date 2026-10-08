<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Bill extends Model
{
    protected $fillable = ['user_id', 'kas_type_id', 'amount', 'status', 'paid_at'];

    protected function casts(): array
    {
        return ['paid_at' => 'datetime'];
    }

    public function scopeUnpaid($query)
    {
        return $query->where('status', 'unpaid');
    }

    /** Ringkasan dipakai beranda + kas web. Satu tempat, cegah drift. */
    public static function summary(): array
    {
        return [
            'collected' => (int) static::where('status', 'paid')->sum('amount'),
            'unpaid' => static::where('status', 'unpaid')->count(),
            'paid' => static::where('status', 'paid')->count(),
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function kasType(): BelongsTo
    {
        return $this->belongsTo(KasType::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
