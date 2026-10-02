<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BenefitResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'revision' => $this->revision,
            'card_id' => $this->card_id,
            'type' => $this->type,
            'title' => $this->title,
            'frequency' => $this->frequency,
            'total_allowed' => $this->total_allowed,
            'used_count' => $this->used_count,
            'remaining' => max(0, $this->total_allowed - $this->used_count),
            'expiry_date' => $this->expiry_date?->toDateString(),
            'estimated_value' => $this->estimated_value !== null ? (float) $this->estimated_value : null,
            'cycle_start_date' => $this->cycle_start_date?->toDateString(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
