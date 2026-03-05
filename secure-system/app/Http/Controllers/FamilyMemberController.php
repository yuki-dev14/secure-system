<?php

namespace App\Http\Controllers;

use App\Models\Beneficiary;
use App\Models\FamilyMember;
use App\Models\VerificationActivityLog;
use App\Services\AgeCalculationService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class FamilyMemberController extends Controller
{
    public function __construct(
        private readonly AgeCalculationService $ageService
    ) {}

    // ─────────────────────────────────────────────────────────────────────────
    // index — list all family members for a beneficiary
    // ─────────────────────────────────────────────────────────────────────────

    public function index($beneficiaryId)
    {
        $beneficiary = Beneficiary::findOrFail($beneficiaryId);
        abort_if(! Auth::user()->hasPermission('beneficiary.view'), 403);

        $members = FamilyMember::where('beneficiary_id', $beneficiaryId)->get();

        // Enrich each member with computed values
        $enriched = $members->map(fn ($m) => $this->enrichMember($m));

        // Counts by relationship type
        $counts = $members->groupBy('relationship_to_head')
            ->map(fn ($g) => $g->count());

        return Inertia::render('Beneficiaries/FamilyMembers', [
            'beneficiary'  => $beneficiary,
            'members'      => $enriched,
            'counts'       => $counts,
            'canManage'    => Auth::user()->hasPermission('beneficiary.update'),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // store — create one or many family members
    // ─────────────────────────────────────────────────────────────────────────

    public function store(Request $request, $beneficiaryId)
    {
        $beneficiary = Beneficiary::findOrFail($beneficiaryId);
        abort_if(! Auth::user()->hasPermission('beneficiary.update'), 403);

        // Batch mode: array of members wrapped in `members[]`
        $isBatch = $request->has('members');

        if ($isBatch) {
            $this->storeBatch($request, $beneficiary);
        } else {
            $this->storeSingle($request, $beneficiary);
        }

        return back()->with('success', 'Family member(s) saved successfully.');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // update
    // ─────────────────────────────────────────────────────────────────────────

    public function update(Request $request, $id)
    {
        $member = FamilyMember::findOrFail($id);
        abort_if(! Auth::user()->hasPermission('beneficiary.update'), 403);

        $data = $this->validateMemberData($request);
        $birthdate = Carbon::parse($data['birthdate']);

        // Auto-resolve enrollment status based on age
        $data['school_enrollment_status'] = $this->ageService->resolveEnrollmentStatus(
            $birthdate,
            $data['school_enrollment_status'] ?? 'Not Applicable'
        );

        DB::beginTransaction();
        try {
            $member->update($data);

            $this->logActivity(
                $member->beneficiary_id,
                "Family member '{$member->full_name}' updated by " . Auth::user()->name
            );

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'Update failed: ' . $e->getMessage()]);
        }

        return back()->with('success', "'{$member->full_name}' updated successfully.");
    }

    // ─────────────────────────────────────────────────────────────────────────
    // destroy
    // ─────────────────────────────────────────────────────────────────────────

    public function destroy($id)
    {
        abort_if(
            ! Auth::user()->hasRole(['Field Officer', 'Administrator']),
            403,
            'Insufficient role to delete family members.'
        );

        $member = FamilyMember::findOrFail($id);
        $beneficiaryId = $member->beneficiary_id;
        $name = $member->full_name;

        DB::beginTransaction();
        try {
            // compliance_records cascade via DB FK, but we log explicitly
            $complianceCount = $member->complianceRecords()->count();
            $member->delete(); // cascades to compliance_records

            $this->logActivity(
                $beneficiaryId,
                "Family member '{$name}' and {$complianceCount} compliance record(s) deleted by " . Auth::user()->name
            );

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'Deletion failed: ' . $e->getMessage()]);
        }

        return back()->with('success', "'{$name}' removed from the household.");
    }

    // ─────────────────────────────────────────────────────────────────────────
    // CSV import
    // ─────────────────────────────────────────────────────────────────────────

    public function importCsv(Request $request, $beneficiaryId)
    {
        $beneficiary = Beneficiary::findOrFail($beneficiaryId);
        abort_if(! Auth::user()->hasPermission('beneficiary.update'), 403);

        $request->validate(['csv_file' => 'required|file|mimes:csv,txt|max:2048']);

        $file = $request->file('csv_file');
        $rows = array_map('str_getcsv', file($file->getRealPath()));
        $header = array_map('trim', array_shift($rows));

        $expectedHeaders = ['full_name', 'birthdate', 'gender', 'relationship_to_head', 'birth_certificate_no', 'school_enrollment_status', 'health_center_registered'];
        $missing = array_diff($expectedHeaders, $header);

        if (! empty($missing)) {
            return back()->withErrors(['csv_file' => 'CSV is missing columns: ' . implode(', ', $missing)]);
        }

        $imported = 0;
        $csvErrors = [];

        DB::beginTransaction();
        try {
            foreach ($rows as $rowIndex => $row) {
                if (count($row) !== count($header)) continue;

                $data = array_combine($header, $row);

                // Validate each row
                try {
                    $birthdate = Carbon::parse($data['birthdate']);
                    if (! $birthdate->isPast()) {
                        $csvErrors[] = "Row " . ($rowIndex + 2) . ": birthdate must be in the past.";
                        continue;
                    }

                    FamilyMember::create([
                        'beneficiary_id'           => $beneficiary->id,
                        'full_name'                => trim($data['full_name']),
                        'birthdate'                => $birthdate->toDateString(),
                        'gender'                   => in_array($data['gender'], ['Male', 'Female']) ? $data['gender'] : null,
                        'relationship_to_head'     => in_array($data['relationship_to_head'], ['Spouse','Child','Parent','Sibling','Other']) ? $data['relationship_to_head'] : 'Other',
                        'birth_certificate_no'     => $data['birth_certificate_no'] ?: null,
                        'school_enrollment_status' => $this->ageService->resolveEnrollmentStatus($birthdate, $data['school_enrollment_status']),
                        'health_center_registered' => filter_var($data['health_center_registered'], FILTER_VALIDATE_BOOLEAN),
                    ]);
                    $imported++;
                } catch (\Throwable $rowErr) {
                    $csvErrors[] = "Row " . ($rowIndex + 2) . ": " . $rowErr->getMessage();
                }
            }

            $this->logActivity($beneficiary->id, "{$imported} family member(s) imported via CSV by " . Auth::user()->name);
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->withErrors(['csv_file' => 'Import failed: ' . $e->getMessage()]);
        }

        $msg = "{$imported} member(s) imported successfully.";
        if ($csvErrors) {
            $msg .= ' Skipped rows: ' . implode('; ', $csvErrors);
        }

        return back()->with('success', $msg);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function storeSingle(Request $request, Beneficiary $beneficiary): FamilyMember
    {
        $data = $this->validateMemberData($request);
        $birthdate = Carbon::parse($data['birthdate']);
        $data['school_enrollment_status'] = $this->ageService->resolveEnrollmentStatus(
            $birthdate,
            $data['school_enrollment_status'] ?? 'Not Applicable'
        );
        $data['beneficiary_id'] = $beneficiary->id;

        $member = FamilyMember::create($data);

        $this->logActivity(
            $beneficiary->id,
            "Family member '{$member->full_name}' added to {$beneficiary->bin} by " . Auth::user()->name
        );

        return $member;
    }

    private function storeBatch(Request $request, Beneficiary $beneficiary): void
    {
        $request->validate([
            'members'                              => 'required|array|min:1',
            'members.*.full_name'                  => 'required|string|max:255',
            'members.*.birthdate'                  => 'required|date|before:today',
            'members.*.gender'                     => 'required|in:Male,Female',
            'members.*.relationship_to_head'       => 'required|in:Spouse,Child,Parent,Sibling,Other',
            'members.*.birth_certificate_no'       => 'nullable|string|max:50',
            'members.*.school_enrollment_status'   => 'required|in:Enrolled,Not Enrolled,Not Applicable',
            'members.*.health_center_registered'   => 'boolean',
        ]);

        DB::beginTransaction();
        try {
            foreach ($request->input('members') as $memberData) {
                $birthdate = Carbon::parse($memberData['birthdate']);
                FamilyMember::create([
                    'beneficiary_id'           => $beneficiary->id,
                    'full_name'                => $memberData['full_name'],
                    'birthdate'                => $memberData['birthdate'],
                    'gender'                   => $memberData['gender'],
                    'relationship_to_head'     => $memberData['relationship_to_head'],
                    'birth_certificate_no'     => $memberData['birth_certificate_no'] ?? null,
                    'school_enrollment_status' => $this->ageService->resolveEnrollmentStatus(
                        $birthdate,
                        $memberData['school_enrollment_status']
                    ),
                    'health_center_registered' => (bool) ($memberData['health_center_registered'] ?? false),
                ]);
            }

            $count = count($request->input('members'));
            $this->logActivity(
                $beneficiary->id,
                "{$count} family member(s) batch-added to {$beneficiary->bin} by " . Auth::user()->name
            );

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    private function validateMemberData(Request $request): array
    {
        return $request->validate([
            'full_name'                => 'required|string|max:255',
            'birthdate'                => 'required|date|before:today',
            'gender'                   => 'required|in:Male,Female',
            'relationship_to_head'     => 'required|in:Spouse,Child,Parent,Sibling,Other',
            'birth_certificate_no'     => 'nullable|string|max:50',
            'school_enrollment_status' => 'required|in:Enrolled,Not Enrolled,Not Applicable',
            'health_center_registered' => 'boolean',
        ]);
    }

    /** Append computed fields to a FamilyMember instance for the frontend. */
    private function enrichMember(FamilyMember $m): array
    {
        $birthdate = Carbon::parse($m->birthdate);
        return [
            'id'                       => $m->id,
            'beneficiary_id'           => $m->beneficiary_id,
            'full_name'                => $m->full_name,
            'birthdate'                => $m->birthdate?->toDateString(),
            'gender'                   => $m->gender,
            'relationship_to_head'     => $m->relationship_to_head,
            'birth_certificate_no'     => $m->birth_certificate_no,
            'school_enrollment_status' => $m->school_enrollment_status,
            'health_center_registered' => $m->health_center_registered,
            'age'                      => $this->ageService->calculateAge($birthdate),
            'age_label'                => $this->ageService->ageLabel($birthdate),
            'is_school_age'            => $this->ageService->isSchoolAge($birthdate),
            'needs_health_monitoring'  => $this->ageService->requiresHealthCompliance($birthdate),
        ];
    }

    private function logActivity(int $beneficiaryId, string $description): void
    {
        VerificationActivityLog::create([
            'user_id'              => Auth::id(),
            'beneficiary_id'       => $beneficiaryId,
            'activity_type'        => 'edit',
            'activity_description' => $description,
            'ip_address'           => request()->ip(),
            'user_agent'           => request()->userAgent(),
            'status'               => 'success',
        ]);
    }
}
