<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveUserRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'institution_id' => $this->input('institution_id') === '' ? null : $this->input('institution_id'),
            'card_generation_limit' => $this->input('card_generation_limit') === '' ? null : $this->input('card_generation_limit'),
        ]);
    }

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        /** @var User|null $targetUser */
        $targetUser = $this->route('user');
        $isCreate = $targetUser === null;

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($targetUser)],
            'password' => [$isCreate ? 'required' : 'nullable', 'string', 'min:8', 'max:255'],
            'role' => ['required', Rule::in(['admin', 'guru'])],
            'is_active' => ['required', 'boolean'],
            'institution_id' => ['nullable', 'integer', 'exists:institutions,id'],
            'card_generation_limit' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
