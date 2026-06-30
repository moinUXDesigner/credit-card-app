<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MonthlySpendEntryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'card_id' => $this->card_id,
            'year' => $this->year,
            'month' => $this->month,
            'amount_spent' => (float) $this->amount_spent,
            'category' => $this->category,
        ];
    }
}
