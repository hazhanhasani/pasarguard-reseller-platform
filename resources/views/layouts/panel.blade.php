<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>@yield('title', 'پنل نمایندگان')</title>
    <link rel="stylesheet" href="/app.css">
</head>
<body class="panel-body">
<div class="shell">
    <aside class="sidebar">
        <a class="brand" href="{{ auth()->user()->role === 'super_admin' ? route('admin.dashboard') : route('reseller.dashboard') }}">
            <span class="brand-mark">◈</span><span><b>مرکز اشتراک</b><small>مدیریت یکپارچه</small></span>
        </a>
        <nav class="nav">
            @if(auth()->user()->role === 'super_admin')
                <a class="{{ request()->routeIs('admin.dashboard') ? 'active' : '' }}" href="{{ route('admin.dashboard') }}">نمای کلی</a>
                <a class="{{ request()->routeIs('admin.resellers.*') ? 'active' : '' }}" href="{{ route('admin.resellers.index') }}">نمایندگان</a>
                <a class="{{ request()->routeIs('admin.providers.*') ? 'active' : '' }}" href="{{ route('admin.providers.index') }}">Providerها</a>
                <a class="{{ request()->routeIs('admin.problems.*') ? 'active' : '' }}" href="{{ route('admin.problems.index') }}">Problem Center</a>
                <a class="{{ request()->routeIs('admin.notifications.*') ? 'active' : '' }}" href="{{ route('admin.notifications.index') }}">هشدارها</a>
                <a class="{{ request()->routeIs('admin.reports.*') ? 'active' : '' }}" href="{{ route('admin.reports.index') }}">گزارش‌ها</a>
                <a class="{{ request()->routeIs('admin.payments.*') ? 'active' : '' }}" href="{{ route('admin.payments.gateway.edit') }}">BluPal</a>
            @else
                <a class="{{ request()->routeIs('reseller.dashboard') ? 'active' : '' }}" href="{{ route('reseller.dashboard') }}">داشبورد</a>
                <a class="{{ request()->routeIs('reseller.subscriptions.*') ? 'active' : '' }}" href="{{ route('reseller.subscriptions.index') }}">اشتراک‌ها</a>
                <a class="{{ request()->routeIs('reseller.stores.*') ? 'active' : '' }}" href="{{ route('reseller.stores.index') }}">فروشگاه‌ها</a>
                <a class="{{ request()->routeIs('reseller.wallet.*') ? 'active' : '' }}" href="{{ route('reseller.wallet.index') }}">کیف پول</a>
                <a class="{{ request()->routeIs('reseller.reports.*') ? 'active' : '' }}" href="{{ route('reseller.reports.index') }}">گزارش‌ها</a>
            @endif
        </nav>
        <div class="sidebar-user">
            <span>{{ auth()->user()->name }}</span><small>{{ auth()->user()->email }}</small>
            <form method="post" action="{{ route('logout') }}">@csrf<button class="link-button">خروج امن</button></form>
        </div>
    </aside>
    <main class="content">
        <header class="topbar"><div><p class="eyebrow">پنل امن و سبک</p><h1>@yield('page-title')</h1></div>@yield('top-action')</header>
        @if(session('success'))<div class="toast success">{{ session('success') }}</div>@endif
        @if(session('error'))<div class="toast error">{{ session('error') }}</div>@endif
        @if(session('new_subscription_link'))
            <div class="notice"><b>لینک جدید اشتراک</b><div class="copy-row"><input readonly value="{{ session('new_subscription_link') }}" onclick="this.select()"><button type="button" class="secondary compact" onclick="navigator.clipboard?.writeText(this.previousElementSibling.value)">کپی</button></div></div>
        @endif
        @if($errors->any())<div class="toast error"><b>لطفاً موارد زیر را اصلاح کنید:</b><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        @yield('content')
    </main>
</div>
</body>
</html>
