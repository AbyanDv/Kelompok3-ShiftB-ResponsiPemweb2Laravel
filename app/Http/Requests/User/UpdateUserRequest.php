<?php

namespace App\Http\Requests\User;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
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
            'nim' => ['sometimes', 'string', 'max:30', Rule::unique('users', 'nim')->ignore($this->route('user'))],
            'name' => ['sometimes', 'string', 'max:255'],
            'password' => ['sometimes', 'string', 'min:8', 'confirmed'],
            'role' => ['sometimes', 'in:member,admin'],
            'status' => ['sometimes', 'in:pending,verified,rejected'],
            'discord_id' => ['nullable', 'string', 'max:50'],
        ];
    }
}
