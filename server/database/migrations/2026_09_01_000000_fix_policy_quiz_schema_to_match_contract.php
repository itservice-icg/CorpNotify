<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // "type" becomes a plain string so it can hold 'policy' in addition to
        // info/warning/critical without a DB-level enum migration every time a
        // new type is added. Validity is enforced in the controller via Rule::in.
        Schema::table('notifications', function (Blueprint $table) {
            $table->string('type', 20)->default('info')->change();
        });

        // Every step below is guarded with a column-existence check so this
        // migration is safe to run no matter what state a given database is
        // already in (some environments may have been migrated by hand,
        // partially, or in a different order while this feature was built).
        Schema::table('notifications', function (Blueprint $table) {
            if (Schema::hasColumn('notifications', 'requires_policy_ack')) {
                $table->dropColumn('requires_policy_ack');
            }
        });

        Schema::table('notifications', function (Blueprint $table) {
            if (Schema::hasColumn('notifications', 'policy_content') && ! Schema::hasColumn('notifications', 'policy_body')) {
                $table->renameColumn('policy_content', 'policy_body');
            } elseif (! Schema::hasColumn('notifications', 'policy_body')) {
                $table->text('policy_body')->nullable();
            }
        });

        // Quiz answers are the Thai words "ใช้" (use) / "ไม่ใช้" (not use), not a
        // yes/no boolean, so both the correct-answer key and submitted answers
        // are stored as short strings ('use' | 'not_use').
        if (Schema::hasTable('notification_quiz_questions')) {
            Schema::table('notification_quiz_questions', function (Blueprint $table) {
                $table->string('correct_answer', 20)->change();
            });
        }

        if (Schema::hasTable('notification_quiz_responses')) {
            Schema::table('notification_quiz_responses', function (Blueprint $table) {
                $table->string('answer', 20)->change();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('notification_quiz_responses')) {
            Schema::table('notification_quiz_responses', function (Blueprint $table) {
                $table->boolean('answer')->change();
            });
        }

        if (Schema::hasTable('notification_quiz_questions')) {
            Schema::table('notification_quiz_questions', function (Blueprint $table) {
                $table->boolean('correct_answer')->change();
            });
        }

        Schema::table('notifications', function (Blueprint $table) {
            if (Schema::hasColumn('notifications', 'policy_body') && ! Schema::hasColumn('notifications', 'policy_content')) {
                $table->renameColumn('policy_body', 'policy_content');
            }
            if (! Schema::hasColumn('notifications', 'requires_policy_ack')) {
                $table->boolean('requires_policy_ack')->default(false);
            }
        });

        Schema::table('notifications', function (Blueprint $table) {
            $table->enum('type', ['info', 'warning', 'critical'])->default('info')->change();
        });
    }
};
