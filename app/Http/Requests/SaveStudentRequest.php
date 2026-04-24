<?php

namespace App\Http\Requests;

use App\Models\Student;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        /** @var Student|null $student */
        $student = $this->route('student');

        return [
            'institution_id' => ['required', 'integer', 'exists:institutions,id'],
            'class_id' => ['nullable', 'integer', 'exists:classes,id'],
            'student_code' => [
                'required',
                'string',
                'max:100',
                Rule::unique('students', 'student_code')
                    ->where(fn ($query) => $query->where('institution_id', $this->input('institution_id')))
                    ->ignore($student),
            ],
            'nis' => ['nullable', 'string', 'max:100'],
            'nisn' => ['nullable', 'string', 'max:100'],
            'nik' => ['nullable', 'string', 'max:100'],
            'npwp' => ['nullable', 'string', 'max:100'],
            'exam_number' => ['nullable', 'string', 'max:100'],
            'name' => ['required', 'string', 'max:255'],
            'school_name' => ['nullable', 'string', 'max:255'],
            'gender' => ['nullable', Rule::in(['male', 'female'])],
            'religion' => ['nullable', 'string', 'max:100'],
            'address' => ['nullable', 'string'],
            'village' => ['nullable', 'string', 'max:150'],
            'district' => ['nullable', 'string', 'max:150'],
            'regency' => ['nullable', 'string', 'max:150'],
            'province' => ['nullable', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            'mobile_phone' => ['nullable', 'string', 'max:30'],
            'motto' => ['nullable', 'string'],
            'social_instagram' => ['nullable', 'string', 'max:255'],
            'social_facebook' => ['nullable', 'string', 'max:255'],
            'social_tiktok' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::in(['active', 'inactive', 'graduated'])],
        ];
    }
}
