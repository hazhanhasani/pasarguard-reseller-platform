@extends('layouts.panel')
@section('title','هشدارها')
@section('page-title','Notification Center')
@section('content')
<section class="card">
<form method="get" class="filter-row"><select name="state"><option value="">همه</option><option value="open" @selected(request('state')==='open')>Open</option><option value="resolved" @selected(request('state')==='resolved')>Resolved</option></select><button class="secondary">فیلتر</button></form>
@forelse($notifications as $item)
<div class="list-row" style="align-items:flex-start"><div style="flex:1"><div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap"><b>{{ $item->title }}</b><span class="badge">{{ $item->severity }}</span>@if($item->resolved_at)<span class="muted">resolved</span>@endif</div><small>{{ $item->message }}</small><small class="muted">{{ $item->event_key }} · تکرار {{ $item->occurrence_count }} · آخرین مشاهده {{ $item->last_seen_at?->format('Y-m-d H:i:s') }}</small></div><div style="display:flex;gap:6px">@if(!$item->read_at)<form method="post" action="{{ route('admin.notifications.read',$item) }}">@csrf<button class="secondary compact">خواندم</button></form>@endif @if(!$item->resolved_at)<form method="post" action="{{ route('admin.notifications.resolve',$item) }}">@csrf<button class="secondary compact">Resolve</button></form>@endif</div></div>
@empty<div class="empty">هشداری ثبت نشده است.</div>@endforelse
{{ $notifications->links() }}
</section>
@endsection
