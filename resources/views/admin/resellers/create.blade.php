@extends('layouts.panel')
@section('title','نماینده جدید')
@section('page-title','ساخت نماینده')
@section('content')
<section class="card form-card"><div class="form-intro"><span class="pill">Reseller</span><h2>حساب فروش مستقل</h2><p>Wallet برای نماینده ساخته می‌شود و بین همه Storeهای او مشترک خواهد بود.</p></div>
<form method="post" action="{{ route('admin.resellers.store') }}">@csrf<div class="form-grid">
<label><span>نام نماینده</span><input required maxlength="120" name="name" value="{{ old('name') }}"></label>
<label><span>ایمیل ورود</span><input required type="email" maxlength="254" name="email" value="{{ old('email') }}"></label>
<label><span>نام فروشگاه اولیه</span><input required maxlength="120" name="store_name" value="{{ old('store_name') }}"></label>
<label><span>رمز عبور</span><input required type="password" minlength="12" name="password" autocomplete="new-password"></label>
<label><span>تکرار رمز عبور</span><input required type="password" minlength="12" name="password_confirmation" autocomplete="new-password"></label>
</div><div class="form-actions"><a class="button ghost" href="{{ route('admin.resellers.index') }}">انصراف</a><button class="button">ساخت نماینده</button></div></form></section>
@endsection
