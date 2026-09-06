<?php
namespace App\Http\Controllers\Reseller;

use App\Http\Controllers\Controller;
use App\Services\ReportExportService;
use App\Services\UsageReportService;
use Illuminate\Http\Request;

final class ReportController extends Controller
{
    public function index(Request $request, UsageReportService $reports)
    {
        $resellerId = $this->resellerId($request);
        $filters = $this->filters($request);
        $built = $reports->build($resellerId, $filters['dimension'], $filters['from'], $filters['to']);
        return view('reports.usage', [
            'admin' => false,
            'rows' => $built['query']->paginate(50)->withQueryString(),
            'columns' => $built['columns'],
            'dimensions' => ['date'=>'تاریخ','store'=>'فروشگاه','subscription'=>'اشتراک'],
            'filters' => $filters,
        ]);
    }

    public function export(Request $request, string $format, UsageReportService $reports, ReportExportService $exports)
    {
        abort_unless(in_array($format, ['csv','xls'], true), 404);
        $resellerId = $this->resellerId($request);
        $filters = $this->filters($request);
        $built = $reports->build($resellerId, $filters['dimension'], $filters['from'], $filters['to']);
        $name = 'usage-'.$filters['dimension'].'-'.now()->format('Ymd-His');
        return $format === 'csv'
            ? $exports->csv($built['query'], $built['columns'], $name)
            : $exports->excel($built['query'], $built['columns'], $name);
    }

    private function filters(Request $request): array
    {
        $data = $request->validate([
            'dimension' => ['nullable','in:date,store,subscription'],
            'from' => ['nullable','date_format:Y-m-d'],
            'to' => ['nullable','date_format:Y-m-d','after_or_equal:from'],
        ]);
        return ['dimension' => $data['dimension'] ?? 'date', 'from' => $data['from'] ?? null, 'to' => $data['to'] ?? null];
    }

    private function resellerId(Request $request): int
    {
        $id = (int) $request->user()->reseller_id;
        abort_if($id < 1, 403);
        return $id;
    }
}
