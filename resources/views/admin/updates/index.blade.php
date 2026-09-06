@extends('layouts.panel')
@section('title','Update Center')
@section('page-title','Update Center')
@section('content')
<div class="metric-grid">
    <article class="metric featured"><span>نسخه فعلی</span><strong>{{ $currentVersion }}</strong><small>Production State</small></article>
    <article class="metric"><span>تاریخچه Update</span><strong>{{ number_format($updates->total()) }}</strong><small>رکورد</small></article>
    <article class="metric"><span>حداکثر ZIP</span><strong>{{ number_format($maxMb) }}</strong><small>MB</small></article>
</div>
<section class="card">
    <div class="card-head"><div><span class="muted">Release Package</span><h2>آپلود نسخه جدید</h2></div></div>
    <form method="post" action="{{ route('admin.updates.upload') }}" enctype="multipart/form-data" class="form-stack">@csrf
        <label>release.zip<input type="file" name="release" accept=".zip,application/zip" required></label>
        <button>Validate & Upload</button>
    </form>
    <div class="notice"><b>فرمت بسته</b><code>manifest.json + application/ + migrations/ + changelog.md</code><span>Checksum همه فایل‌ها، compatibility و مسیرهای محافظت‌شده قبل از Apply بررسی می‌شوند.</span></div>
</section>
<section class="card">
<div class="card-head"><div><span class="muted">Update History</span><h2>نسخه‌ها</h2></div></div>
<div class="table-wrap"><table><thead><tr><th>نسخه</th><th>Channel</th><th>وضعیت</th><th>Package</th><th>Safety Backup</th><th>Rollback</th><th>تاریخ</th><th>عملیات</th></tr></thead><tbody>
@forelse($updates as $update)
<tr>
<td><b>{{ $update->version }}</b></td><td>{{ $update->channel }}</td>
<td><span class="badge {{ $update->status === 'completed' ? 'ok' : ($update->status === 'failed' ? 'danger' : 'warn') }}">{{ $update->status }}</span></td>
<td><small>{{ $update->package_name }}</small>@if($update->package_path === '')<br><span class="muted">ZIP deleted</span>@endif</td>
<td>{{ $update->backup_id ? '#'.$update->backup_id : '—' }}</td><td>{{ $update->rollback_status ?: '—' }}</td><td>{{ $update->created_at?->format('Y-m-d H:i') }}</td>
<td>
@if($update->status === 'validated' && $update->package_path !== '')
<details><summary>Apply</summary><form method="post" action="{{ route('admin.updates.apply',$update) }}" class="form-stack">@csrf<input type="password" name="password" placeholder="رمز Super Admin" required><input name="confirmation" placeholder="APPLY" required><button class="danger" onclick="return confirm('قبل از Apply به‌صورت خودکار Safety Backup ساخته می‌شود. ادامه؟')">Apply Update</button></form></details>
@endif
@if($update->package_path !== '' && !in_array($update->status,['preparing','applying'],true))
<form method="post" action="{{ route('admin.updates.package.destroy',$update) }}" style="display:inline" onsubmit="return confirm('فقط فایل ZIP حذف شود؟ History باقی می‌ماند.')">@csrf @method('DELETE')<button class="secondary compact">حذف ZIP</button></form>
@endif
@if($update->error)<details><summary>خطا</summary><code>{{ $update->error }}</code></details>@endif
</td>
</tr>
@empty<tr><td colspan="8"><div class="empty">هنوز بسته Update آپلود نشده است.</div></td></tr>@endforelse
</tbody></table></div>{{ $updates->links() }}
</section>
@endsection
