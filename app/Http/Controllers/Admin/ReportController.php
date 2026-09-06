<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ReportExportService;
use App\Services\UsageReportService;
use Illuminate\Http\Request;

final class ReportController extends Controller
{
    public function index(Request $request, UsageReportService $reports)
    {
        $filters = $this->filters($request, ['date','reseller','store','subscription','provider']);
        $built = $reports->build(null, $filters['dimension'], $filters['from'], $filters['to']);
        return view('reports.usage', [
            'admin' => true,
            'rows' => $built['query']->paginate(50)->withQueryString(),
            'columns' => $built['columns'],
            'dimensions' => ['date'=>'تاریخ','reseller'=>'نماینده','store'=>'فروشگاه','subscription'=>'اشتراک','provider'=>'Provider'],
            'filters' => $filters,
        ]);
    }

    public function export(Request $request, string $format, UsageReportService $reports, ReportExportService $exports)
    {
        abort_unless(in_array($format, ['csv','xls'], true), 404);
        $filters = $this->filters($request, ['date','reseller','store','subscription','provider']);
        $built = $reports->build(null, $filters['dimension'], $filters['from'], $filters['to']);
        $name = 'admin-usage-'.$filters['dimension'].'-'.now()->format('Ymd-His');
        return $format === 'csv'
            ? $exports->csv($built['query'], $built['columns'], $name)
            : $exports->excel($built['query'], $built['columns'], $name);
    }

    private function filters(Request $request, array $allowed): array
    {
        $data = $request->validate([
            'dimension' => ['nullable','in:'.implode(',', $allowed)],
            'from' => ['nullable','date_format:Y-m-d'],
            'to' => ['nullable','date_format:Y-m-d','after_or_equal:from'],
        ]);
        return ['dimension' => $data['dimension'] ?? 'date', 'from' => $data['from'] ?? null, 'to' => $data['to'] ?? null];
    }
}
