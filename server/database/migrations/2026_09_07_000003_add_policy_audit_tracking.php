<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notification_devices', function (Blueprint $table) {
            $table->dateTime('read_completed_at')->nullable()->after('opened_at');
        });

        Schema::table('notification_quiz_attempts', function (Blueprint $table) {
            $table->uuid('device_uuid_snapshot')->nullable();
            $table->string('hostname_snapshot')->nullable();
            $table->string('username_snapshot')->nullable();
            $table->string('department_snapshot')->nullable();
            $table->string('ip_address_snapshot', 45)->nullable();
            $table->string('agent_version_snapshot', 50)->nullable();
        });

        Schema::create('notification_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('notification_id')->constrained()->cascadeOnDelete();
            $table->foreignId('device_id')->constrained()->cascadeOnDelete();
            $table->string('event_type', 64);
            $table->dateTime('event_at');
            $table->json('metadata')->nullable();
            $table->uuid('device_uuid_snapshot')->nullable();
            $table->string('hostname_snapshot')->nullable();
            $table->string('username_snapshot')->nullable();
            $table->string('department_snapshot')->nullable();
            $table->string('ip_address_snapshot', 45)->nullable();
            $table->string('agent_version_snapshot', 50)->nullable();
            $table->timestamps();
            $table->index(['notification_id', 'device_id', 'event_at']);
            $table->index(['event_type', 'event_at']);
        });

        $devices = DB::table('devices')->get()->keyBy('id');
        DB::table('notification_quiz_attempts')->orderBy('id')->get()->each(function ($attempt) use ($devices) {
            $device = $devices->get($attempt->device_id);
            if (! $device) return;
            DB::table('notification_quiz_attempts')->where('id', $attempt->id)->update([
                'device_uuid_snapshot' => $device->device_uuid,
                'hostname_snapshot' => $device->hostname,
                'username_snapshot' => $device->username,
                'department_snapshot' => $device->department,
                'ip_address_snapshot' => $device->ip_address,
                'agent_version_snapshot' => $device->agent_version,
            ]);
        });

        foreach (DB::table('notification_quiz_attempts')->orderBy('id')->get() as $attempt) {
            $device = $devices->get($attempt->device_id);
            if (! $device || ! $attempt->submitted_at) continue;
            foreach (['quiz_submitted', $attempt->passed ? 'quiz_passed' : 'quiz_failed'] as $type) {
                DB::table('notification_events')->insert([
                    'notification_id' => $attempt->notification_id,
                    'device_id' => $attempt->device_id,
                    'event_type' => $type,
                    'event_at' => $attempt->submitted_at,
                    'metadata' => json_encode(['attempt_no' => $attempt->attempt_no]),
                    'device_uuid_snapshot' => $device->device_uuid,
                    'hostname_snapshot' => $device->hostname,
                    'username_snapshot' => $device->username,
                    'department_snapshot' => $device->department,
                    'ip_address_snapshot' => $device->ip_address,
                    'agent_version_snapshot' => $device->agent_version,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }

        foreach (DB::table('notification_devices')->orderBy('id')->get() as $row) {
            $device = $devices->get($row->device_id);
            if (! $device) continue;
            foreach (['delivered_at' => 'delivered', 'opened_at' => 'opened', 'acknowledged_at' => 'acknowledged'] as $column => $type) {
                if (! $row->{$column}) continue;
                DB::table('notification_events')->insert([
                    'notification_id' => $row->notification_id,
                    'device_id' => $row->device_id,
                    'event_type' => $type,
                    'event_at' => $row->{$column},
                    'device_uuid_snapshot' => $device->device_uuid,
                    'hostname_snapshot' => $device->hostname,
                    'username_snapshot' => $device->username,
                    'department_snapshot' => $device->department,
                    'ip_address_snapshot' => $device->ip_address,
                    'agent_version_snapshot' => $device->agent_version,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_events');
        Schema::table('notification_quiz_attempts', function (Blueprint $table) {
            $table->dropColumn([
                'device_uuid_snapshot', 'hostname_snapshot', 'username_snapshot',
                'department_snapshot', 'ip_address_snapshot', 'agent_version_snapshot',
            ]);
        });

        Schema::table('notification_devices', function (Blueprint $table) {
            $table->dropColumn('read_completed_at');
        });
    }
};
