<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'final_amount' => 'integer',
            'last_verified_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    public function reseller(): BelongsTo { return $this->belongsTo(Reseller::class); }
}
