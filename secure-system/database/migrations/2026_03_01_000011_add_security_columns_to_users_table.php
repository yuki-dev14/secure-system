<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add security-related columns to the users table.
     *
     * - password_changed_at: tracks when the password was last changed
     * - password_history: JSON array of last 5 bcrypt hashes for reuse prevention
     * - two_factor_enabled: boolean flag for future 2FA
     * - two_factor_secret: encrypted TOTP secret for future 2FA
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('password_changed_at')->nullable()->after('last_login_at')
                  ->comment('Timestamp of the last password change');

            $table->json('password_history')->nullable()->after('password_changed_at')
                  ->comment('JSON array of last 5 bcrypt hashes; used to prevent password reuse');

            // ── 2FA Preparation ──────────────────────────────────────────────
            $table->boolean('two_factor_enabled')->default(false)->after('password_history')
                  ->comment('Whether TOTP 2FA is enabled for this user');

            $table->text('two_factor_secret')->nullable()->after('two_factor_enabled')
                  ->comment('Encrypted TOTP secret for 2FA (filled when 2FA is configured)');
        });
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'password_changed_at',
                'password_history',
                'two_factor_enabled',
                'two_factor_secret',
            ]);
        });
    }
};
