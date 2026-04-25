<?php

namespace App\Http\Requests;

use App\Models\MediaAsset;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMediaAssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'category' => ['required', Rule::in(MediaAsset::USER_UPLOAD_CATEGORIES)],
            'owner_type' => ['required', Rule::in(MediaAsset::OWNER_TYPE_OPTIONS)],
            'owner_id' => ['required', 'integer', 'min:1'],
            'file' => $this->fileRules(),
        ];
    }

    protected function fileRules(): array
    {
        return match ($this->input('category')) {
            'institution_stamp' => ['required', 'image', 'mimes:png', 'max:4096', 'dimensions:min_width=200,min_height=200'],
            'institution_signature' => ['required', 'image', 'mimes:png', 'max:2048', 'dimensions:min_width=200,min_height=80'],
            'student_photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120', 'dimensions:min_width=300,min_height=300'],
            'template_background_front', 'template_background_back' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192', 'dimensions:min_width=600,min_height=300'],
            default => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096', 'dimensions:min_width=200,min_height=200'],
        };
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $expectedOwner = match ($this->input('category')) {
                'institution_logo', 'institution_stamp', 'institution_signature' => 'institution',
                'student_photo' => 'student',
                'template_background_front', 'template_background_back' => 'institution',
                default => null,
            };

            if ($expectedOwner !== null && $this->input('owner_type') !== $expectedOwner) {
                $validator->errors()->add('owner_type', 'Owner type tidak sesuai dengan kategori media.');
            }
        });
    }
}
