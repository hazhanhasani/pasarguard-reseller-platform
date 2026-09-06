<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UsageSnapshot extends Model
{
    public const UPDATED_AT = null;
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'previous_bytes' => 'integer',
            'observed_bytes' => 'integer',
            'delta_bytes' => 'integer',
            'observed_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function mapping(): BelongsTo { return $this->belongsTo(ProviderUserMapping::class, 'mapping_id'); }
    public function subscription(): BelongsTo { return $this->belongsTo(MasterSubscription::class, 'master_subscription_id', 'master_subscription_id'); }
    public function provider(): BelongsTo { return $this->belongsTo(Provider::class); }
}
