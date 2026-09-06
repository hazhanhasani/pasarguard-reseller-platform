<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProviderOperation extends Model
{
    protected $guarded = [];
    protected $hidden = ['payload'];

    protected function casts(): array
    {
        return [
            'payload' => 'encrypted:array',
            'desired_version' => 'integer',
            'attempts' => 'integer',
            'available_at' => 'datetime',
            'last_attempt_at' => 'datetime',
        ];
    }

    public function subscription(): BelongsTo { return $this->belongsTo(MasterSubscription::class, 'master_subscription_id', 'master_subscription_id'); }
    public function provider(): BelongsTo { return $this->belongsTo(Provider::class); }
}
