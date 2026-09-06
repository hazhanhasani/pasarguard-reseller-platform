@extends('layouts.panel')
@section('title','System Health')
@section('page-title','System Health')
@section('top-action')<form method="post" action="{{ route('admin.system-health.diagnostic') }}">@csrf<button>Diagnostic Bundle</button></form>@endsection
@section('content')
<div class="metric-grid">
    <article class="metric featured"><span>نسخه</span><strong>{{ $health['version'] }}</strong><small>Application</small></article>
    <article class="metric"><span>PHP</span><strong>{{ $health['php']['version'] }}</strong><small>{{ $health['php']['is_64bit'] ? '64-bit' : '32-bit' }}</small></article>
    <article class="metric"><span>Queue Backlog</span><strong>{{ number_format($health['queue']['backlog']) }}</strong><small>Failed: {{ number_format($health['queue']['failed']) }}</small></article>
    <article class="metric"><span>فضای آزاد</span><strong>{{ number_format($health['disk']['free_bytes']/1000000000,2) }}</strong><small>GB</small></article>
    <article class="metric"><span>Cron Age</span><strong>{{ $health['cron']['age_seconds'] === null ? '—' : number_format($health['cron']['age_seconds']) }}</strong><small>seconds</small></article>
    <article class="metric"><span>Pending Migrations</span><strong>{{ count($health['migrations']['pending']) }}</strong><small>of {{ $health['migrations']['file_count'] }}</small></article>
</div>
@if($health['maintenance']['write_lock'])<div class="alert warning"><b>Maintenance Write Lock فعال است.</b><span>{{ $health['maintenance']['reason'] ?: 'unknown' }}</span></div>@endif
<div class="split-grid">
<section class="card"><div class="card-head"><div><span class="muted">Runtime</span><h2>هسته سیستم</h2></div></div>
<div class="list-row"><span>Database</span><b>{{ $health['database']['driver'] }} — {{ $health['database']['version'] }}</b></div>
<div class="list-row"><span>آخرین Cron</span><b>{{ $health['cron']['last_finished'] ?: '—' }}</b></div>
<div class="list-row"><span>خطای Cron</span><b>{{ $health['cron']['last_error'] ?: '—' }}</b></div>
<div class="list-row"><span>آخرین Backup</span><b>{{ $health['backup'] ? '#'.$health['backup']['id'].' / '.$health['backup']['completed_at'] : '—' }}</b></div>
<div class="list-row"><span>آخرین Update</span><b>{{ $health['update'] ? $health['update']['version'].' / '.$health['update']['status'] : '—' }}</b></div>
</section>
<section class="card"><div class="card-head"><div><span class="muted">Writable & Extensions</span><h2>سازگاری Hosting</h2></div></div>
@foreach($health['writable'] as $name=>$ok)<div class="list-row"><span>{{ $name }}</span><b>{{ $ok ? '✓ Writable' : '✗ Not writable' }}</b></div>@endforeach
@foreach($health['php']['extensions'] as $name=>$ok)<div class="list-row"><span>{{ $name }}</span><b>{{ $ok ? '✓' : '✗' }}</b></div>@endforeach
</section>
</div>
<section class="card"><div class="card-head"><div><span class="muted">Provider Health</span><h2>Providerها</h2></div></div>
<div class="table-wrap"><table><thead><tr><th>ID</th><th>نام داخلی</th><th>Mode</th><th>Health</th><th>Score</th><th>Latency</th><th>Errors</th></tr></thead><tbody>
@forelse($health['providers'] as $provider)<tr><td>#{{ $provider['id'] }}</td><td>{{ $provider['name'] }}</td><td>{{ $provider['mode'] }}</td><td>{{ $provider['health'] }}</td><td>{{ $provider['health_score'] }}</td><td>{{ $provider['latency_ms'] === null ? '—' : $provider['latency_ms'].' ms' }}</td><td>{{ $provider['error_counter'] }}</td></tr>
@empty<tr><td colspan="7"><div class="empty">Provider ثبت نشده است.</div></td></tr>@endforelse
</tbody></table></div></section>
<div class="split-grid">
<section class="card"><div class="card-head"><div><span class="muted">BluPal</span><h2>Payment Health</h2></div></div>
<div class="list-row"><span>Configured</span><b>{{ $health['blupal']['configured'] ? 'Yes' : 'No' }}</b></div><div class="list-row"><span>Enabled</span><b>{{ $health['blupal']['enabled'] ? 'Yes' : 'No' }}</b></div><div class="list-row"><span>Last Success</span><b>{{ $health['blupal']['last_success_at'] ?: '—' }}</b></div><div class="list-row"><span>Last Failure</span><b>{{ $health['blupal']['last_failure_at'] ?: '—' }}</b></div>
</section>
<section class="card"><div class="card-head"><div><span class="muted">Recent Error Summaries</span><h2>هشدارهای باز</h2></div></div>
@forelse($health['recent_errors'] as $error)<div class="list-row"><div><b>{{ $error['event_key'] }}</b><small>{{ $error['last_seen_at'] }}</small></div><span>{{ $error['severity'] }} × {{ $error['occurrences'] }}</span></div>@empty<div class="empty">هشدار باز وجود ندارد.</div>@endforelse
</section>
</div>
@if($health['migrations']['pending'])<section class="card"><h2>Pending Migrations</h2>@foreach($health['migrations']['pending'] as $migration)<code style="display:block">{{ $migration }}</code>@endforeach</section>@endif
@endsection
