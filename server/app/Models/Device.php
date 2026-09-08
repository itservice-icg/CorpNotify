<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Device extends Model
{
    use HasFactory;

    protected $fillable = [
        'device_uuid',
        'api_token_hash',
        'api_token_issued_at',
        'hostname',
        'username',
        'ip_address',
        'department',
        'agent_version',
        'last_seen_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'last_seen_at' => 'datetime',
            'api_token_issued_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function notifications(): BelongsToMany
    {
        return $this->belongsToMany(Notification::class, 'notification_devices')
            ->withPivot(['delivered_at', 'opened_at', 'read_completed_at', 'acknowledged_at'])
            ->withTimestamps();
    }
}
