@extends('layouts.panel')
@section('title','نتیجه پرداخت')
@section('page-title','وضعیت پرداخت')
@section('content')
@php
$ok=$payment->status==='paid';$pending=in_array($payment->status,['creating','pending'],true);
@endphp
<section class="card" style="max-width:680px;margin:auto;text-align:center">
    <div style="font-size:52px;margin:8px">{{ $ok ? '✓' : ($pending ? '…' : '×') }}</div>
    <h2>{{ $ok ? 'پرداخت موفق' : ($pending ? 'پرداخت در انتظار تأیید' : 'پرداخت ناموفق') }}</h2>
    <p class="muted">وضعیت فقط از اطلاعات ثبت‌شده و بررسی سمت سرور نمایش داده می‌شود.</p>
    <div class="list-row"><span>مبلغ</span><b>{{ number_format($payment->amount) }} ریال</b></div>
    <div class="list-row"><span>شناسه تراکنش</span><b>{{ $payment->gateway_transaction_id ?: '—' }}</b></div>
    <div class="list-row"><span>Reference ID</span><b>{{ $payment->reference_id }}</b></div>
    <div class="list-row"><span>Invoice ID</span><b>{{ $payment->gateway_invoice_id ?: '—' }}</b></div>
    <div class="list-row"><span>تاریخ</span><b>{{ ($payment->paid_at ?: $payment->created_at)?->format('Y-m-d H:i:s') }}</b></div>
    <div style="margin-top:18px"><a class="button" href="{{ route('reseller.wallet.index') }}">بازگشت به کیف پول</a></div>
</section>
@endsection
