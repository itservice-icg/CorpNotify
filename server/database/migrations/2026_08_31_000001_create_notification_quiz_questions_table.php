<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_quiz_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('notification_id')->constrained()->cascadeOnDelete();
            $table->string('question', 1000);
            $table->boolean('correct_answer');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['notification_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_quiz_questions');
    }
};
