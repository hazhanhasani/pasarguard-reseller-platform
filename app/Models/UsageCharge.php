<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UsageCharge extends Model
{
    public const UPDATED_AT = null;
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'usage_bytes' => 'integer',
            'rate_minor_per_gb' => 'integer',
            'charged_minor' => 'integer',
            'fractional_remainder_before' => 'integer',
            'fractional_remainder_after' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function snapshot(): BelongsTo { return $this->belongsTo(UsageSnapshot::class, 'usage_snapshot_id'); }
    public function subscription(): BelongsTo { return $this->belongsTo(MasterSubscription::class, 'master_subscription_id', 'master_subscription_id'); }
    public function reseller(): BelongsTo { return $this->belongsTo(Reseller::class); }
}
