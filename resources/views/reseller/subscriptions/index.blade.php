@extends('layouts.panel')
@section('title','اشتراک‌ها')
@section('page-title','اشتراک‌ها')
@section('top-action')<a class="button" href="{{ route('reseller.subscriptions.create') }}">+ ساخت اشتراک</a>@endsection
@section('content')
@php($stateLabels=['active'=>'فعال','manual_suspended'=>'تعلیق دستی','wallet_zero'=>'کیف پول صفر','quota_exceeded'=>'اتمام حجم','expired'=>'منقضی'])
<form class="filters card" method="get">
    <input name="q" value="{{ request('q') }}" placeholder="جستجو نام اشتراک">
    <select name="state"><option value="">همه وضعیت‌ها</option>@foreach($stateLabels as $key=>$label)<option value="{{ $key }}" @selected(request('state')===$key)>{{ $label }}</option>@endforeach</select>
    @if($stores->count()>1)<select name="store"><option value="">همه فروشگاه‌ها</option>@foreach($stores as $store)<option value="{{ $store->id }}" @selected((string)request('store')===(string)$store->id)>{{ $store->name }}</option>@endforeach</select>@endif
    <button class="secondary compact">فیلتر</button>
</form>
<section class="card table-card"><div class="table-wrap"><table><thead><tr><th>اشتراک</th><th>فروشگاه</th><th>وضعیت</th><th>مصرف</th><th>اعتبار</th><th>لینک</th><th></th></tr></thead><tbody>
@forelse($subscriptions as $subscription)
<tr>
<td data-label="اشتراک"><b>{{ $subscription->name }}</b><small class="mono">{{ $subscription->master_subscription_id }}</small></td>
<td data-label="فروشگاه">{{ $subscription->store?->name ?? 'آرشیوشده' }}</td>
<td data-label="وضعیت"><span class="badge state-{{ $subscription->desired_state }}">{{ $stateLabels[$subscription->desired_state] ?? $subscription->desired_state }}</span></td>
<td data-label="مصرف"><b>{{ number_format($subscription->used_bytes/1_000_000_000,3) }} GB</b><small>{{ $subscription->quota_bytes===0 ? 'نامحدود' : 'از '.number_format($subscription->quota_bytes/1_000_000_000,3).' GB' }}</small></td>
<td data-label="اعتبار">{{ $subscription->expires_at ? $subscription->expires_at->format('Y-m-d') : 'نامحدود' }}</td>
<td data-label="لینک">@if($subscription->public_url)<input class="link-input" readonly value="{{ $subscription->public_url }}" onclick="this.select()">@else<span class="muted">لغو شده</span>@endif</td>
<td data-label="عملیات"><details class="actions"><summary>عملیات</summary><div class="action-popover">
    @if($subscription->desired_state==='manual_suspended')<form method="post" action="{{ route('reseller.subscriptions.reactivate',$subscription->master_subscription_id) }}">@csrf<button class="secondary compact">فعال‌سازی</button></form>@else<form method="post" action="{{ route('reseller.subscriptions.suspend',$subscription->master_subscription_id) }}">@csrf<button class="secondary compact">تعلیق دستی</button></form>@endif
    <form class="inline-form" method="post" action="{{ route('reseller.subscriptions.volume',$subscription->master_subscription_id) }}">@csrf<input name="volume_gb" inputmode="decimal" placeholder="GB"><button class="secondary compact">افزودن حجم</button></form>
    <form class="inline-form" method="post" action="{{ route('reseller.subscriptions.extend',$subscription->master_subscription_id) }}">@csrf<input name="days" inputmode="numeric" placeholder="روز"><button class="secondary compact">تمدید</button></form>
    <form method="post" action="{{ route('reseller.subscriptions.rotate-token',$subscription->master_subscription_id) }}" onsubmit="return confirm('لینک قبلی فوراً از کار می‌افتد. ادامه می‌دهید؟')">@csrf<button class="secondary compact">تعویض لینک</button></form>
    <form method="post" action="{{ route('reseller.subscriptions.destroy',$subscription->master_subscription_id) }}" onsubmit="return confirm('اشتراک آرشیو و حذف از همه Providerها در صف قرار می‌گیرد. ادامه می‌دهید؟')">@csrf @method('DELETE')<button class="danger compact">حذف</button></form>
</div></details></td>
</tr>
@empty<tr><td colspan="7"><div class="empty"><b>هنوز اشتراکی ندارید.</b><span>اولین اشتراک را بسازید تا همگام‌سازی Providerها آغاز شود.</span><a class="button" href="{{ route('reseller.subscriptions.create') }}">ساخت اولین اشتراک</a></div></td></tr>@endforelse
</tbody></table></div>
@if($subscriptions->hasPages())<div class="pager">@if($subscriptions->previousPageUrl())<a href="{{ $subscriptions->previousPageUrl() }}">قبلی</a>@endif<span>صفحه {{ $subscriptions->currentPage() }} از {{ $subscriptions->lastPage() }}</span>@if($subscriptions->nextPageUrl())<a href="{{ $subscriptions->nextPageUrl() }}">بعدی</a>@endif</div>@endif
</section>
@endsection
