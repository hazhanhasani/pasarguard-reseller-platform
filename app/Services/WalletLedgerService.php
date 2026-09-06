<?php
namespace App\Services;

use App\Models\Wallet;
use App\Models\WalletLedger;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;
use OverflowException;

final class WalletLedgerService
{
    private const TYPES = ['credit','debit','usage_charge','manual_topup','online_payment','refund','adjustment'];

    public function __construct(private readonly SubscriptionStateService $states) {}

    public function credit(int $resellerId, int $amount, string $type, string $reference, ?string $description = null): WalletLedger
    {
        if ($amount <= 0) throw new InvalidArgumentException('Credit amount must be positive');
        return $this->apply($resellerId, $amount, $type, $reference, $description);
    }

    public function debit(int $resellerId, int $amount, string $type, string $reference, ?string $description = null): WalletLedger
    {
        if ($amount <= 0) throw new InvalidArgumentException('Debit amount must be positive');
        return $this->apply($resellerId, -$amount, $type, $reference, $description);
    }

    public function apply(int $resellerId, int $signedAmount, string $type, string $reference, ?string $description = null): WalletLedger
    {
        if ($signedAmount === 0) throw new InvalidArgumentException('Zero-value ledger entries are not allowed');
        if (!in_array($type, self::TYPES, true)) throw new InvalidArgumentException('Unsupported wallet transaction type');
        $reference = trim($reference);
        if ($reference === '' || strlen($reference) > 191) throw new InvalidArgumentException('Invalid wallet reference');

        $result = DB::transaction(function () use ($resellerId, $signedAmount, $type, $reference, $description) {
            $existing = WalletLedger::query()->where('reference', $reference)->lockForUpdate()->first();
            if ($existing) {
                if ((int) $existing->reseller_id !== $resellerId || (int) $existing->amount !== $signedAmount || $existing->type !== $type) {
                    throw new LogicException('Idempotency reference conflict');
                }
                return ['entry' => $existing, 'transition' => null];
            }

            $wallet = Wallet::query()->where('reseller_id', $resellerId)->lockForUpdate()->firstOrFail();
            $oldBalance = (int) $wallet->balance;
            $newBalance = $this->safeAdd($oldBalance, $signedAmount);
            $entry = WalletLedger::query()->create([
                'reseller_id' => $resellerId,
                'amount' => $signedAmount,
                'type' => $type,
                'reference' => $reference,
                'description' => $description,
                'balance_after' => $newBalance,
                'created_at' => now(),
            ]);
            $wallet->balance = $newBalance;
            $wallet->save();
            return ['entry' => $entry, 'transition' => [$oldBalance, $newBalance]];
        }, 5);

        if ($result['transition'] !== null) {
            [$oldBalance, $newBalance] = $result['transition'];
            $this->states->applyWalletTransition($resellerId, $oldBalance, $newBalance);
        }
        return $result['entry'];
    }

    private function safeAdd(int $a, int $b): int
    {
        if ($b > 0 && $a > PHP_INT_MAX - $b) throw new OverflowException('Wallet balance overflow');
        if ($b < 0 && $a < PHP_INT_MIN - $b) throw new OverflowException('Wallet balance underflow');
        return $a + $b;
    }
}
