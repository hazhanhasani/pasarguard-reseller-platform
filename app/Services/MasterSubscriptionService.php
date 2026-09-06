<?php
namespace App\Services;

use App\Models\MasterSubscription;
use App\Models\Provider;
use App\Models\ProviderUserMapping;
use App\Models\Store;
use App\Models\SubscriptionEvent;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use OverflowException;
use ResellerPlatform\Domain\SubscriptionPolicy;

final class MasterSubscriptionService
{
    public function __construct(
        private readonly SubscriptionTokenService $tokens,
        private readonly ProviderOperationService $operations,
        private readonly SubscriptionStateService $states,
    ) {}

    /** @return array{subscription:MasterSubscription,token:string} */
    public function create(int $resellerId, int $storeId, string $name, int $quotaBytes, int $durationSeconds): array
    {
        $name = trim($name);
        if ($name === '' || mb_strlen($name) > 120) throw new InvalidArgumentException('Invalid subscription name');
        if ($quotaBytes < 0 || $durationSeconds < 0) throw new InvalidArgumentException('Negative limits are invalid');

        return DB::transaction(function () use ($resellerId, $storeId, $name, $quotaBytes, $durationSeconds) {
            Store::query()->where('reseller_id', $resellerId)->lockForUpdate()->findOrFail($storeId);
            $wallet = Wallet::query()->where('reseller_id', $resellerId)->lockForUpdate()->firstOrFail();
            $issued = $this->tokens->issue();
            $id = (string) Str::ulid();
            $expiresAt = $durationSeconds === 0 ? null : now()->addSeconds($durationSeconds);
            $state = SubscriptionPolicy::desiredState(false, false, $expiresAt?->getTimestamp(), now()->getTimestamp(), $quotaBytes, 0, (int) $wallet->balance);

            $subscription = MasterSubscription::query()->create([
                'master_subscription_id' => $id,
                'reseller_id' => $resellerId,
                'store_id' => $storeId,
                'name' => $name,
                'public_token_hash' => $issued['hash'],
                'public_token_encrypted' => $issued['plain'],
                'token_version' => 1,
                'quota_bytes' => $quotaBytes,
                'used_bytes' => 0,
                'duration_seconds' => $durationSeconds,
                'expires_at' => $expiresAt,
                'manual_suspended' => false,
                'desired_state' => $state,
                'state_version' => 1,
                'last_state_change_at' => now(),
            ]);

            $username = 'u_'.strtolower(substr($id, 0, 26));
            foreach (Provider::query()->where('mode', 'active')->orderBy('id')->get() as $provider) {
                ProviderUserMapping::query()->create([
                    'master_subscription_id' => $id,
                    'provider_id' => $provider->id,
                    'provider_username' => $username,
                    'sync_status' => 'pending',
                    'usage_epoch' => 'initial',
                ]);
                $this->operations->enqueue($subscription, (int) $provider->id, 'create_user', [
                    'data_limit' => $quotaBytes,
                    'expire' => $expiresAt?->getTimestamp() ?? 0,
                ]);
            }

            SubscriptionEvent::query()->create([
                'master_subscription_id' => $id,
                'reseller_id' => $resellerId,
                'store_id' => $storeId,
                'event_name' => 'subscription.created',
                'payload' => ['quota_bytes' => $quotaBytes, 'duration_seconds' => $durationSeconds, 'desired_state' => $state],
            ]);

            return ['subscription' => $subscription, 'token' => $issued['plain']];
        }, 5);
    }

    public function suspend(string $id): MasterSubscription
    {
        return $this->changeStateFlag($id, true, 'subscription.suspended');
    }

    public function reactivate(string $id): MasterSubscription
    {
        return $this->changeStateFlag($id, false, 'subscription.reactivated');
    }

    public function addVolume(string $id, int $bytes): MasterSubscription
    {
        if ($bytes <= 0) throw new InvalidArgumentException('Added volume must be positive');
        return DB::transaction(function () use ($id, $bytes) {
            $subscription = MasterSubscription::query()->lockForUpdate()->findOrFail($id);
            if ((int) $subscription->quota_bytes !== 0) {
                if ((int) $subscription->quota_bytes > PHP_INT_MAX - $bytes) throw new OverflowException('Quota overflow');
                $subscription->quota_bytes = (int) $subscription->quota_bytes + $bytes;
                $subscription->state_version++;
                $subscription->save();
                $wallet = (int) Wallet::query()->where('reseller_id', $subscription->reseller_id)->value('balance');
                $this->states->refresh($subscription, $wallet);
                foreach ($subscription->mappings()->pluck('provider_id') as $providerId) {
                    $this->operations->enqueue($subscription, (int) $providerId, 'update_user', ['data_limit' => $subscription->quota_bytes]);
                }
            }
            SubscriptionEvent::query()->create([
                'master_subscription_id' => $subscription->master_subscription_id,
                'reseller_id' => $subscription->reseller_id,
                'store_id' => $subscription->store_id,
                'event_name' => 'subscription.volume_added',
                'payload' => ['bytes' => $bytes, 'quota_bytes' => $subscription->quota_bytes],
            ]);
            return $subscription->fresh();
        }, 5);
    }

    public function extend(string $id, int $seconds): MasterSubscription
    {
        if ($seconds <= 0) throw new InvalidArgumentException('Extension must be positive');
        return DB::transaction(function () use ($id, $seconds) {
            $subscription = MasterSubscription::query()->lockForUpdate()->findOrFail($id);
            if ($subscription->expires_at !== null) {
                $base = $subscription->expires_at->isFuture() ? $subscription->expires_at : now();
                $subscription->expires_at = $base->copy()->addSeconds($seconds);
                if ((int) $subscription->duration_seconds <= PHP_INT_MAX - $seconds) $subscription->duration_seconds = (int) $subscription->duration_seconds + $seconds;
                $subscription->state_version++;
                $subscription->save();
                $wallet = (int) Wallet::query()->where('reseller_id', $subscription->reseller_id)->value('balance');
                $this->states->refresh($subscription, $wallet);
                foreach ($subscription->mappings()->pluck('provider_id') as $providerId) {
                    $this->operations->enqueue($subscription, (int) $providerId, 'update_user', ['expire' => $subscription->expires_at->getTimestamp()]);
                }
            }
            SubscriptionEvent::query()->create([
                'master_subscription_id' => $subscription->master_subscription_id,
                'reseller_id' => $subscription->reseller_id,
                'store_id' => $subscription->store_id,
                'event_name' => 'subscription.extended',
                'payload' => ['seconds' => $seconds, 'expires_at' => $subscription->expires_at?->toIso8601String()],
            ]);
            return $subscription->fresh();
        }, 5);
    }

    public function delete(string $id): void
    {
        DB::transaction(function () use ($id) {
            $subscription = MasterSubscription::query()->lockForUpdate()->findOrFail($id);
            $subscription->desired_state = 'deleted';
            $subscription->state_version++;
            $subscription->last_state_change_at = now();
            $subscription->save();
            foreach ($subscription->mappings()->pluck('provider_id') as $providerId) {
                $this->operations->enqueue($subscription, (int) $providerId, 'delete_user');
            }
            SubscriptionEvent::query()->create([
                'master_subscription_id' => $subscription->master_subscription_id,
                'reseller_id' => $subscription->reseller_id,
                'store_id' => $subscription->store_id,
                'event_name' => 'subscription.deleted',
                'payload' => ['state_version' => $subscription->state_version],
            ]);
            $subscription->delete();
        }, 5);
    }

    private function changeStateFlag(string $id, bool $suspended, string $event): MasterSubscription
    {
        return DB::transaction(function () use ($id, $suspended, $event) {
            $subscription = MasterSubscription::query()->lockForUpdate()->findOrFail($id);
            $subscription->manual_suspended = $suspended;
            $subscription->save();
            $wallet = (int) Wallet::query()->where('reseller_id', $subscription->reseller_id)->value('balance');
            $this->states->refresh($subscription, $wallet);
            $subscription = $subscription->fresh();
            $this->operations->enqueueStateForAll($subscription);
            SubscriptionEvent::query()->create([
                'master_subscription_id' => $subscription->master_subscription_id,
                'reseller_id' => $subscription->reseller_id,
                'store_id' => $subscription->store_id,
                'event_name' => $event,
                'payload' => ['desired_state' => $subscription->desired_state],
            ]);
            return $subscription;
        }, 5);
    }
}
