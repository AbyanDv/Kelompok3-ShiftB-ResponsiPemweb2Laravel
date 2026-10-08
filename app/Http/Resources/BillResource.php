<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BillResource extends JsonResource
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
            'amount' => $this->amount,
            'status' => $this->status,
            'paid_at' => $this->paid_at,
            'user' => $this->whenLoaded('user', fn () => [
                'id' => $this->user->id,
                'nim' => $this->user->nim,
                'name' => $this->user->name,
            ]),
            'kas_type' => $this->whenLoaded('kasType', fn () => [
                'id' => $this->kasType->id,
                'name' => $this->kasType->name,
                'amount' => $this->kasType->amount,
                'due_date' => $this->kasType->due_date,
            ]),
            'created_at' => $this->created_at,
        ];
    }
}
