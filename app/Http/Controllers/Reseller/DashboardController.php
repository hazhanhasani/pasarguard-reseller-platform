<?php
namespace App\Http\Controllers\Reseller;

use App\Http\Controllers\Controller;
use App\Models\MasterSubscription;
use App\Models\Store;
use App\Models\UsageCharge;
use App\Models\Wallet;
use App\Models\WalletLedger;
use Illuminate\Http\Request;

final class DashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        $resellerId = (int) $request->user()->reseller_id;
        abort_if($resellerId < 1, 403);
        $today = now()->startOfDay();
        $month = now()->startOfMonth();
        $wallet = Wallet::query()->where('reseller_id', $resellerId)->firstOrFail();
        $base = MasterSubscription::query()->where('reseller_id', $resellerId);

        return view('reseller.dashboard', [
            'wallet' => $wallet,
            'usageToday' => (int) UsageCharge::query()->where('reseller_id', $resellerId)->where('created_at', '>=', $today)->sum('usage_bytes'),
            'usageMonth' => (int) UsageCharge::query()->where('reseller_id', $resellerId)->where('created_at', '>=', $month)->sum('usage_bytes'),
            'costToday' => (int) UsageCharge::query()->where('reseller_id', $resellerId)->where('created_at', '>=', $today)->sum('charged_minor'),
            'costMonth' => (int) UsageCharge::query()->where('reseller_id', $resellerId)->where('created_at', '>=', $month)->sum('charged_minor'),
            'activeCount' => (clone $base)->where('desired_state', 'active')->count(),
            'suspendedCount' => (clone $base)->whereIn('desired_state', ['manual_suspended','wallet_zero','quota_exceeded'])->count(),
            'expiredCount' => (clone $base)->where('desired_state', 'expired')->count(),
            'storeCount' => Store::query()->where('reseller_id', $resellerId)->count(),
            'recentTransactions' => WalletLedger::query()->where('reseller_id', $resellerId)->latest('id')->limit(8)->get(),
        ]);
    }
}
