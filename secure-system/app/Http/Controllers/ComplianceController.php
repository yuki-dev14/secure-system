<?php

namespace App\Http\Controllers;

use App\Models\Beneficiary;
use App\Models\ComplianceRecord;
use App\Models\FamilyMember;
use App\Models\VerificationActivityLog;
use App\Services\ComplianceSummaryService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class ComplianceController extends Controller
{
    public function __construct(
        private ComplianceSummaryService $summary,
    ) {}

    // ─────────────────────────────────────────────────────────────────────────
    // recordEducation — POST /compliance/education
    // ─────────────────────────────────────────────────────────────────────────

    public function recordEducation(Request $request)
    {
        $validated = $request->validate([
            'family_member_id'      => ['required', 'integer', 'exists:family_members,id'],
            'compliance_period'     => ['required', 'string', 'regex:/^\d{4}-\d{2}$/'],
            'school_name'           => ['required', 'string', 'max:255'],
            'enrollment_status'     => ['required', Rule::in(['Enrolled', 'Not Enrolled', 'Not Applicable'])],
            'attendance_percentage' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        $member = FamilyMember::with('beneficiary')->findOrFail($validated['family_member_id']);

        // Check if family member is school age (5–21)
        $age         = $member->age;
        $isSchoolAge = $age >= 5 && $age <= 21;

        $isCompliant = (float) $validated['attendance_percentage'] >= 85.0;

        $record = DB::transaction(function () use ($validated, $isCompliant, $member) {
            $rec = ComplianceRecord::updateOrCreate(
                [
                    'family_member_id'  => $validated['family_member_id'],
                    'compliance_type'   => 'education',
                    'compliance_period' => $validated['compliance_period'],
                ],
                [
                    'school_name'           => $validated['school_name'],
                    'enrollment_status'     => $validated['enrollment_status'],
                    'attendance_percentage' => $validated['attendance_percentage'],
                    'is_compliant'          => $isCompliant,
                    'verified_at'           => null,
                    'verified_by_user_id'   => null,
                ]
            );
            return $rec;
        });

        // Log activity
        $this->logActivity(
            $member->beneficiary_id,
            'edit',
            sprintf(
                'Education compliance recorded for %s (Period: %s, Attendance: %.1f%%, Compliant: %s) by %s.',
                $member->full_name,
                $validated['compliance_period'],
                $validated['attendance_percentage'],
                $isCompliant ? 'Yes' : 'No',
                Auth::user()->name
            ),
            'success',
            $request
        );

        // Trigger summary recalculation
        $this->recalculate($member->beneficiary_id);

        return response()->json([
            'success'        => true,
            'message'        => 'Education compliance recorded successfully.',
            'is_school_age'  => $isSchoolAge,
            'is_compliant'   => $isCompliant,
            'threshold'      => 85.0,
            'record'         => $this->formatRecord($record->load('familyMember', 'verifiedBy')),
        ], 201);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // recordHealth — POST /compliance/health
    // ─────────────────────────────────────────────────────────────────────────

    public function recordHealth(Request $request)
    {
        $validated = $request->validate([
            'family_member_id'    => ['required', 'integer', 'exists:family_members,id'],
            'compliance_period'   => ['required', 'string', 'regex:/^\d{4}-\d{2}$/'],
            'health_checkup_date' => ['required', 'date', 'before_or_equal:today'],
            'vaccination_status'  => ['required', Rule::in(['Complete', 'Incomplete', 'Not Applicable'])],
            'health_center_name'  => ['nullable', 'string', 'max:255'],
        ]);

        $member = FamilyMember::with('beneficiary')->findOrFail($validated['family_member_id']);

        // Determine compliance
        // Checkup date must fall within the compliance_period month
        $periodStart = Carbon::createFromFormat('Y-m', $validated['compliance_period'])->startOfMonth();
        $periodEnd   = Carbon::createFromFormat('Y-m', $validated['compliance_period'])->endOfMonth();
        $checkupDate = Carbon::parse($validated['health_checkup_date']);

        $checkupWithinPeriod = $checkupDate->between($periodStart, $periodEnd);
        $isCompliant = $checkupWithinPeriod && $validated['vaccination_status'] === 'Complete';

        $record = DB::transaction(function () use ($validated, $isCompliant) {
            return ComplianceRecord::updateOrCreate(
                [
                    'family_member_id'  => $validated['family_member_id'],
                    'compliance_type'   => 'health',
                    'compliance_period' => $validated['compliance_period'],
                ],
                [
                    'health_checkup_date' => $validated['health_checkup_date'],
                    'vaccination_status'  => $validated['vaccination_status'],
                    'school_name'         => $validated['health_center_name'] ?? null, // reuse field for center name
                    'is_compliant'        => $isCompliant,
                    'verified_at'         => null,
                    'verified_by_user_id' => null,
                ]
            );
        });

        $this->logActivity(
            $member->beneficiary_id,
            'edit',
            sprintf(
                'Health compliance recorded for %s (Period: %s, Checkup: %s, Vaccination: %s, Compliant: %s) by %s.',
                $member->full_name,
                $validated['compliance_period'],
                $validated['health_checkup_date'],
                $validated['vaccination_status'],
                $isCompliant ? 'Yes' : 'No',
                Auth::user()->name
            ),
            'success',
            $request
        );

        $this->recalculate($member->beneficiary_id);

        return response()->json([
            'success'                  => true,
            'message'                  => 'Health compliance recorded successfully.',
            'checkup_within_period'    => $checkupWithinPeriod,
            'is_compliant'             => $isCompliant,
            'record'                   => $this->formatRecord($record->load('familyMember', 'verifiedBy')),
        ], 201);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // recordFDS — POST /compliance/fds
    // ─────────────────────────────────────────────────────────────────────────

    public function recordFDS(Request $request)
    {
        $validated = $request->validate([
            'beneficiary_id'    => ['required', 'integer', 'exists:beneficiaries,id'],
            'compliance_period' => ['required', 'string', 'regex:/^\d{4}-\d{2}$/'],
            'fds_session_date'  => ['required', 'date'],
            'fds_topic'         => ['nullable', 'string', 'max:255'],
            'fds_location'      => ['nullable', 'string', 'max:255'],
            'attendees'         => ['required', 'array', 'min:1'],
            'attendees.*'       => ['required', 'integer', 'exists:family_members,id'],
        ]);

        $beneficiary = Beneficiary::with('familyMembers')->findOrFail($validated['beneficiary_id']);

        // Verify family head attended (head is the member whose relationship_to_head = 'Head' or first member)
        $headMember = $beneficiary->familyMembers
            ->firstWhere(fn($m) => strtolower($m->relationship_to_head) === 'head')
            ?? $beneficiary->familyMembers->first();

        $headAttended = $headMember && in_array($headMember->id, $validated['attendees']);

        // Count existing FDS sessions in this compliance_period for this beneficiary
        $existingSessionCount = ComplianceRecord::whereHas('familyMember', function ($q) use ($validated) {
                $q->where('beneficiary_id', $validated['beneficiary_id']);
            })
            ->where('compliance_type', 'fds')
            ->where('compliance_period', $validated['compliance_period'])
            ->where('fds_attendance', true)
            ->whereNotIn('family_member_id', $validated['attendees']) // avoid double-counting current session
            ->distinct('family_member_id')
            ->count();

        // After this session: new total = existing distinct sessions + 1
        $sessionsAfter = $existingSessionCount + 1;
        $isCompliant   = $sessionsAfter >= 2;

        $records = DB::transaction(function () use ($validated, $isCompliant, $sessionsAfter) {
            $created = [];
            foreach ($validated['attendees'] as $memberId) {
                $rec = ComplianceRecord::updateOrCreate(
                    [
                        'family_member_id'  => $memberId,
                        'compliance_type'   => 'fds',
                        'compliance_period' => $validated['compliance_period'],
                    ],
                    [
                        'fds_attendance'      => true,
                        'school_name'         => $validated['fds_topic'] ?? null,   // reuse field for topic
                        'enrollment_status'   => null,
                        'is_compliant'        => $isCompliant,
                        'verified_at'         => null,
                        'verified_by_user_id' => null,
                    ]
                );
                $created[] = $rec;
            }
            return $created;
        });

        $this->logActivity(
            $validated['beneficiary_id'],
            'edit',
            sprintf(
                'FDS session recorded (Period: %s, Date: %s, %d attendee(s), Head attended: %s, Total sessions: %d, Compliant: %s) by %s.',
                $validated['compliance_period'],
                $validated['fds_session_date'],
                count($validated['attendees']),
                $headAttended ? 'Yes' : 'No',
                $sessionsAfter,
                $isCompliant ? 'Yes' : 'No',
                Auth::user()->name
            ),
            'success',
            $request
        );

        $this->recalculate($validated['beneficiary_id']);

        return response()->json([
            'success'         => true,
            'message'         => 'FDS attendance recorded successfully.',
            'head_attended'   => $headAttended,
            'sessions_count'  => $sessionsAfter,
            'sessions_needed' => 2,
            'is_compliant'    => $isCompliant,
            'records_created' => count($records),
        ], 201);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // verifyCompliance — POST /compliance/verify/{id}
    // ─────────────────────────────────────────────────────────────────────────

    public function verifyCompliance(Request $request, $complianceId)
    {
        $actor = Auth::user();
        abort_if(
            ! $actor->hasRole(['Compliance Verifier', 'Administrator']),
            403,
            'Only Compliance Verifiers and Administrators can verify compliance records.'
        );

        $record = ComplianceRecord::with('familyMember.beneficiary')->findOrFail($complianceId);

        if ($record->verified_at !== null) {
            return response()->json([
                'success' => false,
                'message' => 'This compliance record has already been verified.',
            ], 422);
        }

        DB::transaction(function () use ($record, $actor) {
            $record->update([
                'verified_at'         => now(),
                'verified_by_user_id' => $actor->id,
            ]);
        });

        $beneficiaryId = $record->familyMember?->beneficiary_id;

        $this->logActivity(
            $beneficiaryId,
            'verify',
            sprintf(
                'Compliance record #%d (%s, Period: %s) verified by %s.',
                $record->id,
                $record->compliance_type,
                $record->compliance_period,
                $actor->name
            ),
            'success',
            $request
        );

        if ($beneficiaryId) {
            $this->recalculate($beneficiaryId);
        }

        return response()->json([
            'success' => true,
            'message' => 'Compliance record verified successfully.',
            'record'  => $this->formatRecord($record->fresh(['familyMember', 'verifiedBy'])),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // index — GET /compliance/{beneficiaryId}
    // ─────────────────────────────────────────────────────────────────────────

    public function index(Request $request, $beneficiaryId)
    {
        $beneficiary = Beneficiary::with('familyMembers')->findOrFail($beneficiaryId);

        $memberIds = $beneficiary->familyMembers->pluck('id');

        $records = ComplianceRecord::with(['familyMember', 'verifiedBy:id,name,role'])
            ->whereIn('family_member_id', $memberIds)
            ->orderByDesc('compliance_period')
            ->orderByDesc('created_at')
            ->paginate(20);

        // Group by family_member_id and compliance_type
        $grouped = $records->getCollection()->groupBy(function ($r) {
            return $r->family_member_id . '_' . $r->compliance_type;
        })->map(function ($group) {
            $first = $group->first();
            return [
                'family_member_id'   => $first->family_member_id,
                'family_member_name' => $first->familyMember?->full_name,
                'compliance_type'    => $first->compliance_type,
                'records'            => $group->map(fn($r) => $this->formatRecord($r))->values(),
            ];
        })->values();

        // Also fetch summary
        $summaryData = $this->summary->calculateOverallCompliance((int) $beneficiaryId);

        return response()->json([
            'success'      => true,
            'beneficiary'  => [
                'id'               => $beneficiary->id,
                'bin'              => $beneficiary->bin,
                'family_head_name' => $beneficiary->family_head_name,
            ],
            'grouped'      => $grouped,
            'summary'      => $summaryData,
            'pagination'   => [
                'total'        => $records->total(),
                'per_page'     => $records->perPage(),
                'current_page' => $records->currentPage(),
                'last_page'    => $records->lastPage(),
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // show — GET /compliance/show/{id}
    // ─────────────────────────────────────────────────────────────────────────

    public function show($complianceId)
    {
        $record = ComplianceRecord::with([
            'familyMember.beneficiary:id,bin,family_head_name,municipality',
            'verifiedBy:id,name,role,office_location',
        ])->findOrFail($complianceId);

        return response()->json([
            'success' => true,
            'record'  => $this->formatRecord($record),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // compliancePage — Inertia page GET /compliance/page/{beneficiaryId}
    // ─────────────────────────────────────────────────────────────────────────

    public function compliancePage($beneficiaryId)
    {
        $beneficiary = Beneficiary::with('familyMembers')->findOrFail($beneficiaryId);

        return Inertia::render('Compliance/Index', [
            'beneficiary'   => [
                'id'               => $beneficiary->id,
                'bin'              => $beneficiary->bin,
                'family_head_name' => $beneficiary->family_head_name,
                'municipality'     => $beneficiary->municipality,
            ],
            'familyMembers' => $beneficiary->familyMembers->map(fn($m) => [
                'id'                      => $m->id,
                'full_name'               => $m->full_name,
                'age'                     => $m->age,
                'relationship_to_head'    => $m->relationship_to_head,
                'is_school_age'           => $m->is_school_age,
                'needs_health_monitoring' => $m->needs_health_monitoring,
            ])->values(),
            'canRecord'     => Auth::user()->hasRole(['Field Officer', 'Administrator']),
            'canVerify'     => Auth::user()->hasRole(['Compliance Verifier', 'Administrator']),
            'isAdmin'       => Auth::user()->isAdministrator(),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Private helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function formatRecord(ComplianceRecord $r): array
    {
        return [
            'id'                    => $r->id,
            'family_member_id'      => $r->family_member_id,
            'family_member_name'    => $r->familyMember?->full_name,
            'family_member_age'     => $r->familyMember?->age,
            'compliance_type'       => $r->compliance_type,
            'compliance_period'     => $r->compliance_period,
            'school_name'           => $r->school_name,
            'enrollment_status'     => $r->enrollment_status,
            'attendance_percentage' => $r->attendance_percentage ? (float) $r->attendance_percentage : null,
            'health_checkup_date'   => $r->health_checkup_date?->toDateString(),
            'vaccination_status'    => $r->vaccination_status,
            'fds_attendance'        => $r->fds_attendance,
            'is_compliant'          => $r->is_compliant,
            'verified_at'           => $r->verified_at?->toIso8601String(),
            'verified_at_human'     => $r->verified_at?->diffForHumans(),
            'verified_by'           => $r->verifiedBy ? [
                'id'   => $r->verifiedBy->id,
                'name' => $r->verifiedBy->name,
                'role' => $r->verifiedBy->role,
            ] : null,
            'beneficiary'           => $r->familyMember?->beneficiary ? [
                'id'               => $r->familyMember->beneficiary->id,
                'bin'              => $r->familyMember->beneficiary->bin,
                'family_head_name' => $r->familyMember->beneficiary->family_head_name,
            ] : null,
            'created_at'            => $r->created_at?->toIso8601String(),
            'updated_at'            => $r->updated_at?->toIso8601String(),
        ];
    }

    private function recalculate(int $beneficiaryId): void
    {
        try {
            $this->summary->calculateOverallCompliance($beneficiaryId);
        } catch (\Throwable $e) {
            Log::warning("ComplianceController: summary recalculation failed for beneficiary #{$beneficiaryId}: " . $e->getMessage());
        }
    }

    private function logActivity(
        ?int    $beneficiaryId,
        string  $activityType,
        string  $description,
        string  $status,
        Request $request,
        ?string $remarks = null
    ): void {
        try {
            VerificationActivityLog::create([
                'user_id'              => Auth::id(),
                'beneficiary_id'       => $beneficiaryId,
                'activity_type'        => $activityType,
                'activity_description' => $description,
                'ip_address'           => $request->ip(),
                'user_agent'           => $request->userAgent(),
                'status'               => $status,
                'remarks'              => $remarks,
            ]);
        } catch (\Throwable $e) {
            Log::error('ComplianceController: Failed to write activity log: ' . $e->getMessage());
        }
    }
}
