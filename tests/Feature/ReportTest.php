<?php
namespace Tests\Feature;

use App\Models\Provider;
use App\Models\Reseller;
use App\Models\Store;
use App\Services\UsageReportService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use DatabaseTransactions;

    public function test_reseller_report_is_tenant_scoped(): void
    {
        [$r1, $s1, $m1, $provider] = $this->subscriptionContext('R1','S1');
        [$r2, $s2, $m2] = $this->subscriptionContext('R2','S2', $provider);
        $this->charge($r1->id, $m1, $provider->id, 1_000_000_000, 1000);
        $this->charge($r2->id, $m2, $provider->id, 9_000_000_000, 9000);

        $built = app(UsageReportService::class)->build($r1->id, 'store', null, null);
        $rows = $built['query']->get();
        $this->assertCount(1, $rows);
        $this->assertSame('S1', $rows->first()->label);
        $this->assertSame(1_000_000_000, (int) $rows->first()->usage_bytes);
        $this->assertSame(1000, (int) $rows->first()->cost_minor);
    }

    public function test_reseller_cannot_request_provider_dimension(): void
    {
        $this->expectException(InvalidArgumentException::class);
        app(UsageReportService::class)->build(1, 'provider', null, null);
    }

    public function test_admin_provider_report_is_available(): void
    {
        [$r, $store, $master, $provider] = $this->subscriptionContext('R','S');
        $this->charge($r->id, $master, $provider->id, 2_000_000_000, 2000);
        $rows = app(UsageReportService::class)->build(null, 'provider', null, null)['query']->get();
        $this->assertSame('Internal Provider', $rows->first()->label);
    }

    private function subscriptionContext(string $resellerName, string $storeName, ?Provider $provider = null): array
    {
        $reseller = Reseller::query()->create(['name' => $resellerName]);
        $store = Store::query()->create(['reseller_id' => $reseller->id, 'name' => $storeName, 'brand_color' => '#4f7cff']);
        $provider ??= Provider::query()->create([
            'name' => 'Internal Provider', 'adapter' => 'pasarguard', 'api_url' => 'https://1.1.1.1',
            'credentials' => ['key' => 'secret-test-key'], 'group_ids' => [1], 'mode' => 'active',
            'ready' => true, 'health' => 'healthy', 'health_score' => 100,
        ]);
        $master = (string) Str::ulid();
        DB::table('master_subscriptions')->insert([
            'master_subscription_id' => $master, 'reseller_id' => $reseller->id, 'store_id' => $store->id,
            'name' => 'Sub '.$storeName, 'public_token_hash' => hash('sha256', random_bytes(16)),
            'public_token_encrypted' => 'encrypted-placeholder', 'quota_bytes' => 0, 'used_bytes' => 0,
            'duration_seconds' => 0, 'manual_suspended' => 0, 'desired_state' => 'active', 'state_version' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        return [$reseller, $store, $master, $provider];
    }

    private function charge(int $resellerId, string $master, int $providerId, int $bytes, int $cost): void
    {
        $mappingId = DB::table('provider_user_mappings')->insertGetId([
            'master_subscription_id' => $master, 'provider_id' => $providerId,
            'provider_username' => 'u_'.strtolower(substr($master, -10)), 'sync_status' => 'synced',
            'last_usage_bytes' => $bytes, 'usage_epoch' => 'a', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $snapshotId = DB::table('usage_snapshots')->insertGetId([
            'mapping_id' => $mappingId, 'master_subscription_id' => $master, 'provider_id' => $providerId,
            'previous_bytes' => 0, 'observed_bytes' => $bytes, 'delta_bytes' => $bytes, 'epoch' => 'a',
            'status' => 'valid', 'idempotency_key' => hash('sha256', $master.$bytes), 'observed_at' => now(), 'created_at' => now(),
        ]);
        DB::table('usage_charges')->insert([
            'usage_snapshot_id' => $snapshotId, 'master_subscription_id' => $master, 'reseller_id' => $resellerId,
            'usage_bytes' => $bytes, 'rate_minor_per_gb' => 1000, 'charged_minor' => $cost,
            'fractional_remainder_before' => 0, 'fractional_remainder_after' => 0, 'created_at' => now(),
        ]);
    }
}
