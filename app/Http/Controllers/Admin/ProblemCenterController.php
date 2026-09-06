<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\ReconcileSubscription;
use App\Models\Provider;
use App\Models\ProviderOperation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class ProblemCenterController extends Controller
{
    public function index(Request $request)
    {
        $query = ProviderOperation::query()->with(['provider','subscription.store'])
            ->whereIn('status', ['pending','retrying','failed']);
        if (in_array($request->query('status'), ['pending','retrying','failed'], true)) $query->where('status', $request->query('status'));
        if ($request->filled('provider')) {
            $providerId = (int) $request->query('provider');
            Provider::query()->findOrFail($providerId);
            $query->where('provider_id', $providerId);
        }
        return view('admin.problems.index', [
            'operations' => $query->orderByRaw("FIELD(status,'failed','retrying','pending')")->latest('id')->paginate(30)->withQueryString(),
            'providers' => Provider::query()->orderBy('name')->get(),
        ]);
    }

    public function retry(int $operation)
    {
        DB::transaction(function () use ($operation) {
            $op = ProviderOperation::query()->lockForUpdate()->findOrFail($operation);
            if ($op->status === 'succeeded') return;
            $op->status = 'retrying';
            $op->attempts = 0;
            $op->available_at = now();
            $op->last_error = null;
            $op->save();
        }, 5);
        return back()->with('success', 'عملیات برای Retry آماده شد.');
    }

    public function retryAll(Request $request)
    {
        $query = ProviderOperation::query()->where('status', 'failed');
        if ($request->filled('provider_id')) $query->where('provider_id', (int) $request->input('provider_id'));
        $ids = $query->orderBy('id')->limit(500)->pluck('id');
        if ($ids->isNotEmpty()) {
            ProviderOperation::query()->whereIn('id', $ids)->update([
                'status' => 'retrying', 'attempts' => 0, 'available_at' => now(), 'last_error' => null, 'updated_at' => now(),
            ]);
        }
        return back()->with('success', number_format($ids->count()).' عملیات برای Retry آماده شد.');
    }

    public function reconcile(string $subscription)
    {
        ReconcileSubscription::dispatch($subscription)->onQueue('reconcile');
        return back()->with('success', 'Reconciliation اشتراک در صف قرار گرفت.');
    }
}
