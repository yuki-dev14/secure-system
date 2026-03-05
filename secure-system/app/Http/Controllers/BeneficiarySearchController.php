<?php

namespace App\Http\Controllers;

use App\Exports\BeneficiaryExport;
use App\Models\Beneficiary;
use App\Models\VerificationActivityLog;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

class BeneficiarySearchController extends Controller
{
    // ─────────────────────────────────────────────────────────────────────────
    // search — main paginated search used by Index page
    // ─────────────────────────────────────────────────────────────────────────

    public function search(Request $request)
    {
        $request->validate([
            'query'         => 'nullable|string|max:255',
            'municipality'  => 'nullable|string|max:255',
            'barangay'      => 'nullable|string|max:255',
            'status'        => 'nullable|in:active,inactive,all',
            'date_from'     => 'nullable|date',
            'date_to'       => 'nullable|date|after_or_equal:date_from',
            'min_income'    => 'nullable|numeric|min:0',
            'max_income'    => 'nullable|numeric|min:0',
            'household_size'=> 'nullable|integer|min:1',
            'sort'          => 'nullable|in:newest,oldest,alpha,alpha_desc,income_asc,income_desc',
            'per_page'      => 'nullable|integer|min:5|max:100',
        ]);

        $query = $this->buildQuery($request);

        $perPage       = $request->input('per_page', 20);
        $beneficiaries = $query->with('registeredBy')
            ->withCount('familyMembers')
            ->paginate($perPage)
            ->withQueryString();

        // Cache distinct filter values (5 min)
        $municipalities = Cache::remember('beneficiary_municipalities', 300, fn () =>
            Beneficiary::distinct()->orderBy('municipality')->pluck('municipality')
        );
        $barangays = $request->input('municipality')
            ? Beneficiary::where('municipality', $request->input('municipality'))
                ->distinct()->orderBy('barangay')->pluck('barangay')
            : Cache::remember('beneficiary_barangays', 300, fn () =>
                Beneficiary::distinct()->orderBy('barangay')->pluck('barangay')
              );

        // Log search activity (lightweight — only when there's an actual query)
        if ($request->input('query')) {
            $this->logSearch($request->input('query'), $beneficiaries->total());
        }

        return Inertia::render('Beneficiaries/Index', [
            'beneficiaries'  => $beneficiaries,
            'filters'        => $request->only([
                'query', 'municipality', 'barangay', 'status',
                'date_from', 'date_to', 'min_income', 'max_income',
                'household_size', 'sort', 'per_page',
            ]),
            'municipalities' => $municipalities,
            'barangays'      => $barangays,
            'totals'         => $this->getTotals(),
            'resultCount'    => $beneficiaries->total(),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // quickSearch — lightweight autocomplete (JSON, not Inertia)
    // ─────────────────────────────────────────────────────────────────────────

    public function quickSearch(Request $request)
    {
        $term = $request->validate(['q' => 'required|string|min:2|max:100'])['q'];

        $results = Beneficiary::where('is_active', true)
            ->where(function (Builder $q) use ($term) {
                $q->whereRaw('LOWER(family_head_name) LIKE ?', ['%' . mb_strtolower($term) . '%'])
                  ->orWhereRaw('LOWER(bin) LIKE ?',            ['%' . mb_strtolower($term) . '%'])
                  ->orWhere('contact_number', 'like',          "%{$term}%");
            })
            ->select('id', 'bin', 'family_head_name', 'municipality', 'barangay', 'is_active')
            ->limit(10)
            ->get()
            ->map(fn ($b) => [
                'id'    => $b->id,
                'bin'   => $b->bin,
                'name'  => $b->family_head_name,
                'label' => "{$b->family_head_name} — {$b->bin}",
                'sub'   => "{$b->barangay}, {$b->municipality}",
            ]);

        return response()->json($results);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // advancedSearch — fuzzy multi-criteria, includes family-member data
    // ─────────────────────────────────────────────────────────────────────────

    public function advancedSearch(Request $request)
    {
        $request->validate([
            'query'              => 'nullable|string|max:255',
            'municipality'       => 'nullable|string|max:255',
            'barangay'           => 'nullable|string|max:255',
            'province'           => 'nullable|string|max:255',
            'status'             => 'nullable|in:active,inactive,all',
            'date_from'          => 'nullable|date',
            'date_to'            => 'nullable|date|after_or_equal:date_from',
            'min_income'         => 'nullable|numeric|min:0',
            'max_income'         => 'nullable|numeric|min:0',
            'household_size'     => 'nullable|integer|min:1',
            'gender'             => 'nullable|in:Male,Female',
            'civil_status'       => 'nullable|in:Single,Married,Widowed,Separated',
            'family_member_name' => 'nullable|string|max:255',
            'sort'               => 'nullable|in:newest,oldest,alpha,alpha_desc,income_asc,income_desc',
            'per_page'           => 'nullable|integer|min:5|max:100',
        ]);

        $query = $this->buildQuery($request);

        // Extra advanced criteria
        if ($province = $request->input('province')) {
            $query->whereRaw('LOWER(province) LIKE ?', ['%' . mb_strtolower($province) . '%']);
        }
        if ($gender = $request->input('gender')) {
            $query->where('gender', $gender);
        }
        if ($civilStatus = $request->input('civil_status')) {
            $query->where('civil_status', $civilStatus);
        }
        // Family-member name search (JOIN into family_members)
        if ($familyName = $request->input('family_member_name')) {
            $query->whereHas('familyMembers', function (Builder $q) use ($familyName) {
                $q->whereRaw('LOWER(full_name) LIKE ?', ['%' . mb_strtolower($familyName) . '%']);
            });
        }

        $perPage       = $request->input('per_page', 20);
        $beneficiaries = $query
            ->with(['registeredBy', 'familyMembers'])
            ->withCount('familyMembers')
            ->paginate($perPage)
            ->withQueryString();

        $this->logSearch($request->input('query', 'advanced'), $beneficiaries->total(), 'advanced');

        return Inertia::render('Beneficiaries/AdvancedSearch', [
            'beneficiaries'  => $beneficiaries,
            'filters'        => $request->all(),
            'municipalities' => Cache::remember('beneficiary_municipalities', 300, fn () =>
                Beneficiary::distinct()->orderBy('municipality')->pluck('municipality')
            ),
            'totals'         => $this->getTotals(),
            'resultCount'    => $beneficiaries->total(),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // export — Excel / CSV / PDF of the current filtered result set
    // ─────────────────────────────────────────────────────────────────────────

    public function export(Request $request)
    {
        abort_if(! Auth::user()->hasPermission('beneficiary.view'), 403);

        $request->validate([
            'format'   => 'required|in:xlsx,csv,pdf',
            // Accept all search params
            'query'         => 'nullable|string|max:255',
            'municipality'  => 'nullable|string|max:255',
            'barangay'      => 'nullable|string|max:255',
            'status'        => 'nullable|in:active,inactive,all',
            'date_from'     => 'nullable|date',
            'date_to'       => 'nullable|date',
            'min_income'    => 'nullable|numeric|min:0',
            'max_income'    => 'nullable|numeric|min:0',
            'household_size'=> 'nullable|integer|min:1',
        ]);

        $format    = $request->input('format');
        $query     = $this->buildQuery($request)->with('registeredBy');
        $timestamp = now()->format('Ymd_His');
        $filename  = "beneficiaries_{$timestamp}";

        // Log the export
        VerificationActivityLog::create([
            'user_id'              => Auth::id(),
            'beneficiary_id'       => null,
            'activity_type'        => 'view',
            'activity_description' => "Beneficiary list exported as {$format} by " . Auth::user()->name
                . ' (' . $query->count() . ' records)',
            'ip_address'           => $request->ip(),
            'user_agent'           => $request->userAgent(),
            'status'               => 'success',
        ]);

        if ($format === 'pdf') {
            $beneficiaries = $query->get();
            $pdf = Pdf::loadView('exports.beneficiaries', [
                'beneficiaries' => $beneficiaries,
                'exportedAt'    => now()->format('F d, Y H:i'),
                'exportedBy'    => Auth::user()->name,
                'totalCount'    => $beneficiaries->count(),
            ])->setPaper('a4', 'landscape');

            return $pdf->download("{$filename}.pdf");
        }

        $writerType = $format === 'xlsx'
            ? \Maatwebsite\Excel\Excel::XLSX
            : \Maatwebsite\Excel\Excel::CSV;

        return Excel::download(new BeneficiaryExport($query), "{$filename}.{$format}", $writerType);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Shared query builder
    // ─────────────────────────────────────────────────────────────────────────

    private function buildQuery(Request $request): Builder
    {
        $query = Beneficiary::query();

        // ── Full-text search (ILIKE for PostgreSQL) ───────────────────────
        if ($term = $request->input('query')) {
            $lower = mb_strtolower($term);
            $query->where(function (Builder $q) use ($lower) {
                $q->whereRaw('LOWER(family_head_name) ILIKE ?', ["%{$lower}%"])
                  ->orWhereRaw('LOWER(bin) ILIKE ?',            ["%{$lower}%"])
                  ->orWhere('contact_number', 'like',           "%{$lower}%")
                  ->orWhereRaw('LOWER(email) ILIKE ?',          ["%{$lower}%"])
                  ->orWhereRaw('LOWER(barangay) ILIKE ?',       ["%{$lower}%"]);
            });
        }

        // ── Address filters ───────────────────────────────────────────────
        if ($municipality = $request->input('municipality')) {
            $query->whereRaw('LOWER(municipality) = ?', [mb_strtolower($municipality)]);
        }
        if ($barangay = $request->input('barangay')) {
            $query->whereRaw('LOWER(barangay) = ?', [mb_strtolower($barangay)]);
        }

        // ── Status ────────────────────────────────────────────────────────
        $status = $request->input('status', 'all');
        if ($status === 'active') {
            $query->where('is_active', true);
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        }

        // ── Date range ────────────────────────────────────────────────────
        if ($dateFrom = $request->input('date_from')) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }
        if ($dateTo = $request->input('date_to')) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        // ── Income range ──────────────────────────────────────────────────
        if ($minIncome = $request->input('min_income')) {
            $query->where('annual_income', '>=', $minIncome);
        }
        if ($maxIncome = $request->input('max_income')) {
            $query->where('annual_income', '<=', $maxIncome);
        }

        // ── Household size ────────────────────────────────────────────────
        if ($householdSize = $request->input('household_size')) {
            $query->where('household_size', $householdSize);
        }

        // ── Sort ──────────────────────────────────────────────────────────
        match ($request->input('sort', 'newest')) {
            'oldest'     => $query->oldest(),
            'alpha'      => $query->orderBy('family_head_name', 'asc'),
            'alpha_desc' => $query->orderBy('family_head_name', 'desc'),
            'income_asc' => $query->orderBy('annual_income', 'asc'),
            'income_desc'=> $query->orderBy('annual_income', 'desc'),
            default      => $query->latest(),
        };

        return $query;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function getTotals(): array
    {
        return Cache::remember('beneficiary_totals', 60, fn () => [
            'all'      => Beneficiary::count(),
            'active'   => Beneficiary::where('is_active', true)->count(),
            'inactive' => Beneficiary::where('is_active', false)->count(),
        ]);
    }

    private function logSearch(string $term, int $results, string $type = 'search'): void
    {
        VerificationActivityLog::create([
            'user_id'              => Auth::id(),
            'beneficiary_id'       => null,
            'activity_type'        => 'view',
            'activity_description' => "Search [{$type}]: \"{$term}\" → {$results} result(s)",
            'ip_address'           => request()->ip(),
            'user_agent'           => request()->userAgent(),
            'status'               => 'success',
        ]);
    }
}
