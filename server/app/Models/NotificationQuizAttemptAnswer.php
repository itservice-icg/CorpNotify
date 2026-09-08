<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationQuizAttemptAnswer extends Model
{
    protected $fillable = [
        'quiz_attempt_id', 'quiz_question_id', 'question_snapshot',
        'answer', 'correct_answer', 'is_correct', 'answered_at',
    ];

    protected function casts(): array
    {
        return ['is_correct' => 'boolean', 'answered_at' => 'datetime'];
    }

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(NotificationQuizAttempt::class, 'quiz_attempt_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(NotificationQuizQuestion::class, 'quiz_question_id');
    }
}
