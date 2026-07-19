<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreStatementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:pdf', 'max:15360'],
            'billing_month' => ['required', 'integer', 'between:1,12'],
            'billing_year' => ['required', 'integer', 'between:2000,2100'],
        ];
    }
}
