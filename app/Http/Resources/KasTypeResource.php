<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class KasTypeResource extends JsonResource
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
            'name' => $this->name,
            'description' => $this->description,
            'amount' => $this->amount,
            'due_date' => $this->due_date,
            'is_active' => $this->is_active,
            'progress' => [
                'total_bills' => $this->bills_count ?? 0,
                'paid_bills' => $this->paid_bills_count ?? 0,
                'collected' => $this->collected_sum ?? 0,
            ],
            'created_at' => $this->created_at,
        ];
    }
}
