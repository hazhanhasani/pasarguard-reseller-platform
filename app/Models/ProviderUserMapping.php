<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProviderUserMapping extends Model
{
    protected $guarded = [];
    protected $hidden = ['provider_subscription_token', 'last_valid_output'];

    protected function casts(): array
    {
        return [
            'provider_subscription_token' => 'encrypted',
            'last_valid_output' => 'encrypted:array',
            'last_usage_bytes' => 'integer',
            'last_valid_output_at' => 'datetime',
            'last_success_at' => 'datetime',
            'last_error_at' => 'datetime',
            'retry_count' => 'integer',
            'last_reconciled_at' => 'datetime',
        ];
    }

    public function subscription(): BelongsTo { return $this->belongsTo(MasterSubscription::class, 'master_subscription_id', 'master_subscription_id'); }
    public function provider(): BelongsTo { return $this->belongsTo(Provider::class); }
    public function snapshots(): HasMany { return $this->hasMany(UsageSnapshot::class, 'mapping_id'); }
}
