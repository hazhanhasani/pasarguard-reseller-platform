@extends('layouts.panel')
@section('title',$store ? 'ویرایش فروشگاه' : 'فروشگاه جدید')
@section('page-title',$store ? 'ویرایش فروشگاه' : 'فروشگاه جدید')
@section('content')
<section class="card form-card">
<form method="post" enctype="multipart/form-data" action="{{ $store ? route('reseller.stores.update',$store->id) : route('reseller.stores.store') }}">@csrf @if($store)@method('PUT')@endif
<div class="form-grid">
<label><span>نام فروشگاه</span><input required maxlength="120" name="name" value="{{ old('name',$store?->name) }}"></label>
<label><span>رنگ برند</span><div class="color-input"><input type="color" name="brand_color" value="{{ old('brand_color',$store?->brand_color ?? '#1483c9') }}"><input readonly value="{{ old('brand_color',$store?->brand_color ?? '#1483c9') }}" onfocus="this.previousElementSibling.click()"></div></label>
<label class="full"><span>لوگو</span><input type="file" name="logo" accept="image/png,image/jpeg,image/webp"><small>PNG / JPG / WebP — حداکثر ۲MB. لوگوی فعلی با آپلود فایل جدید جایگزین می‌شود.</small></label>
</div>
<div class="form-actions"><a class="button ghost" href="{{ route('reseller.stores.index') }}">انصراف</a><button class="button">ذخیره</button></div>
</form>
</section>
@endsection
