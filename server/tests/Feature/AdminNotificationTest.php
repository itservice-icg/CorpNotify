<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_pages_require_authentication(): void
    {
        $this->get('/notifications')->assertRedirect('/login');
        $this->get('/devices')->assertRedirect('/login');
    }

    public function test_viewer_can_read_but_cannot_modify_notifications(): void
    {
        $viewer = User::factory()->state(['role' => 'viewer'])->create();
        $notification = Notification::create([
            'title' => 'Viewer test',
            'message' => 'Read only',
            'type' => 'info',
            'target_type' => 'all',
            'start_at' => now()->subMinute(),
            'is_active' => true,
            'created_by' => $viewer->id,
        ]);

        $this->actingAs($viewer)->get(route('notifications.index'))->assertOk();
        $this->actingAs($viewer)->get(route('notifications.show', $notification))->assertOk();
        $this->actingAs($viewer)->get(route('notifications.create'))->assertForbidden();
        $this->actingAs($viewer)->patch(route('notifications.deactivate', $notification))->assertForbidden();
    }

    public function test_authenticated_admin_can_create_and_deactivate_notification(): void
    {
        $admin = User::factory()->create();

        $response = $this->actingAs($admin)->post('/notifications', [
            'title' => 'แจ้งปิดระบบ ERP',
            'message' => 'ปิดปรับปรุงเวลา 17:00 - 18:00',
            'type' => 'warning',
            'target_type' => 'all',
            'url' => 'https://intranet.example.test/news/1',
            'start_at' => now()->format('Y-m-d H:i:s'),
            'expire_at' => now()->addHour()->format('Y-m-d H:i:s'),
            'is_active' => '1',
        ]);

        $notification = Notification::firstOrFail();
        $response->assertRedirect(route('notifications.show', $notification));
        $this->assertNull($notification->target_value);
        $this->assertSame($admin->id, $notification->created_by);

        $this->actingAs($admin)->get('/dashboard')->assertOk();
        $this->actingAs($admin)->get('/notifications')->assertOk()->assertSee('แจ้งปิดระบบ ERP');
        $this->actingAs($admin)->get(route('notifications.show', $notification))->assertOk();
        $this->actingAs($admin)->get(route('notifications.edit', $notification))->assertOk();
        $this->actingAs($admin)->get('/devices')->assertOk();

        $this->actingAs($admin)->patch(route('notifications.deactivate', $notification))->assertRedirect();
        $this->assertFalse($notification->fresh()->is_active);
    }

    public function test_admin_can_create_policy_notification_with_quiz(): void
    {
        $admin = User::factory()->create();

        $response = $this->actingAs($admin)->post('/notifications', [
            'title' => 'นโยบายการใช้ซอฟต์แวร์',
            'message' => 'กรุณาอ่านและทำข้อสอบ',
            'type' => 'policy',
            'target_type' => 'all',
            'policy_body' => "1. ใช้ซอฟต์แวร์ที่บริษัทอนุญาตเท่านั้น\n2. ห้ามติดตั้งโปรแกรมเถื่อน",
            'questions' => [
                ['question' => 'ติดตั้งโปรแกรมเถื่อนได้หรือไม่', 'correct_answer' => 'not_use'],
                ['question' => 'ต้องปฏิบัติตามนโยบายนี้หรือไม่', 'correct_answer' => 'use'],
            ],
            'start_at' => now()->format('Y-m-d H:i:s'),
            'is_active' => '1',
        ]);

        $notification = Notification::firstOrFail();
        $response->assertRedirect(route('notifications.show', $notification));
        $this->assertSame('policy', $notification->type);
        $this->assertSame(2, $notification->quizQuestions()->count());
        $this->actingAs($admin)->get(route('notifications.show', $notification))
            ->assertOk()
            ->assertSee('ติดตั้งโปรแกรมเถื่อนได้หรือไม่');
    }

    public function test_editing_published_policy_creates_new_version_and_preserves_old_questions(): void
    {
        $admin = User::factory()->create();

        $this->actingAs($admin)->post('/notifications', [
            'title' => 'Policy v1', 'message' => 'อ่านก่อนทำแบบทดสอบ', 'type' => 'policy',
            'target_type' => 'all', 'policy_body' => 'เนื้อหาเดิม',
            'questions' => [
                ['question' => 'คำถามเดิม 1', 'correct_answer' => 'use'],
                ['question' => 'คำถามเดิม 2', 'correct_answer' => 'not_use'],
            ],
            'start_at' => now()->format('Y-m-d H:i:s'), 'is_active' => '1',
        ])->assertRedirect();

        $original = Notification::firstOrFail();
        $this->assertSame(2, $original->quizQuestions()->count());
        $this->assertNotNull($original->published_at);

        $response = $this->actingAs($admin)->put(route('notifications.update', $original), [
            'title' => 'Policy v2', 'message' => 'เวอร์ชันใหม่', 'type' => 'policy',
            'target_type' => 'all', 'policy_body' => 'เนื้อหาใหม่',
            'questions' => [['question' => 'คำถามใหม่', 'correct_answer' => 'use']],
            'start_at' => now()->format('Y-m-d H:i:s'), 'is_active' => '1',
        ]);

        $this->assertDatabaseCount('notifications', 2);
        $newVersion = Notification::whereKeyNot($original->id)->firstOrFail();
        $response->assertRedirect(route('notifications.show', $newVersion));
        $this->assertFalse($original->fresh()->is_active);
        $this->assertSame(2, $original->quizQuestions()->count());
        $this->assertSame(1, $newVersion->quizQuestions()->count());
        $this->assertSame(2, $newVersion->policy_version);
        $this->assertSame($original->id, $newVersion->supersedes_notification_id);
    }

    public function test_notification_rejects_unsafe_url_scheme_and_missing_target(): void
    {
        $admin = User::factory()->create();

        $this->actingAs($admin)->post('/notifications', [
            'title' => 'Invalid', 'message' => 'Invalid', 'type' => 'info',
            'target_type' => 'department', 'url' => 'javascript:alert(1)',
            'start_at' => now()->format('Y-m-d H:i:s'),
        ])->assertSessionHasErrors(['target_value', 'url']);
    }
}
