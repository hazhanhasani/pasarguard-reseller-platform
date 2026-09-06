@extends('layouts.panel')
@section('title','کیف پول')
@section('page-title','کیف پول و پرداخت‌ها')
@section('content')
<div class="split-grid">
<section class="card">
    <div class="card-head"><div><span class="muted">موجودی فعلی</span><h2>{{ number_format($wallet->balance) }} ریال</h2></div></div>
    <form method="post" action="{{ route('reseller.wallet.pay') }}" class="form-grid">@csrf
        <label class="field"><span>مبلغ شارژ (ریال)</span><input name="amount" inputmode="numeric" min="100000" step="1" value="{{ old('amount') }}" required></label>
        <p class="muted">با زدن دکمه، سرور فاکتور را می‌سازد و مستقیم به صفحه پرداخت BluPal هدایت می‌شوید.</p>
        <button class="button" type="submit">پرداخت و شارژ کیف پول</button>
    </form>
</section>
<section class="card"><div class="card-head"><div><span class="muted">تراکنش‌ها</span><h2>آخرین تغییرات کیف پول</h2></div></div>
@forelse($transactions as $tx)<div class="list-row"><div><b>{{ $tx->type }}</b><small>{{ $tx->created_at?->format('Y-m-d H:i') }}</small></div><strong class="{{ $tx->amount >= 0 ? 'positive' : 'negative' }}">{{ $tx->amount >= 0 ? '+' : '' }}{{ number_format($tx->amount) }}</strong></div>@empty<div class="empty">تراکنشی ثبت نشده است.</div>@endforelse
</section>
</div>
<section class="card" style="margin-top:18px"><div class="card-head"><div><span class="muted">سوابق درگاه</span><h2>پرداخت‌های آنلاین</h2></div></div>
<div class="table-wrap"><table><thead><tr><th>مرجع</th><th>مبلغ</th><th>وضعیت</th><th>شناسه درگاه</th><th>تاریخ</th><th></th></tr></thead><tbody>
@forelse($payments as $payment)<tr><td>{{ $payment->reference_id }}</td><td>{{ number_format($payment->amount) }} ریال</td><td><span class="badge">{{ $payment->status }}</span></td><td>{{ $payment->gateway_transaction_id ?: $payment->gateway_invoice_id ?: '—' }}</td><td>{{ $payment->created_at?->format('Y-m-d H:i') }}</td><td><a href="{{ route('reseller.wallet.callback',$payment->public_id) }}">بررسی وضعیت</a></td></tr>@empty<tr><td colspan="6"><div class="empty">هنوز پرداختی ثبت نشده است.</div></td></tr>@endforelse
</tbody></table></div>{{ $payments->links() }}
</section>
@endsection
