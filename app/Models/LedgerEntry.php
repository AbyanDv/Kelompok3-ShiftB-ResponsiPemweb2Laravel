<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class LedgerEntry extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'type', 'category', 'amount', 'description', 'entry_date', 'payment_id', 'created_by',
    ];

    protected function casts(): array
    {
        return ['entry_date' => 'date'];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
