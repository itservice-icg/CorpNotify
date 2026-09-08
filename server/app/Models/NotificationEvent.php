<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationEvent extends Model
{
    protected $fillable = [
        'notification_id', 'device_id', 'event_type', 'event_at', 'metadata',
        'device_uuid_snapshot', 'hostname_snapshot', 'username_snapshot',
        'department_snapshot', 'ip_address_snapshot', 'agent_version_snapshot',
    ];

    protected function casts(): array
    {
        return ['event_at' => 'datetime', 'metadata' => 'array'];
    }

    public function notification(): BelongsTo { return $this->belongsTo(Notification::class); }
    public function device(): BelongsTo { return $this->belongsTo(Device::class); }
}
