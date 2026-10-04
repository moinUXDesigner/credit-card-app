<?php

namespace App\Http\Requests;

use App\Models\Card;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStatementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        if ($this->hasFile('file') && ! $this->has('preview_id')) {
            return [
                'file' => ['required', 'file', 'mimes:pdf', 'max:15360'],
                'billing_month' => ['required', 'integer', 'between:1,12'],
                'billing_year' => ['required', 'integer', 'between:2000,2100'],
            ];
        }

        return [
            'file' => 'nullable|file|mimes:pdf|max:15360',
            'preview_id' => 'required|uuid', 'idempotency_key' => 'required|uuid',
            'billing_month' => 'required|integer|between:1,12', 'billing_year' => 'required|integer|between:2000,2100',
            'revision' => 'required|integer|min:1', 'acknowledge_identity' => 'required|boolean', 'save_pdf_only' => 'required|boolean',
            'summary' => 'present|array:statement_date,due_date,total_due,minimum_due,credit_limit,reward_point_balance',
            'summary.statement_date' => 'nullable|date_format:Y-m-d', 'summary.due_date' => 'nullable|date_format:Y-m-d',
            'summary.total_due' => 'nullable|numeric|min:0|max:9999999999.99', 'summary.minimum_due' => 'nullable|numeric|min:0|max:9999999999.99',
            'summary.credit_limit' => 'nullable|numeric|min:0|max:9999999999.99', 'summary.reward_point_balance' => 'nullable|numeric|min:0|max:9999999999.99',
            'apply_summary' => 'present|array:current_outstanding,reward_point_balance,total_limit',
            'apply_summary.*' => 'boolean', 'rows' => 'present|array|max:200',
            'rows.*' => 'array:transaction_date,description,amount,direction,category,duplicate_action',
            'rows.*.transaction_date' => 'required|date_format:Y-m-d', 'rows.*.description' => 'required|string|max:255',
            'rows.*.amount' => 'required|numeric|min:0.01|max:9999999999.99', 'rows.*.direction' => 'required|in:purchase,credit',
            'rows.*.category' => ['required', Rule::in(Card::CATEGORIES)], 'rows.*.duplicate_action' => 'nullable|in:skip,keep',
        ];
    }
}
