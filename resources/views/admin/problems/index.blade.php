@extends('layouts.panel')
@section('title','Problem Center')
@section('page-title','مرکز مشکلات Sync')
@section('content')
<section class="card">
<div class="card-head"><div><span class="muted">Provider Operations</span><h2>Pending / Retrying / Failed</h2></div><form method="post" action="{{ route('admin.problems.retry-all') }}">@csrf<button class="secondary">Retry Failed (حداکثر 500)</button></form></div>
<form method="get" class="filter-row"><select name="status"><option value="">همه وضعیت‌ها</option>@foreach(['failed','retrying','pending'] as $s)<option value="{{ $s }}" @selected(request('status')===$s)>{{ $s }}</option>@endforeach</select><select name="provider"><option value="">همه Providerها</option>@foreach($providers as $provider)<option value="{{ $provider->id }}" @selected((string)request('provider')===(string)$provider->id)>{{ $provider->name }}</option>@endforeach</select><button class="secondary">فیلتر</button></form>
<div class="table-wrap"><table><thead><tr><th>Subscription</th><th>Provider</th><th>Operation</th><th>Status</th><th>Attempts</th><th>Error</th><th>آخرین تلاش</th><th>Action</th></tr></thead><tbody>
@forelse($operations as $op)<tr><td><b>{{ $op->subscription?->name ?? $op->master_subscription_id }}</b><small class="muted" style="display:block">{{ $op->master_subscription_id }}</small></td><td>{{ $op->provider?->name ?? 'Archived' }}</td><td>{{ $op->operation }}</td><td>{{ $op->status }}</td><td>{{ $op->attempts }}</td><td style="max-width:260px;word-break:break-word">{{ $op->last_error ?: '—' }}</td><td>{{ $op->last_attempt_at?->format('Y-m-d H:i:s') ?? '—' }}</td><td><div style="display:flex;gap:6px;flex-wrap:wrap"><form method="post" action="{{ route('admin.problems.retry',$op) }}">@csrf<button class="secondary compact">Retry</button></form><form method="post" action="{{ route('admin.problems.reconcile',$op->master_subscription_id) }}">@csrf<button class="secondary compact">Reconcile</button></form></div></td></tr>@empty<tr><td colspan="8"><div class="empty">مشکل Sync فعالی وجود ندارد.</div></td></tr>@endforelse
</tbody></table></div>{{ $operations->links() }}
</section>
@endsection
