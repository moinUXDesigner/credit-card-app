<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'card_id' => $this->card_id,
            'statement_id' => $this->statement_id,
            'transaction_date' => $this->transaction_date?->toDateString(),
            'description' => $this->description,
            'amount' => (float) $this->amount,
            'category' => $this->category,
        ];
    }
}
