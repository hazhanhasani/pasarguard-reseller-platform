@extends('layouts.panel')
@section('title','گزارش مصرف')
@section('page-title','گزارش مصرف و هزینه')
@section('content')
<section class="card">
<div class="card-head"><div><span class="muted">Aggregated Report</span><h2>{{ $admin ? 'گزارش مدیریتی' : 'گزارش نماینده' }}</h2></div><div style="display:flex;gap:8px"><a class="secondary compact" href="{{ $admin ? route('admin.reports.export',array_merge(['format'=>'csv'],request()->query())) : route('reseller.reports.export',array_merge(['format'=>'csv'],request()->query())) }}">CSV</a><a class="secondary compact" href="{{ $admin ? route('admin.reports.export',array_merge(['format'=>'xls'],request()->query())) : route('reseller.reports.export',array_merge(['format'=>'xls'],request()->query())) }}">Excel</a></div></div>
<form method="get" class="filter-row"><select name="dimension">@foreach($dimensions as $key=>$title)<option value="{{ $key }}" @selected($filters['dimension']===$key)>{{ $title }}</option>@endforeach</select><input type="date" name="from" value="{{ $filters['from'] }}"><input type="date" name="to" value="{{ $filters['to'] }}"><button class="secondary">اعمال فیلتر</button></form>
<div class="table-wrap"><table><thead><tr>@foreach($columns as $title)<th>{{ $title }}</th>@endforeach</tr></thead><tbody>
@forelse($rows as $row)<tr>@foreach(array_keys($columns) as $key)<td>@if($key==='usage_bytes'){{ number_format($row->{$key}/1000000000,3) }} GB @elseif($key==='cost_minor'){{ number_format($row->{$key}) }} ریال @else{{ $row->{$key} }}@endif</td>@endforeach</tr>@empty<tr><td colspan="{{ count($columns) }}"><div class="empty">برای فیلتر انتخاب‌شده داده‌ای وجود ندارد.</div></td></tr>@endforelse
</tbody></table></div>{{ $rows->links() }}
</section>
@endsection
