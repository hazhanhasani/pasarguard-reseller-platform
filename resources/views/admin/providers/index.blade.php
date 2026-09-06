@extends('layouts.panel')
@section('title','Providerها')
@section('page-title','مدیریت Providerها')
@section('top-action')<a class="button" href="{{ route('admin.providers.create') }}">+ Provider جدید</a>@endsection
@section('content')
<section class="card">
<form method="get" class="filter-row"><input name="q" value="{{ request('q') }}" placeholder="جستجو نام Provider"><select name="health"><option value="">همه وضعیت‌ها</option>@foreach(['healthy','slow','degraded','offline','auth_error','maintenance','unknown'] as $h)<option value="{{ $h }}" @selected(request('health')===$h)>{{ $h }}</option>@endforeach</select><button class="secondary">فیلتر</button></form>
<div class="table-wrap"><table><thead><tr><th>Provider</th><th>Mode</th><th>Ready</th><th>Health</th><th>Latency</th><th>Mappings</th><th>Errors</th><th>عملیات</th></tr></thead><tbody>
@forelse($providers as $provider)
<tr><td><b>{{ $provider->name }}</b><small style="display:block" class="muted">{{ $provider->api_url }}</small></td><td>{{ $provider->mode }}</td><td>{{ $provider->ready ? 'READY' : 'NOT READY' }}</td><td><b>{{ $provider->health }}</b><small style="display:block">{{ $provider->health_score }} / 100</small></td><td>{{ $provider->latency_ms !== null ? number_format($provider->latency_ms).' ms' : '—' }}</td><td>{{ $provider->mappings_count }}</td><td>{{ $provider->error_counter }}</td><td><div style="display:flex;gap:6px;flex-wrap:wrap"><a class="secondary compact" href="{{ route('admin.providers.edit',$provider) }}">ویرایش</a><form method="post" action="{{ route('admin.providers.test',$provider) }}">@csrf<button class="secondary compact">Test</button></form><form method="post" action="{{ route('admin.providers.force-sync',$provider) }}">@csrf<button class="secondary compact">Force Sync</button></form></div></td></tr>
@empty<tr><td colspan="8"><div class="empty">Provider ثبت نشده است.</div></td></tr>@endforelse
</tbody></table></div>{{ $providers->links() }}
</section>
@endsection
