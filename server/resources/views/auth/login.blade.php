@extends('layouts.app')
@section('title', 'เข้าสู่ระบบ')
@section('body_class', 'cn-login-body')
@section('content')
<main class="cn-login-shell">
  <section class="cn-login-card" aria-labelledby="login-title">
    <img class="cn-login-logo" src="{{ asset('images/corpnotify-logo.png') }}" alt="CorpNotify">
    <h1 id="login-title">เข้าสู่ระบบผู้ดูแล</h1>
    <p class="cn-login-subtitle">เข้าสู่ระบบเพื่อจัดการประกาศและอุปกรณ์</p>
    <form method="POST" action="{{ route('login.store') }}" class="cn-login-form">
      @csrf
      <div class="cn-login-field"><label for="email">อีเมล</label><input id="email" type="email" name="email" value="{{ old('email') }}" placeholder="admin@example.com" autocomplete="email" required autofocus></div>
      <div class="cn-login-field"><label for="password">รหัสผ่าน</label><input id="password" type="password" name="password" placeholder="••••••••" autocomplete="current-password" required></div>
      <label class="cn-login-remember"><input type="checkbox" name="remember" value="1"><span>จดจำฉัน</span></label>
      <button type="submit" class="cn-login-submit">เข้าสู่ระบบ</button>
    </form>
  </section>
</main>
@endsection
