<?php
namespace Tests\Feature;

use App\Models\MasterSubscription;
use App\Models\Reseller;
use App\Models\Store;
use App\Models\User;
use App\Models\Wallet;
use App\Services\MasterSubscriptionService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ResellerPanelTest extends TestCase
{
    use DatabaseTransactions;

    private function resellerUser(string $email): array
    {
        $reseller = Reseller::query()->create(['name' => $email]);
        Wallet::query()->create(['reseller_id' => $reseller->id, 'balance' => 1000, 'fractional_numerator' => 0]);
        $store = Store::query()->create(['reseller_id' => $reseller->id, 'name' => 'Store']);
        $user = User::query()->create(['reseller_id' => $reseller->id, 'name' => 'User', 'email' => $email, 'password' => 'a-very-strong-password', 'role' => 'reseller']);
        return [$reseller, $store, $user];
    }

    public function test_reseller_cannot_edit_another_resellers_store(): void
    {
        [, , $userA] = $this->resellerUser('a@example.test');
        [, $storeB] = $this->resellerUser('b@example.test');
        $this->actingAs($userA)->get(route('reseller.stores.edit', $storeB->id))->assertNotFound();
    }

    public function test_reseller_cannot_operate_on_another_resellers_subscription(): void
    {
        [, , $userA] = $this->resellerUser('a2@example.test');
        [$resellerB, $storeB] = $this->resellerUser('b2@example.test');
        $subscription = app(MasterSubscriptionService::class)->create($resellerB->id, $storeB->id, 'B', 0, 0)['subscription'];
        $this->actingAs($userA)->post(route('reseller.subscriptions.suspend', $subscription->master_subscription_id))->assertNotFound();
        $this->assertSame('active', $subscription->fresh()->desired_state);
    }

    public function test_single_store_is_selected_automatically_when_creating_subscription(): void
    {
        [$reseller, $store, $user] = $this->resellerUser('single@example.test');
        $response = $this->actingAs($user)->post(route('reseller.subscriptions.store'), [
            'name' => 'Customer',
            'volume_gb' => '0',
            'duration_days' => 0,
        ]);
        $response->assertRedirect(route('reseller.subscriptions.index'));
        $subscription = MasterSubscription::query()->where('reseller_id', $reseller->id)->firstOrFail();
        $this->assertSame($store->id, $subscription->store_id);
        $this->assertSame(0, (int) $subscription->quota_bytes);
        $this->assertNull($subscription->expires_at);
    }

    public function test_admin_can_create_reseller_with_wallet_and_initial_store(): void
    {
        $admin = User::query()->create(['name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'a-very-strong-password', 'role' => 'super_admin']);
        $response = $this->actingAs($admin)->post(route('admin.resellers.store'), [
            'name' => 'New reseller',
            'email' => 'new-reseller@example.test',
            'password' => 'another-very-strong-password',
            'password_confirmation' => 'another-very-strong-password',
            'store_name' => 'First store',
        ]);
        $response->assertRedirect(route('admin.resellers.index'));
        $reseller = Reseller::query()->where('name', 'New reseller')->firstOrFail();
        $this->assertDatabaseHas('wallets', ['reseller_id' => $reseller->id, 'balance' => 0]);
        $this->assertDatabaseHas('stores', ['reseller_id' => $reseller->id, 'name' => 'First store']);
        $this->assertDatabaseHas('users', ['reseller_id' => $reseller->id, 'email' => 'new-reseller@example.test', 'role' => 'reseller']);
    }
}
