<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->boolean('requires_policy_ack')->default(false)->after('url');
            $table->text('policy_content')->nullable()->after('requires_policy_ack');
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropColumn(['requires_policy_ack', 'policy_content']);
        });
    }
};
