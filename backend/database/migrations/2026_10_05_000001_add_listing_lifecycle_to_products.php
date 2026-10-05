<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamp('expired_at')->nullable();
            $table->timestamp('deletion_scheduled_at')->nullable()->index();
            foreach (['expiry', 'reminder'] as $stage) {
                foreach (['mail', 'database'] as $channel) {
                    $table->timestamp($stage.'_'.$channel.'_sent_at')->nullable();
                }
            }
            $table->softDeletes();
        });
        // Legacy publication has no recorded approval date. Require review,
        // rather than invent an approval or immediately expire old records.
        DB::table('products')->where('status', 'active')->update(['status' => 'pending']);
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropIndex(['expires_at']);
            $table->dropIndex(['deletion_scheduled_at']);
            $table->dropColumn(['approved_at', 'expires_at', 'expired_at', 'deletion_scheduled_at',
                'expiry_mail_sent_at', 'expiry_database_sent_at', 'reminder_mail_sent_at',
                'reminder_database_sent_at', 'deleted_at']);
        });
    }
};
