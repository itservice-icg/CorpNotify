<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Illuminate\Support\Facades\Storage;

class NotificationController extends Controller
{
    public function index(): View
    {
        return view('notifications.index', [
            'notifications' => Notification::withCount([
                'devices as delivered_count' => fn ($query) => $query->whereNotNull('delivered_at'),
                'devices as acknowledged_count' => fn ($query) => $query->whereNotNull('acknowledged_at'),
            ])->latest()->paginate(20),
        ]);
    }

    public function create(): View
    {
        return view('notifications.form', ['notification' => new Notification]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $questions = $data['questions'] ?? [];
        unset($data['questions']);
        $data['image_path'] = $this->storeImage($request);

        $notification = Notification::create($data + [
            'created_by' => $request->user()->id,
            'policy_version' => 1,
            'published_at' => ($data['type'] ?? null) === 'policy' ? now() : null,
        ]);

        $this->syncQuizQuestions($notification, $questions);

        return redirect()->route('notifications.show', $notification)->with('success', 'สร้างประกาศแล้ว');
    }

    public function show(Request $request, Notification $notification): View
    {
        $notification->load([
            'creator', 'quizQuestions', 'devices',
            'quizResponses.device', 'quizResponses.question',
            'quizAttempts.answers', 'quizAttempts.device',
            'events.device',
        ])->loadCount([
            'devices as delivered_count' => fn ($query) => $query->whereNotNull('delivered_at'),
            'devices as opened_count' => fn ($query) => $query->whereNotNull('opened_at'),
            'devices as read_completed_count' => fn ($query) => $query->whereNotNull('read_completed_at'),
            'devices as acknowledged_count' => fn ($query) => $query->whereNotNull('acknowledged_at'),
        ]);

        $tracking = $this->trackingRows($notification);
        if ($q = trim((string) $request->query('q'))) {
            $tracking = $tracking->filter(fn ($row) => str_contains(strtolower($row['search']), strtolower($q)));
        }
        if ($status = $request->query('status')) {
            $tracking = $tracking->where('status', $status);
        }

        return view('notifications.show', compact('notification', 'tracking'));
    }

    public function exportCsv(Notification $notification)
    {
        $this->loadTrackingData($notification);
        $rows = $this->trackingRows($notification);
        $filename = 'notification-'.$notification->id.'-tracking.csv';

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Hostname','Username','Department','Delivered','Opened','Read Completed','Attempts','Passed','Acknowledged','Last Activity']);
            foreach ($rows as $row) fputcsv($out, $this->trackingExportRow($row));
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function exportExcel(Notification $notification)
    {
        $this->loadTrackingData($notification);
        $rows = $this->trackingRows($notification);
        $filename = 'notification-'.$notification->id.'-tracking.xls';

        return response()->streamDownload(function () use ($rows) {
            echo '<?xml version="1.0" encoding="UTF-8"?>';
            echo '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"><Worksheet ss:Name="Tracking"><Table>';
            $header = ['Hostname','Username','Department','Delivered','Opened','Read Completed','Attempts','Passed','Acknowledged','Last Activity'];
            echo $this->excelRow($header);
            foreach ($rows as $row) echo $this->excelRow($this->trackingExportRow($row));
            echo '</Table></Worksheet></Workbook>';
        }, $filename, ['Content-Type' => 'application/vnd.ms-excel; charset=UTF-8']);
    }

    public function edit(Notification $notification): View
    {
        $notification->load('quizQuestions');

        return view('notifications.form', compact('notification'));
    }

    public function update(Request $request, Notification $notification): RedirectResponse
    {
        $data = $this->validated($request);
        $questions = $data['questions'] ?? [];
        unset($data['questions']);
        if ($image = $this->storeImage($request)) $data['image_path'] = $image;

        if ($notification->isPolicy() && $notification->published_at !== null) {
            if (($data['type'] ?? null) !== 'policy') {
                return back()->withErrors([
                    'type' => 'Policy ที่เผยแพร่แล้วไม่สามารถเปลี่ยนประเภทได้ ให้สร้างประกาศใหม่แทน',
                ])->withInput();
            }

            $newVersion = Notification::create($data + [
                'created_by' => $request->user()->id,
                'policy_version' => ((int) $notification->policy_version) + 1,
                'supersedes_notification_id' => $notification->id,
                'published_at' => now(),
            ]);

            $this->syncQuizQuestions($newVersion, $questions);
            $notification->update(['is_active' => false]);

            return redirect()->route('notifications.show', $newVersion)
                ->with('success', 'สร้าง Policy เวอร์ชันใหม่แล้ว และเก็บเวอร์ชันเดิมไว้สำหรับ Audit');
        }

        $notification->update($data);
        $this->syncQuizQuestions($notification, $questions);

        return redirect()->route('notifications.show', $notification)->with('success', 'แก้ไขประกาศแล้ว');
    }

    private function syncQuizQuestions(Notification $notification, array $questions): void
    {
        $notification->quizQuestions()->delete();

        if (! $notification->isPolicy()) {
            return;
        }

        foreach (array_values($questions) as $order => $row) {
            if (blank($row['question'] ?? null)) {
                continue;
            }

            $notification->quizQuestions()->create([
                'question' => $row['question'],
                'correct_answer' => in_array($row['correct_answer'] ?? 'use', ['use', 'not_use'], true)
                    ? $row['correct_answer']
                    : 'use',
                'sort_order' => $order,
            ]);
        }
    }

    public function deactivate(Notification $notification): RedirectResponse
    {
        $notification->update(['is_active' => false]);

        return back()->with('success', 'ปิดประกาศแล้ว');
    }

    private function loadTrackingData(Notification $notification): void
    {
        $notification->loadMissing(['devices', 'quizAttempts.answers', 'quizAttempts.device', 'events.device']);
    }

    private function trackingRows(Notification $notification): Collection
    {
        $this->loadTrackingData($notification);
        $attemptGroups = $notification->quizAttempts->groupBy('device_id');
        $eventGroups = $notification->events->groupBy('device_id');

        return $notification->devices->map(function ($device) use ($attemptGroups, $eventGroups) {
            $attempts = ($attemptGroups->get($device->id) ?? collect())->sortBy('attempt_no')->values();
            $events = ($eventGroups->get($device->id) ?? collect())->sortBy('event_at')->values();
            $snapshot = $attempts->last() ?? $events->last();
            $pivot = $device->pivot;
            $passed = $attempts->contains(fn ($attempt) => $attempt->passed);

            $status = $pivot->acknowledged_at ? 'acknowledged'
                : ($passed ? 'passed'
                : ($attempts->isNotEmpty() ? 'failed'
                : ($pivot->read_completed_at ? 'read'
                : ($pivot->opened_at ? 'reading' : 'not_opened'))));

            $times = collect([$pivot->delivered_at, $pivot->opened_at, $pivot->read_completed_at, $pivot->acknowledged_at])
                ->filter()->map(fn ($time) => Carbon::parse($time));
            $times = $times->merge($attempts->pluck('submitted_at')->filter())
                ->merge($events->pluck('event_at')->filter());

            $hostname = $snapshot?->hostname_snapshot ?: $device->hostname;
            $username = $snapshot?->username_snapshot ?: $device->username;
            $department = $snapshot?->department_snapshot ?: $device->department;

            return [
                'device' => $device, 'pivot' => $pivot, 'attempts' => $attempts, 'events' => $events,
                'hostname' => $hostname, 'username' => $username, 'department' => $department,
                'status' => $status, 'passed' => $passed,
                'last_activity' => $times->sortDesc()->first(),
                'search' => implode(' ', array_filter([$hostname, $username, $department, $device->device_uuid])),
            ];
        })->sortByDesc('last_activity')->values();
    }

    private function trackingExportRow(array $row): array
    {
        $format = fn ($value) => $value ? Carbon::parse($value)->format('Y-m-d H:i:s') : '';
        return [
            $row['hostname'], $row['username'], $row['department'] ?? '',
            $format($row['pivot']->delivered_at),
            $format($row['pivot']->opened_at),
            $format($row['pivot']->read_completed_at),
            $row['attempts']->count(),
            $row['passed'] ? 'YES' : 'NO',
            $format($row['pivot']->acknowledged_at),
            $row['last_activity']?->format('Y-m-d H:i:s') ?? '',
        ];
    }

    private function excelRow(array $cells): string
    {
        $xml = '<Row>';
        foreach ($cells as $cell) {
            $value = htmlspecialchars((string) $cell, ENT_XML1 | ENT_QUOTES, 'UTF-8');
            $xml .= '<Cell><Data ss:Type="String">'.$value.'</Data></Cell>';
        }
        return $xml.'</Row>';
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:10000'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:5120'],
            'type' => ['required', Rule::in(['info', 'warning', 'critical', 'policy'])],
            'target_type' => ['required', Rule::in(['all', 'department', 'device', 'user'])],
            'target_value' => ['nullable', 'required_unless:target_type,all', 'string', 'max:255'],
            'url' => ['nullable', 'url:http,https', 'max:2048'],
            'start_at' => ['required', 'date'],
            'expire_at' => ['nullable', 'date', 'after:start_at'],
            'is_active' => ['sometimes', 'boolean'],
            'policy_body' => ['nullable', 'required_if:type,policy', 'string', 'max:20000'],
            'questions' => ['nullable', 'array'],
            'questions.*.question' => ['nullable', 'string', 'max:1000'],
            'questions.*.correct_answer' => ['nullable', Rule::in(['use', 'not_use'])],
        ]);

        $data['target_value'] = $data['target_type'] === 'all' ? null : $data['target_value'];
        $data['is_active'] = $request->boolean('is_active', true);

        if ($data['type'] !== 'policy') {
            $data['policy_body'] = null;
        }

        return $data;
    }

    private function storeImage(Request $request): ?string
    {
        return $request->hasFile('image') ? $request->file('image')->store('notifications', 'public') : null;
    }
}
