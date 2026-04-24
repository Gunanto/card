<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreStudentImportRequest;
use App\Jobs\ProcessStudentImportJob;
use App\Models\Import;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ImportController extends Controller
{
    public function index(): Response
    {
        $user = request()->user();
        $institutions = $this->visibleInstitutions($user);
        $imports = $this->scopeByInstitution(
            Import::query()->with(['institution', 'importedBy'])->latest(),
            $user,
        )->limit(50)->get();

        return Inertia::render('Imports/Index', [
            'imports' => $imports->map(fn (Import $import): array => [
                'id' => $import->id,
                'institution_name' => $import->institution?->name,
                'imported_by_name' => $import->importedBy?->name,
                'type' => $import->type,
                'source_filename' => $import->source_filename,
                'status' => $import->status,
                'total_rows' => $import->total_rows,
                'success_rows' => $import->success_rows,
                'failed_rows' => $import->failed_rows,
                'error_summary_json' => $import->error_summary_json,
                'created_at' => $import->created_at?->toDateTimeString(),
            ])->values(),
            'institutions' => $institutions->map(fn ($institution): array => [
                'id' => $institution->id,
                'name' => $institution->name,
            ])->values(),
            'forcedInstitutionId' => $user->isGuru() ? $user->institution_id : null,
            'sampleHeaders' => [
                'student_code',
                'nis',
                'nisn',
                'nik',
                'npwp',
                'name',
                'class_code',
                'class_name',
                'exam_number',
                'school_name',
                'gender',
                'religion',
                'phone',
                'mobile_phone',
                'address',
                'village',
                'district',
                'regency',
                'province',
                'motto',
                'social_instagram',
                'social_facebook',
                'social_tiktok',
                'status',
            ],
            'mappingFields' => [
                ['key' => 'student_code', 'label' => 'Student Code', 'required' => true],
                ['key' => 'name', 'label' => 'Name', 'required' => true],
                ['key' => 'class_code', 'label' => 'Class Code', 'required' => false],
                ['key' => 'class_name', 'label' => 'Class Name', 'required' => false],
                ['key' => 'nis', 'label' => 'NIS', 'required' => false],
                ['key' => 'nisn', 'label' => 'NISN', 'required' => false],
                ['key' => 'nik', 'label' => 'NIK', 'required' => false],
                ['key' => 'npwp', 'label' => 'NPWP', 'required' => false],
                ['key' => 'exam_number', 'label' => 'Exam Number', 'required' => false],
                ['key' => 'school_name', 'label' => 'School Name', 'required' => false],
                ['key' => 'gender', 'label' => 'Gender', 'required' => false],
                ['key' => 'religion', 'label' => 'Religion', 'required' => false],
                ['key' => 'address', 'label' => 'Address', 'required' => false],
                ['key' => 'village', 'label' => 'Village', 'required' => false],
                ['key' => 'district', 'label' => 'District', 'required' => false],
                ['key' => 'regency', 'label' => 'Regency', 'required' => false],
                ['key' => 'province', 'label' => 'Province', 'required' => false],
                ['key' => 'phone', 'label' => 'Phone', 'required' => false],
                ['key' => 'mobile_phone', 'label' => 'Mobile Phone', 'required' => false],
                ['key' => 'motto', 'label' => 'Motto', 'required' => false],
                ['key' => 'social_instagram', 'label' => 'Instagram', 'required' => false],
                ['key' => 'social_facebook', 'label' => 'Facebook', 'required' => false],
                ['key' => 'social_tiktok', 'label' => 'TikTok', 'required' => false],
                ['key' => 'status', 'label' => 'Status', 'required' => false],
            ],
        ]);
    }

    public function store(StoreStudentImportRequest $request): RedirectResponse
    {
        $institutionId = $request->user()->isGuru()
            ? $request->user()->institution_id
            : $request->integer('institution_id');

        if ($institutionId === null) {
            abort(422, 'Institution harus dipilih.');
        }

        $this->ensureInstitutionAccess($request->user(), $institutionId);

        $file = $request->file('file');
        $extension = strtolower($file->getClientOriginalExtension());
        $type = in_array($extension, ['xlsx', 'xls'], true) ? 'students_excel' : 'students_csv';
        $storedPath = $file->store('imports/students', 'local');

        $import = Import::query()->create([
            'institution_id' => $institutionId,
            'imported_by' => $request->user()->id,
            'type' => $type,
            'source_filename' => $file->getClientOriginalName(),
            'mapping_json' => $request->mappingPayload(),
            'status' => 'pending',
        ]);

        ProcessStudentImportJob::dispatch($import->id, $storedPath, 'local');

        return back()->with('status', 'Import file diterima dan sedang diproses.');
    }
}
