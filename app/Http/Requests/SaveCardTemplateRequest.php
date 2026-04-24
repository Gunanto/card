<?php

namespace App\Http\Requests;

use App\Models\CardTemplate;
use App\Support\DefaultCardTemplateData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use JsonException;

class SaveCardTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        /** @var CardTemplate|null $cardTemplate */
        $cardTemplate = $this->route('cardTemplate');

        return [
            'institution_id' => ['nullable', 'integer', 'exists:institutions,id'],
            'card_type_id' => ['required', 'integer', 'exists:card_types,id'],
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('card_templates', 'name')
                    ->where(fn ($query) => $query->where('institution_id', $this->input('institution_id')))
                    ->ignore($cardTemplate),
            ],
            'width_mm' => ['required', 'numeric', 'min:20'],
            'height_mm' => ['required', 'numeric', 'min:20'],
            'background_front_media_id' => ['nullable', 'integer', 'exists:media_assets,id'],
            'background_back_media_id' => ['nullable', 'integer', 'exists:media_assets,id'],
            'config_json_text' => ['required', 'string'],
            'print_layout_json_text' => ['nullable', 'string'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            try {
                $config = $this->configPayload();
            } catch (JsonException) {
                $validator->errors()->add('config_json_text', 'Config JSON tidak valid.');
                $config = null;
            }

            try {
                $this->printLayoutPayload();
            } catch (JsonException) {
                $validator->errors()->add('print_layout_json_text', 'Print layout JSON tidak valid.');
            }

            if (is_array($config) && empty($config['elements'])) {
                $validator->errors()->add('config_json_text', 'Config JSON harus memiliki elemen template.');
            }
        });
    }

    public function configPayload(): array
    {
        return json_decode($this->string('config_json_text')->toString(), true, 512, JSON_THROW_ON_ERROR);
    }

    public function printLayoutPayload(): array
    {
        $raw = trim($this->string('print_layout_json_text')->toString());

        if ($raw === '') {
            return DefaultCardTemplateData::printLayout();
        }

        return json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    }
}
