<?php
namespace App\Services;

use App\Models\MasterSubscription;
use App\Models\SubscriptionEvent;
use App\Models\Wallet;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use ResellerPlatform\Domain\SubscriptionPolicy;

final class SubscriptionStateService
{
    public function compute(MasterSubscription $subscription, int $walletBalance, ?CarbonInterface $at = null): string
    {
        $at ??= now();
        return SubscriptionPolicy::desiredState(
            $subscription->trashed() || $subscription->desired_state === 'deleted',
            (bool) $subscription->manual_suspended,
            $subscription->expires_at?->getTimestamp(),
            $at->getTimestamp(),
            (int) $subscription->quota_bytes,
            (int) $subscription->used_bytes,
            $walletBalance,
        );
    }

    public function refresh(MasterSubscription $subscription, ?int $walletBalance = null): string
    {
        $walletBalance ??= (int) Wallet::query()->where('reseller_id', $subscription->reseller_id)->value('balance');
        $next = $this->compute($subscription, $walletBalance);
        if ($next !== $subscription->desired_state) {
            $previous = $subscription->desired_state;
            $subscription->desired_state = $next;
            $subscription->state_version = (int) $subscription->state_version + 1;
            $subscription->last_state_change_at = now();
            $subscription->save();
            SubscriptionEvent::create([
                'master_subscription_id' => $subscription->master_subscription_id,
                'reseller_id' => $subscription->reseller_id,
                'store_id' => $subscription->store_id,
                'event_name' => 'subscription.state_changed',
                'payload' => ['from' => $previous, 'to' => $next, 'state_version' => $subscription->state_version],
            ]);
        }
        return $next;
    }

    public function applyWalletTransition(int $resellerId, int $oldBalance, int $newBalance): void
    {
        if ($oldBalance > 0 && $newBalance <= 0) {
            DB::transaction(function () use ($resellerId) {
                $ids = MasterSubscription::query()
                    ->where('reseller_id', $resellerId)
                    ->where('desired_state', 'active')
                    ->lockForUpdate()
                    ->pluck('master_subscription_id');
                if ($ids->isEmpty()) return;
                MasterSubscription::query()->whereIn('master_subscription_id', $ids)->update([
                    'desired_state' => 'wallet_zero',
                    'state_version' => DB::raw('state_version + 1'),
                    'last_state_change_at' => now(),
                    'updated_at' => now(),
                ]);
                foreach ($ids as $id) {
                    $s = MasterSubscription::query()->find($id);
                    if (!$s) continue;
                    SubscriptionEvent::create([
                        'master_subscription_id' => $id,
                        'reseller_id' => $resellerId,
                        'store_id' => $s->store_id,
                        'event_name' => 'wallet.depleted',
                        'payload' => ['state' => 'wallet_zero'],
                    ]);
                }
            }, 5);
            return;
        }

        if ($oldBalance <= 0 && $newBalance > 0) {
            MasterSubscription::query()
                ->where('reseller_id', $resellerId)
                ->where('desired_state', 'wallet_zero')
                ->orderBy('master_subscription_id')
                ->chunkById(100, function ($subscriptions) use ($newBalance) {
                    foreach ($subscriptions as $subscription) $this->refresh($subscription, $newBalance);
                }, 'master_subscription_id', 'master_subscription_id');
        }
    }
}
