<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use JsonException;

class StoreGenerateBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'template_id' => ['required', 'integer', 'exists:card_templates,id'],
            'student_ids' => ['required', 'array', 'min:1'],
            'student_ids.*' => ['integer', 'distinct', 'exists:students,id'],
            'options_json_text' => ['nullable', 'string'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            try {
                $this->optionsPayload();
            } catch (JsonException) {
                $validator->errors()->add('options_json_text', 'Options JSON tidak valid.');
            }
        });
    }

    public function optionsPayload(): array
    {
        $raw = trim($this->string('options_json_text')->toString());

        if ($raw === '') {
            return [];
        }

        return json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    }
}
