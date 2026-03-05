<?php

namespace App\Services;

use App\Models\Beneficiary;
use App\Models\DuplicateDetectionLog;
use App\Models\VerificationActivityLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * DuplicateDetectionService
 *
 * Handles both pre-registration duplicate checks and real-time
 * duplicate / ghost detection during QR scanning.
 */
class DuplicateDetectionService
{
    /** Percentage threshold (0–100) above which two names are considered duplicates */
    const NAME_SIMILARITY_THRESHOLD = 85;

    /** Max families allowed at the same barangay-level address */
    const MAX_FAMILIES_PER_ADDRESS = 3;

    // ─────────────────────────────────────────────────────────────────────────
    // Original pre-registration API (unchanged)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Run all duplicate checks against the existing database.
     *
     * @param  array       $data         Incoming beneficiary data
     * @param  int|null    $excludeId    Skip this beneficiary ID (update scenario)
     * @return array       ['found' => bool, 'matches' => [...]]
     */
    public function detectDuplicates(array $data, ?int $excludeId = null): array
    {
        $candidates = Beneficiary::when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
            ->get();

        $matches = [];

        foreach ($candidates as $candidate) {
            $reasons = [];
            $score   = 0;

            // 1. Exact name match
            if (strtolower(trim($candidate->family_head_name)) === strtolower(trim($data['family_head_name']))) {
                $reasons[] = 'Exact name match';
                $score     = max($score, 100);
            } else {
                // 2. Similar name (Levenshtein / similar_text)
                $similarity = $this->checkNameSimilarity(
                    $candidate->family_head_name,
                    $data['family_head_name']
                );
                if ($similarity >= self::NAME_SIMILARITY_THRESHOLD) {
                    $reasons[] = "Similar name ({$similarity}% match)";
                    $score     = max($score, $similarity);
                }
            }

            // 3. Same address + contact number
            if ($this->checkAddressMatchByFields($candidate->barangay, $candidate->municipality, $candidate->province, $data) &&
                $this->checkContactMatch($candidate->contact_number, $data['contact_number'])) {
                $reasons[] = 'Same address and contact number';
                $score     = max($score, 95);
            }

            // 4. Same contact number, different name
            if (
                $this->checkContactMatch($candidate->contact_number, $data['contact_number']) &&
                strtolower(trim($candidate->family_head_name)) !== strtolower(trim($data['family_head_name']))
            ) {
                $reasons[] = 'Same contact number with different name';
                $score     = max($score, 80);
            }

            if (! empty($reasons)) {
                $matches[] = [
                    'beneficiary' => [
                        'id'                => $candidate->id,
                        'bin'               => $candidate->bin,
                        'family_head_name'  => $candidate->family_head_name,
                        'contact_number'    => $candidate->contact_number,
                        'barangay'          => $candidate->barangay,
                        'municipality'      => $candidate->municipality,
                        'province'          => $candidate->province,
                        'civil_status'      => $candidate->civil_status,
                        'gender'            => $candidate->gender,
                        'is_active'         => $candidate->is_active,
                    ],
                    'reasons'     => $reasons,
                    'score'       => $score,
                    'type'        => $this->resolveType($reasons),
                ];
            }
        }

        usort($matches, fn ($a, $b) => $b['score'] <=> $a['score']);

        return [
            'found'   => count($matches) > 0,
            'matches' => $matches,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Real-time scan detection (new)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Run all duplicate checks in the context of a QR scan.
     *
     * Returns a structured detection result including:
     *   - is_duplicate, duplicate_type, confidence_score
     *   - similar_beneficiaries, recommendation
     */
    public function checkDuringVerification(int $beneficiaryId, string $scannedToken): array
    {
        $detections = [];

        // 1. Recent scan check
        $recentScanResult = $this->checkRecentScans($scannedToken);
        if ($recentScanResult['scan_count'] > 0) {
            $detections[] = [
                'type'       => 'recent_scan',
                'score'      => $this->recentScanScore($recentScanResult['scan_count']),
                'data'       => $recentScanResult,
                'description'=> "This QR token was scanned {$recentScanResult['scan_count']} time(s) in the last 5 minutes.",
            ];
        }

        // 2. Address duplicate check
        $addressResult = $this->checkAddressDuplicates($beneficiaryId);
        if ($addressResult['count'] > self::MAX_FAMILIES_PER_ADDRESS) {
            $detections[] = [
                'type'        => 'address_match',
                'score'       => min(90, 60 + ($addressResult['count'] - self::MAX_FAMILIES_PER_ADDRESS) * 10),
                'data'        => $addressResult,
                'description' => "{$addressResult['count']} families share the same address (threshold: " . self::MAX_FAMILIES_PER_ADDRESS . ').',
            ];
        }

        // 3. Multiple registration check (same person, different BIN)
        $multiRegResult = $this->checkMultipleRegistrations($beneficiaryId);
        if ($multiRegResult['found']) {
            // Use the highest similarity score from matches
            $topScore = collect($multiRegResult['matches'])->max('score') ?? 70;
            $detections[] = [
                'type'        => 'multiple_registration',
                'score'       => (int) $topScore,
                'data'        => $multiRegResult,
                'description' => count($multiRegResult['matches']) . ' similar beneficiary registration(s) found.',
            ];
        }

        // ── Build aggregated result ───────────────────────────────────────────
        if (empty($detections)) {
            return [
                'is_duplicate'         => false,
                'duplicate_type'       => null,
                'confidence_score'     => 0,
                'similar_beneficiaries'=> [],
                'recommendation'       => 'allow',
                'detections'           => [],
            ];
        }

        // Pick the highest-confidence detection as the primary
        usort($detections, fn ($a, $b) => $b['score'] <=> $a['score']);
        $primary = $detections[0];

        $confidenceScore = $primary['score'];
        $recommendation  = $this->resolveRecommendation($confidenceScore);

        // Collect similar beneficiaries from all detections
        $similarBeneficiaries = $this->collectSimilarBeneficiaries($detections, $beneficiaryId);

        // Log all significant detections
        $this->persistDetections($beneficiaryId, $detections, $confidenceScore, $recommendation);

        return [
            'is_duplicate'          => true,
            'duplicate_type'        => $primary['type'],
            'confidence_score'      => $confidenceScore,
            'similar_beneficiaries' => $similarBeneficiaries,
            'recommendation'        => $recommendation,
            'detections'            => collect($detections)->map(fn($d) => [
                'type'        => $d['type'],
                'score'       => $d['score'],
                'description' => $d['description'],
            ])->toArray(),
        ];
    }

    /**
     * Check if a token was recently scanned (within $minutes minutes).
     *
     * Queries verification_activity_logs for recent successful scans of
     * this token. Uses Cache as L1 for speed, falls back to DB.
     */
    public function checkRecentScans(string $token, int $minutes = 5): array
    {
        $cacheKey = 'recent_scans_' . md5($token);

        // L1: cache-backed fast path
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $cutoff = Carbon::now()->subMinutes($minutes);

        $rows = DB::table('verification_activity_logs')
            ->where('activity_type', 'scan')
            ->where('activity_description', 'like', '%' . substr($token, 0, 8) . '%')
            ->where('created_at', '>=', $cutoff)
            ->where('status', 'success')
            ->select(['user_id', 'created_at', 'ip_address'])
            ->orderByDesc('created_at')
            ->get();

        $result = [
            'scan_count'    => $rows->count(),
            'last_scan_time'=> $rows->first()?->created_at,
            'scanning_users'=> $rows->unique('user_id')->pluck('user_id')->values()->toArray(),
        ];

        // Cache for 60 s to reduce DB pressure during rapid re-scans
        Cache::put($cacheKey, $result, 60);

        return $result;
    }

    /**
     * Check whether an unusual number of families share the same address.
     */
    public function checkAddressDuplicates(int $beneficiaryId): array
    {
        $beneficiary = Beneficiary::find($beneficiaryId);

        if (! $beneficiary) {
            return ['count' => 0, 'beneficiaries' => []];
        }

        $matches = Beneficiary::where('id', '!=', $beneficiaryId)
            ->where('is_active', true)
            ->whereRaw('LOWER(barangay)     = LOWER(?)', [$beneficiary->barangay])
            ->whereRaw('LOWER(municipality) = LOWER(?)', [$beneficiary->municipality])
            ->whereRaw('LOWER(province)     = LOWER(?)', [$beneficiary->province])
            ->select(['id', 'bin', 'family_head_name', 'contact_number', 'barangay', 'municipality', 'province',
                      'household_size', 'created_at', 'gender', 'civil_status'])
            ->get();

        return [
            'count'         => $matches->count(),
            'address'       => implode(', ', array_filter([
                $beneficiary->barangay,
                $beneficiary->municipality,
                $beneficiary->province,
            ])),
            'beneficiaries' => $matches->map(fn($b) => [
                'id'               => $b->id,
                'bin'              => $b->bin,
                'family_head_name' => $b->family_head_name,
                'contact_number'   => $b->contact_number,
                'household_size'   => $b->household_size,
                'registered_at'    => optional($b->created_at)->format('M d, Y'),
                'similarity_type'  => 'address_match',
            ])->toArray(),
        ];
    }

    /**
     * Check if the beneficiary appears to have multiple registrations
     * based on name similarity and contact number matching.
     */
    public function checkMultipleRegistrations(int $beneficiaryId): array
    {
        $beneficiary = Beneficiary::find($beneficiaryId);
        if (! $beneficiary) {
            return ['found' => false, 'matches' => []];
        }

        $candidates = Beneficiary::where('id', '!=', $beneficiaryId)
            ->where('is_active', true)
            ->get(['id', 'bin', 'family_head_name', 'contact_number', 'barangay',
                   'municipality', 'province', 'gender', 'civil_status', 'created_at',
                   'family_head_birthdate', 'household_size']);

        $matches = [];

        foreach ($candidates as $candidate) {
            $reasons = [];
            $score   = 0;

            // Name similarity
            $nameSim = $this->checkNameSimilarity(
                $beneficiary->family_head_name,
                $candidate->family_head_name
            );
            if ($nameSim >= self::NAME_SIMILARITY_THRESHOLD) {
                $reasons[] = "Similar name ({$nameSim}%)";
                $score     = max($score, $nameSim);
            }

            // Contact match
            if (
                $beneficiary->contact_number &&
                $candidate->contact_number &&
                $this->checkContactMatch($beneficiary->contact_number, $candidate->contact_number)
            ) {
                $reasons[] = 'Same contact number';
                $score     = max($score, 85);
            }

            // Birthdate match (compare as date strings)
            $bDate  = $beneficiary->family_head_birthdate instanceof \Carbon\Carbon
                ? $beneficiary->family_head_birthdate->toDateString()
                : (string) $beneficiary->family_head_birthdate;
            $cDate  = $candidate->family_head_birthdate instanceof \Carbon\Carbon
                ? $candidate->family_head_birthdate->toDateString()
                : (string) $candidate->family_head_birthdate;

            if ($beneficiary->family_head_birthdate && $candidate->family_head_birthdate && $bDate === $cDate) {
                $reasons[] = 'Same birthdate';
                $score     = (int) min(100, $score * 1.1 + 10);
            }

            if (! empty($reasons)) {
                $matches[] = [
                    'id'               => $candidate->id,
                    'bin'              => $candidate->bin,
                    'family_head_name' => $candidate->family_head_name,
                    'contact_number'   => $candidate->contact_number,
                    'barangay'         => $candidate->barangay,
                    'municipality'     => $candidate->municipality,
                    'province'         => $candidate->province,
                    'gender'           => $candidate->gender,
                    'civil_status'     => $candidate->civil_status,
                    'household_size'   => $candidate->household_size,
                    'registered_at'    => optional($candidate->created_at)->format('M d, Y'),
                    'reasons'          => $reasons,
                    'score'            => $score,
                    'similarity_type'  => 'multiple_registration',
                ];
            }
        }

        usort($matches, fn ($a, $b) => $b['score'] <=> $a['score']);

        return [
            'found'   => count($matches) > 0,
            'matches' => array_slice($matches, 0, 5),   // top 5
        ];
    }

    /**
     * Persist duplicate detection events to duplicate_detection_logs.
     * Maps new detection types to the existing enum by using 'address_match'
     * or 'name_match' for DB compatibility; extended details go in JSON.
     */
    public function logDuplicateDetection(
        int     $primaryId,
        ?int    $duplicateId,
        string  $type,
        array   $details = [],
        int     $confidenceScore = 0,
        string  $recommendation = 'flag'
    ): DuplicateDetectionLog {
        // Map runtime types to DB enum values
        $dbType = match ($type) {
            'recent_scan'           => 'token_collision',
            'address_match'         => 'address_match',
            'multiple_registration' => 'name_match',
            'contact_match'         => 'contact_match',
            default                 => 'name_match',
        };

        return DuplicateDetectionLog::create([
            'primary_beneficiary_id'     => $primaryId,
            'duplicate_beneficiary_id'   => $duplicateId,
            'duplicate_type'             => $dbType,
            'detection_date'             => now(),
            'detected_by_system_or_user' => 'system',
            'action_taken'               => 'flagged',
            'merged_or_flagged'          => 'flagged',
            'confidence_score'           => $confidenceScore,
            'detection_details'          => $details,
            'recommendation'             => $recommendation,
            'status'                     => 'active',
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Original public helpers (unchanged)
    // ─────────────────────────────────────────────────────────────────────────

    public function checkNameSimilarity(string $name1, string $name2): int
    {
        $n1 = strtolower(trim($name1));
        $n2 = strtolower(trim($name2));

        similar_text($n1, $n2, $similarPercent);

        $maxLen     = max(strlen($n1), strlen($n2));
        $levPercent = $maxLen > 0
            ? (1 - levenshtein($n1, $n2) / $maxLen) * 100
            : 100;

        return (int) round(max($similarPercent, $levPercent));
    }

    public function checkAddressMatch(Beneficiary $existing, array $incoming): bool
    {
        return strtolower($existing->barangay)     === strtolower($incoming['barangay']     ?? '') &&
               strtolower($existing->municipality) === strtolower($incoming['municipality'] ?? '') &&
               strtolower($existing->province)     === strtolower($incoming['province']     ?? '');
    }

    public function checkContactMatch(string $contact1, string $contact2): bool
    {
        return preg_replace('/\D/', '', $contact1) === preg_replace('/\D/', '', $contact2);
    }

    /**
     * Address match when only raw field strings are available (e.g. from stdClass query results).
     */
    public function checkAddressMatchByFields(
        ?string $barangay,
        ?string $municipality,
        ?string $province,
        array   $incoming
    ): bool {
        return strtolower((string) $barangay)     === strtolower($incoming['barangay']     ?? '') &&
               strtolower((string) $municipality) === strtolower($incoming['municipality'] ?? '') &&
               strtolower((string) $province)     === strtolower($incoming['province']     ?? '');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Private helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function recentScanScore(int $scanCount): int
    {
        // 1 scan in 5 min → 60, 2 → 80, 3+ → 95
        return match (true) {
            $scanCount >= 3 => 95,
            $scanCount === 2 => 80,
            default          => 60,
        };
    }

    private function resolveRecommendation(int $score): string
    {
        return match (true) {
            $score >= 90 => 'block',
            $score >= 65 => 'flag',
            default      => 'allow',
        };
    }

    private function collectSimilarBeneficiaries(array $detections, int $excludeId): array
    {
        $seen    = [];
        $similar = [];

        foreach ($detections as $detection) {
            $beneficiaries = [];

            if ($detection['type'] === 'address_match') {
                $beneficiaries = $detection['data']['beneficiaries'] ?? [];
            } elseif ($detection['type'] === 'multiple_registration') {
                $beneficiaries = $detection['data']['matches'] ?? [];
            }

            foreach ($beneficiaries as $b) {
                if (! isset($seen[$b['id']]) && $b['id'] !== $excludeId) {
                    $seen[$b['id']] = true;
                    $similar[]      = $b;
                }
            }
        }

        // Sort by score descending
        usort($similar, fn ($a, $b) => ($b['score'] ?? 0) <=> ($a['score'] ?? 0));

        return array_slice($similar, 0, 10);
    }

    private function persistDetections(
        int    $beneficiaryId,
        array  $detections,
        int    $confidenceScore,
        string $recommendation
    ): void {
        foreach ($detections as $detection) {
            try {
                $similarIds = [];
                if ($detection['type'] === 'address_match') {
                    $similarIds = array_column($detection['data']['beneficiaries'] ?? [], 'id');
                } elseif ($detection['type'] === 'multiple_registration') {
                    $similarIds = array_column($detection['data']['matches'] ?? [], 'id');
                }

                // Log one entry per similar beneficiary (or one for recent_scan)
                $targets = ! empty($similarIds) ? $similarIds : [null];

                foreach ($targets as $targetId) {
                    // Avoid redundant logs (same pair in last 30 min)
                    $alreadyLogged = DuplicateDetectionLog::where('primary_beneficiary_id', $beneficiaryId)
                        ->where('duplicate_beneficiary_id', $targetId)
                        ->where('status', 'active')
                        ->where('detection_date', '>=', now()->subMinutes(30))
                        ->exists();

                    if ($alreadyLogged) continue;

                    $this->logDuplicateDetection(
                        $beneficiaryId,
                        $targetId,
                        $detection['type'],
                        [
                            'description'    => $detection['description'],
                            'detection_data' => $detection['data'],
                        ],
                        $detection['score'],
                        $recommendation
                    );
                }
            } catch (\Throwable $e) {
                Log::error('DuplicateDetectionService: failed to persist detection', [
                    'beneficiary_id' => $beneficiaryId,
                    'type'           => $detection['type'],
                    'error'          => $e->getMessage(),
                ]);
            }
        }
    }

    private function resolveType(array $reasons): string
    {
        foreach ($reasons as $r) {
            if (str_contains($r, 'name'))    return 'name_match';
            if (str_contains($r, 'address')) return 'address_match';
            if (str_contains($r, 'contact')) return 'contact_match';
        }
        return 'name_match';
    }
}
