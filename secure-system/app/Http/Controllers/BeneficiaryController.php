<?php

namespace App\Http\Controllers;

use App\Models\Beneficiary;
use App\Models\VerificationActivityLog;
use App\Services\DuplicateDetectionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;

class BeneficiaryController extends Controller
{
    public function __construct(
        private readonly DuplicateDetectionService $duplicateService
    ) {}

    // ─────────────────────────────────────────────────────────────────────────
    // index — list with search / filter / pagination
    // ─────────────────────────────────────────────────────────────────────────

    public function index(Request $request)
    {
        $query = Beneficiary::with('registeredBy')
            ->withCount('familyMembers');

        // Search
        if ($term = $request->input('search')) {
            $query->search($term);
        }

        // Filters
        if ($municipality = $request->input('municipality')) {
            $query->where('municipality', $municipality);
        }
        if ($barangay = $request->input('barangay')) {
            $query->where('barangay', $barangay);
        }
        if ($request->has('status')) {
            $query->where('is_active', $request->input('status') === 'active');
        }

        // Sort
        $sort = $request->input('sort', 'newest');
        match ($sort) {
            'alpha'  => $query->orderBy('family_head_name'),
            default  => $query->latest(),
        };

        $beneficiaries = $query->paginate(20)->withQueryString();

        // Distinct values for filter dropdowns
        $municipalities = Beneficiary::distinct()->orderBy('municipality')->pluck('municipality');
        $barangays      = Beneficiary::distinct()->orderBy('barangay')->pluck('barangay');

        return Inertia::render('Beneficiaries/Index', [
            'beneficiaries' => $beneficiaries,
            'filters'       => $request->only(['search', 'municipality', 'barangay', 'status', 'sort']),
            'municipalities' => $municipalities,
            'barangays'     => $barangays,
            'totals'        => [
                'all'      => Beneficiary::count(),
                'active'   => Beneficiary::active()->count(),
                'inactive' => Beneficiary::where('is_active', false)->count(),
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // create — return blank form page
    // ─────────────────────────────────────────────────────────────────────────

    public function create()
    {
        return Inertia::render('Beneficiaries/Create');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // store — validate → duplicate-check → save
    // ─────────────────────────────────────────────────────────────────────────

    public function store(Request $request)
    {
        $validated = $request->validate($this->validationRules());

        // ── Duplicate detection ───────────────────────────────────────────
        $detection = $this->duplicateService->detectDuplicates($validated);

        if ($detection['found'] && ! $request->boolean('override_duplicates')) {
            return back()->with([
                'duplicates_found' => true,
                'duplicate_matches' => $detection['matches'],
            ])->withInput();
        }

        DB::beginTransaction();
        try {
            $beneficiary = Beneficiary::create([
                ...$validated,
                'bin'                  => $this->generateBin(),
                'verification_token'   => Str::random(64),
                'registered_by_user_id'=> Auth::id(),
                'token_status'         => 'active',
                'is_active'            => true,
            ]);

            // Log duplicate override if admin bypassed
            if ($detection['found'] && $request->boolean('override_duplicates')) {
                foreach ($detection['matches'] as $match) {
                    $this->duplicateService->logDuplicateDetection(
                        $match['beneficiary']['id'],
                        $beneficiary->id,
                        $match['type']
                    );
                }
            }

            // Activity log
            VerificationActivityLog::create([
                'user_id'              => Auth::id(),
                'beneficiary_id'       => $beneficiary->id,
                'activity_type'        => 'edit',
                'activity_description' => "Beneficiary {$beneficiary->bin} registered by " . Auth::user()->name,
                'ip_address'           => $request->ip(),
                'user_agent'           => $request->userAgent(),
                'status'               => 'success',
            ]);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'Registration failed: ' . $e->getMessage()]);
        }

        return redirect()->route('beneficiaries.show', $beneficiary->id)
            ->with('success', "Beneficiary {$beneficiary->bin} registered successfully.");
    }

    // ─────────────────────────────────────────────────────────────────────────
    // show — full details
    // ─────────────────────────────────────────────────────────────────────────

    public function show($id)
    {
        $beneficiary = Beneficiary::with([
            'registeredBy',
            'familyMembers',
            'activityLogs.user',
        ])->findOrFail($id);

        // Permission gate: all authenticated roles can view
        abort_if(! Auth::user()->hasPermission('beneficiary.view'), 403);

        return Inertia::render('Beneficiaries/Show', [
            'beneficiary'       => $beneficiary,
            'familyMembersCount'=> $beneficiary->familyMembers->count(),
            'recentActivities'  => $beneficiary->activityLogs()->latest('timestamp')->take(10)->get(),
            'canEdit'           => Auth::user()->hasPermission('beneficiary.update'),
            'canDelete'         => Auth::user()->hasPermission('beneficiary.delete'),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // edit — return populated form page
    // ─────────────────────────────────────────────────────────────────────────

    public function edit($id)
    {
        $beneficiary = Beneficiary::findOrFail($id);
        abort_if(! Auth::user()->hasPermission('beneficiary.update'), 403);

        return Inertia::render('Beneficiaries/Edit', [
            'beneficiary' => $beneficiary,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // update — validate → duplicate-check → save
    // ─────────────────────────────────────────────────────────────────────────

    public function update(Request $request, $id)
    {
        $beneficiary = Beneficiary::findOrFail($id);
        abort_if(! Auth::user()->hasPermission('beneficiary.update'), 403);

        $rules = $this->validationRules($id);
        $validated = $request->validate($rules);

        // Re-run duplicate check, excluding self
        $detection = $this->duplicateService->detectDuplicates($validated, (int) $id);

        if ($detection['found'] && ! $request->boolean('override_duplicates')) {
            return back()->with([
                'duplicates_found'  => true,
                'duplicate_matches' => $detection['matches'],
            ])->withInput();
        }

        DB::beginTransaction();
        try {
            // Critical-change fields require Administrator role
            $criticalChanged = $beneficiary->family_head_name !== $validated['family_head_name']
                || (string) $beneficiary->family_head_birthdate !== $validated['family_head_birthdate'];

            if ($criticalChanged && ! Auth::user()->isAdministrator()) {
                return back()->withErrors(['error' => 'Only Administrators may change the name or birthdate.']);
            }

            $beneficiary->update($validated);

            VerificationActivityLog::create([
                'user_id'              => Auth::id(),
                'beneficiary_id'       => $beneficiary->id,
                'activity_type'        => 'edit',
                'activity_description' => "Beneficiary {$beneficiary->bin} updated by " . Auth::user()->name,
                'ip_address'           => $request->ip(),
                'user_agent'           => $request->userAgent(),
                'status'               => 'success',
            ]);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'Update failed: ' . $e->getMessage()]);
        }

        return redirect()->route('beneficiaries.show', $beneficiary->id)
            ->with('success', 'Beneficiary updated successfully.');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // destroy — soft delete (deactivate)
    // ─────────────────────────────────────────────────────────────────────────

    public function destroy(Request $request, $id)
    {
        abort_if(! Auth::user()->isAdministrator(), 403, 'Only Administrators can deactivate beneficiaries.');

        $beneficiary = Beneficiary::findOrFail($id);

        DB::beginTransaction();
        try {
            $beneficiary->update([
                'is_active'    => false,
                'token_status' => 'revoked',
            ]);

            // Invalidate active QR codes for this beneficiary
            $beneficiary->qrCodes()->where('status', 'active')->update(['status' => 'revoked']);

            VerificationActivityLog::create([
                'user_id'              => Auth::id(),
                'beneficiary_id'       => $beneficiary->id,
                'activity_type'        => 'delete',
                'activity_description' => "Beneficiary {$beneficiary->bin} deactivated by " . Auth::user()->name,
                'ip_address'           => $request->ip(),
                'user_agent'           => $request->userAgent(),
                'status'               => 'success',
            ]);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'Deactivation failed: ' . $e->getMessage()]);
        }

        return redirect()->route('beneficiaries.index')
            ->with('success', "Beneficiary {$beneficiary->bin} has been deactivated.");
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Generate a unique BIN in the format BIN-YYYY-NNNNN.
     */
    private function generateBin(): string
    {
        $year = now()->year;

        // Highest sequence in the current year
        $lastBin = Beneficiary::where('bin', 'like', "BIN-{$year}-%")
            ->orderBy('bin', 'desc')
            ->value('bin');

        $nextSeq = $lastBin
            ? ((int) substr($lastBin, -5)) + 1
            : 1;

        do {
            $bin = sprintf('BIN-%d-%05d', $year, $nextSeq);
            $nextSeq++;
        } while (Beneficiary::where('bin', $bin)->exists());

        return $bin;
    }

    /**
     * Shared validation rules.
     *
     * @param  int|null  $excludeId  Beneficiary ID to exclude from unique email check
     */
    private function validationRules(?int $excludeId = null): array
    {
        $emailUnique = $excludeId
            ? "nullable|email|unique:beneficiaries,email,{$excludeId}"
            : 'nullable|email|unique:beneficiaries,email';

        return [
            'family_head_name'      => 'required|string|max:255',
            'family_head_birthdate' => 'required|date|before:today',
            'gender'                => 'required|in:Male,Female',
            'civil_status'          => 'required|in:Single,Married,Widowed,Separated',
            'contact_number'        => ['required', 'regex:/^[0-9]{11}$/'],
            'email'                 => $emailUnique,
            'barangay'              => 'required|string|max:255',
            'municipality'          => 'required|string|max:255',
            'province'              => 'required|string|max:255',
            'zip_code'              => 'required|string|size:4',
            'annual_income'         => 'required|numeric|min:0',
            'household_size'        => 'required|integer|min:1',
        ];
    }
}
