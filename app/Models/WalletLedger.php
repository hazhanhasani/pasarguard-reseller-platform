<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

class WalletLedger extends Model
{
    protected $table = 'wallet_ledger';
    public const UPDATED_AT = null;
    protected $guarded = [];

    protected function casts(): array
    {
        return ['amount' => 'integer', 'balance_after' => 'integer', 'created_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Wallet ledger is immutable'));
        static::deleting(fn () => throw new LogicException('Wallet ledger is immutable'));
    }
}
