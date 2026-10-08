<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KasType extends Model
{
    protected $fillable = ['name', 'description', 'amount', 'due_date', 'is_active', 'created_by'];

    protected function casts(): array
    {
        return ['due_date' => 'date', 'is_active' => 'boolean'];
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function bills(): HasMany
    {
        return $this->hasMany(Bill::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'bills', 'kas_type_id', 'user_id')
            ->withPivot(['amount', 'status', 'paid_at'])
            ->withTimestamps();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
