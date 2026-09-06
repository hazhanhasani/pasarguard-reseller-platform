<?php
namespace App\Services;

use App\Models\MasterSubscription;
use App\Models\ProviderOperation;

final class ProviderOperationService
{
    public function enqueue(MasterSubscription $subscription, int $providerId, string $operation, array $payload = [], ?string $suffix = null): ProviderOperation
    {
        $material = implode('|', [
            $subscription->master_subscription_id,
            $providerId,
            $operation,
            (int) $subscription->state_version,
            $suffix ?? '',
        ]);
        $key = hash('sha256', $material);
        return ProviderOperation::query()->firstOrCreate(
            ['idempotency_key' => $key],
            [
                'master_subscription_id' => $subscription->master_subscription_id,
                'provider_id' => $providerId,
                'operation' => $operation,
                'desired_version' => $subscription->state_version,
                'status' => 'pending',
                'payload' => $payload,
                'attempts' => 0,
                'available_at' => now(),
            ],
        );
    }

    public function enqueueStateForAll(MasterSubscription $subscription): void
    {
        foreach ($subscription->mappings()->pluck('provider_id') as $providerId) {
            $this->enqueue($subscription, (int) $providerId, 'sync_state', ['desired_state' => $subscription->desired_state]);
        }
    }
}
