<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StatementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'preview_id' => $this->preview_id,
            'extracted_summary' => $this->extracted_summary,
            'requires_review' => $this->analysis_status === 'pending',
            'revision' => $this->revision,
            'card_id' => $this->card_id,
            'original_filename' => $this->original_filename,
            'billing_month' => $this->billing_month,
            'billing_year' => $this->billing_year,
            'analysis_status' => $this->analysis_status,
            'analysis_message' => $this->analysis_message,
            'statement_date' => $this->statement_date?->toDateString(),
            'due_date' => $this->due_date?->toDateString(),
            'total_due' => $this->total_due !== null ? (float) $this->total_due : null,
            'minimum_due' => $this->minimum_due !== null ? (float) $this->minimum_due : null,
            'transactions' => TransactionResource::collection($this->whenLoaded('transactions')),
            'created_at' => $this->created_at,
        ];
    }
}
