<?php
namespace App\Services;

use App\Models\MasterSubscription;

final class SubscriptionOutputService
{
    /**
     * Merge every cached Provider output in stable order.
     * No deduplication, health filtering or broken-entry filtering is performed.
     */
    public function aggregate(MasterSubscription $subscription): array
    {
        $configs = [];
        $mappings = $subscription->mappings()
            ->whereNotNull('last_valid_output_at')
            ->orderBy('provider_id')
            ->orderBy('id')
            ->get();

        foreach ($mappings as $mapping) {
            $output = $mapping->last_valid_output;
            if (!is_array($output)) continue;
            foreach ($output as $entry) $configs[] = $entry;
        }

        return $configs;
    }
}
