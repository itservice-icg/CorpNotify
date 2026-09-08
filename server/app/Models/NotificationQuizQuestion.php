<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationQuizQuestion extends Model
{
    protected $fillable = [
        'notification_id',
        'question',
        'correct_answer',
        'sort_order',
    ];

    public function notification(): BelongsTo
    {
        return $this->belongsTo(Notification::class);
    }
}
