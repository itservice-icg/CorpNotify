<?php

namespace Tests\Feature;

use App\Models\Device;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DeviceApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_upserts_device_by_uuid(): void
    {
        config(['app.agent_enrollment_key' => 'test-enrollment-key']);
        $uuid = (string) Str::uuid();
        $payload = ['device_uuid' => $uuid, 'hostname' => 'PC-001', 'username' => 'user01', 'ip_address' => '192.168.1.10', 'department' => 'IT', 'agent_version' => '1.0.0', 'enrollment_key' => 'test-enrollment-key'];

        $first = $this->postJson('/api/device/register', $payload)->assertCreated()->assertJsonPath('data.hostname', 'PC-001');
        $this->assertNotEmpty($first->json('api_token'));
        $this->postJson('/api/device/register', $payload + ['hostname' => 'ignored'])->assertOk();

        $this->assertSame(1, Device::count());
    }

    public function test_heartbeat_requires_registered_device(): void
    {
        $this->postJson('/api/device/heartbeat', [
            'device_uuid' => (string) Str::uuid(), 'hostname' => 'PC-404',
            'username' => 'user', 'agent_version' => '1.0.0',
        ])->assertNotFound();
    }
}
