<?php
namespace App\Services;

use App\Models\MasterSubscription;
use App\Models\SubscriptionEvent;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class SubscriptionTokenService
{
    /** @return array{plain:string,hash:string} */
    public function issue(): array
    {
        $plain = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        return ['plain' => $plain, 'hash' => hash('sha256', $plain)];
    }

    public function hashForLookup(string $token): string
    {
        return hash('sha256', $token);
    }

    public function resolve(string $token): ?MasterSubscription
    {
        if (strlen($token) < 32 || strlen($token) > 128) return null;
        return MasterSubscription::query()
            ->where('public_token_hash', $this->hashForLookup($token))
            ->whereNull('token_revoked_at')
            ->first();
    }

    public function rotate(string $masterSubscriptionId): string
    {
        return DB::transaction(function () use ($masterSubscriptionId) {
            $subscription = MasterSubscription::query()->lockForUpdate()->findOrFail($masterSubscriptionId);
            $issued = $this->issue();
            $subscription->public_token_hash = $issued['hash'];
            $subscription->public_token_encrypted = $issued['plain'];
            $subscription->token_version++;
            $subscription->token_revoked_at = null;
            $subscription->save();
            SubscriptionEvent::create([
                'master_subscription_id' => $subscription->master_subscription_id,
                'reseller_id' => $subscription->reseller_id,
                'store_id' => $subscription->store_id,
                'event_name' => 'subscription.token_rotated',
                'payload' => ['token_version' => $subscription->token_version],
            ]);
            return $issued['plain'];
        }, 5);
    }

    public function revoke(string $masterSubscriptionId): void
    {
        DB::transaction(function () use ($masterSubscriptionId) {
            $subscription = MasterSubscription::query()->lockForUpdate()->findOrFail($masterSubscriptionId);
            if ($subscription->token_revoked_at !== null) return;
            $subscription->token_revoked_at = now();
            $subscription->save();
            SubscriptionEvent::create([
                'master_subscription_id' => $subscription->master_subscription_id,
                'reseller_id' => $subscription->reseller_id,
                'store_id' => $subscription->store_id,
                'event_name' => 'subscription.token_revoked',
                'payload' => ['token_version' => $subscription->token_version],
            ]);
        }, 5);
    }

    public function revealForOwner(MasterSubscription $subscription): string
    {
        if ($subscription->token_revoked_at !== null) throw new RuntimeException('subscription_token_revoked');
        return (string) $subscription->public_token_encrypted;
    }
}
