<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MasterSubscription extends Model
{
    use SoftDeletes;

    protected $primaryKey = 'master_subscription_id';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $guarded = [];
    protected $hidden = ['public_token_hash', 'public_token_encrypted'];

    protected function casts(): array
    {
        return [
            'public_token_encrypted' => 'encrypted',
            'token_revoked_at' => 'datetime',
            'quota_bytes' => 'integer',
            'used_bytes' => 'integer',
            'duration_seconds' => 'integer',
            'expires_at' => 'datetime',
            'manual_suspended' => 'boolean',
            'state_version' => 'integer',
            'last_state_change_at' => 'datetime',
        ];
    }

    public function reseller(): BelongsTo { return $this->belongsTo(Reseller::class)->withTrashed(); }
    public function store(): BelongsTo { return $this->belongsTo(Store::class)->withTrashed(); }
    public function mappings(): HasMany { return $this->hasMany(ProviderUserMapping::class, 'master_subscription_id', 'master_subscription_id'); }
    public function operations(): HasMany { return $this->hasMany(ProviderOperation::class, 'master_subscription_id', 'master_subscription_id'); }
}
