<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NotificationQuizAttempt extends Model
{
    protected $fillable = [
        'notification_id', 'device_id', 'attempt_no', 'passed', 'submitted_at',
        'device_uuid_snapshot', 'hostname_snapshot', 'username_snapshot',
        'department_snapshot', 'ip_address_snapshot', 'agent_version_snapshot',
    ];

    protected function casts(): array
    {
        return ['passed' => 'boolean', 'submitted_at' => 'datetime'];
    }

    public function notification(): BelongsTo
    {
        return $this->belongsTo(Notification::class);
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(NotificationQuizAttemptAnswer::class, 'quiz_attempt_id');
    }
}
