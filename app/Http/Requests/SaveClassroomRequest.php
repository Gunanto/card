<?php

namespace App\Http\Requests;

use App\Models\Classroom;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveClassroomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        /** @var Classroom|null $classroom */
        $classroom = $this->route('classroom');

        return [
            'institution_id' => ['required', 'integer', 'exists:institutions,id'],
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('classes', 'code')
                    ->where(fn ($query) => $query->where('institution_id', $this->input('institution_id')))
                    ->ignore($classroom),
            ],
            'name' => ['required', 'string', 'max:255'],
            'level' => ['nullable', 'string', 'max:20'],
            'major' => ['nullable', 'string', 'max:100'],
            'homeroom_teacher_user_id' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }
}
