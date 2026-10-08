<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
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
            'nim' => $this->nim,
            'name' => $this->name,
            'role' => $this->role,
            'status' => $this->status,
            'discord_id' => $this->discord_id,
            'created_at' => $this->created_at,
        ];
    }
}
