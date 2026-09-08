<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $attemptTableExisted = Schema::hasTable('notification_quiz_attempts');

        if (! $attemptTableExisted) {
            Schema::create('notification_quiz_attempts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('notification_id')->constrained()->cascadeOnDelete();
                $table->foreignId('device_id')->constrained()->cascadeOnDelete();
                $table->unsignedInteger('attempt_no');
                $table->boolean('passed')->default(false);
                $table->dateTime('submitted_at');
                $table->timestamps();

                $table->unique(['notification_id', 'device_id', 'attempt_no'], 'notification_device_attempt_unique');
                $table->index(['notification_id', 'device_id', 'submitted_at'], 'notification_device_submitted_idx');
            });
        }

        if ($attemptTableExisted) {
            Schema::table('notification_quiz_attempts', function (Blueprint $table) {
                if (! Schema::hasColumn('notification_quiz_attempts', 'attempt_no')) {
                    $table->unsignedInteger('attempt_no')->nullable()->after('device_id');
                }
                if (! Schema::hasColumn('notification_quiz_attempts', 'submitted_at')) {
                    $table->dateTime('submitted_at')->nullable()->after('passed');
                }
            });

            if (Schema::hasColumn('notification_quiz_attempts', 'answers')) {
                Schema::table('notification_quiz_attempts', function (Blueprint $table) {
                    $table->json('answers')->nullable()->change();
                });
            }

            $legacyGroups = DB::table('notification_quiz_attempts')
                ->orderBy('notification_id')->orderBy('device_id')->orderBy('id')
                ->get()->groupBy(fn ($row) => $row->notification_id.':'.$row->device_id);

            foreach ($legacyGroups as $rows) {
                foreach ($rows->values() as $index => $row) {
                    DB::table('notification_quiz_attempts')->where('id', $row->id)->update([
                        'attempt_no' => $row->attempt_no ?: $index + 1,
                        'submitted_at' => $row->submitted_at ?: ($row->created_at ?: now()),
                    ]);
                }
            }

            Schema::table('notification_quiz_attempts', function (Blueprint $table) {
                $table->unique(['notification_id', 'device_id', 'attempt_no'], 'notification_device_attempt_unique');
                $table->index(['notification_id', 'device_id', 'submitted_at'], 'notification_device_submitted_idx');
            });
        }

        if (! Schema::hasTable('notification_quiz_attempt_answers')) {
            Schema::create('notification_quiz_attempt_answers', function (Blueprint $table) {
                $table->id();
                $table->foreignId('quiz_attempt_id')->constrained('notification_quiz_attempts')->cascadeOnDelete();
                $table->foreignId('quiz_question_id')->constrained('notification_quiz_questions')->restrictOnDelete();
                $table->string('question_snapshot', 1000);
                $table->string('answer', 20);
                $table->string('correct_answer', 20);
                $table->boolean('is_correct');
                $table->dateTime('answered_at');
                $table->timestamps();
                $table->unique(['quiz_attempt_id', 'quiz_question_id'], 'attempt_question_unique');
            });
        }

        $groups = DB::table('notification_quiz_responses')
            ->orderBy('notification_id')->orderBy('device_id')->orderBy('quiz_question_id')
            ->get()->groupBy(fn ($row) => $row->notification_id.':'.$row->device_id);

        foreach ($groups as $responses) {
            $first = $responses->first();
            $questionCount = DB::table('notification_quiz_questions')
                ->where('notification_id', $first->notification_id)->count();
            $passed = $responses->count() === $questionCount
                && $responses->every(fn ($row) => (bool) $row->is_correct);
            $submittedAt = $responses->max('answered_at') ?? now();
            $attemptNo = ((int) DB::table('notification_quiz_attempts')
                ->where('notification_id', $first->notification_id)
                ->where('device_id', $first->device_id)->max('attempt_no')) + 1;

            $attemptId = DB::table('notification_quiz_attempts')->insertGetId([
                'notification_id' => $first->notification_id,
                'device_id' => $first->device_id,
                'attempt_no' => $attemptNo,
                'passed' => $passed,
                'submitted_at' => $submittedAt,
                'created_at' => now(), 'updated_at' => now(),
            ]);

            foreach ($responses as $response) {
                $question = DB::table('notification_quiz_questions')
                    ->where('id', $response->quiz_question_id)->first();
                if (! $question) continue;

                DB::table('notification_quiz_attempt_answers')->insert([
                    'quiz_attempt_id' => $attemptId,
                    'quiz_question_id' => $response->quiz_question_id,
                    'question_snapshot' => $question->question,
                    'answer' => $response->answer,
                    'correct_answer' => $question->correct_answer,
                    'is_correct' => $response->is_correct,
                    'answered_at' => $response->answered_at,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_quiz_attempt_answers');

        if (! Schema::hasColumn('notification_quiz_attempts', 'answers')) {
            Schema::dropIfExists('notification_quiz_attempts');
        }
    }
};
