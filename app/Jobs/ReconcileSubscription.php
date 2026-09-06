<?php
namespace App\Jobs;

use App\Models\MasterSubscription;
use App\Models\Wallet;
use App\Services\ProviderOperationService;
use App\Services\SubscriptionStateService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class ReconcileSubscription implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 20;
    public int $uniqueFor = 120;

    public function __construct(public readonly string $masterSubscriptionId) {}
    public function uniqueId(): string { return $this->masterSubscriptionId; }

    public function handle(SubscriptionStateService $states, ProviderOperationService $operations): void
    {
        $subscription = MasterSubscription::withTrashed()->find($this->masterSubscriptionId);
        if (!$subscription) return;
        $walletBalance = (int) Wallet::query()->where('reseller_id', $subscription->reseller_id)->value('balance');
        $states->refresh($subscription, $walletBalance);
        $subscription = MasterSubscription::withTrashed()->findOrFail($this->masterSubscriptionId);

        foreach ($subscription->mappings()->get() as $mapping) {
            if ($subscription->desired_state === 'deleted') {
                if ($mapping->actual_state !== 'deleted') $operations->enqueue($subscription, (int) $mapping->provider_id, 'delete_user');
            } elseif ($mapping->sync_status === 'missing') {
                $operations->enqueue($subscription, (int) $mapping->provider_id, 'create_user');
            } else {
                $shouldBeActive = $subscription->desired_state === 'active';
                $isActive = $mapping->actual_state === 'active';
                if ($mapping->actual_state !== null && $shouldBeActive !== $isActive) {
                    $operations->enqueue($subscription, (int) $mapping->provider_id, 'sync_state', ['desired_state' => $subscription->desired_state]);
                }
            }
            $mapping->last_reconciled_at = now();
            $mapping->save();
        }
    }
}
