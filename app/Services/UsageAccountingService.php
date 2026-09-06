<?php
namespace App\Services;

use App\Models\MasterSubscription;
use App\Models\ProviderUserMapping;
use App\Models\UsageCharge;
use App\Models\UsageSnapshot;
use App\Models\Wallet;
use App\Models\WalletLedger;
use DateTimeInterface;
use DomainException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use OverflowException;
use ResellerPlatform\Domain\Accounting;

final class UsageAccountingService
{
    public function __construct(
        private readonly PriceService $prices,
        private readonly SubscriptionStateService $states,
    ) {}

    public function record(
        int $mappingId,
        int $observedBytes,
        string $epoch,
        DateTimeInterface $observedAt,
        string $idempotencyKey,
        ?int $verifiedBaseline = null,
    ): UsageSnapshot {
        if ($observedBytes < 0) throw new InvalidArgumentException('Usage cannot be negative');
        if ($epoch === '' || strlen($epoch) > 64) throw new InvalidArgumentException('Invalid usage epoch');
        $key = hash('sha256', $idempotencyKey);

        $existing = UsageSnapshot::query()->where('idempotency_key', $key)->first();
        if ($existing) return $existing;

        $result = DB::transaction(function () use ($mappingId, $observedBytes, $epoch, $observedAt, $key, $verifiedBaseline) {
            $mapping = ProviderUserMapping::query()->lockForUpdate()->findOrFail($mappingId);
            $duplicate = UsageSnapshot::query()->where('idempotency_key', $key)->first();
            if ($duplicate) return ['snapshot' => $duplicate, 'transition' => null];

            $subscription = MasterSubscription::withTrashed()->lockForUpdate()->findOrFail($mapping->master_subscription_id);
            $previous = (int) $mapping->last_usage_bytes;
            try {
                $delta = Accounting::delta($previous, $observedBytes, (string) $mapping->usage_epoch, $epoch, $verifiedBaseline);
            } catch (DomainException) {
                $snapshot = UsageSnapshot::query()->create([
                    'mapping_id' => $mapping->id,
                    'master_subscription_id' => $mapping->master_subscription_id,
                    'provider_id' => $mapping->provider_id,
                    'previous_bytes' => $previous,
                    'observed_bytes' => $observedBytes,
                    'delta_bytes' => 0,
                    'epoch' => $epoch,
                    'status' => 'reconcile_required',
                    'idempotency_key' => $key,
                    'observed_at' => $observedAt,
                    'created_at' => now(),
                ]);
                $mapping->sync_status = 'stale';
                $mapping->last_error = 'usage_counter_regression';
                $mapping->last_error_at = now();
                $mapping->retry_count = (int) $mapping->retry_count + 1;
                $mapping->save();
                return ['snapshot' => $snapshot, 'transition' => null];
            }

            $wallet = Wallet::query()->where('reseller_id', $subscription->reseller_id)->lockForUpdate()->firstOrFail();
            $rate = $this->prices->rateAt($observedAt);
            $remainderBefore = (int) $wallet->fractional_numerator;
            $billing = Accounting::charge($delta, $rate, $remainderBefore);
            $charge = (int) $billing['charge'];
            $remainderAfter = (int) $billing['remainder'];

            $snapshot = UsageSnapshot::query()->create([
                'mapping_id' => $mapping->id,
                'master_subscription_id' => $mapping->master_subscription_id,
                'provider_id' => $mapping->provider_id,
                'previous_bytes' => $previous,
                'observed_bytes' => $observedBytes,
                'delta_bytes' => $delta,
                'epoch' => $epoch,
                'status' => 'valid',
                'idempotency_key' => $key,
                'observed_at' => $observedAt,
                'created_at' => now(),
            ]);

            $usedBefore = (int) $subscription->used_bytes;
            if ($delta > PHP_INT_MAX - $usedBefore) throw new OverflowException('Subscription usage overflow');
            $subscription->used_bytes = $usedBefore + $delta;

            $oldBalance = (int) $wallet->balance;
            if ($charge > 0 && $oldBalance < PHP_INT_MIN + $charge) throw new OverflowException('Wallet balance underflow');
            $newBalance = $oldBalance - $charge;
            $ledgerId = null;
            if ($charge > 0) {
                $ledger = WalletLedger::query()->create([
                    'reseller_id' => $subscription->reseller_id,
                    'amount' => -$charge,
                    'type' => 'usage_charge',
                    'reference' => 'usage:'.$snapshot->id,
                    'description' => 'Usage charge',
                    'balance_after' => $newBalance,
                    'created_at' => now(),
                ]);
                $ledgerId = $ledger->id;
            }
            $wallet->balance = $newBalance;
            $wallet->fractional_numerator = $remainderAfter;
            $wallet->save();

            UsageCharge::query()->create([
                'usage_snapshot_id' => $snapshot->id,
                'master_subscription_id' => $subscription->master_subscription_id,
                'reseller_id' => $subscription->reseller_id,
                'usage_bytes' => $delta,
                'rate_minor_per_gb' => $rate,
                'charged_minor' => $charge,
                'fractional_remainder_before' => $remainderBefore,
                'fractional_remainder_after' => $remainderAfter,
                'wallet_ledger_id' => $ledgerId,
                'created_at' => now(),
            ]);

            $mapping->last_usage_bytes = $observedBytes;
            $mapping->usage_epoch = $epoch;
            $mapping->sync_status = 'synced';
            $mapping->last_success_at = now();
            $mapping->last_error = null;
            $mapping->last_error_at = null;
            $mapping->retry_count = 0;
            $mapping->save();

            $nextState = $this->states->compute($subscription, $newBalance);
            if ($nextState !== $subscription->desired_state) {
                $subscription->desired_state = $nextState;
                $subscription->state_version = (int) $subscription->state_version + 1;
                $subscription->last_state_change_at = now();
            }
            $subscription->save();

            return ['snapshot' => $snapshot, 'transition' => [$subscription->reseller_id, $oldBalance, $newBalance]];
        }, 5);

        if ($result['transition'] !== null) {
            [$resellerId, $oldBalance, $newBalance] = $result['transition'];
            $this->states->applyWalletTransition((int) $resellerId, (int) $oldBalance, (int) $newBalance);
        }
        return $result['snapshot'];
    }
}
