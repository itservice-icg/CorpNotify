<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class NotificationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_application_uses_bangkok_timezone_for_scheduling(): void
    {
        $this->assertSame('Asia/Bangkok', config('app.timezone'));
        $this->assertSame('+07:00', now()->format('P'));
    }

    public function test_pending_returns_only_active_current_matching_unacknowledged_notifications(): void
    {
        $device = $this->device();
        $matching = $this->notification(['target_type' => 'department', 'target_value' => 'IT']);
        $this->notification(['target_type' => 'department', 'target_value' => 'HR']);
        $this->notification(['is_active' => false]);
        $this->notification(['start_at' => now()->addHour()]);

        $this->getJson('/api/notifications/pending?device_uuid='.$device->device_uuid)
            ->assertOk()->assertJsonCount(1)->assertJsonPath('0.id', $matching->id);

        $this->postJson("/api/notifications/{$matching->id}/delivered", ['device_uuid' => $device->device_uuid])->assertOk();
        $this->postJson("/api/notifications/{$matching->id}/opened", ['device_uuid' => $device->device_uuid])->assertOk();
        $this->postJson("/api/notifications/{$matching->id}/acknowledge", ['device_uuid' => $device->device_uuid])->assertOk();

        $this->getJson('/api/notifications/pending?device_uuid='.$device->device_uuid)->assertOk()->assertJsonCount(0);
        $this->assertDatabaseHas('notification_devices', ['notification_id' => $matching->id, 'device_id' => $device->id]);
    }

    public function test_policy_quiz_must_be_correct_before_acknowledge(): void
    {
        $device = $this->device();
        $notification = $this->notification(['type' => 'policy', 'policy_body' => 'ห้ามติดตั้งซอฟต์แวร์นอกบัญชี']);
        $allowed = $notification->quizQuestions()->create([
            'question' => 'ติดตั้งโปรแกรมจากแหล่งที่บริษัทไม่อนุญาตได้หรือไม่',
            'correct_answer' => 'not_use',
            'sort_order' => 0,
        ]);
        $mustUse = $notification->quizQuestions()->create([
            'question' => 'ต้องปฏิบัติตามนโยบายนี้หรือไม่',
            'correct_answer' => 'use',
            'sort_order' => 1,
        ]);

        $this->getJson('/api/notifications/pending?device_uuid='.$device->device_uuid)
            ->assertOk()
            ->assertJsonPath('0.type', 'policy')
            ->assertJsonPath('0.policy_body', 'ห้ามติดตั้งซอฟต์แวร์นอกบัญชี')
            ->assertJsonPath('0.questions.0.id', $allowed->id)
            ->assertJsonMissingPath('0.questions.0.correct_answer');

        $this->postJson("/api/notifications/{$notification->id}/opened", ['device_uuid' => $device->device_uuid])->assertOk();
        $this->postJson("/api/notifications/{$notification->id}/read-completed", ['device_uuid' => $device->device_uuid])->assertOk();
        $this->postJson("/api/notifications/{$notification->id}/quiz-started", ['device_uuid' => $device->device_uuid])->assertOk();

        $this->postJson("/api/notifications/{$notification->id}/quiz", [
            'device_uuid' => $device->device_uuid,
            'answers' => [
                ['question_id' => $allowed->id, 'answer' => 'use'],
                ['question_id' => $mustUse->id, 'answer' => 'use'],
            ],
        ])->assertStatus(422)->assertJsonPath('passed', false)->assertJsonPath('incorrect_question_ids.0', $allowed->id);

        $this->postJson("/api/notifications/{$notification->id}/acknowledge", [
            'device_uuid' => $device->device_uuid,
        ])->assertStatus(422);

        $this->postJson("/api/notifications/{$notification->id}/quiz", [
            'device_uuid' => $device->device_uuid,
            'answers' => [
                ['question_id' => $allowed->id, 'answer' => 'not_use'],
                ['question_id' => $mustUse->id, 'answer' => 'use'],
            ],
        ])->assertOk()->assertJsonPath('passed', true)->assertJsonPath('attempt_no', 2);

        $this->assertDatabaseCount('notification_quiz_attempts', 2);
        $this->assertDatabaseCount('notification_quiz_attempt_answers', 4);
        $this->assertDatabaseHas('notification_quiz_attempts', [
            'notification_id' => $notification->id, 'device_id' => $device->id,
            'attempt_no' => 1, 'passed' => false,
        ]);
        $this->assertDatabaseHas('notification_quiz_attempts', [
            'notification_id' => $notification->id, 'device_id' => $device->id,
            'attempt_no' => 2, 'passed' => true,
        ]);

        $this->postJson("/api/notifications/{$notification->id}/acknowledge", [
            'device_uuid' => $device->device_uuid,
        ])->assertOk();

        $this->assertDatabaseHas('notification_devices', [
            'notification_id' => $notification->id, 'device_id' => $device->id,
        ]);
        $this->assertDatabaseHas('notification_events', ['notification_id' => $notification->id, 'device_id' => $device->id, 'event_type' => 'policy_read_completed']);
        $this->assertDatabaseHas('notification_events', ['notification_id' => $notification->id, 'device_id' => $device->id, 'event_type' => 'quiz_failed']);
        $this->assertDatabaseHas('notification_events', ['notification_id' => $notification->id, 'device_id' => $device->id, 'event_type' => 'quiz_passed']);
        $this->assertDatabaseHas('notification_events', ['notification_id' => $notification->id, 'device_id' => $device->id, 'event_type' => 'acknowledged']);
        $this->assertDatabaseHas('notification_quiz_attempts', ['notification_id' => $notification->id, 'hostname_snapshot' => 'PC-001']);
    }

    public function test_device_cannot_update_status_before_notification_is_assigned(): void
    {
        $device = $this->device();
        $notification = $this->notification();

        $this->postJson("/api/notifications/{$notification->id}/acknowledge", [
            'device_uuid' => $device->device_uuid,
        ])->assertNotFound();
    }

    private function device(): Device
    {
        return Device::create(['device_uuid' => (string) Str::uuid(), 'hostname' => 'PC-001', 'username' => 'user01', 'department' => 'IT', 'agent_version' => '1.0.0', 'last_seen_at' => now()]);
    }

    private function notification(array $overrides = []): Notification
    {
        return Notification::create(array_merge([
            'title' => 'ประกาศ', 'message' => 'รายละเอียด', 'type' => 'info',
            'target_type' => 'all', 'target_value' => null, 'start_at' => now()->subMinute(),
            'expire_at' => now()->addHour(), 'is_active' => true,
            'created_by' => User::factory()->create()->id,
        ], $overrides));
    }
}
