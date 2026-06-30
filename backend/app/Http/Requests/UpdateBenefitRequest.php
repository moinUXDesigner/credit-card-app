<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBenefitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['sometimes', 'required', Rule::in(['lounge', 'cashback', 'reward_points', 'dining', 'movie', 'other'])],
            'title' => ['sometimes', 'required', 'string', 'max:150'],
            'frequency' => ['sometimes', 'required', Rule::in(['monthly', 'quarterly', 'yearly', 'one_time'])],
            'total_allowed' => ['sometimes', 'required', 'integer', 'min:1'],
            'used_count' => ['nullable', 'integer', 'min:0'],
            'expiry_date' => ['nullable', 'date'],
            'estimated_value' => ['nullable', 'numeric', 'min:0'],
            'cycle_start_date' => ['nullable', 'date'],
        ];
    }
}
