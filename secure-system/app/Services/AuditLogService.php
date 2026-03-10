<?php

namespace App\Services;

use App\Models\VerificationActivityLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\DB;

/**
 * AuditLogService
 *
 * Centralized service for creating immutable audit trail entries in
 * verification_activity_logs. All controllers and models should route
 * their logging through this service rather than writing directly to
 * the table so that field handling remains consistent.
 */
class AuditLogService
{
    // Sensitive fields that should never be stored in request_data
    private const REDACTED_FIELDS = [
        'password', 'password_confirmation', 'current_password',
        'token', 'secret', 'api_key', 'card_number', 'cvv',
        'signature_data_url', // large base64 blob — strip to avoid bloat
    ];

    // ─────────────────────────────────────────────────────────────
    // Core log method
    // ─────────────────────────────────────────────────────────────

    /**
     * Write a general audit log entry.
     *
     * @param  string  $activityType  One of the enum values
     * @param  string  $description   Human-readable description
     * @param  array   $additionalData  Optional overrides / extra fields
     * @return int|null  ID of the created log entry
     */
    public function logActivity(
        string $activityType,
        string $description,
        array  $additionalData = []
    ): ?int {
        try {
            $entry = VerificationActivityLog::create([
                'user_id'              => $additionalData['user_id']       ?? Auth::id(),
                'beneficiary_id'       => $additionalData['beneficiary_id'] ?? null,
                'activity_type'        => $activityType,
                'activity_category'    => $additionalData['activity_category'] ?? $this->inferCategory($activityType),
                'activity_description' => $description,
                'ip_address'           => request()->ip()        ?? '0.0.0.0',
                'user_agent'           => request()->userAgent() ?? null,
                'timestamp'            => now(),
                'status'               => $additionalData['status']          ?? 'success',
                'remarks'              => $additionalData['remarks']          ?? null,
                'old_values'           => isset($additionalData['old_values'])
                                            ? $this->safeJson($additionalData['old_values']) : null,
                'new_values'           => isset($additionalData['new_values'])
                                            ? $this->safeJson($additionalData['new_values']) : null,
                'request_data'         => isset($additionalData['request_data'])
                                            ? $this->sanitizePayload($additionalData['request_data']) : null,
                'response_status'      => $additionalData['response_status'] ?? 200,
                'execution_time'       => $additionalData['execution_time']  ?? null,
                'resource_type'        => $additionalData['resource_type']   ?? null,
                'resource_id'          => $additionalData['resource_id']     ?? null,
                'severity'             => $additionalData['severity']        ?? null,
                'is_acknowledged'      => false,
            ]);

            return $entry->id;
        } catch (\Throwable $e) {
            // Audit logging must NEVER crash the main application
            Log::error('AuditLogService: Failed to write log entry: ' . $e->getMessage(), [
                'type'        => $activityType,
                'description' => $description,
            ]);
            return null;
        }
    }

    // ─────────────────────────────────────────────────────────────
    // Specialised helpers
    // ─────────────────────────────────────────────────────────────

    /**
     * Log a data-access event (view / download / export).
     *
     * @param  string  $resourceType   e.g. 'Beneficiary', 'Document'
     * @param  int     $resourceId     Primary key of the accessed record
     * @param  string  $action         'view' | 'download' | 'export'
     * @param  string  $sensitivity    'public' | 'sensitive' | 'confidential'
     */
    public function logDataAccess(
        string $resourceType,
        int    $resourceId,
        string $action = 'view',
        string $sensitivity = 'sensitive'
    ): ?int {
        $actorName = Auth::user()?->name ?? 'System';
        return $this->logActivity(
            $action,
            "{$actorName} accessed {$resourceType} #{$resourceId} (sensitivity: {$sensitivity}).",
            [
                'activity_category' => 'data_access',
                'resource_type'     => $resourceType,
                'resource_id'       => $resourceId,
                'severity'          => $sensitivity === 'confidential' ? 'medium' : 'low',
            ]
        );
    }

    /**
     * Log a model change event with before/after diff.
     *
     * @param  string  $modelClass   Fully-qualified class name
     * @param  array   $oldData      Attributes before update
     * @param  array   $newData      Attributes after update
     * @param  string  $action       'edit' | 'delete'
     * @param  int|null  $resourceId
     * @param  int|null  $beneficiaryId
     * @param  string|null  $reason   Optional reason for change
     */
    public function logDataChange(
        string  $modelClass,
        array   $oldData,
        array   $newData,
        string  $action = 'edit',
        ?int    $resourceId = null,
        ?int    $beneficiaryId = null,
        ?string $reason = null
    ): ?int {
        $modelName = class_basename($modelClass);
        $changedFields = $this->diffArrays($oldData, $newData);
        $changedList   = implode(', ', array_keys($changedFields));
        $actorName     = Auth::user()?->name ?? 'System';

        $description = "{$actorName} {$action}d {$modelName}";
        if ($resourceId) {
            $description .= " #{$resourceId}";
        }
        if ($changedList) {
            $description .= ". Changed fields: [{$changedList}].";
        }
        if ($reason) {
            $description .= " Reason: {$reason}.";
        }

        return $this->logActivity(
            $action,
            $description,
            [
                'activity_category' => 'data_change',
                'beneficiary_id'    => $beneficiaryId,
                'resource_type'     => $modelName,
                'resource_id'       => $resourceId,
                'old_values'        => $this->redactSensitive($oldData),
                'new_values'        => $this->redactSensitive($newData),
                'remarks'           => $reason,
            ]
        );
    }

    /**
     * Log a failed access / authentication attempt.
     *
     * Triggers an alert if the same IP has failed more than 5 times
     * in the last 60 minutes.
     *
     * @param  string  $attemptType  'failed_login' | 'failed_scan' | 'unauthorized_access'
     * @param  string  $reason
     */
    public function logFailedAttempt(
        string $attemptType,
        string $reason,
        array  $additionalData = []
    ): ?int {
        $id = $this->logActivity(
            'verify',
            "Failed {$attemptType}: {$reason}",
            array_merge($additionalData, [
                'activity_category' => 'security',
                'status'            => 'failed',
                'severity'          => 'medium',
                'remarks'           => $reason,
            ])
        );

        // Check for repeated failures from this IP in the last hour
        $ip            = request()->ip() ?? '0.0.0.0';
        $recentFails   = VerificationActivityLog::where('ip_address', $ip)
            ->where('status', 'failed')
            ->where('activity_category', 'security')
            ->where('timestamp', '>=', now()->subHour())
            ->count();

        if ($recentFails >= 5) {
            $this->logSecurityEvent(
                'multiple_failed_attempts',
                "IP {$ip} has had {$recentFails} failed attempts in the last hour (latest: {$attemptType}).",
                'high',
                $additionalData
            );
        }

        return $id;
    }

    /**
     * Log a security event.
     *
     * High / critical severity events are also written to the Laravel
     * application log to ensure they are captured even if the database
     * is unavailable.
     *
     * @param  string  $eventType   'suspicious_activity' | 'multiple_failed_logins' | 'unusual_access_pattern'
     * @param  string  $description
     * @param  string  $severity    'low' | 'medium' | 'high' | 'critical'
     */
    public function logSecurityEvent(
        string $eventType,
        string $description,
        string $severity = 'medium',
        array  $additionalData = []
    ): ?int {
        if (in_array($severity, ['high', 'critical'])) {
            Log::channel('stack')->critical(
                "[SECURITY-{$severity}] {$eventType}: {$description}",
                ['ip' => request()->ip(), 'user_id' => Auth::id()]
            );
        } elseif ($severity === 'medium') {
            Log::warning("[SECURITY-medium] {$eventType}: {$description}");
        }

        return $this->logActivity(
            'security_event',
            $description,
            array_merge($additionalData, [
                'activity_category' => 'security',
                'severity'          => $severity,
                'remarks'           => $eventType,
            ])
        );
    }

    // ─────────────────────────────────────────────────────────────
    // Internal helpers
    // ─────────────────────────────────────────────────────────────

    private function inferCategory(string $activityType): string
    {
        return match ($activityType) {
            'view', 'download', 'export' => 'data_access',
            'edit', 'delete'             => 'data_change',
            'login', 'logout', 'verify',
            'security_event'             => 'security',
            'scan', 'approve', 'reject',
            'distribution',
            'bulk_distribution'          => 'compliance',
            default                      => 'system',
        };
    }

    private function diffArrays(array $old, array $new): array
    {
        $changed = [];
        $allKeys = array_unique(array_merge(array_keys($old), array_keys($new)));
        foreach ($allKeys as $key) {
            $oldVal = $old[$key] ?? null;
            $newVal = $new[$key] ?? null;
            // Skip identical or timestamp auto-fields
            if ($oldVal !== $newVal && !in_array($key, ['updated_at', 'created_at'])) {
                $changed[$key] = ['old' => $oldVal, 'new' => $newVal];
            }
        }
        return $changed;
    }

    private function redactSensitive(array $data): array
    {
        foreach (self::REDACTED_FIELDS as $field) {
            if (array_key_exists($field, $data)) {
                $data[$field] = '[REDACTED]';
            }
        }
        return $data;
    }

    private function sanitizePayload(array $payload): array
    {
        return $this->redactSensitive($payload);
    }

    /** Safely encode values — ensure it's array/null, not double-encoded string. */
    private function safeJson(mixed $value): ?array
    {
        if (is_array($value)) {
            return $value;
        }
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            return is_array($decoded) ? $decoded : ['raw' => $value];
        }
        return null;
    }
}
