<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_quiz_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('notification_id')->constrained()->cascadeOnDelete();
            $table->foreignId('device_id')->constrained()->cascadeOnDelete();
            $table->foreignId('quiz_question_id')->constrained('notification_quiz_questions')->cascadeOnDelete();
            $table->boolean('answer');
            $table->boolean('is_correct');
            $table->dateTime('answered_at');
            $table->timestamps();

            $table->unique(['device_id', 'quiz_question_id'], 'device_question_unique');
            $table->index(['notification_id', 'device_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_quiz_responses');
    }
};
