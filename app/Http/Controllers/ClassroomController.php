<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveClassroomRequest;
use App\Models\Classroom;
use App\Models\User;
use App\Services\ActivityLogService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ClassroomController extends Controller
{
    public function index(): Response
    {
        $user = request()->user();
        $institutions = $this->visibleInstitutions($user);
        $classrooms = $this->scopeByInstitution(
            Classroom::query()->with(['institution', 'homeroomTeacher']),
            $user,
        )->orderBy('name')->get();
        $teachers = User::query()
            ->where('role', 'guru')
            ->when(
                $user->isGuru(),
                fn ($query) => $query->where('institution_id', $user->institution_id),
            )
            ->orderBy('name')
            ->get();

        return Inertia::render('Classrooms/Index', [
            'classrooms' => $classrooms->map(fn (Classroom $classroom): array => [
                'id' => $classroom->id,
                'institution_id' => $classroom->institution_id,
                'institution_name' => $classroom->institution?->name,
                'code' => $classroom->code,
                'name' => $classroom->name,
                'level' => $classroom->level,
                'major' => $classroom->major,
                'homeroom_teacher_user_id' => $classroom->homeroom_teacher_user_id,
                'homeroom_teacher_name' => $classroom->homeroomTeacher?->name,
            ])->values(),
            'institutions' => $institutions->map(fn ($institution): array => [
                'id' => $institution->id,
                'name' => $institution->name,
            ])->values(),
            'teachers' => $teachers->map(fn (User $teacher): array => [
                'id' => $teacher->id,
                'institution_id' => $teacher->institution_id,
                'name' => $teacher->name,
            ])->values(),
            'forcedInstitutionId' => $user->isGuru() ? $user->institution_id : null,
        ]);
    }

    public function store(SaveClassroomRequest $request): RedirectResponse
    {
        $data = $request->validated();

        if ($request->user()->isGuru()) {
            $data['institution_id'] = $request->user()->institution_id;
        }

        $this->ensureInstitutionAccess($request->user(), $data['institution_id']);
        $this->assertTeacherWithinInstitution($data['institution_id'], $data['homeroom_teacher_user_id'] ?? null);

        $classroom = Classroom::query()->create($data);
        app(ActivityLogService::class)->write(
            actor: $request->user(),
            action: 'classroom.create',
            subject: $classroom,
            request: $request,
        );

        return back()->with('status', 'Classroom created.');
    }

    public function update(SaveClassroomRequest $request, Classroom $classroom): RedirectResponse
    {
        $this->ensureInstitutionAccess($request->user(), $classroom->institution_id);

        $data = $request->validated();

        if ($request->user()->isGuru()) {
            $data['institution_id'] = $request->user()->institution_id;
        }

        $this->assertTeacherWithinInstitution($data['institution_id'], $data['homeroom_teacher_user_id'] ?? null);
        $classroom->update($data);
        app(ActivityLogService::class)->write(
            actor: $request->user(),
            action: 'classroom.update',
            subject: $classroom,
            request: $request,
        );

        return back()->with('status', 'Classroom updated.');
    }

    public function destroy(Classroom $classroom): RedirectResponse
    {
        $this->ensureInstitutionAccess(request()->user(), $classroom->institution_id);
        app(ActivityLogService::class)->write(
            actor: request()->user(),
            action: 'classroom.delete',
            subject: $classroom,
            request: request(),
        );

        $classroom->delete();

        return back()->with('status', 'Classroom deleted.');
    }

    protected function assertTeacherWithinInstitution(int $institutionId, ?int $teacherId): void
    {
        if ($teacherId === null) {
            return;
        }

        $teacher = User::query()->findOrFail($teacherId);

        abort_unless($teacher->role === 'guru' && $teacher->institution_id === $institutionId, 422);
    }
}
