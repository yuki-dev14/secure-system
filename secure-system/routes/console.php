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


