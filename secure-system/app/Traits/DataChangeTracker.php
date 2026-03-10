<?php

namespace App\Traits;

use App\Services\AuditLogService;

/**
 * DataChangeTracker
 *
 * Add this trait to any Eloquent model that requires a complete
 * before/after change log in the audit trail.
 *
 * Usage:
 *   use App\Traits\DataChangeTracker;
 *   class Beneficiary extends Model {
 *       use DataChangeTracker;
 *   }
 *
 * The trait hooks into three model events:
 *   - updating  → snapshot old attributes
 *   - updated   → compare snapshot with new attributes, write log
 *   - deleting  → log the full attribute set before the row disappears
 */
trait DataChangeTracker
{
    /**
     * Stores the original attributes before an update so they can be
     * compared with the post-save attributes in the updated event.
     */
    public array $oldAttributes = [];

    // ─────────────────────────────────────────────────────────────
    // Boot
    // ─────────────────────────────────────────────────────────────

    public static function bootDataChangeTracker(): void
    {
        // ── Capture before update ────────────────────────────────
        static::updating(function ($model) {
            $model->oldAttributes = $model->getOriginal();
        });

        // ── Log after update ─────────────────────────────────────
        static::updated(function ($model) {
            try {
                $beneficiaryId = match (true) {
                    $model instanceof \App\Models\Beneficiary   => $model->id,
                    property_exists($model, 'beneficiary_id')   => $model->beneficiary_id,
                    default                                      => null,
                };

                app(AuditLogService::class)->logDataChange(
                    get_class($model),
                    $model->oldAttributes,
                    $model->getAttributes(),
                    'edit',
                    $model->getKey(),
                    $beneficiaryId
                );
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error(
                    'DataChangeTracker@updated: ' . $e->getMessage()
                );
            }
        });

        // ── Log before delete ─────────────────────────────────────
        static::deleting(function ($model) {
            try {
                $beneficiaryId = match (true) {
                    $model instanceof \App\Models\Beneficiary  => $model->id,
                    property_exists($model, 'beneficiary_id')  => $model->beneficiary_id,
                    default                                     => null,
                };

                app(AuditLogService::class)->logActivity(
                    'delete',
                    'Deleted ' . class_basename($model) . ' ID: ' . $model->getKey(),
                    [
                        'activity_category' => 'data_change',
                        'resource_type'     => class_basename($model),
                        'resource_id'       => $model->getKey(),
                        'beneficiary_id'    => $beneficiaryId,
                        'old_values'        => $model->getAttributes(),
                        'severity'          => 'medium',
                    ]
                );
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error(
                    'DataChangeTracker@deleting: ' . $e->getMessage()
                );
            }
        });
    }
}
