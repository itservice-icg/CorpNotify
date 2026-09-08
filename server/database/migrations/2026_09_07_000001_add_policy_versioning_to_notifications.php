<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->unsignedInteger('policy_version')->default(1)->after('policy_body');
            $table->foreignId('supersedes_notification_id')->nullable()->after('policy_version')
                ->constrained('notifications')->nullOnDelete();
            $table->dateTime('published_at')->nullable()->after('supersedes_notification_id');
            $table->index(['supersedes_notification_id', 'policy_version']);
        });

        DB::table('notifications')
            ->where('type', 'policy')
            ->whereNull('published_at')
            ->update(['published_at' => DB::raw('COALESCE(start_at, created_at)')]);
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropForeign(['supersedes_notification_id']);
            $table->dropIndex(['supersedes_notification_id', 'policy_version']);
            $table->dropColumn(['policy_version', 'supersedes_notification_id', 'published_at']);
        });
    }
};
