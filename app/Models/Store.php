<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Store extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    public function reseller(): BelongsTo { return $this->belongsTo(Reseller::class); }
    public function subscriptions(): HasMany { return $this->hasMany(MasterSubscription::class); }
}
