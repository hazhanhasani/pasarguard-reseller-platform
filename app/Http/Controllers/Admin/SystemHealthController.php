<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\DiagnosticBundleService;
use App\Services\SystemHealthService;

final class SystemHealthController extends Controller
{
    public function index(SystemHealthService $health)
    {
        return view('admin.system-health.index', ['health'=>$health->collect()]);
    }

    public function diagnostic(DiagnosticBundleService $bundles)
    {
        try {
            $path = $bundles->create();
            return response()->download($path, 'platform-diagnostic-'.now()->format('Ymd-His').'.zip', [
                'Cache-Control'=>'no-store, private',
                'X-Content-Type-Options'=>'nosniff',
            ])->deleteFileAfterSend(true);
        } catch (\Throwable $e) {
            report($e);
            return back()->with('error','ساخت Diagnostic Bundle ناموفق بود.');
        }
    }
}
