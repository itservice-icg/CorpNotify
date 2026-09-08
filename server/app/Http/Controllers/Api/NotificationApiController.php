<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\Notification;
use App\Models\NotificationEvent;
use App\Models\NotificationQuizAttempt;
use App\Models\NotificationQuizResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class NotificationApiController extends Controller
{
    public function pending(Request $request): JsonResponse
    {
        $device = $this->authenticatedDevice($request, true);

        $notifications = Notification::query()
            ->where('is_active', true)
            ->where('start_at', '<=', now())
            ->where(fn (Builder $query) => $query->whereNull('expire_at')->orWhere('expire_at', '>', now()))
            ->where(fn (Builder $query) => $this->matchingTarget($query, $device))
            ->whereDoesntHave('devices', fn (Builder $query) => $query
                ->where('devices.id', $device->id)
                ->whereNotNull('notification_devices.acknowledged_at'))
            ->with(['quizQuestions:id,notification_id,question,sort_order'])
            ->orderBy('start_at')
            ->get(['id', 'title', 'message', 'image_path', 'type', 'url', 'policy_body']);

        $payload = $notifications->map(function (Notification $notification) use ($device) {
            $notification->devices()->syncWithoutDetaching([$device->id]);

            return [
                'id' => $notification->id,
                'title' => $notification->title,
                'message' => $notification->message,
                'image_url' => $notification->image_path ? url(Storage::url($notification->image_path)) : null,
                'image_base64' => $notification->image_path && Storage::disk('public')->exists($notification->image_path)
                    ? base64_encode(Storage::disk('public')->get($notification->image_path)) : null,
                'type' => $notification->type,
                'start_at' => $notification->start_at?->toIso8601String(),
                'url' => $notification->url,
                'policy_body' => $notification->isPolicy() ? $notification->policy_body : null,
                'questions' => $notification->isPolicy()
                    ? $notification->quizQuestions->map(fn ($q) => ['id' => $q->id, 'question' => $q->question])->values()
                    : [],
            ];
        })->values();

        return response()->json($payload);
    }

    public function submitQuiz(Request $request, Notification $notification): JsonResponse
    {
        $request->validate([
            'device_uuid' => ['required', 'uuid'],
            'answers' => ['required', 'array', 'min:1'],
            'answers.*.question_id' => ['required', 'integer'],
            'answers.*.answer' => ['required', Rule::in(['use', 'not_use'])],
        ]);

        abort_unless($notification->isPolicy(), 422, 'This notification does not require a quiz.');

        $device = $this->authenticatedDevice($request);
        $this->ensureAssigned($notification, $device);
        $questions = $notification->quizQuestions()->get(['id', 'question', 'correct_answer']);
        $submittedAnswers = collect($request->input('answers'))->keyBy('question_id');
        $answeredAt = now();

        [$attemptNo, $passed, $incorrectIds] = DB::transaction(function () use ($notification, $device, $questions, $submittedAnswers, $answeredAt) {
            $attemptNo = (int) NotificationQuizAttempt::where('notification_id', $notification->id)
                ->where('device_id', $device->id)
                ->lockForUpdate()
                ->max('attempt_no') + 1;

            $evaluated = $questions->map(function ($question) use ($submittedAnswers) {
                $submitted = $submittedAnswers->get($question->id);
                $answer = $submitted['answer'] ?? null;

                return [
                    'question' => $question,
                    'answer' => $answer,
                    'is_correct' => $answer !== null && $question->correct_answer === $answer,
                ];
            });

            $passed = $evaluated->count() === $questions->count()
                && $evaluated->every(fn ($row) => $row['is_correct']);

            $attempt = NotificationQuizAttempt::create([
                'notification_id' => $notification->id,
                'device_id' => $device->id,
                'attempt_no' => $attemptNo,
                'passed' => $passed,
                'submitted_at' => $answeredAt,
                ...$this->deviceSnapshot($device),
            ]);

            foreach ($evaluated as $row) {
                $question = $row['question'];
                $answer = $row['answer'];

                if ($answer === null) {
                    continue;
                }

                $attempt->answers()->create([
                    'quiz_question_id' => $question->id,
                    'question_snapshot' => $question->question,
                    'answer' => $answer,
                    'correct_answer' => $question->correct_answer,
                    'is_correct' => $row['is_correct'],
                    'answered_at' => $answeredAt,
                ]);

                NotificationQuizResponse::updateOrCreate(
                    ['device_id' => $device->id, 'quiz_question_id' => $question->id],
                    [
                        'notification_id' => $notification->id,
                        'answer' => $answer,
                        'is_correct' => $row['is_correct'],
                        'answered_at' => $answeredAt,
                    ]
                );
            }

            $incorrectIds = $evaluated
                ->reject(fn ($row) => $row['is_correct'])
                ->pluck('question.id')
                ->values();

            return [$attemptNo, $passed, $incorrectIds];
        });

        $this->recordEvent($notification, $device, 'quiz_submitted', ['attempt_no' => $attemptNo]);
        $this->recordEvent($notification, $device, $passed ? 'quiz_passed' : 'quiz_failed', ['attempt_no' => $attemptNo]);

        $payload = [
            'passed' => $passed,
            'attempt_no' => $attemptNo,
            'incorrect_question_ids' => $incorrectIds,
        ];

        return response()->json($payload, $passed ? 200 : 422);
    }

    public function delivered(Request $request, Notification $notification): JsonResponse
    {
        return $this->recordStatus($request, $notification, 'delivered_at');
    }

    public function opened(Request $request, Notification $notification): JsonResponse
    {
        return $this->recordStatus($request, $notification, 'opened_at');
    }

    public function readCompleted(Request $request, Notification $notification): JsonResponse
    {
        abort_unless($notification->isPolicy(), 422, 'Read completion is only valid for policy notifications.');
        return $this->recordStatus($request, $notification, 'read_completed_at');
    }

    public function quizStarted(Request $request, Notification $notification): JsonResponse
    {
        abort_unless($notification->isPolicy(), 422, 'Quiz start is only valid for policy notifications.');
        $device = $this->authenticatedDevice($request);
        $this->ensureAssigned($notification, $device);
        $this->recordEvent($notification, $device, 'quiz_started');
        return response()->json(['message' => 'Quiz start recorded.']);
    }

    public function acknowledge(Request $request, Notification $notification): JsonResponse
    {
        if ($notification->isPolicy()) {
            $device = $this->authenticatedDevice($request);
            $this->ensureAssigned($notification, $device);

            $readCompleted = DB::table('notification_devices')
                ->where('notification_id', $notification->id)
                ->where('device_id', $device->id)
                ->whereNotNull('read_completed_at')->exists();
            abort_unless($readCompleted, 422, 'Policy must be read completely before acknowledging.');

            $questionIds = $notification->quizQuestions()->pluck('id');
            $hasPassedAttempt = $questionIds->isEmpty()
                || NotificationQuizAttempt::query()
                    ->where('notification_id', $notification->id)
                    ->where('device_id', $device->id)
                    ->where('passed', true)
                    ->whereHas('answers', fn ($query) => $query->whereIn('quiz_question_id', $questionIds))
                    ->withCount(['answers as correct_answer_count' => fn ($query) =>
                        $query->whereIn('quiz_question_id', $questionIds)->where('is_correct', true)])
                    ->get()
                    ->contains(fn ($attempt) => $attempt->correct_answer_count === $questionIds->count());

            abort_unless($hasPassedAttempt, 422, 'Quiz must be completed correctly before acknowledging.');
        }

        return $this->recordStatus($request, $notification, 'acknowledged_at');
    }

    private function recordStatus(Request $request, Notification $notification, string $column): JsonResponse
    {
        $device = $this->authenticatedDevice($request);
        $row = DB::table('notification_devices')
            ->where('notification_id', $notification->id)
            ->where('device_id', $device->id)
            ->first();

        abort_if(! $row, 404, 'Notification was not assigned to this device.');

        if ($row->{$column} === null) {
            $recordedAt = now();
            DB::table('notification_devices')
                ->where('id', $row->id)
                ->update([$column => $recordedAt, 'updated_at' => $recordedAt]);
            $eventType = $column === 'read_completed_at'
                ? 'policy_read_completed'
                : str($column)->beforeLast('_at')->toString();
            $this->recordEvent($notification, $device, $eventType, [], $recordedAt);
        }

        return response()->json(['message' => str($column)->beforeLast('_at')->headline().' recorded.']);
    }

    private function authenticatedDevice(Request $request, bool $mustBeActive = false): Device
    {
        $request->validate(['device_uuid' => ['required', 'uuid']]);
        $query = Device::where('device_uuid', $request->string('device_uuid'));
        if ($mustBeActive) $query->where('is_active', true);
        $device = $query->firstOrFail();

        if ($device->api_token_hash) {
            $token = (string) $request->bearerToken();
            abort_unless($token !== '' && hash_equals($device->api_token_hash, hash('sha256', $token)), 401, 'Device authentication failed.');
        }

        return $device;
    }

    private function ensureAssigned(Notification $notification, Device $device): void
    {
        $assigned = DB::table('notification_devices')
            ->where('notification_id', $notification->id)
            ->where('device_id', $device->id)->exists();
        abort_unless($assigned, 404, 'Notification was not assigned to this device.');
    }

    private function deviceSnapshot(Device $device): array
    {
        return [
            'device_uuid_snapshot' => $device->device_uuid,
            'hostname_snapshot' => $device->hostname,
            'username_snapshot' => $device->username,
            'department_snapshot' => $device->department,
            'ip_address_snapshot' => $device->ip_address,
            'agent_version_snapshot' => $device->agent_version,
        ];
    }

    private function recordEvent(Notification $notification, Device $device, string $type, array $metadata = [], $eventAt = null): void
    {
        NotificationEvent::create([
            'notification_id' => $notification->id,
            'device_id' => $device->id,
            'event_type' => $type,
            'event_at' => $eventAt ?? now(),
            'metadata' => $metadata ?: null,
            ...$this->deviceSnapshot($device),
        ]);
    }

    private function matchingTarget(Builder $query, Device $device): void
    {
        $query->where('target_type', 'all')
            ->orWhere(fn (Builder $target) => $target
                ->where('target_type', 'department')->where('target_value', $device->department))
            ->orWhere(fn (Builder $target) => $target
                ->where('target_type', 'device')->where('target_value', $device->device_uuid))
            ->orWhere(fn (Builder $target) => $target
                ->where('target_type', 'user')->where('target_value', $device->username));
    }
}
