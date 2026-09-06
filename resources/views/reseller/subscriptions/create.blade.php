@extends('layouts.panel')
@section('title','ساخت اشتراک')
@section('page-title','ساخت اشتراک جدید')
@section('content')
<section class="card form-card">
    <div class="form-intro"><span class="pill">Master Subscription</span><h2>یک لینک، همه مسیرها</h2><p>اشتراک مرکزی ساخته می‌شود و عملیات Providerها مستقل در صف همگام‌سازی قرار می‌گیرند.</p></div>
    <form method="post" action="{{ route('reseller.subscriptions.store') }}">@csrf
        <div class="form-grid">
            <label><span>نام اشتراک</span><input required maxlength="120" name="name" value="{{ old('name') }}" placeholder="مثلاً مشتری ۱۲۳"></label>
            @if($stores->count()>1)<label><span>فروشگاه</span><select required name="store_id"><option value="">انتخاب کنید</option>@foreach($stores as $store)<option value="{{ $store->id }}" @selected(old('store_id')==$store->id)>{{ $store->name }}</option>@endforeach</select></label>@endif
            <label><span>حجم کل (GB)</span><input required inputmode="decimal" name="volume_gb" value="{{ old('volume_gb','0') }}" placeholder="0"><small>۰ = نامحدود؛ این حجم بین همه Providerها مشترک است.</small></label>
            <label><span>مدت (روز)</span><input required type="number" min="0" max="36500" name="duration_days" value="{{ old('duration_days','0') }}"><small>۰ = بدون تاریخ انقضا.</small></label>
        </div>
        <div class="form-actions"><a class="button ghost" href="{{ route('reseller.subscriptions.index') }}">انصراف</a><button class="button">ساخت اشتراک</button></div>
    </form>
</section>
@endsection
