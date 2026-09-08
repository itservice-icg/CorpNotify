<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class NotificationMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_notifications_table_has_the_required_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('notifications', [
            'id',
            'title',
            'message',
            'policy_body',
            'type',
            'url',
            'target_type',
            'target_value',
            'start_at',
            'expire_at',
            'is_active',
            'created_by',
            'created_at',
            'updated_at',
            'deleted_at',
        ]));

        $this->assertTrue(Schema::hasColumns('devices', [
            'device_uuid', 'hostname', 'username', 'ip_address', 'department',
            'agent_version', 'last_seen_at', 'is_active',
        ]));

        $this->assertTrue(Schema::hasColumns('notification_devices', [
            'notification_id', 'device_id', 'delivered_at', 'opened_at', 'acknowledged_at',
        ]));
    }
}
