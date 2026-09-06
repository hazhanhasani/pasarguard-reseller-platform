<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Wallet extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['balance' => 'integer', 'fractional_numerator' => 'integer'];
    }

    public function reseller(): BelongsTo { return $this->belongsTo(Reseller::class); }
    public function ledger(): HasMany { return $this->hasMany(WalletLedger::class, 'reseller_id', 'reseller_id'); }
}
