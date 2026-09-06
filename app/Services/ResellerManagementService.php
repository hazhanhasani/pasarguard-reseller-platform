<?php
namespace App\Services;

use App\Models\Reseller;
use App\Models\Store;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;

final class ResellerManagementService
{
    /** @return array{reseller:Reseller,user:User,store:Store} */
    public function create(string $name, string $email, string $password, string $storeName): array
    {
        return DB::transaction(function () use ($name, $email, $password, $storeName) {
            $reseller = Reseller::query()->create(['name' => trim($name)]);
            $user = User::query()->create([
                'reseller_id' => $reseller->id,
                'name' => trim($name),
                'email' => strtolower(trim($email)),
                'password' => $password,
                'role' => 'reseller',
            ]);
            $store = Store::query()->create([
                'reseller_id' => $reseller->id,
                'name' => trim($storeName),
            ]);
            Wallet::query()->create([
                'reseller_id' => $reseller->id,
                'balance' => 0,
                'fractional_numerator' => 0,
            ]);
            return compact('reseller', 'user', 'store');
        }, 5);
    }
}
