<?php

namespace App\Http\Requests;

use App\Models\Card;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreCardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return self::fieldRules();
    }

    /**
     * Shared with CardImportService so bulk-imported rows are validated
     * against the exact same rules as the single-card creation endpoint.
     */
    public static function fieldRules(): array
    {
        return [
            'card_name' => ['required', 'string', 'max:100'],
            'bank_name' => ['required', 'string', 'max:100'],
            'last_four_digits' => ['required', 'digits:4'],
            'network' => ['required', Rule::in(['visa', 'mastercard', 'rupay', 'amex'])],
            'total_limit' => ['required', 'numeric', 'min:0'],
            'shared_limit_group' => ['nullable', 'string', 'max:100'],
            'current_outstanding' => ['required', 'numeric', 'min:0'],
            'statement_day' => ['required', 'integer', 'between:1,31'],
            'due_day' => ['required', 'integer', 'between:1,31'],
            'annual_fee_amount' => ['required', 'numeric', 'min:0'],
            'annual_fee_month' => ['required', 'integer', 'between:1,12'],
            'waiver_spend_required' => ['required', 'numeric', 'min:0'],
            'waiver_spend_completed' => ['nullable', 'numeric', 'min:0'],
            'card_year_start_month' => ['required', 'integer', 'between:1,12'],
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

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $group = $this->input('shared_limit_group');
            if (! $group) {
                return;
            }

            $sibling = Card::query()
                ->where('user_id', $this->user()->id)
                ->where('shared_limit_group', $group)
                ->first();

            if ($sibling && (float) $sibling->total_limit !== (float) $this->input('total_limit')) {
                $validator->errors()->add(
                    'total_limit',
                    "Cards sharing limit group \"{$group}\" must all use the same total_limit ({$sibling->total_limit})."
                );
            }
        });
    }
}
