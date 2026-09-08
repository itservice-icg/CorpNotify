<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Notification extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'title',
        'message',
        'image_path',
        'type',
        'url',
        'policy_body',
        'policy_version',
        'supersedes_notification_id',
        'published_at',
        'target_type',
        'target_value',
        'start_at',
        'expire_at',
        'is_active',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'start_at' => 'datetime',
            'expire_at' => 'datetime',
            'published_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function isPolicy(): bool
    {
        return $this->type === 'policy';
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function devices(): BelongsToMany
    {
        return $this->belongsToMany(Device::class, 'notification_devices')
            ->withPivot(['delivered_at', 'opened_at', 'read_completed_at', 'acknowledged_at'])
            ->withTimestamps();
    }

    public function quizQuestions(): HasMany
    {
        return $this->hasMany(NotificationQuizQuestion::class)->orderBy('sort_order');
    }

    public function quizResponses(): HasMany
    {
        return $this->hasMany(NotificationQuizResponse::class);
    }

    public function quizAttempts(): HasMany
    {
        return $this->hasMany(NotificationQuizAttempt::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(NotificationEvent::class)->orderBy('event_at');
    }

    public function supersedes(): BelongsTo
    {
        return $this->belongsTo(self::class, 'supersedes_notification_id');
    }
}
