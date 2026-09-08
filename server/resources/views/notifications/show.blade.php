@extends('layouts.app')
@section('title', $notification->title)
@section('content')
@php
  $typeClass = ['info'=>'info','warning'=>'warning','critical'=>'critical','policy'=>'policy'][$notification->type] ?? 'info';
  $targetLabel = ['all'=>'ทุกเครื่อง','department'=>'Department','device'=>'Device','user'=>'Username'][$notification->target_type] ?? $notification->target_type;
@endphp
<div class="cn-page">
  <div class="cn-page-header">
    <div>
      <div class="cn-eyebrow">Notification Detail</div>
      <h1 class="cn-title">{{ $notification->title }}</h1>
      <div class="d-flex gap-2 flex-wrap mt-2">
        <span class="cn-badge cn-badge-{{ $typeClass }}">{{ strtoupper($notification->type) }}</span>
        <span class="cn-badge {{ $notification->is_active ? 'cn-badge-active' : 'cn-badge-inactive' }}">{{ $notification->is_active ? 'เปิดใช้งาน' : 'ปิดแล้ว' }}</span>
        @if($notification->type === 'policy')<span class="cn-badge cn-badge-policy">Policy v{{ $notification->policy_version }}</span>@endif
      </div>
    </div>
    <div class="d-flex gap-2"><a href="{{ route('notifications.index') }}" class="btn btn-outline-secondary">กลับ</a><a href="{{ route('notifications.edit',$notification) }}" class="btn btn-primary">แก้ไข</a></div>
  </div>

  <div class="cn-stat-grid">
    <div class="cn-stat"><div class="cn-stat-label">Delivered</div><div class="cn-stat-value">{{ $notification->delivered_count }}</div></div>
    <div class="cn-stat"><div class="cn-stat-label">Opened</div><div class="cn-stat-value">{{ $notification->opened_count }}</div></div>
    @if($notification->type === 'policy')<div class="cn-stat"><div class="cn-stat-label">Read complete</div><div class="cn-stat-value">{{ $notification->read_completed_count }}</div></div>@endif
    <div class="cn-stat"><div class="cn-stat-label">Acknowledged</div><div class="cn-stat-value">{{ $notification->acknowledged_count }}</div></div>
    <div class="cn-stat"><div class="cn-stat-label">Target</div><div class="cn-stat-value fs-5">{{ $targetLabel }}</div></div>
  </div>
  <div class="cn-detail-grid">
    <div class="cn-panel">
      <div class="cn-panel-header"><div class="cn-section-title mb-0">เนื้อหาประกาศ</div></div>
      <div class="cn-panel-body"><div style="white-space:pre-wrap; line-height:1.75">{{ $notification->message }}</div></div>
    </div>
    <div class="cn-panel">
      <div class="cn-panel-header"><div class="cn-section-title mb-0">รายละเอียด</div></div>
      <div class="cn-panel-body">
        <dl class="cn-kv mb-0">
          <dt>เป้าหมาย</dt><dd>{{ $targetLabel }} {{ $notification->target_value ? '· '.$notification->target_value : '' }}</dd>
          <dt>เริ่ม</dt><dd>{{ $notification->start_at->format('d/m/Y H:i') }}</dd>
          <dt>หมดอายุ</dt><dd>{{ $notification->expire_at?->format('d/m/Y H:i') ?? 'ไม่กำหนด' }}</dd>
          <dt>URL</dt><dd>@if($notification->url)<a href="{{ $notification->url }}" target="_blank" rel="noopener">{{ $notification->url }}</a>@else - @endif</dd>
          <dt>ผู้สร้าง</dt><dd>{{ $notification->creator->name }}</dd>
        </dl>
      </div>
    </div>
  </div>

  @if($notification->type === 'policy')
  <div class="cn-panel">
    <div class="cn-panel-header d-flex justify-content-between align-items-center"><div><div class="cn-section-title mb-0">Policy + แบบทดสอบ</div><div class="cn-meta mt-1">พนักงานต้องอ่านและตอบถูกก่อนรับทราบ</div></div><span class="cn-badge cn-badge-policy">POLICY</span></div>
    <div class="cn-panel-body"><div class="mb-4" style="white-space:pre-wrap; line-height:1.75">{{ $notification->policy_body }}</div>
      <div class="border-top pt-4">
        <div class="fw-bold mb-3">คำถามแบบทดสอบ ({{ $notification->quizQuestions->count() }} ข้อ)</div>
        <div class="d-grid gap-2">
          @foreach($notification->quizQuestions as $q)
            <div class="border rounded-3 p-3 bg-light d-flex justify-content-between gap-3 align-items-start">
              <div>{{ $loop->iteration }}. {{ $q->question }}</div>
              <span class="cn-badge cn-badge-active">{{ $q->correct_answer === 'use' ? 'ใช้' : 'ไม่ใช้' }}</span>
            </div>
          @endforeach
        </div>
      </div>
    </div>
  </div>

  <div class="cn-panel">
    <div class="cn-panel-header d-flex justify-content-between align-items-center gap-3 flex-wrap">
      <div><div class="cn-section-title mb-0">Policy Tracking & Audit</div><div class="cn-meta mt-1">ติดตามการอ่าน แบบทดสอบ และประวัติ Event รายเครื่อง</div></div>
      <div class="d-flex gap-2 flex-wrap">
        <a class="btn btn-sm btn-outline-secondary" href="{{ route('notifications.export.csv',$notification) }}">Export CSV</a>
        <a class="btn btn-sm btn-outline-success" href="{{ route('notifications.export.excel',$notification) }}">Export Excel</a>
      </div>
    </div>
    <div class="cn-panel-body">
      <form class="row g-2 mb-3" method="GET">
        <div class="col-md-6"><input class="form-control" name="q" value="{{ request('q') }}" placeholder="ค้นหา Hostname / Username / Department / UUID"></div>
        <div class="col-md-4"><select class="form-select" name="status"><option value="">ทุกสถานะ</option>@foreach(['not_opened'=>'ยังไม่เปิด','reading'=>'กำลังอ่าน','read'=>'อ่านครบแล้ว','failed'=>'Quiz ไม่ผ่าน','passed'=>'Quiz ผ่าน','acknowledged'=>'รับทราบแล้ว'] as $value=>$label)<option value="{{ $value }}" @selected(request('status')===$value)>{{ $label }}</option>@endforeach</select></div>
        <div class="col-md-2 d-grid"><button class="btn btn-outline-primary">ค้นหา / กรอง</button></div>
      </form>

      <div class="d-grid gap-3">
      @forelse($tracking as $row)
        @php
          $p=$row['pivot'];
          $statusLabels=['not_opened'=>'ยังไม่เปิด','reading'=>'กำลังอ่าน','read'=>'อ่านครบแล้ว','failed'=>'Quiz ไม่ผ่าน','passed'=>'Quiz ผ่าน','acknowledged'=>'รับทราบแล้ว'];
          $statusClass=['not_opened'=>'cn-badge-inactive','reading'=>'cn-badge-info','read'=>'cn-badge-info','failed'=>'cn-badge-critical','passed'=>'cn-badge-active','acknowledged'=>'cn-badge-active'][$row['status']] ?? 'cn-badge-info';
        @endphp
        <details class="border rounded-3 bg-white p-3 cn-audit-device">
          <summary class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
            <div><div class="fw-bold fs-6">{{ $row['hostname'] }}</div><div class="cn-meta">{{ $row['username'] }} · {{ $row['department'] ?: 'ไม่ระบุแผนก' }} · {{ $row['device']->device_uuid }}</div></div>
            <div class="text-end"><span class="cn-badge {{ $statusClass }}">{{ $statusLabels[$row['status']] ?? $row['status'] }}</span><div class="cn-meta mt-1">ล่าสุด {{ $row['last_activity']?->format('d/m/Y H:i:s') ?? '-' }}</div></div>
          </summary>
          <div class="row g-2 mt-3">
            @foreach(['Delivered'=>$p->delivered_at,'Opened'=>$p->opened_at,'Read complete'=>$p->read_completed_at,'Acknowledged'=>$p->acknowledged_at] as $label=>$time)
              <div class="col-md-3"><div class="border rounded-3 p-2 h-100"><div class="cn-meta">{{ $label }}</div><div class="fw-semibold mt-1">{{ $time ? \Illuminate\Support\Carbon::parse($time)->format('d/m/Y H:i:s') : '-' }}</div></div></div>
            @endforeach
          </div>

          <div class="mt-4"><div class="fw-bold mb-2">Quiz Attempts ({{ $row['attempts']->count() }})</div>
            @forelse($row['attempts'] as $attempt)
              <div class="border rounded-3 p-3 mb-2"><div class="d-flex justify-content-between gap-2"><strong>Attempt #{{ $attempt->attempt_no }}</strong><span class="cn-badge {{ $attempt->passed ? 'cn-badge-active':'cn-badge-critical' }}">{{ $attempt->passed ? 'PASS':'FAIL' }}</span></div><div class="cn-meta mb-2">{{ $attempt->submitted_at?->format('d/m/Y H:i:s') ?? '-' }}</div>
                @foreach(($attempt->answers ?? collect()) as $answer)<div class="border-top py-2 d-flex justify-content-between gap-3 flex-wrap"><div>{{ $answer->question_snapshot }}</div><div><span class="cn-badge cn-badge-info">{{ $answer->answer==='use'?'ใช้':'ไม่ใช้' }}</span> <span class="cn-badge {{ $answer->is_correct?'cn-badge-active':'cn-badge-critical' }}">{{ $answer->is_correct?'ถูก':'ผิด' }}</span></div></div>@endforeach
              </div>
            @empty<div class="cn-meta">ยังไม่มีการส่งแบบทดสอบ</div>@endforelse
          </div>

          <div class="mt-4"><div class="fw-bold mb-2">Audit Timeline</div><div class="d-grid gap-1">@forelse($row['events'] as $event)<div class="d-flex justify-content-between border-top py-2"><span>{{ str($event->event_type)->replace('_',' ')->headline() }}</span><span class="cn-meta">{{ $event->event_at?->format('d/m/Y H:i:s') }}</span></div>@empty<div class="cn-meta">ยังไม่มี Event</div>@endforelse</div></div>
        </details>
      @empty
        <div class="cn-empty py-4"><div class="fw-bold text-dark">ไม่พบข้อมูลเครื่องตามเงื่อนไข</div><div class="mt-1">ข้อมูลจะปรากฏเมื่อ Agent ได้รับประกาศนี้</div></div>
      @endforelse
      </div>
    </div>
  </div>
  @endif
</div>
@endsection
