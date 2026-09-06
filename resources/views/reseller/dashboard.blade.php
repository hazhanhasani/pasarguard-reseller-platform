@extends('layouts.panel')
@section('title','داشبورد نماینده')
@section('page-title','داشبورد')
@section('top-action')<a class="button" href="{{ route('reseller.subscriptions.create') }}">+ اشتراک جدید</a>@endsection
@section('content')
<div class="metric-grid">
    <article class="metric featured"><span>موجودی کیف پول</span><strong>{{ number_format($wallet->balance) }}</strong><small>ریال</small></article>
    <article class="metric"><span>مصرف امروز</span><strong>{{ number_format($usageToday / 1_000_000_000, 3) }}</strong><small>GB</small></article>
    <article class="metric"><span>هزینه امروز</span><strong>{{ number_format($costToday) }}</strong><small>ریال</small></article>
    <article class="metric"><span>مصرف این ماه</span><strong>{{ number_format($usageMonth / 1_000_000_000, 3) }}</strong><small>GB</small></article>
    <article class="metric"><span>هزینه این ماه</span><strong>{{ number_format($costMonth) }}</strong><small>ریال</small></article>
    <article class="metric"><span>فروشگاه‌ها</span><strong>{{ number_format($storeCount) }}</strong><small>فعال</small></article>
</div>
@if($wallet->balance <= 0)<div class="alert warning"><b>کیف پول قابل استفاده نیست.</b><span>اشتراک‌های فعال با دلیل wallet_zero مسدود می‌مانند تا موجودی دوباره مثبت شود.</span></div>@endif
<div class="split-grid">
    <section class="card"><div class="card-head"><div><span class="muted">وضعیت اشتراک‌ها</span><h2>نمای سریع</h2></div><a href="{{ route('reseller.subscriptions.index') }}">مشاهده همه</a></div>
        <div class="status-row"><div><i class="dot ok"></i><span>فعال</span><b>{{ $activeCount }}</b></div><div><i class="dot warn"></i><span>تعلیق</span><b>{{ $suspendedCount }}</b></div><div><i class="dot neutral"></i><span>منقضی</span><b>{{ $expiredCount }}</b></div></div>
    </section>
    <section class="card"><div class="card-head"><div><span class="muted">کیف پول</span><h2>تراکنش‌های اخیر</h2></div></div>
        @forelse($recentTransactions as $tx)
            <div class="list-row"><div><b>{{ $tx->type }}</b><small>{{ $tx->created_at?->format('Y-m-d H:i') }}</small></div><strong class="{{ $tx->amount >= 0 ? 'positive' : 'negative' }}">{{ $tx->amount >= 0 ? '+' : '' }}{{ number_format($tx->amount) }}</strong></div>
        @empty<div class="empty">هنوز تراکنشی ثبت نشده است.</div>@endforelse
    </section>
</div>
@endsection
