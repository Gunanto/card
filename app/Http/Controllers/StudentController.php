<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveStudentRequest;
use App\Models\Classroom;
use App\Models\Student;
use App\Services\ActivityLogService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class StudentController extends Controller
{
    public function index(): Response
    {
        $user = request()->user();
        $institutions = $this->visibleInstitutions($user);
        $classrooms = $this->scopeByInstitution(
            Classroom::query()->orderBy('name'),
            $user,
        )->get();
        $students = $this->scopeByInstitution(
            Student::query()->with(['institution', 'classroom']),
            $user,
        )->orderBy('name')->get();

        return Inertia::render('Students/Index', [
            'students' => $students->map(fn (Student $student): array => [
                'id' => $student->id,
                'institution_id' => $student->institution_id,
                'institution_name' => $student->institution?->name,
                'class_id' => $student->class_id,
                'classroom_name' => $student->classroom?->name,
                'student_code' => $student->student_code,
                'nis' => $student->nis,
                'nisn' => $student->nisn,
                'nik' => $student->nik,
                'npwp' => $student->npwp,
                'exam_number' => $student->exam_number,
                'name' => $student->name,
                'school_name' => $student->school_name,
                'gender' => $student->gender,
                'religion' => $student->religion,
                'address' => $student->address,
                'village' => $student->village,
                'district' => $student->district,
                'regency' => $student->regency,
                'province' => $student->province,
                'phone' => $student->phone,
                'mobile_phone' => $student->mobile_phone,
                'motto' => $student->motto,
                'social_instagram' => $student->social_instagram,
                'social_facebook' => $student->social_facebook,
                'social_tiktok' => $student->social_tiktok,
                'status' => $student->status,
            ])->values(),
            'institutions' => $institutions->map(fn ($institution): array => [
                'id' => $institution->id,
                'name' => $institution->name,
            ])->values(),
            'classrooms' => $classrooms->map(fn (Classroom $classroom): array => [
                'id' => $classroom->id,
                'institution_id' => $classroom->institution_id,
                'name' => $classroom->name,
            ])->values(),
            'forcedInstitutionId' => $user->isGuru() ? $user->institution_id : null,
        ]);
    }

    public function store(SaveStudentRequest $request): RedirectResponse
    {
        $data = $request->validated();

        if ($request->user()->isGuru()) {
            $data['institution_id'] = $request->user()->institution_id;
        }

        $this->ensureInstitutionAccess($request->user(), $data['institution_id']);
        $this->assertClassroomWithinInstitution($data['institution_id'], $data['class_id'] ?? null);

        $student = Student::query()->create($data);
        app(ActivityLogService::class)->write(
            actor: $request->user(),
            action: 'student.create',
            subject: $student,
            request: $request,
        );

        return back()->with('status', 'Student created.');
    }

    public function update(SaveStudentRequest $request, Student $student): RedirectResponse
    {
        $this->ensureInstitutionAccess($request->user(), $student->institution_id);

        $data = $request->validated();

        if ($request->user()->isGuru()) {
            $data['institution_id'] = $request->user()->institution_id;
        }

        $this->assertClassroomWithinInstitution($data['institution_id'], $data['class_id'] ?? null);
        $student->update($data);
        app(ActivityLogService::class)->write(
            actor: $request->user(),
            action: 'student.update',
            subject: $student,
            request: $request,
        );

        return back()->with('status', 'Student updated.');
    }

    public function destroy(Student $student): RedirectResponse
    {
        $this->ensureInstitutionAccess(request()->user(), $student->institution_id);
        app(ActivityLogService::class)->write(
            actor: request()->user(),
            action: 'student.delete',
            subject: $student,
            request: request(),
        );

        $student->delete();

        return back()->with('status', 'Student deleted.');
    }

    protected function assertClassroomWithinInstitution(int $institutionId, ?int $classroomId): void
    {
        if ($classroomId === null) {
            return;
        }

        $classroom = Classroom::query()->findOrFail($classroomId);

        abort_unless($classroom->institution_id === $institutionId, 422);
    }
}
