<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'bill_id' => $this->bill_id,
            'order_id' => $this->order_id,
            'channel' => $this->channel,
            'amount' => $this->amount,
            'fee' => $this->fee,
            'total_amount' => $this->total_amount,
            'status' => $this->status,
            'qr_string' => $this->qr_string,
            'expires_at' => $this->expires_at,
            'paid_at' => $this->paid_at,
            'note' => $this->note,
            'created_at' => $this->created_at,
        ];
    }
}
