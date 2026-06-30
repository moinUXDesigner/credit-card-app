<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'card_name' => ['sometimes', 'required', 'string', 'max:100'],
            'bank_name' => ['sometimes', 'required', 'string', 'max:100'],
            'last_four_digits' => ['sometimes', 'required', 'digits:4'],
            'network' => ['sometimes', 'required', Rule::in(['visa', 'mastercard', 'rupay', 'amex'])],
            'total_limit' => ['sometimes', 'required', 'numeric', 'min:0'],
            'current_outstanding' => ['sometimes', 'required', 'numeric', 'min:0'],
            'statement_day' => ['sometimes', 'required', 'integer', 'between:1,31'],
            'due_day' => ['sometimes', 'required', 'integer', 'between:1,31'],
            'annual_fee_amount' => ['sometimes', 'required', 'numeric', 'min:0'],
            'annual_fee_month' => ['sometimes', 'required', 'integer', 'between:1,12'],
            'waiver_spend_required' => ['sometimes', 'required', 'numeric', 'min:0'],
            'waiver_spend_completed' => ['nullable', 'numeric', 'min:0'],
            'card_year_start_month' => ['sometimes', 'required', 'integer', 'between:1,12'],
            'reward_point_balance' => ['nullable', 'numeric', 'min:0'],
            'reward_point_value_estimate' => ['nullable', 'numeric', 'min:0'],
            'best_categories' => ['nullable', 'array'],
            'best_categories.*' => ['string', Rule::in(['fuel', 'grocery', 'amazon', 'dining', 'travel', 'utilities', 'online', 'other'])],
            'reward_rate_general' => ['nullable', 'numeric', 'min:0'],
            'cashback_cap_amount' => ['nullable', 'numeric', 'min:0'],
            'lounge_access' => ['boolean'],
            'is_active' => ['boolean'],
        ];
    }
}
