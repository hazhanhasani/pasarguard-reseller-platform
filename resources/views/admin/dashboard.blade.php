@extends('layouts.panel')
@section('title','مدیریت کل')
@section('page-title','مدیریت کل')
@section('top-action')<a class="button" href="{{ route('admin.resellers.create') }}">+ نماینده جدید</a>@endsection
@section('content')
<div class="metric-grid admin-metrics">
<article class="metric featured"><span>نمایندگان</span><strong>{{ number_format($totalResellers) }}</strong><small>{{ number_format($totalStores) }} فروشگاه</small></article>
<article class="metric"><span>کل اشتراک‌ها</span><strong>{{ number_format($totalSubscriptions) }}</strong><small>{{ number_format($activeSubscriptions) }} فعال</small></article>
<article class="metric"><span>مصرف امروز</span><strong>{{ number_format($usageToday/1_000_000_000,3) }}</strong><small>GB</small></article>
<article class="metric"><span>مصرف ماه</span><strong>{{ number_format($usageMonth/1_000_000_000,3) }}</strong><small>GB</small></article>
<article class="metric"><span>درآمد مصرف ماه</span><strong>{{ number_format($revenueMonth) }}</strong><small>ریال</small></article>
<article class="metric"><span>شارژ کیف پول ماه</span><strong>{{ number_format($walletDepositsMonth) }}</strong><small>ریال</small></article>
</div>
<div class="split-grid">
<section class="card"><div class="card-head"><div><span class="muted">Provider Health</span><h2>سلامت منابع</h2></div></div>
@forelse($providers as $provider)<div class="list-row"><div><b>{{ $provider->name }}</b><small>{{ $provider->mode }} · {{ $provider->latency_ms ? $provider->latency_ms.' ms' : 'بدون سنجش' }}</small></div><span class="badge health-{{ $provider->health }}">{{ $provider->health }}</span></div>@empty<div class="empty">هنوز Provider اضافه نشده است.</div>@endforelse
</section>
<section class="card"><div class="card-head"><div><span class="muted">Operations</span><h2>وضعیت سیستم</h2></div></div>
<div class="system-list"><div><span>تعلیق‌شده</span><b>{{ number_format($suspendedSubscriptions) }}</b></div><div><span>منقضی</span><b>{{ number_format($expiredSubscriptions) }}</b></div><div><span>Sync شکست‌خورده</span><b>{{ number_format($failedSyncs) }}</b></div><div><span>Pending / Retry</span><b>{{ number_format($partialSyncs) }}</b></div><div><span>Queue backlog</span><b>{{ number_format($queueBacklog) }}</b></div><div><span>آخرین Cron</span><b>{{ $cronLastRun ?: 'ثبت نشده' }}</b></div></div>
@if($cronLastError)<div class="alert warning"><b>آخرین Tick خطا داشته است.</b><span>{{ $cronLastError }}</span></div>@endif
</section>
</div>
@endsection
