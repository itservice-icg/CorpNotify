<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationQuizResponse extends Model
{
    protected $fillable = [
        'notification_id',
        'device_id',
        'quiz_question_id',
        'answer',
        'is_correct',
        'answered_at',
    ];

    protected function casts(): array
    {
        return [
            'is_correct' => 'boolean',
            'answered_at' => 'datetime',
        ];
    }

    public function notification(): BelongsTo
    {
        return $this->belongsTo(Notification::class);
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(NotificationQuizQuestion::class, 'quiz_question_id');
    }
}
