@extends('layouts.app')
@php($editing = $notification->exists)
@section('title', $editing ? 'แก้ไขประกาศ' : 'สร้างประกาศ')
@section('content')
<div class="cn-page">
  <div class="cn-page-header">
    <div>
      <div class="cn-eyebrow">Notification Composer</div>
      <h1 class="cn-title">{{ $editing ? 'แก้ไขประกาศ' : 'สร้างประกาศใหม่' }}</h1>
      <p class="cn-subtitle">กำหนดข้อความ กลุ่มเป้าหมาย เวลาเผยแพร่ และ Policy Quiz ได้ในหน้าเดียว</p>
    </div>
    <a href="{{ route('notifications.index') }}" class="btn btn-outline-secondary">กลับรายการ</a>
  </div>

  <form method="POST" enctype="multipart/form-data" action="{{ $editing ? route('notifications.update',$notification) : route('notifications.store') }}" class="cn-form-grid">
    @csrf @if($editing) @method('PUT') @endif
    <div class="cn-panel">
      <section class="cn-section">
        <div class="cn-section-title">เนื้อหาประกาศ</div>
        <div class="cn-section-help">เขียนให้สั้น ชัด และผู้รับเข้าใจได้ทันที</div>
        <div class="mb-3"><label class="form-label cn-field-label">หัวข้อ</label><input class="form-control form-control-lg" name="title" value="{{ old('title',$notification->title) }}" maxlength="255" required></div>
        <div><label class="form-label cn-field-label">ข้อความ</label><textarea class="form-control" name="message" rows="7" required>{{ old('message',$notification->message) }}</textarea></div>
      </section>
      <section class="cn-section">
        <div class="cn-section-title">การส่งและกลุ่มเป้าหมาย</div>
        <div class="cn-section-help">กำหนดประเภทประกาศ ผู้รับ และลิงก์รายละเอียด</div>
        <div class="row g-3">
          <div class="col-md-4"><label class="form-label cn-field-label">ประเภท</label><select class="form-select" id="type" name="type" onchange="togglePolicySection(this.value==='policy')">@foreach(['info','warning','critical','policy'] as $type)<option value="{{ $type }}" @selected(old('type',$notification->type ?? 'info')===$type)>{{ $type === 'policy' ? 'Policy (อ่าน + แบบทดสอบ)' : ucfirst($type) }}</option>@endforeach</select></div>
          <div class="col-md-4"><label class="form-label cn-field-label">เป้าหมาย</label><select class="form-select" name="target_type">@foreach(['all'=>'ทุกเครื่อง','department'=>'Department','device'=>'Device UUID','user'=>'Username'] as $value=>$label)<option value="{{ $value }}" @selected(old('target_type',$notification->target_type ?? 'all')===$value)>{{ $label }}</option>@endforeach</select></div>
          <div class="col-md-4"><label class="form-label cn-field-label">ค่าเป้าหมาย</label><input class="form-control" name="target_value" value="{{ old('target_value',$notification->target_value) }}" placeholder="เว้นว่างเมื่อเลือกทุกเครื่อง"></div>
          <div class="col-12"><label class="form-label cn-field-label">รูปภาพประกาศ</label><input class="form-control" type="file" name="image" accept="image/jpeg,image/png,image/webp,image/gif"><div class="form-text">รองรับ JPG, PNG, WEBP, GIF ขนาดไม่เกิน 5 MB</div></div>
          <div class="col-12"><label class="form-label cn-field-label">URL รายละเอียด</label><input type="url" class="form-control" name="url" value="{{ old('url',$notification->url) }}" placeholder="https://..."><div class="form-text">รองรับเฉพาะ http / https</div></div>
        </div>
      </section>

      <section class="cn-section">
        <div class="cn-section-title">ช่วงเวลาเผยแพร่</div>
        <div class="cn-section-help">กำหนดเวลาที่ Agent จะเริ่มเห็นประกาศ และวันหมดอายุถ้ามี</div>
        <div class="row g-3">
          <div class="col-md-6"><label class="form-label cn-field-label">เริ่มเผยแพร่</label><input type="datetime-local" class="form-control" name="start_at" value="{{ old('start_at',$notification->start_at?->format('Y-m-d\\TH:i') ?? now()->format('Y-m-d\\TH:i')) }}" required></div>
          <div class="col-md-6"><label class="form-label cn-field-label">หมดอายุ</label><input type="datetime-local" class="form-control" name="expire_at" value="{{ old('expire_at',$notification->expire_at?->format('Y-m-d\\TH:i')) }}"></div>
        </div>
      </section>
      <section id="policy-section" class="cn-section" style="display:none">
        <div class="cn-section-title">Policy + แบบทดสอบ</div>
        <div class="cn-section-help">พนักงานต้องอ่านเนื้อหาและตอบคำถามให้ถูกต้องก่อนรับทราบ</div>
        <div class="mb-3"><label class="form-label cn-field-label">เนื้อหานโยบาย</label><textarea class="form-control" name="policy_body" rows="9" placeholder="พิมพ์เนื้อหานโยบาย...">{{ old('policy_body', $notification->policy_body) }}</textarea></div>
        <div class="d-flex justify-content-between align-items-center mb-2"><label class="form-label cn-field-label mb-0">คำถามแบบทดสอบ</label><button type="button" class="btn btn-sm btn-outline-primary" onclick="addQuizRow()">+ เพิ่มคำถาม</button></div>
        <div class="cn-section-help mb-3">คำตอบใช้ค่า “ใช้ / ไม่ใช้” และต้องตอบถูกครบทุกข้อ</div>
        <div id="quiz-questions"></div>
      </section>
    </div>

    <aside class="cn-panel cn-sticky">
      <div class="cn-panel-body">
        <div class="cn-section-title">สถานะประกาศ</div>
        <p class="cn-side-note">เปิดใช้งานเพื่อให้ Agent เห็นประกาศตามช่วงเวลาที่กำหนด ปิดได้ภายหลังโดยไม่ลบประวัติ</p>
        <div class="form-check form-switch py-2 mb-3">
          <input type="hidden" name="is_active" value="0">
          <input class="form-check-input" type="checkbox" role="switch" name="is_active" value="1" id="active" @checked(old('is_active',$notification->exists ? $notification->is_active : true))>
          <label class="form-check-label fw-bold ms-1" for="active">เปิดใช้งาน</label>
        </div>
        <div class="d-grid gap-2"><button type="submit" class="btn btn-primary cn-btn-primary js-notification-submit">{{ $editing ? 'บันทึกการแก้ไข' : 'สร้างประกาศ' }}</button><a class="btn btn-light border" href="{{ route('notifications.index') }}">ยกเลิก</a></div>
      </div>
    </aside>
  </form>
</div>
<template id="quiz-row-template">
  <div class="quiz-row cn-panel p-3 mb-2 d-flex gap-2 align-items-start">
    <input type="text" class="form-control" name="questions[__INDEX__][question]" maxlength="1000" placeholder="เช่น พนักงานสามารถแชร์รหัสผ่านให้เพื่อนร่วมงานได้">
    <select class="form-select" style="max-width:170px" name="questions[__INDEX__][correct_answer]">
      <option value="use">คำตอบ: ใช้</option>
      <option value="not_use">คำตอบ: ไม่ใช้</option>
    </select>
    <button type="button" class="btn btn-outline-danger" onclick="this.closest('.quiz-row').remove()">ลบ</button>
  </div>
</template>
<script>
let quizIndex = 0;
const existingQuestions = @json(old('questions', $notification->quizQuestions ?? []));
function addQuizRow(question = '', correctAnswer = 'use') {
  const html = document.getElementById('quiz-row-template').innerHTML.replaceAll('__INDEX__', quizIndex);
  const wrapper = document.createElement('div'); wrapper.innerHTML = html;
  const row = wrapper.firstElementChild;
  row.querySelector('input[type=text]').value = question;
  row.querySelector('select').value = correctAnswer === 'not_use' ? 'not_use' : 'use';
  document.getElementById('quiz-questions').appendChild(row); quizIndex++;
}
function togglePolicySection(show) { document.getElementById('policy-section').style.display = show ? 'block' : 'none'; }
document.addEventListener('DOMContentLoaded', () => {
  togglePolicySection(document.getElementById('type').value === 'policy');
  if (existingQuestions.length) existingQuestions.forEach(q => addQuizRow(q.question ?? '', q.correct_answer ?? 'use')); else addQuizRow();

  const form = document.querySelector('.cn-form-grid');
  const submitButton = document.querySelector('.js-notification-submit');
  form.addEventListener('submit', event => {
    event.preventDefault();
    Swal.fire({
      title: @json($editing ? 'บันทึกการแก้ไขนี้?' : 'สร้างประกาศนี้?'),
      text: @json($editing ? 'ข้อมูลประกาศจะถูกปรับปรุงตามรายการที่แก้ไข' : 'ประกาศจะถูกบันทึกและเผยแพร่ตามช่วงเวลาที่กำหนด'),
      icon: @json($editing ? 'warning' : 'question'),
      showCancelButton: true,
      confirmButtonText: @json($editing ? 'บันทึกการแก้ไข' : 'สร้างประกาศ'),
      cancelButtonText: 'ยกเลิก',
      reverseButtons: true,
      focusCancel: true,
      buttonsStyling: false,
      customClass: { confirmButton: 'btn btn-primary mx-1', cancelButton: 'btn btn-light border mx-1' }
    }).then(result => {
      if (result.isConfirmed) {
        submitButton.disabled = true;
        form.submit();
      }
    });
  });
});
</script>
@endsection
