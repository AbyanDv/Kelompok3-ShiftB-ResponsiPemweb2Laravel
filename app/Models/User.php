<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['nim', 'name', 'email', 'password', 'role', 'status', 'discord_id'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isVerified(): bool
    {
        return $this->status === 'verified' || $this->isAdmin();
    }

    public function bills(): HasMany
    {
        return $this->hasMany(Bill::class);
    }

    public function kasTypes(): BelongsToMany
    {
        return $this->belongsToMany(KasType::class, 'bills', 'user_id', 'kas_type_id')
            ->withPivot(['amount', 'status', 'paid_at'])
            ->withTimestamps();
    }

    public function createdKasTypes(): HasMany
    {
        return $this->hasMany(KasType::class, 'created_by');
    }

    public function recordedPayments(): HasMany
    {
        return $this->hasMany(Payment::class, 'recorded_by');
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class, 'created_by');
    }
}
