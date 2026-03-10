<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

/*
|--------------------------------------------------------------------------
| QR Code Expiration Check — runs daily at midnight (Asia/Manila)
|--------------------------------------------------------------------------
*/
Schedule::command('qr:check-expired')
    ->daily()
    ->at('00:00')
    ->timezone('Asia/Manila')
    ->description('Mark expired QR codes as invalid and log expiration events');

/*
|--------------------------------------------------------------------------
| Document Expiration Processing — runs daily at 07:00 (Asia/Manila)
| Marks expired submitted documents, sends notifications (30-day window),
| and refreshes compliance summary cache for affected beneficiaries.
|--------------------------------------------------------------------------
*/
Schedule::command('requirements:process-expirations', ['--notify-days=30'])
    ->dailyAt('07:00')
    ->timezone('Asia/Manila')
    ->withoutOverlapping()
    ->description('Process expired documents, send expiry notifications, refresh compliance cache');

/*
|--------------------------------------------------------------------------
| Compliance Alerts — daily at 08:00 (Asia/Manila)
| Sends non-compliant, pending-verification, and expiring-period alerts.
|--------------------------------------------------------------------------
*/
Schedule::command('compliance:generate-alerts')
    ->dailyAt('08:00')
    ->timezone('Asia/Manila')
    ->withoutOverlapping()
    ->description('Generate in-app compliance alerts for field officers and verifiers');

/*
|--------------------------------------------------------------------------
| Compliance Summary Recalculation — every hour
| Keeps the compliance_summary_cache table fresh for dashboard queries.
|--------------------------------------------------------------------------
*/
Schedule::command('compliance:recalculate-summaries')
    ->hourly()
    ->timezone('Asia/Manila')
    ->withoutOverlapping()
    ->description('Recalculate and refresh compliance summary cache for all beneficiaries');

/*
|--------------------------------------------------------------------------
| Audit Log Archival — monthly on the 1st at 02:00 (Asia/Manila)
| Moves logs older than 2 years to audit_logs_archive for retention
| compliance (Data Privacy Act requires 5-year minimum retention).
|--------------------------------------------------------------------------
*/
Schedule::command('audit:archive')
    ->monthlyOn(1, '02:00')
    ->timezone('Asia/Manila')
    ->withoutOverlapping()
    ->description('Archive audit logs older than 2 years to audit_logs_archive table');

/*
|--------------------------------------------------------------------------
| Weekly Security Report — every Monday at 07:00 (Asia/Manila)
| Analyzes logs for suspicious patterns and flags anomalies.
|--------------------------------------------------------------------------
*/
Schedule::command('audit:security-report')
    ->weeklyOn(1, '07:00')
    ->timezone('Asia/Manila')
    ->withoutOverlapping()
    ->description('Generate weekly security analysis report from audit logs');

/*
|--------------------------------------------------------------------------
| Analytics & Reporting — Daily, Weekly, Monthly
|--------------------------------------------------------------------------
*/
Schedule::command('reports:daily')
    ->dailyAt('06:00')
    ->timezone('Asia/Manila')
    ->withoutOverlapping()
    ->description('Generate daily summary report (registrations, distributions, compliance, security)');

Schedule::command('reports:weekly')
    ->weeklyOn(1, '06:00')   // Every Monday at 6 AM
    ->timezone('Asia/Manila')
    ->withoutOverlapping()
    ->description('Generate comprehensive weekly summary report with trend analysis');

Schedule::command('reports:monthly')
    ->monthlyOn(1, '06:00')  // 1st of every month at 6 AM
    ->timezone('Asia/Manila')
    ->withoutOverlapping()
    ->description('Generate complete monthly report with all metrics, comparison, and PDF archive');
