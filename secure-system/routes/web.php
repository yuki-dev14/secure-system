<?php

use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\BeneficiaryController;
use App\Http\Controllers\BeneficiarySearchController;
use App\Http\Controllers\ComplianceController;
use App\Http\Controllers\ComplianceDashboardController;
use App\Http\Controllers\FamilyMemberController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\QRCodeController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SecurityController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\RequirementsController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\DistributionController;
use App\Http\Controllers\VerificationController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin'       => Route::has('login'),
        'canRegister'    => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion'     => PHP_VERSION,
    ]);
});

/*
|--------------------------------------------------------------------------
| Authenticated & Verified Routes
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'verified'])->group(function () {

    // ── Dashboard ──────────────────────────────────────────────────────
    Route::get('/dashboard', fn () => Inertia::render('Dashboard'))->name('dashboard');

    // ── Profile ────────────────────────────────────────────────────────
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // ── Activity Log ───────────────────────────────────────────────────
    Route::get('/activity', [ActivityLogController::class, 'index'])->name('activity.index');

    // ── Security Dashboard ─────────────────────────────────────────────
    Route::get('/security', [SecurityController::class, 'index'])->name('security.index');
    Route::delete('/security/sessions', [SecurityController::class, 'terminateOtherSessions'])
         ->name('security.sessions.terminate');

    // ── Beneficiaries ──────────────────────────────────────────────────────
    Route::middleware('throttle:60,1')->group(function () {

        // List: all authenticated roles
        Route::get('/beneficiaries', [BeneficiaryController::class, 'index'])
             ->name('beneficiaries.index');

        // ── Search & Export — STATIC segments BEFORE wildcards ──────────────
        // Full paginated search (replaces/augments index)
        Route::get('/beneficiaries/search', [BeneficiarySearchController::class, 'search'])
             ->name('beneficiaries.search');

        // Advanced multi-criteria search
        Route::get('/beneficiaries/advanced-search', [BeneficiarySearchController::class, 'advancedSearch'])
             ->name('beneficiaries.advanced-search');

        // Export (download — all roles with view permission)
        Route::get('/beneficiaries/export', [BeneficiarySearchController::class, 'export'])
             ->name('beneficiaries.export');

        // Create / Update: Field Officer + Administrator
        Route::middleware('role:Field Officer,Administrator')->group(function () {
            // IMPORTANT: /create must be declared before /{id} to avoid route collision
            Route::get('/beneficiaries/create', [BeneficiaryController::class, 'create'])
                 ->name('beneficiaries.create');
            Route::post('/beneficiaries', [BeneficiaryController::class, 'store'])
                 ->name('beneficiaries.store');
            Route::get('/beneficiaries/{id}/edit', [BeneficiaryController::class, 'edit'])
                 ->name('beneficiaries.edit');
            Route::put('/beneficiaries/{id}', [BeneficiaryController::class, 'update'])
                 ->name('beneficiaries.update');
        });

        // Destroy: Administrator only
        Route::middleware('role:Administrator')->group(function () {
            Route::delete('/beneficiaries/{id}', [BeneficiaryController::class, 'destroy'])
                 ->name('beneficiaries.destroy');
        });

        // Show: all authenticated roles — AFTER static segments
        Route::get('/beneficiaries/{id}', [BeneficiaryController::class, 'show'])
             ->name('beneficiaries.show');
    });

    // ── Family Members (nested under beneficiaries) ────────────────────
    Route::middleware('throttle:60,1')->group(function () {

        // View family members: all authenticated roles
        Route::get('/beneficiaries/{beneficiaryId}/family-members', [FamilyMemberController::class, 'index'])
             ->name('family-members.index');

        // Manage family members: Field Officer + Administrator
        Route::middleware('role:Field Officer,Administrator')->group(function () {
            Route::post('/beneficiaries/{beneficiaryId}/family-members', [FamilyMemberController::class, 'store'])
                 ->name('family-members.store');
            Route::post('/beneficiaries/{beneficiaryId}/family-members/import', [FamilyMemberController::class, 'importCsv'])
                 ->name('family-members.import');
            Route::put('/family-members/{id}', [FamilyMemberController::class, 'update'])
                 ->name('family-members.update');
            Route::delete('/family-members/{id}', [FamilyMemberController::class, 'destroy'])
                 ->name('family-members.destroy');
        });
    });

    // ── User Management (Admin Only) ───────────────────────────────────
    Route::middleware('role:Administrator')->group(function () {
        Route::resource('users', UserController::class);
    });

    // ── Search API (JSON — for autocomplete) ───────────────────────────
    Route::middleware('throttle:120,1')->group(function () {
        Route::get('/api/beneficiaries/quick-search', [BeneficiarySearchController::class, 'quickSearch'])
             ->name('beneficiaries.quick-search');
    });

    // ── QR Code Management ─────────────────────────────────────────────────
    Route::prefix('qr')->group(function () {

        // ── Inertia page (Field Officer + Administrator) ──────────────────────
        Route::middleware('role:Field Officer,Administrator')
             ->get('/{beneficiaryId}/manage', [QRCodeController::class, 'showPage'])
             ->name('qr.manage');

        // ── Secure file serve endpoint — throttled at 30 req/min ─────────────
        Route::middleware(['role:Field Officer,Administrator', 'throttle:30,1'])
             ->get('/image/{beneficiaryId}/{filename}', [QRCodeController::class, 'serveImage'])
             ->where('filename', '[a-zA-Z0-9_\-]+\.(png|svg)')   // regex guard against traversal
             ->name('qr.image');

        // ── Throttled write operations (generate / regenerate) — 30 req/min ──
        Route::middleware('throttle:30,1')->group(function () {

            // Generate QR — Field Officer + Administrator
            Route::middleware('role:Field Officer,Administrator')
                 ->post('/generate/{beneficiaryId}', [QRCodeController::class, 'generate'])
                 ->name('qr.generate');

            // Regenerate QR — Administrator only
            Route::middleware('role:Administrator')
                 ->post('/regenerate/{beneficiaryId}', [QRCodeController::class, 'regenerate'])
                 ->name('qr.regenerate');

            // Batch-generate ID cards PDF — Administrator only
            Route::middleware('role:Administrator')
                 ->post('/batch-cards', [QRCodeController::class, 'batchGenerateCards'])
                 ->name('qr.batch-cards');
        });

        // ── Read endpoints (all authenticated users) — standard throttle ─────
        Route::middleware('throttle:60,1')->group(function () {

            // Get active QR code for beneficiary
            Route::get('/show/{beneficiaryId}', [QRCodeController::class, 'show'])
                 ->name('qr.show');

            // Validate a QR token
            Route::get('/validate/{token}', [QRCodeController::class, 'validateQR'])
                 ->name('qr.validate');

            // QR history — Field Officer + Administrator
            Route::middleware('role:Field Officer,Administrator')
                 ->get('/history/{beneficiaryId}', [QRCodeController::class, 'getHistory'])
                 ->name('qr.history');

            // Generate & download ID card PDF — Field Officer + Administrator
            Route::middleware('role:Field Officer,Administrator')
                 ->get('/card/{beneficiaryId}', [QRCodeController::class, 'generateCard'])
                 ->name('qr.card');
        });
    });
    // ── Verification & QR Scanning ────────────────────────────────────────────
    Route::prefix('verification')->name('verification.')->middleware('throttle:60,1')->group(function () {

        // Scanner Inertia page — Field Officer, Compliance Verifier, Administrator
        Route::middleware('role:Field Officer,Compliance Verifier,Administrator')
             ->get('/scanner', [VerificationController::class, 'showScannerPage'])
             ->name('scanner');

        // Scan QR token — Field Officer, Compliance Verifier, Administrator
        Route::middleware('role:Field Officer,Compliance Verifier,Administrator')
             ->post('/scan', [VerificationController::class, 'scan'])
             ->name('scan');

        // Manual verification confirmation — Compliance Verifier, Administrator
        Route::middleware('role:Compliance Verifier,Administrator')
             ->post('/verify/{beneficiaryId}', [VerificationController::class, 'verify'])
             ->name('verify');

        // Eligibility check — all authenticated
        Route::get('/eligibility/{beneficiaryId}', [VerificationController::class, 'checkEligibility'])
             ->name('eligibility');

        // Verification history — all authenticated
        Route::get('/history/{beneficiaryId}', [VerificationController::class, 'getVerificationHistory'])
             ->name('history');

        // Duplicate flags for a beneficiary — all authenticated
        Route::get('/duplicate-flags/{beneficiaryId}', [VerificationController::class, 'getDuplicateFlags'])
             ->name('duplicate-flags');

        // Override a duplicate block — Administrator only
        Route::middleware('role:Administrator')
             ->post('/override-duplicate/{beneficiaryId}', [VerificationController::class, 'overrideDuplicate'])
             ->name('override-duplicate');
    });

    // ── Requirements / Document Submission ─────────────────────────────────
    Route::prefix('requirements')->name('requirements.')->
        group(function () {

        // Inertia page — all authenticated
        Route::get('/{beneficiaryId}', [RequirementsController::class, 'requirementsPage'])
             ->name('page');

        // Upload — Field Officer + Administrator, rate-limited 30 per hour
        Route::middleware(['role:Field Officer,Administrator', 'throttle:30,60'])
             ->post('/upload/{beneficiaryId}', [RequirementsController::class, 'upload'])
             ->name('upload');

        // List all requirements for a beneficiary — all authenticated
        Route::get('/list/{beneficiaryId}', [RequirementsController::class, 'index'])
             ->name('index');

        // Show single requirement — all authenticated
        Route::get('/show/{requirementId}', [RequirementsController::class, 'show'])
             ->name('show');

        // Download file — all authenticated
        Route::get('/download/{requirementId}', [RequirementsController::class, 'download'])
             ->name('download');

        // Approve / Reject / Bulk — Compliance Verifier + Administrator
        Route::middleware('role:Compliance Verifier,Administrator')->group(function () {
            Route::post('/approve/{requirementId}', [RequirementsController::class, 'approve'])
                 ->name('approve');
            Route::post('/reject/{requirementId}', [RequirementsController::class, 'reject'])
                 ->name('reject');
            Route::post('/bulk-approve', [RequirementsController::class, 'bulkApprove'])
                 ->name('bulk-approve');
        });

        // Expired documents — Administrator only
        Route::middleware('role:Administrator')
             ->get('/expired', [RequirementsController::class, 'checkExpiredDocuments'])
             ->name('expired');

        // Completion status + eligibility — all authenticated
        Route::get('/completion/{beneficiaryId}', [RequirementsController::class, 'completionStatus'])
             ->name('completion');
    });

    // ── Compliance Recording & Tracking ────────────────────────────────────
    Route::prefix('compliance')->name('compliance.')->middleware('throttle:60,1')->group(function () {

        // Inertia page — all authenticated
        Route::get('/page/{beneficiaryId}', [ComplianceController::class, 'compliancePage'])
             ->name('page');

        // Read — all authenticated
        Route::get('/{beneficiaryId}', [ComplianceController::class, 'index'])
             ->name('index');
        Route::get('/show/{id}', [ComplianceController::class, 'show'])
             ->name('show');

        // Record — Field Officer + Administrator
        Route::middleware('role:Field Officer,Administrator')->group(function () {
            Route::post('/education', [ComplianceController::class, 'recordEducation'])
                 ->name('education');
            Route::post('/health',    [ComplianceController::class, 'recordHealth'])
                 ->name('health');
            Route::post('/fds',       [ComplianceController::class, 'recordFDS'])
                 ->name('fds');
        });

        // Verify — Compliance Verifier + Administrator
        Route::middleware('role:Compliance Verifier,Administrator')
             ->post('/verify/{id}', [ComplianceController::class, 'verifyCompliance'])
             ->name('verify');
    });

    // ── Compliance Monitoring Dashboard ──────────────────────────────────
    Route::prefix('dashboard/compliance')->name('dashboard.compliance.')->middleware('throttle:60,1')->group(function () {
        Route::get('/',               [ComplianceDashboardController::class, 'index'])              ->name('index');
        Route::get('/location',       [ComplianceDashboardController::class, 'complianceByLocation'])->name('location');
        Route::get('/trends',         [ComplianceDashboardController::class, 'complianceTrends'])   ->name('trends');
        Route::get('/at-risk',        [ComplianceDashboardController::class, 'atRiskBeneficiaries'])->name('at-risk');
        Route::get('/{beneficiaryId}',[ComplianceDashboardController::class, 'complianceDetails'])  ->name('details');
    });

    // ── In-App Notifications ──────────────────────────────────────────────
    Route::prefix('notifications')->name('notifications.')->group(function () {
        Route::get('/',           [NotificationController::class, 'index'])       ->name('index');
        Route::get('/all',        [NotificationController::class, 'all'])         ->name('all');
        Route::post('/read-all',  [NotificationController::class, 'markAllAsRead'])->name('read-all');
        Route::post('/{id}/read', [NotificationController::class, 'markAsRead'])  ->name('read');
    });

    // ── Cash Grant Distributions ──────────────────────────────────────────
    Route::prefix('distribution')->name('distribution.')->group(function () {

        // Inertia page — all authenticated
        Route::get('/page', [DistributionController::class, 'page'])->name('page');

        // Approval page — Compliance Verifier + Administrator
        Route::middleware('role:Compliance Verifier,Administrator')
             ->get('/approve-page/{beneficiaryId}', [DistributionController::class, 'approvalPage'])
             ->name('approval-page');

        // Approve for distribution — Compliance Verifier + Administrator, 30 req/min
        Route::middleware(['role:Compliance Verifier,Administrator', 'throttle:30,1'])
             ->post('/approve/{beneficiaryId}', [DistributionController::class, 'approveForDistribution'])
             ->name('approve');

        // Record distribution — Compliance Verifier + Administrator, 30 req/min
        Route::middleware(['role:Compliance Verifier,Administrator', 'throttle:30,1'])
             ->post('/record', [DistributionController::class, 'recordDistribution'])
             ->name('record');

        // Read endpoints — all authenticated, standard throttle
        Route::middleware('throttle:60,1')->group(function () {
            Route::get('/',                              [DistributionController::class, 'index'])    ->name('index');
            Route::get('/history/{beneficiaryId}',      [DistributionController::class, 'history'])  ->name('history');
            Route::get('/{id}',                         [DistributionController::class, 'show'])     ->name('show');
            Route::get('/{id}/receipt',                 [DistributionController::class, 'downloadReceipt'])->name('receipt.download');
        });

        // Reconciliation — Administrator only
        Route::middleware('role:Administrator')
             ->post('/reconcile', [DistributionController::class, 'reconcile'])
             ->name('reconcile');

        // Bulk distribution — Administrator only
        Route::middleware(['role:Administrator', 'throttle:10,1'])
             ->post('/bulk', [DistributionController::class, 'bulkDistribute'])
             ->name('bulk');

        // Serve signature image securely
        Route::get('/signature/{path}', [DistributionController::class, 'serveSignature'])
             ->name('signature.serve')
             ->where('path', '.+');
    });

    // ── Audit Logs (Administrator only) ──────────────────────────────────
    Route::prefix('audit-logs')->name('audit.')->middleware('role:Administrator')->group(function () {

        // Inertia page
        Route::get('/page', [AuditLogController::class, 'page'])->name('page');

        // JSON API endpoints
        Route::middleware('throttle:60,1')->group(function () {
            Route::get('/',                    [AuditLogController::class, 'index'])            ->name('index');
            Route::get('/statistics',          [AuditLogController::class, 'statistics'])       ->name('statistics');
            Route::get('/security-alerts',     [AuditLogController::class, 'securityAlerts'])   ->name('security-alerts');
            Route::get('/privacy-report',      [AuditLogController::class, 'generatePrivacyComplianceReport'])->name('privacy-report');
            Route::get('/{id}',                [AuditLogController::class, 'show'])             ->name('show');
        });

        Route::middleware('throttle:10,1')->group(function () {
            Route::post('/export',             [AuditLogController::class, 'export'])           ->name('export');
            Route::post('/{id}/acknowledge',   [AuditLogController::class, 'acknowledgeAlert'])->name('acknowledge');
        });
    });

    // ── Reports & Analytics ───────────────────────────────────────
    Route::prefix('reports')->name('reports.')->middleware('throttle:30,1')->group(function () {

        // Dashboard — all authenticated
        Route::get('/dashboard', [ReportController::class, 'dashboard'])->name('dashboard');

        // Beneficiary Statistics — all authenticated
        Route::post('/beneficiary-statistics', [ReportController::class, 'beneficiaryStatistics'])
             ->name('beneficiary-statistics');

        // Compliance Report — all authenticated
        Route::post('/compliance', [ReportController::class, 'complianceReport'])
             ->name('compliance');

        // Distribution Report — Administrator, Compliance Verifier
        Route::middleware('role:Administrator,Compliance Verifier')
             ->post('/distribution', [ReportController::class, 'distributionReport'])
             ->name('distribution');

        // Fraud Detection Report — Administrator only
        Route::middleware('role:Administrator')
             ->post('/fraud-detection', [ReportController::class, 'fraudDetectionReport'])
             ->name('fraud-detection');

        // User Activity Report — Administrator only
        Route::middleware('role:Administrator')
             ->post('/user-activity', [ReportController::class, 'userActivityReport'])
             ->name('user-activity');

        // QR Code Report — Administrator, Field Officer
        Route::middleware('role:Administrator,Field Officer')
             ->post('/qr-code', [ReportController::class, 'qrCodeReport'])
             ->name('qr-code');

        // Export — all authenticated (controller gates per report type internally)
        Route::post('/export/{type}', [ReportController::class, 'exportReport'])
             ->name('export')
             ->where('type', 'beneficiary|compliance|distribution|fraud|user|qr');
    });
});

require __DIR__ . '/auth.php';
