<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'CorpNotify')</title>
    <link rel="icon" type="image/png" href="{{ asset('images/corpnotify-favicon.png') }}?v=3">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="{{ asset('css/corpnotify.css') }}" rel="stylesheet">
</head>
<body class="bg-light @yield('body_class')">
<nav class="cn-navbar">
    <div class="container cn-navbar-inner">
        <a class="cn-brand" href="{{ route('dashboard') }}" aria-label="CorpNotify dashboard"><img src="{{ asset('images/corpnotify-logo.png') }}" alt="CorpNotify"></a>
        @auth
            <div class="cn-nav-links me-auto">
                <a class="cn-nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}"><i class="bi bi-grid-1x2-fill" aria-hidden="true"></i>Dashboard</a>
                <a class="cn-nav-link {{ request()->routeIs('notifications.*') ? 'active' : '' }}" href="{{ route('notifications.index') }}"><i class="bi bi-megaphone-fill" aria-hidden="true"></i>ประกาศ</a>
                <a class="cn-nav-link {{ request()->routeIs('devices.*') ? 'active' : '' }}" href="{{ route('devices.index') }}"><i class="bi bi-pc-display" aria-hidden="true"></i>อุปกรณ์</a>
            </div>
            <form method="POST" action="{{ route('logout') }}">@csrf<button class="cn-logout-button"><i class="bi bi-box-arrow-right" aria-hidden="true"></i><span>ออกจากระบบ</span></button></form>
        @endauth
    </div>
</nav>
<main class="container py-4 py-lg-5">
    @if(session('success'))
        <script>
            document.addEventListener('DOMContentLoaded', () => Swal.fire({
                toast: true,
                position: 'top-end',
                icon: 'success',
                title: @json(session('success')),
                showConfirmButton: false,
                timer: 2600,
                timerProgressBar: true
            }));
        </script>
    @endif
    @if($errors->any())
        <script>
            document.addEventListener('DOMContentLoaded', () => Swal.fire({
                icon: 'error',
                title: 'ตรวจสอบข้อมูล',
                html: @json(implode('<br>', $errors->all())),
                confirmButtonText: 'ตกลง'
            }));
        </script>
    @endif
    @yield('content')
</main>
</body>
</html>
