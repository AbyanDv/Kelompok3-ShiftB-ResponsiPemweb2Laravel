<?php

namespace App\Http\Requests\Ledger;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreLedgerEntryRequest extends FormRequest
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
            'type' => ['required', 'in:income,expense'],
            'category' => ['required', 'string', 'max:30'],
            'amount' => ['required', 'integer', 'min:1'],
            'description' => ['nullable', 'string'],
            'entry_date' => ['required', 'date'],
        ];
    }
}
