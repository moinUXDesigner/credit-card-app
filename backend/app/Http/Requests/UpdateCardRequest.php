<?php

namespace App\Http\Requests;

use App\Models\Card;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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
            'shared_limit_group' => ['nullable', 'string', 'max:100'],
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
            'best_categories.*' => ['string', Rule::in(Card::CATEGORIES)],
            'reward_rate_general' => ['nullable', 'numeric', 'min:0'],
            'cashback_cap_amount' => ['nullable', 'numeric', 'min:0'],
            'forex_markup_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'fuel_surcharge_waiver_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'insurance_cover_amount' => ['nullable', 'numeric', 'min:0'],
            'lounge_access' => ['boolean'],
            'is_active' => ['boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            /** @var Card $card */
            $card = $this->route('card');
            $group = $this->input('shared_limit_group', $card->shared_limit_group);
            if (! $group) {
                return;
            }

            $effectiveLimit = (float) $this->input('total_limit', $card->total_limit);

            $sibling = Card::query()
                ->where('user_id', $card->user_id)
                ->where('shared_limit_group', $group)
                ->where('id', '!=', $card->id)
                ->first();

            if ($sibling && (float) $sibling->total_limit !== $effectiveLimit) {
                $validator->errors()->add(
                    'total_limit',
                    "Cards sharing limit group \"{$group}\" must all use the same total_limit ({$sibling->total_limit})."
                );
            }
        });
    }
}
