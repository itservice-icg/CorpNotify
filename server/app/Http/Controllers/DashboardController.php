<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Models\Notification;
use App\Models\NotificationEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $role = in_array(auth()->user()->role ?? 'admin', ['admin', 'manager', 'viewer'], true)
            ? (auth()->user()->role ?? 'admin')
            : 'viewer';
        $roleLabel = ['admin' => 'Administrator', 'manager' => 'Manager', 'viewer' => 'Viewer'][$role];
        $activeNotifications = Notification::where('is_active', true)->count();
        $registeredDevices = Device::count();
        $onlineDevices = Device::where('is_active', true)
            ->where('last_seen_at', '>=', now()->subMinutes(2))->count();
        $acknowledgedCount = DB::table('notification_devices')->whereNotNull('acknowledged_at')->count();
        $assignedCount = DB::table('notification_devices')->count();

        $recentNotifications = Notification::query()
            ->withCount(['devices as acknowledged_count' => fn ($query) => $query->whereNotNull('acknowledged_at')])
            ->latest('created_at')->limit(4)->get();

        $devices = Device::query()->orderByDesc('last_seen_at')->limit(4)->get();
        $recentEvents = NotificationEvent::query()
            ->with('notification')->latest('event_at')->limit(6)->get();
        $days = collect(range(6, 0))->map(fn ($offset) => now()->subDays($offset)->startOfDay());
        $chart = $days->map(function ($day) {
            $next = $day->copy()->addDay();
            return [
                'label' => $day->locale('th')->translatedFormat('j M'),
                'created' => Notification::whereBetween('created_at', [$day, $next])->count(),
                // Keep the chart on the same notification-level unit as
                // `created`: one notification is counted once per day even
                // when several devices acknowledge it.
                'acknowledged' => DB::table('notification_devices')
                    ->whereBetween('acknowledged_at', [$day, $next])
                    ->distinct('notification_id')->count('notification_id'),
            ];
        });

        $ackPercent = $assignedCount > 0 ? (int) round(($acknowledgedCount / $assignedCount) * 100) : 0;
        $pendingCount = max($assignedCount - $acknowledgedCount, 0);

        return view('dashboard', compact(
            'activeNotifications', 'registeredDevices', 'onlineDevices', 'acknowledgedCount',
            'assignedCount', 'recentNotifications', 'devices', 'recentEvents', 'chart',
            'ackPercent', 'pendingCount', 'role', 'roleLabel'
        ));
    }
}
