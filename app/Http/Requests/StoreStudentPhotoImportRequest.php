<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreStudentPhotoImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'institution_id' => ['nullable', 'integer', 'exists:institutions,id'],
            'file' => ['required', 'file', 'mimes:zip', 'max:102400'],
        ];
    }
}
