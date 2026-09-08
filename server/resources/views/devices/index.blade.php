@extends('layouts.app')
@section('title', 'อุปกรณ์')
@section('content')
@php
    $pageDevices = $devices->getCollection();
    $onlineCount = $pageDevices->filter(fn ($device) => $device->is_active && $device->last_seen_at?->gte(now()->subMinutes(2)))->count();
    $activeCount = $pageDevices->where('is_active', true)->count();
@endphp
<div class="cn-page cn-devices-page">
  <header class="cn-devices-hero">
    <div>
      <div class="cn-eyebrow">DEVICE MANAGEMENT</div>
      <div class="cn-devices-title-row"><h1 class="cn-title">อุปกรณ์</h1><span class="cn-count-pill">{{ number_format($devices->total()) }}</span></div>
      <p class="cn-subtitle">ตรวจสอบอุปกรณ์ที่ลงทะเบียน สถานะการเชื่อมต่อ และข้อมูล Agent</p>
    </div>
  </header>
  <section class="cn-devices-metrics" aria-label="สรุปอุปกรณ์">
    <div><span><i class="bi bi-pc-display" aria-hidden="true"></i> อุปกรณ์ทั้งหมด</span><strong>{{ number_format($devices->total()) }}</strong></div>
    <div><span><i class="bi bi-check-circle" aria-hidden="true"></i> ลงทะเบียนใช้งาน</span><strong>{{ number_format($activeCount) }}</strong></div>
    <div><span><i class="bi bi-wifi" aria-hidden="true"></i> ออนไลน์ใน 2 นาที</span><strong>{{ number_format($onlineCount) }}</strong></div>
  </section>
  <section class="cn-devices-panel">
    <div class="cn-devices-panel-header"><div><h2>รายการอุปกรณ์</h2><p>เรียงตามเวลาที่เชื่อมต่อล่าสุด</p></div><span class="cn-page-count">หน้า {{ $devices->currentPage() }} / {{ $devices->lastPage() }}</span></div>
    <div class="cn-device-feed">
      @forelse($devices as $device)
        @php($online = $device->is_active && $device->last_seen_at?->gte(now()->subMinutes(2)))
        <article class="cn-device-card {{ $online ? 'is-online' : '' }}">
          <div class="cn-device-card-icon"><i class="bi bi-pc-display" aria-hidden="true"></i></div>
          <div class="cn-device-card-main">
            <div class="cn-device-card-title"><h3>{{ $device->hostname }}</h3><span class="cn-device-status {{ $online ? 'is-online' : '' }}"><i class="bi {{ $online ? 'bi-wifi' : 'bi-wifi-off' }}" aria-hidden="true"></i>{{ $online ? 'ออนไลน์' : 'ออฟไลน์' }}</span></div>
            <div class="cn-device-card-meta"><span><i class="bi bi-person" aria-hidden="true"></i>{{ $device->username }}</span><span><i class="bi bi-diagram-3" aria-hidden="true"></i>{{ $device->department ?: 'ไม่ระบุแผนก' }}</span><span><i class="bi bi-globe2" aria-hidden="true"></i>{{ $device->ip_address ?: '-' }}</span></div>
          </div>
          <div class="cn-device-card-details"><span>Agent {{ $device->agent_version }}</span><small>เชื่อมต่อล่าสุด {{ $device->last_seen_at?->format('d/m/Y H:i:s') ?? '-' }}</small></div>
        </article>
      @empty
        <div class="cn-device-empty"><i class="bi bi-pc-display" aria-hidden="true"></i><h2>ยังไม่มีอุปกรณ์</h2><p>เมื่อมี Agent ลงทะเบียน อุปกรณ์จะแสดงที่นี่</p></div>
      @endforelse
    </div>
    @if($devices->hasPages())<div class="cn-devices-pagination">{{ $devices->links() }}</div>@endif
  </section>
</div>
@endsection
