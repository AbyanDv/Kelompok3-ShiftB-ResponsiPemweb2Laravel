<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Payment extends Model
{
    protected $fillable = [
        'bill_id', 'order_id', 'channel', 'amount', 'fee', 'total_amount',
        'status', 'gateway_ref', 'qr_string', 'expires_at', 'gateway_payload',
        'recorded_by', 'note', 'paid_at',
    ];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'paid_at' => 'datetime', 'gateway_payload' => 'array'];
    }

    public function bill(): BelongsTo
    {
        return $this->belongsTo(Bill::class);
    }

    public function ledgerEntry(): HasOne
    {
        return $this->hasOne(LedgerEntry::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
