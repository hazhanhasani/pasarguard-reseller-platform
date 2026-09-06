<?php
namespace App\Services;

use App\Models\Provider;
use Illuminate\Support\Facades\DB;

final class OperationalHealthService
{
    public function __construct(
        private readonly ProviderHealthService $health,
        private readonly NotificationService $notifications,
    ) {}

    public function run(): void
    {
        $limit = max(1, (int) config('platform.health.provider_batch', 100));
        Provider::query()->orderBy('id')->limit($limit)->get()->each(function (Provider $provider) {
            $provider = $this->health->persist($provider);
            $entity = 'provider:'.$provider->id;
            if (in_array($provider->health, ['offline','auth_error','degraded','slow'], true)) {
                $severity = match ($provider->health) {
                    'auth_error','offline' => 'critical',
                    'degraded' => 'error',
                    default => 'warning',
                };
                $this->notifications->signal(
                    'provider.health', $entity, $severity,
                    'وضعیت Provider '.$provider->name.': '.$provider->health,
                    'Health score: '.(int) $provider->health_score,
                );
            } else {
                $this->notifications->resolve('provider.health', $entity);
            }
        });

        $backlog = (int) DB::table('jobs')->count();
        $threshold = max(1, (int) config('platform.health.queue_backlog_warning', 500));
        if ($backlog >= $threshold) {
            $this->notifications->signal('queue.backlog', 'queue:database', 'warning', 'صف پردازش شلوغ است', 'Jobs: '.$backlog);
        } else {
            $this->notifications->resolve('queue.backlog', 'queue:database');
        }
    }
}
