<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MasterSubscription;
use App\Models\Provider;
use App\Models\ProviderOperation;
use App\Models\Reseller;
use App\Models\Store;
use App\Models\UsageCharge;
use App\Models\WalletLedger;
use Illuminate\Support\Facades\DB;

final class DashboardController extends Controller
{
    public function __invoke()
    {
        $today = now()->startOfDay();
        $month = now()->startOfMonth();
        return view('admin.dashboard', [
            'totalResellers' => Reseller::query()->count(),
            'totalStores' => Store::query()->count(),
            'totalSubscriptions' => MasterSubscription::query()->count(),
            'activeSubscriptions' => MasterSubscription::query()->where('desired_state', 'active')->count(),
            'suspendedSubscriptions' => MasterSubscription::query()->whereIn('desired_state', ['manual_suspended','wallet_zero','quota_exceeded'])->count(),
            'expiredSubscriptions' => MasterSubscription::query()->where('desired_state', 'expired')->count(),
            'usageToday' => (int) UsageCharge::query()->where('created_at', '>=', $today)->sum('usage_bytes'),
            'usageMonth' => (int) UsageCharge::query()->where('created_at', '>=', $month)->sum('usage_bytes'),
            'revenueMonth' => (int) UsageCharge::query()->where('created_at', '>=', $month)->sum('charged_minor'),
            'walletDepositsMonth' => (int) WalletLedger::query()->where('created_at', '>=', $month)->where('amount', '>', 0)->sum('amount'),
            'providers' => Provider::query()->orderBy('health')->orderBy('name')->get(),
            'failedSyncs' => ProviderOperation::query()->where('status', 'failed')->count(),
            'partialSyncs' => ProviderOperation::query()->whereIn('status', ['pending','retrying'])->count(),
            'queueBacklog' => (int) DB::table('jobs')->count(),
            'cronLastRun' => DB::table('settings')->where('key', 'cron_last_run')->value('value'),
            'cronLastError' => DB::table('settings')->where('key', 'cron_last_error')->value('value'),
        ]);
    }
}
