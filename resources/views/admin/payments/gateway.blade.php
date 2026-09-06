@extends('layouts.panel')
@section('title','تنظیمات BluPal')
@section('page-title','درگاه پرداخت BluPal')
@section('content')
<section class="card" style="max-width:760px">
    <div class="card-head"><div><span class="muted">پرداخت آنلاین</span><h2>اتصال امن BluPal</h2></div></div>
    <p class="muted">API Key فقط در Backend استفاده می‌شود و پس از ذخیره با APP_KEY رمزنگاری خواهد شد. کلید موجود هیچ‌گاه دوباره در فرم نمایش داده نمی‌شود.</p>
    <form method="post" action="{{ route('admin.payments.gateway.update') }}" class="form-grid">@csrf @method('PUT')
        <label class="field"><span>API Key جدید</span><input name="api_key" autocomplete="off" placeholder="blu_live_... یا blu_test_..."></label>
        <label class="field" style="display:flex;align-items:center;gap:10px"><input type="checkbox" name="enabled" value="1" {{ $setting?->enabled ? 'checked' : '' }}><span>درگاه فعال باشد</span></label>
        <div class="notice"><b>وضعیت فعلی</b><span>{{ $setting?->health ?? 'unknown' }}</span>@if($setting?->last_error)<small>آخرین خطا: {{ $setting->last_error }}</small>@endif</div>
        <button class="button" type="submit">ذخیره تنظیمات</button>
    </form>
</section>
@endsection
