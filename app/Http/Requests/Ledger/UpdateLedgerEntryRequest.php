<?php

namespace App\Http\Requests\Ledger;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateLedgerEntryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'type' => ['sometimes', 'in:income,expense'],
            'category' => ['sometimes', 'string', 'max:30'],
            'amount' => ['sometimes', 'integer', 'min:1'],
            'description' => ['nullable', 'string'],
            'entry_date' => ['sometimes', 'date'],
        ];
    }
}
