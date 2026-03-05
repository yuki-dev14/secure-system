<?php

namespace App\Services;

use App\Models\Beneficiary;
use App\Models\ComplianceRecord;
use App\Models\Notification;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ComplianceAlertService
{
    // ─────────────────────────────────────────────────────────────────────────
    // generateAlerts
    // ─────────────────────────────────────────────────────────────────────────

    public function generateAlerts(): array
    {
        $generated = [
            'non_compliant'       => 0,
            'pending_verification'=> 0,
            'expiring_period'     => 0,
        ];

        $period = Carbon::now()->format('Y-m');
        $endOfMonth = Carbon::now()->endOfMonth();
        $daysLeft = Carbon::now()->diffInDays($endOfMonth, false);

        // ── 1. Non-compliant beneficiaries → alert Field Officers ────────────
        $nonCompliant = DB::table('compliance_summary_cache')
            ->whereIn('overall_compliance_status', ['non_compliant', 'partial'])
            ->get();

        $fieldOfficers = User::where('role', 'Field Officer')->pluck('id');

        foreach ($nonCompliant as $cache) {
            $beneficiary = Beneficiary::select('id', 'bin', 'family_head_name', 'municipality')
                ->find($cache->beneficiary_id);
            if (!$beneficiary) continue;

            $missing = json_decode($cache->missing_requirements ?? '[]', true) ?? [];
            $statusLabel = $cache->overall_compliance_status === 'non_compliant'
                ? 'NON-COMPLIANT' : 'PARTIALLY COMPLIANT';

            $message = sprintf(
                'Beneficiary %s (%s) from %s is %s for period %s. %s',
                $beneficiary->family_head_name,
                $beneficiary->bin,
                $beneficiary->municipality,
                $statusLabel,
                $period,
                count($missing) ? 'Issues: ' . implode('; ', array_slice($missing, 0, 3)) : ''
            );

            foreach ($fieldOfficers as $userId) {
                // Deduplicate: don't create duplicate unread alerts for same beneficiary + period
                $exists = Notification::where('user_id', $userId)
                    ->where('notification_type', 'non_compliant_beneficiary')
                    ->whereNull('read_at')
                    ->whereJsonContains('data->beneficiary_id', $beneficiary->id)
                    ->exists();

                if (!$exists) {
                    $this->createInAppNotification(
                        $userId,
                        'non_compliant_beneficiary',
                        "⚠️ {$statusLabel}: {$beneficiary->family_head_name}",
                        $message,
                        [
                            'beneficiary_id'   => $beneficiary->id,
                            'bin'              => $beneficiary->bin,
                            'compliance_period'=> $period,
                            'status'           => $cache->overall_compliance_status,
                            'redirect_url'     => "/compliance/page/{$beneficiary->id}",
                        ]
                    );
                    $generated['non_compliant']++;
                }
            }
        }

        // ── 2. Pending Verifications → alert Compliance Verifiers ────────────
        $pendingCount = ComplianceRecord::whereNull('verified_at')
            ->where('compliance_period', $period)
            ->count();

        if ($pendingCount > 0) {
            $verifiers = User::whereIn('role', ['Compliance Verifier', 'Administrator'])->pluck('id');

            foreach ($verifiers as $userId) {
                $exists = Notification::where('user_id', $userId)
                    ->where('notification_type', 'pending_verification')
                    ->whereNull('read_at')
                    ->whereRaw("DATE(created_at) = ?", [Carbon::today()->toDateString()])
                    ->exists();

                if (!$exists) {
                    $this->createInAppNotification(
                        $userId,
                        'pending_verification',
                        "🕐 {$pendingCount} Record(s) Pending Verification",
                        "There are {$pendingCount} compliance record(s) awaiting verification for period {$period}.",
                        [
                            'pending_count'    => $pendingCount,
                            'compliance_period'=> $period,
                            'redirect_url'     => '/compliance',
                        ]
                    );
                    $generated['pending_verification']++;
                }
            }
        }

        // ── 3. Expiring compliance period (≤ 5 days left) ────────────────────
        if ($daysLeft <= 5 && $daysLeft >= 0) {
            $allUsers = User::pluck('id');

            foreach ($allUsers as $userId) {
                $exists = Notification::where('user_id', $userId)
                    ->where('notification_type', 'expiring_period')
                    ->whereNull('read_at')
                    ->whereRaw("DATE(created_at) = ?", [Carbon::today()->toDateString()])
                    ->exists();

                if (!$exists) {
                    $this->createInAppNotification(
                        $userId,
                        'expiring_period',
                        "📅 Compliance Period Ending in {$daysLeft} Day(s)",
                        "The compliance period {$period} ends in {$daysLeft} day(s). Ensure all records are submitted and verified.",
                        [
                            'compliance_period' => $period,
                            'days_left'         => $daysLeft,
                            'end_date'          => $endOfMonth->toDateString(),
                        ]
                    );
                    $generated['expiring_period']++;
                }
            }
        }

        Log::info('ComplianceAlertService::generateAlerts', $generated);
        return $generated;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // createInAppNotification
    // ─────────────────────────────────────────────────────────────────────────

    public function createInAppNotification(
        int    $userId,
        string $type,
        string $title,
        string $message,
        array  $data = []
    ): Notification {
        return Notification::create([
            'user_id'           => $userId,
            'notification_type' => $type,
            'title'             => mb_substr($title, 0, 255),
            'message'           => $message,
            'data'              => $data,
            'read_at'           => null,
            'created_at'        => now(),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // sendEmailAlert
    // ─────────────────────────────────────────────────────────────────────────

    public function sendEmailAlert(int $userId, string $subject, string $message): bool
    {
        try {
            $user = User::find($userId);
            if (!$user || !$user->email) {
                Log::warning("ComplianceAlertService: User #{$userId} not found or has no email.");
                return false;
            }

            Mail::raw($message, function ($mail) use ($user, $subject) {
                $mail->to($user->email, $user->name)
                     ->subject("[SECURE] {$subject}");
            });

            Log::info("ComplianceAlertService: Email sent to {$user->email} — {$subject}");
            return true;
        } catch (\Throwable $e) {
            Log::error("ComplianceAlertService::sendEmailAlert failed for user #{$userId}: " . $e->getMessage());
            return false;
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // getUnreadNotifications
    // ─────────────────────────────────────────────────────────────────────────

    public function getUnreadNotifications(int $userId): array
    {
        $notifications = Notification::forUser($userId)
            ->unread()
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->map(fn($n) => [
                'id'                => $n->id,
                'notification_type' => $n->notification_type,
                'icon'              => $n->icon,
                'severity'          => $n->severity,
                'title'             => $n->title,
                'message'           => $n->message,
                'data'              => $n->data ?? [],
                'created_at'        => $n->created_at?->toIso8601String(),
                'created_at_human'  => $n->created_at?->diffForHumans(),
            ]);

        return [
            'unread_count'  => $notifications->count(),
            'notifications' => $notifications->values(),
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // markAsRead
    // ─────────────────────────────────────────────────────────────────────────

    public function markAsRead(int $notificationId, int $userId): bool
    {
        $notification = Notification::where('id', $notificationId)
            ->where('user_id', $userId)
            ->first();

        if (!$notification) {
            return false;
        }

        $notification->markAsRead();
        return true;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // markAllAsRead
    // ─────────────────────────────────────────────────────────────────────────

    public function markAllAsRead(int $userId): int
    {
        return Notification::forUser($userId)
            ->unread()
            ->update(['read_at' => now()]);
    }
}
