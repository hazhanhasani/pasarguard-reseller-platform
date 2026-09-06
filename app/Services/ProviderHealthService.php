<?php
namespace App\Services;

use App\Models\Provider;
use App\Models\ProviderOperation;

final class ProviderHealthService
{
    /** @return array{score:int,status:string} */
    public function evaluate(Provider $provider): array
    {
        if ($provider->mode === 'maintenance') return ['score' => 100, 'status' => 'maintenance'];
        if ($provider->health === 'auth_error') return ['score' => 0, 'status' => 'auth_error'];

        $score = 100;
        $latency = (int) ($provider->latency_ms ?? 0);
        if ($latency >= 5000) $score -= 35;
        elseif ($latency >= 3000) $score -= 25;
        elseif ($latency >= 1500) $score -= 12;

        $score -= min(30, (int) $provider->error_counter * 5);

        $staleSeconds = max(60, (int) config('platform.health.provider_stale_seconds', 900));
        if (!$provider->last_successful_sync) $score -= 20;
        elseif ($provider->last_successful_sync->lt(now()->subSeconds($staleSeconds))) $score -= 20;

        $pending = ProviderOperation::query()->where('provider_id', $provider->id)->whereIn('status', ['pending','retrying'])->count();
        $failed = ProviderOperation::query()->where('provider_id', $provider->id)->where('status', 'failed')->count();
        $score -= min(10, $pending);
        $score -= min(20, $failed * 2);
        $score = max(0, min(100, $score));

        $status = match (true) {
            !$provider->ready => 'degraded',
            $score >= 80 && $latency < 1500 => 'healthy',
            $score >= 65 => 'slow',
            $score >= 35 => 'degraded',
            default => 'offline',
        };
        return ['score' => $score, 'status' => $status];
    }

    public function persist(Provider $provider): Provider
    {
        $health = $this->evaluate($provider);
        $provider->health_score = $health['score'];
        if ($provider->mode !== 'maintenance') $provider->health = $health['status'];
        $provider->save();
        return $provider->fresh();
    }
}
