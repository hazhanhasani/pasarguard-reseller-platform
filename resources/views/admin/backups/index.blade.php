@extends('layouts.panel')
@section('title','Backup Center')
@section('page-title','Backup & Restore')
@section('content')
<div class="metric-grid">
    <article class="metric featured"><span>فضای آزاد</span><strong>{{ number_format($freeBytes / 1000000000, 2) }}</strong><small>GB</small></article>
    <article class="metric"><span>تعداد Backup</span><strong>{{ number_format($backups->total()) }}</strong><small>رکورد</small></article>
    <article class="metric"><span>Retention</span><strong>{{ number_format($keepLast) }}</strong><small>آخرین نسخه</small></article>
</div>
<div class="split-grid">
<section class="card">
    <div class="card-head"><div><span class="muted">Manual Backup</span><h2>ساخت Backup جدید</h2></div></div>
    <form method="post" action="{{ route('admin.backups.create') }}" class="form-stack">@csrf
        <label>نوع Backup<select name="type"><option value="full">Full — Database + Persistent Files</option><option value="database">Database</option><option value="persistent">Persistent Files</option></select></label>
        <button>ساخت Backup</button>
    </form>
    <p class="muted">برای Backup زمان‌بندی‌شده از cPanel Cron و دستور <code>php artisan platform:backup full</code> استفاده کنید. برنامه هیچ interval داخلی ندارد.</p>
</section>
<section class="card">
    <div class="card-head"><div><span class="muted">Retention</span><h2>کنترل فضای دیسک</h2></div></div>
    <form method="post" action="{{ route('admin.backups.retention') }}" class="form-stack">@csrf @method('PUT')
        <label>نگه‌داری آخرین N نسخه<input type="number" name="keep_last" min="1" max="500" value="{{ $keepLast }}"></label>
        <label>حداکثر فضای Backup (GB، صفر = بدون محدودیت)<input type="number" step="0.1" name="max_gb" min="0" value="{{ number_format($maxBytes / 1000000000, 2, '.', '') }}"></label>
        <button class="secondary">ذخیره Retention</button>
    </form>
</section>
</div>
<section class="card">
<div class="card-head"><div><span class="muted">Backup History</span><h2>نسخه‌های پشتیبان</h2></div></div>
<div class="table-wrap"><table><thead><tr><th>ID</th><th>نوع</th><th>وضعیت</th><th>حجم</th><th>نسخه</th><th>تاریخ</th><th>عملیات</th></tr></thead><tbody>
@forelse($backups as $backup)
<tr><td>#{{ $backup->id }}</td><td>{{ $backup->type }}</td><td><span class="badge {{ $backup->status === 'completed' ? 'ok' : ($backup->status === 'failed' ? 'danger' : 'warn') }}">{{ $backup->status }}</span></td><td>{{ number_format($backup->size_bytes / 1000000, 2) }} MB</td><td>{{ $backup->app_version ?: '—' }}</td><td>{{ $backup->created_at?->format('Y-m-d H:i') }}</td><td>
@if($backup->status === 'completed')<a class="secondary compact" href="{{ route('admin.backups.download',$backup) }}">دانلود</a>@endif
<form method="post" action="{{ route('admin.backups.destroy',$backup) }}" style="display:inline" onsubmit="return confirm('Backup حذف شود؟')">@csrf @method('DELETE')<button class="danger compact">حذف</button></form>
@if($backup->status === 'completed')<details style="margin-top:8px"><summary>Restore</summary><form method="post" action="{{ route('admin.backups.restore',$backup) }}" class="form-stack">@csrf<input type="password" name="password" placeholder="رمز Super Admin" required><input name="confirmation" placeholder="RESTORE" required><button class="danger" onclick="return confirm('Restore داده‌های فعلی را جایگزین می‌کند. ادامه؟')">Restore امن</button></form></details>@endif
</td></tr>
@empty<tr><td colspan="7"><div class="empty">هنوز Backup ساخته نشده است.</div></td></tr>@endforelse
</tbody></table></div>{{ $backups->links() }}
</section>
@endsection
