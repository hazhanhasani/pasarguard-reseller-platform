@extends('layouts.panel')
@section('title','فروشگاه‌ها')
@section('page-title','فروشگاه‌ها')
@section('top-action')<a class="button" href="{{ route('reseller.stores.create') }}">+ فروشگاه جدید</a>@endsection
@section('content')
<div class="store-grid">
@forelse($stores as $store)
<article class="store-card card">
    <div class="store-logo" style="--brand:{{ $store->brand_color }}">@if($store->logo_path)<img src="{{ $store->logo_path }}" alt="">@else{{ mb_substr($store->name,0,1) }}@endif</div>
    <div class="grow"><h2>{{ $store->name }}</h2><p>{{ number_format($store->subscriptions_count) }} اشتراک</p><span class="color-chip"><i style="background:{{ $store->brand_color }}"></i>{{ $store->brand_color }}</span></div>
    <div class="store-actions"><a class="button ghost compact" href="{{ route('reseller.stores.edit',$store->id) }}">ویرایش</a><form method="post" action="{{ route('reseller.stores.destroy',$store->id) }}" onsubmit="return confirm('فروشگاه آرشیو می‌شود ولی سوابق مالی و اشتراک‌ها حفظ می‌شوند. ادامه می‌دهید؟')">@csrf @method('DELETE')<button class="danger compact">آرشیو</button></form></div>
</article>
@empty<div class="card empty"><b>هنوز فروشگاهی ساخته نشده.</b><span>برای ساخت اشتراک، حداقل یک فروشگاه نیاز دارید.</span><a class="button" href="{{ route('reseller.stores.create') }}">ساخت فروشگاه</a></div>@endforelse
</div>
@if($stores->hasPages())<div class="pager">@if($stores->previousPageUrl())<a href="{{ $stores->previousPageUrl() }}">قبلی</a>@endif<span>صفحه {{ $stores->currentPage() }} از {{ $stores->lastPage() }}</span>@if($stores->nextPageUrl())<a href="{{ $stores->nextPageUrl() }}">بعدی</a>@endif</div>@endif
@endsection
