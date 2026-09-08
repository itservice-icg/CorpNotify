@extends('layouts.app')
@section('title', 'ประกาศ')
@section('content')
@php
    $pageItems = $notifications->getCollection();
    $activeCount = $pageItems->where('is_active', true)->count();
    $inactiveCount = $pageItems->where('is_active', false)->count();
    $noAckCount = $pageItems->where('acknowledged_count', 0)->count();
    $withAckCount = $pageItems->where('acknowledged_count', '>', 0)->count();
    $deliveredCount = $pageItems->sum('delivered_count');
    $ackCount = $pageItems->sum('acknowledged_count');
@endphp
<div class="cn-page cn-notifications-page cn-notifications-modern">
  <header class="cn-modern-hero">
    <div>
      <div class="cn-eyebrow">Communication Center</div>
      <div class="cn-modern-title-row">
        <h1>Notifications</h1>
        <span class="cn-count-pill">{{ $notifications->total() }}</span>
      </div>
      <p>ติดตามประกาศภายในองค์กร การส่งถึง และการรับทราบจากเครื่องพนักงาน</p>
    </div>
    @if(in_array(auth()->user()->role, ['admin', 'manager'], true))
      <a href="{{ route('notifications.create') }}" class="btn btn-primary cn-create-modern"><i class="bi bi-plus-circle" aria-hidden="true"></i> สร้างประกาศ</a>
    @endif
  </header>  <section class="cn-modern-metrics" aria-label="สรุปประกาศในหน้านี้">
    <div><span><i class="bi bi-file-earmark-text" aria-hidden="true"></i> ในหน้านี้</span><strong>{{ $pageItems->count() }}</strong></div>
    <div><span><i class="bi bi-play-circle" aria-hidden="true"></i> เปิดใช้งาน</span><strong>{{ $activeCount }}</strong></div>
    <div><span><i class="bi bi-send" aria-hidden="true"></i> ส่งถึงแล้ว</span><strong>{{ $deliveredCount }}</strong></div>
    <div><span><i class="bi bi-person-check" aria-hidden="true"></i> รับทราบแล้ว</span><strong>{{ $ackCount }}</strong></div>
  </section>

  <section class="cn-modern-panel">
    <div class="cn-modern-toolbar">
      <label class="cn-modern-search" for="notification-search">
        <i class="bi bi-search" aria-hidden="true"></i>
        <input id="notification-search" type="search" placeholder="ค้นหาหัวข้อ เนื้อหา เป้าหมาย หรือประเภท" autocomplete="off">
      </label>
      <select id="notification-type" class="cn-modern-select" aria-label="กรองตามประเภทประกาศ">
        <option value="all">ทุกประเภท</option>
        <option value="info">Info</option>
        <option value="warning">Warning</option>
        <option value="critical">Critical</option>
        <option value="policy">Policy</option>
      </select>
    </div>

    <div class="cn-modern-tabs" role="tablist" aria-label="กรองสถานะประกาศ">
      <button type="button" class="cn-modern-tab active" data-filter="all" aria-pressed="true">ทั้งหมด <span>{{ $pageItems->count() }}</span></button>
      <button type="button" class="cn-modern-tab" data-filter="active" aria-pressed="false">เปิดอยู่ <span>{{ $activeCount }}</span></button>
      <button type="button" class="cn-modern-tab" data-filter="inactive" aria-pressed="false">ปิดแล้ว <span>{{ $inactiveCount }}</span></button>
      <button type="button" class="cn-modern-tab" data-filter="noack" aria-pressed="false">ยังไม่มีผู้รับทราบ <span>{{ $noAckCount }}</span></button>
      <button type="button" class="cn-modern-tab" data-filter="withack" aria-pressed="false">มีผู้รับทราบ <span>{{ $withAckCount }}</span></button>
    </div>
    <div class="cn-feed" id="notification-list">
      @forelse($notifications as $notification)
        @php
          $typeClass = ['info'=>'info','warning'=>'warning','critical'=>'critical','policy'=>'policy'][$notification->type] ?? 'info';
          $targetLabel = ['all'=>'ทุกเครื่อง','department'=>'Department','device'=>'Device','user'=>'Username'][$notification->target_type] ?? $notification->target_type;
          $hasAck = $notification->acknowledged_count > 0;
          $statusToken = $notification->is_active ? 'active' : 'inactive';
          $icon = ['info'=>'i','warning'=>'!','critical'=>'!!','policy'=>'P'][$notification->type] ?? 'i';
        @endphp
        <article class="cn-feed-item {{ $notification->is_active ? 'is-active' : 'is-inactive' }}"
          data-status="{{ $statusToken }}"
          data-ack="{{ $hasAck ? 'withack' : 'noack' }}"
          data-type="{{ $notification->type }}"
          data-search="{{ strtolower($notification->title.' '.$notification->message.' '.$notification->type.' '.$notification->target_type.' '.$notification->target_value) }}">
          <div class="cn-feed-status" aria-hidden="true"></div>
          <div class="cn-feed-icon cn-notification-icon-{{ $typeClass }}" aria-hidden="true"><i class="bi {{ ['info'=>'bi-info-circle-fill','warning'=>'bi-exclamation-triangle-fill','critical'=>'bi-exclamation-octagon-fill','policy'=>'bi-shield-check'][$notification->type] ?? 'bi-bell-fill' }}"></i></div>
          <div class="cn-feed-body">
            <div class="cn-feed-heading">
              <div class="cn-feed-title-wrap">
                <a href="{{ route('notifications.show',$notification) }}" class="cn-feed-title">{{ $notification->title }}</a>
                <span class="cn-badge cn-badge-{{ $typeClass }}">{{ strtoupper($notification->type) }}</span>
              </div>
              <time datetime="{{ $notification->start_at->toIso8601String() }}" title="{{ $notification->start_at->format('d/m/Y H:i') }}">{{ $notification->start_at->diffForHumans() }}</time>
            </div>            <p class="cn-feed-description">{{ $notification->message }}</p>
            <div class="cn-feed-meta">
              <span class="cn-meta-pill"><i class="bi {{ $notification->is_active ? 'bi-check-circle-fill' : 'bi-pause-circle-fill' }}" aria-hidden="true"></i>{{ $notification->is_active ? 'เปิดใช้งาน' : 'ปิดแล้ว' }}</span>
              <span class="cn-meta-pill"><i class="bi bi-pc-display" aria-hidden="true"></i>{{ $targetLabel }}{{ $notification->target_value ? ' · '.$notification->target_value : '' }}</span>
              <span class="cn-meta-pill"><i class="bi bi-send" aria-hidden="true"></i>ส่งถึง {{ $notification->delivered_count }}</span>
              <span class="cn-meta-pill"><i class="bi bi-people" aria-hidden="true"></i>รับทราบ {{ $notification->acknowledged_count }}</span>
              <span class="cn-meta-pill"><i class="bi bi-calendar3" aria-hidden="true"></i>หมดอายุ {{ $notification->expire_at?->format('d/m/Y H:i') ?? 'ไม่กำหนด' }}</span>
            </div>
          </div>

          <details class="cn-feed-menu">
            <summary aria-label="เมนูการทำงาน"><i class="bi bi-three-dots-vertical" aria-hidden="true"></i></summary>
            <div class="cn-feed-menu-popover">
              <a href="{{ route('notifications.show',$notification) }}">ดูรายละเอียด</a>
              @if(in_array(auth()->user()->role, ['admin', 'manager'], true))
                <a href="{{ route('notifications.edit',$notification) }}">แก้ไข</a>
                @if($notification->is_active)
                  <form method="POST" action="{{ route('notifications.deactivate',$notification) }}">
                    @csrf @method('PATCH')
                    <button type="submit" class="js-deactivate-notification">ปิดประกาศ</button>
                  </form>
                @endif
              @endif
            </div>
          </details>
        </article>
      @empty
        <div class="cn-modern-empty">
          <div class="cn-modern-empty-icon" aria-hidden="true"><i class="bi bi-inbox"></i></div>
          <h2>ยังไม่มีประกาศ</h2>
          <p>เมื่อมีประกาศใหม่ รายการจะปรากฏที่นี่</p>
          @if(in_array(auth()->user()->role, ['admin', 'manager'], true))
            <a href="{{ route('notifications.create') }}" class="btn btn-primary">สร้างประกาศ</a>
          @endif
        </div>
      @endforelse      <div id="notification-no-results" class="cn-modern-empty d-none" role="status">
        <div class="cn-modern-empty-icon" aria-hidden="true"><i class="bi bi-search"></i></div>
        <h2>ไม่พบประกาศ</h2>
        <p>ลองเปลี่ยนคำค้นหรือเลือกตัวกรองอื่น</p>
      </div>
    </div>

    <div class="cn-modern-pagination">{{ $notifications->links() }}</div>
  </section>
</div>
<script>
document.addEventListener('DOMContentLoaded', () => {
  const search = document.getElementById('notification-search');
  const type = document.getElementById('notification-type');
  const tabs = [...document.querySelectorAll('.cn-modern-tab')];
  const items = [...document.querySelectorAll('.cn-feed-item')];
  const empty = document.getElementById('notification-no-results');
  let currentFilter = 'all';

  const applyFilters = () => {
    const term = search.value.trim().toLowerCase();
    const selectedType = type.value;
    let visible = 0;
    items.forEach(item => {
      const statusMatch = currentFilter === 'all' || item.dataset.status === currentFilter || item.dataset.ack === currentFilter;
      const typeMatch = selectedType === 'all' || item.dataset.type === selectedType;
      const textMatch = !term || item.dataset.search.includes(term);
      const show = statusMatch && typeMatch && textMatch;
      item.hidden = !show;
      if (show) visible++;
    });    empty.classList.toggle('d-none', visible !== 0 || items.length === 0);
  };

  tabs.forEach(tab => tab.addEventListener('click', () => {
    currentFilter = tab.dataset.filter;
    tabs.forEach(btn => {
      const active = btn === tab;
      btn.classList.toggle('active', active);
      btn.setAttribute('aria-pressed', active ? 'true' : 'false');
    });
    applyFilters();
  }));

  search.addEventListener('input', applyFilters);
  type.addEventListener('change', applyFilters);

  document.querySelectorAll('.js-deactivate-notification').forEach(button => {
    button.closest('form').addEventListener('submit', event => {
      event.preventDefault();
      Swal.fire({
        title: 'ปิดประกาศนี้?',
        text: 'เมื่อปิดแล้ว ประกาศจะไม่ถูกส่งให้เครื่องใหม่',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'ตกลง',
        cancelButtonText: 'ยกเลิก',
        reverseButtons: true,
        focusCancel: true,
        buttonsStyling: false,
        customClass: { confirmButton: 'btn btn-danger mx-1', cancelButton: 'btn btn-light border mx-1' }
      }).then(result => {
        if (result.isConfirmed) event.target.submit();
      });
    });
  });
});
</script>
@endsection
