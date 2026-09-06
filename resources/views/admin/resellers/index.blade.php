@extends('layouts.panel')
@section('title','نمایندگان')
@section('page-title','نمایندگان')
@section('top-action')<a class="button" href="{{ route('admin.resellers.create') }}">+ نماینده جدید</a>@endsection
@section('content')
<form class="filters card" method="get"><input name="q" value="{{ request('q') }}" placeholder="جستجو نام نماینده"><button class="secondary compact">جستجو</button></form>
<section class="card table-card"><div class="table-wrap"><table><thead><tr><th>نماینده</th><th>فروشگاه</th><th>اشتراک</th><th>موجودی</th><th>شارژ دستی</th></tr></thead><tbody>
@forelse($resellers as $reseller)<tr><td data-label="نماینده"><b>{{ $reseller->name }}</b><small>#{{ $reseller->id }}</small></td><td data-label="فروشگاه">{{ number_format($reseller->stores_count) }}</td><td data-label="اشتراک">{{ number_format($reseller->subscriptions_count) }}</td><td data-label="موجودی"><b>{{ number_format($reseller->wallet?->balance ?? 0) }}</b><small>ریال</small></td><td data-label="شارژ دستی"><form class="inline-form" method="post" action="{{ route('admin.resellers.topup',$reseller->id) }}">@csrf<input required name="amount_minor" inputmode="numeric" placeholder="مبلغ ریال"><button class="secondary compact">شارژ</button></form></td></tr>
@empty<tr><td colspan="5"><div class="empty">هنوز نماینده‌ای ساخته نشده است.</div></td></tr>@endforelse
</tbody></table></div>
@if($resellers->hasPages())<div class="pager">@if($resellers->previousPageUrl())<a href="{{ $resellers->previousPageUrl() }}">قبلی</a>@endif<span>صفحه {{ $resellers->currentPage() }} از {{ $resellers->lastPage() }}</span>@if($resellers->nextPageUrl())<a href="{{ $resellers->nextPageUrl() }}">بعدی</a>@endif</div>@endif
</section>
@endsection
