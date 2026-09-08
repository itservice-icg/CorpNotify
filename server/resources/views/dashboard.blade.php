@extends('layouts.app')
@section('title', 'Dashboard')
@section('content')
@php
  $maxChart = max(1, $chart->max(fn($d) => max($d['created'], $d['acknowledged'])));
  $eventLabels = [
    'delivered'=>'ส่งถึงเครื่องแล้ว','opened'=>'เปิดประกาศ','policy_read_completed'=>'อ่าน Policy ครบแล้ว',
    'quiz_started'=>'เริ่มแบบทดสอบ','quiz_submitted'=>'ส่งแบบทดสอบ','quiz_failed'=>'แบบทดสอบไม่ผ่าน',
    'quiz_passed'=>'แบบทดสอบผ่าน','acknowledged'=>'รับทราบประกาศแล้ว',
  ];
@endphp
<div class="cn-page cn-dashboard-page">
  <section class="cn-dashboard-hero">
    <div>
      <div class="cn-eyebrow">Overview</div>
      <div class="cn-dashboard-title-row"><h1 class="cn-title cn-dashboard-title">Dashboard</h1><span class="cn-role-badge"><i class="bi bi-person-badge-fill" aria-hidden="true"></i>{{ $roleLabel }}</span></div>
      <p class="cn-subtitle">ภาพรวมการใช้งานระบบแจ้งประกาศภายในองค์กร</p>
    </div>
    <div class="cn-date-chip">
      <span class="cn-date-icon"><i class="bi bi-calendar3" aria-hidden="true"></i></span>
      <div><small>วันนี้</small><strong>{{ now()->locale('th')->translatedFormat('j M Y') }}</strong></div>
    </div>
  </section>

  <section class="cn-dashboard-stats">
    @foreach([
      ['ประกาศที่เปิดอยู่',$activeNotifications,'doc','primary','ประกาศที่กำลังเผยแพร่'],
      ['อุปกรณ์ทั้งหมด',$registeredDevices,'device','violet','เครื่องที่ลงทะเบียนแล้ว'],
      ['ออนไลน์ใน 2 นาที',$onlineDevices,'wifi','success','จาก '.$registeredDevices.' เครื่อง'],
      ['รับทราบแล้ว',$acknowledgedCount,'users','amber',$ackPercent.'% ของรายการที่ส่ง'],
    ] as [$label,$value,$icon,$tone,$hint])
      <article class="cn-dashboard-stat cn-stat-{{ $tone }}">
        <div class="cn-dashboard-stat-top"><span class="cn-stat-icon"><i class="bi {{ ['doc'=>'bi-megaphone-fill','device'=>'bi-pc-display','wifi'=>'bi-wifi','users'=>'bi-person-check-fill'][$icon] }}" aria-hidden="true"></i></span><span>{{ $label }}</span></div>
        <div class="cn-dashboard-stat-main"><strong>{{ number_format($value) }}</strong><span class="cn-mini-spark"><i></i><i></i><i></i><i></i><i></i></span></div>
        <div class="cn-dashboard-stat-hint">{{ $hint }}</div>
      </article>
    @endforeach
  </section>
  <section class="cn-dashboard-grid cn-dashboard-grid-main">
    <article class="cn-panel cn-dashboard-card cn-chart-card">
      <div class="cn-panel-header cn-card-heading"><div><span class="cn-card-icon"><i class="bi bi-bar-chart-fill" aria-hidden="true"></i></span><strong>ภาพรวมประกาศ</strong></div><span class="cn-card-filter">7 วันที่ผ่านมา</span></div>
      <div class="cn-panel-body">
        <div class="cn-bar-chart">
          @foreach($chart as $day)
            <div class="cn-bar-column">
              <div class="cn-bar-pair">
                <i class="cn-bar-created" style="height:{{ max(5, round(($day['created']/$maxChart)*118)) }}px"></i>
                <i class="cn-bar-ack" style="height:{{ max(5, round(($day['acknowledged']/$maxChart)*118)) }}px"></i>
              </div>
              <small>{{ $day['label'] }}</small>
            </div>
          @endforeach
        </div>
        <div class="cn-chart-legend"><span><i class="is-blue"></i>ประกาศที่สร้าง</span><span><i class="is-green"></i>รับทราบแล้ว</span></div>
      </div>
    </article>

    <article class="cn-panel cn-dashboard-card">
      <div class="cn-panel-header cn-card-heading"><div><span class="cn-card-icon"><i class="bi bi-pie-chart-fill" aria-hidden="true"></i></span><strong>สถานะการรับทราบ</strong></div></div>
      <div class="cn-panel-body cn-ack-overview">
        <div class="cn-donut" style="--value:{{ min(100,$ackPercent) }}"><div><strong>{{ $ackPercent }}%</strong><span>รับทราบแล้ว</span></div></div>
        <div class="cn-meta text-center mt-2">คำนวณจากรายการประกาศต่อเครื่องที่ส่งทั้งหมด ไม่ใช่จำนวนคนไม่ซ้ำ</div>
        <div class="cn-ack-legend">
          <div><span><i class="is-green"></i>รับทราบแล้ว</span><strong>{{ $acknowledgedCount }}</strong></div>
          <div><span><i class="is-blue"></i>ยังไม่รับทราบ</span><strong>{{ $pendingCount }}</strong></div>
          <div><span><i class="is-gray"></i>รายการที่ส่งทั้งหมด</span><strong>{{ $assignedCount }}</strong></div>
        </div>
      </div>
    </article>

    <article class="cn-panel cn-dashboard-card">
      <div class="cn-panel-header cn-card-heading"><div><span class="cn-card-icon"><i class="bi bi-pc-display-horizontal" aria-hidden="true"></i></span><strong>สถานะอุปกรณ์</strong></div><a href="{{ route('devices.index') }}">ดูทั้งหมด <i class="bi bi-arrow-right" aria-hidden="true"></i></a></div>
      <div class="cn-panel-body cn-device-list">
        @forelse($devices as $device)
          @php($online=$device->is_active && $device->last_seen_at?->gte(now()->subMinutes(2)))
          <div class="cn-device-row"><span class="cn-device-dot {{ $online?'is-online':'' }}"></span><span class="cn-device-glyph"><i class="bi bi-pc-display" aria-hidden="true"></i></span><div><strong>{{ $device->hostname }}</strong><small>{{ $device->username }}{{ $device->department?' · '.$device->department:'' }}</small></div><span class="cn-device-state {{ $online?'is-online':'' }}">{{ $online?'ออนไลน์':'ออฟไลน์' }}</span></div>
        @empty
          <div class="cn-empty py-4">ยังไม่มีอุปกรณ์ลงทะเบียน</div>
        @endforelse
        <div class="cn-device-summary"><span>อุปกรณ์ทั้งหมด <strong>{{ $registeredDevices }}</strong></span><span><i class="is-green"></i>ออนไลน์ {{ $onlineDevices }}</span><span><i class="is-gray"></i>ออฟไลน์ {{ max($registeredDevices-$onlineDevices,0) }}</span></div>
      </div>
    </article>
  </section>
  <section class="cn-dashboard-grid cn-dashboard-grid-bottom">
    <article class="cn-panel cn-dashboard-card">
      <div class="cn-panel-header cn-card-heading"><div><span class="cn-card-icon"><i class="bi bi-activity" aria-hidden="true"></i></span><strong>กิจกรรมล่าสุด</strong></div></div>
      <div class="cn-panel-body cn-activity-list">
        @forelse($recentEvents as $event)
          <div class="cn-activity-row">
            <span class="cn-activity-dot"></span>
            <div><strong>{{ $eventLabels[$event->event_type] ?? str($event->event_type)->replace('_',' ')->headline() }}</strong><small>{{ $event->notification?->title ?? 'Notification #'.$event->notification_id }}</small></div>
            <time>{{ $event->event_at?->format('d/m H:i') }}</time>
          </div>
        @empty
          <div class="cn-empty py-4">ยังไม่มีกิจกรรมล่าสุด</div>
        @endforelse
      </div>
    </article>

    <article class="cn-panel cn-dashboard-card">
      <div class="cn-panel-header cn-card-heading"><div><span class="cn-card-icon"><i class="bi bi-megaphone-fill" aria-hidden="true"></i></span><strong>ประกาศล่าสุด</strong></div><a href="{{ route('notifications.index') }}">ดูทั้งหมด <i class="bi bi-arrow-right" aria-hidden="true"></i></a></div>
      <div class="cn-panel-body cn-recent-list">
        @forelse($recentNotifications as $notification)
          <a href="{{ route('notifications.show',$notification) }}" class="cn-recent-row">
            <span class="cn-recent-icon cn-recent-{{ $notification->type }}">{{ $notification->type==='policy'?'P':'N' }}</span>
            <div><strong>{{ $notification->title }}</strong><small>{{ str($notification->message)->limit(72) }}</small></div>
            <div class="cn-recent-side"><span class="cn-badge {{ $notification->is_active?'cn-badge-active':'cn-badge-inactive' }}">{{ $notification->is_active?'กำลังแสดง':'ปิดแล้ว' }}</span><small>{{ $notification->created_at?->format('d/m/Y') }}</small></div>
          </a>
        @empty
          <div class="cn-empty py-4">ยังไม่มีประกาศ</div>
        @endforelse
      </div>
    </article>

    <article class="cn-panel cn-dashboard-card">
      <div class="cn-panel-header cn-card-heading"><div><span class="cn-card-icon"><i class="bi bi-lightning-charge-fill" aria-hidden="true"></i></span><strong>การดำเนินการด่วน</strong></div></div>
      <div class="cn-panel-body cn-quick-actions">
        @if($role !== 'viewer')
        <a class="is-blue" href="{{ route('notifications.create') }}"><span><i class="bi bi-plus-circle-fill" aria-hidden="true"></i></span><strong>สร้างประกาศใหม่</strong><b><i class="bi bi-arrow-right" aria-hidden="true"></i></b></a>
        <a class="is-violet" href="{{ route('devices.index') }}"><span><i class="bi bi-pc-display" aria-hidden="true"></i></span><strong>จัดการอุปกรณ์</strong><b><i class="bi bi-arrow-right" aria-hidden="true"></i></b></a>
        @endif
        <a class="is-green" href="{{ route('notifications.index',['type'=>'policy']) }}"><span><i class="bi bi-shield-check" aria-hidden="true"></i></span><strong>ดู Policy และการรับทราบ</strong><b><i class="bi bi-arrow-right" aria-hidden="true"></i></b></a>
        <a class="is-gray" href="{{ route('notifications.index') }}"><span><i class="bi bi-list-ul" aria-hidden="true"></i></span><strong>ดูประกาศทั้งหมด</strong><b><i class="bi bi-arrow-right" aria-hidden="true"></i></b></a>
      </div>
    </article>
  </section>
</div>
@endsection
