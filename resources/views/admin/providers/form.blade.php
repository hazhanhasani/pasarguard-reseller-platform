@extends('layouts.panel')
@section('title',$provider ? 'ویرایش Provider' : 'Provider جدید')
@section('page-title',$provider ? 'ویرایش Provider' : 'افزودن Provider')
@section('content')
<div class="split-grid">
<section class="card">
<form method="post" action="{{ $provider ? route('admin.providers.update',$provider) : route('admin.providers.store') }}" class="form-grid">@csrf @if($provider)@method('PUT')@endif
<label class="field"><span>نام داخلی</span><input name="name" value="{{ old('name',$provider?->name) }}" maxlength="120" required></label>
<label class="field"><span>API URL</span><input name="api_url" value="{{ old('api_url',$provider?->api_url) }}" placeholder="https://panel.example.com" required></label>
<label class="field"><span>{{ $provider ? 'API Key جدید (اختیاری)' : 'API Key' }}</span><input name="api_key" type="password" autocomplete="new-password" {{ $provider ? '' : 'required' }}></label>
<label class="field"><span>Group IDs</span><input name="group_ids" value="{{ old('group_ids',$provider ? implode(',',$provider->group_ids ?? []) : '') }}" placeholder="1,2,3" required></label>
<p class="muted">Credential در دیتابیس رمزنگاری می‌شود. تغییر URL یا API Key، Provider را تا Test Connection بعدی خودکار غیرفعال می‌کند.</p>
<button class="button">{{ $provider ? 'ذخیره تغییرات' : 'افزودن و Test Connection' }}</button>
</form>
</section>
@if($provider)<section class="card"><div class="card-head"><div><span class="muted">وضعیت عملیاتی</span><h2>{{ $provider->health }} · {{ $provider->health_score }}/100</h2></div></div>
<div class="list-row"><span>Ready</span><b>{{ $provider->ready ? 'بله' : 'خیر' }}</b></div><div class="list-row"><span>آخرین Test</span><b>{{ $provider->last_tested_at?->format('Y-m-d H:i:s') ?? '—' }}</b></div><div class="list-row"><span>آخرین خطا</span><b>{{ $provider->last_error ?: '—' }}</b></div>
<form method="post" action="{{ route('admin.providers.mode',$provider) }}" class="form-grid" style="margin-top:16px">@csrf<select name="mode"><option value="disabled" @selected($provider->mode==='disabled')>Disabled</option><option value="maintenance" @selected($provider->mode==='maintenance')>Maintenance</option><option value="active" @selected($provider->mode==='active')>Active</option></select><button class="secondary">تغییر Mode</button></form>
<h3 style="margin-top:20px">Capabilities</h3>@forelse(($provider->capabilities ?? []) as $key=>$cap)<div class="list-row"><span>{{ $key }}</span><b>{{ !empty($cap['supported']) ? 'supported' : 'no' }} / {{ !empty($cap['verified']) ? 'live verified' : 'not live-tested' }}</b></div>@empty<div class="empty">هنوز Probe اجرا نشده است.</div>@endforelse
<form method="post" action="{{ route('admin.providers.destroy',$provider) }}" style="margin-top:18px" onsubmit="return confirm('Provider آرشیو شود؟')">@csrf @method('DELETE')<button class="link-button">آرشیو Provider</button></form>
</section>@endif
</div>
@endsection
