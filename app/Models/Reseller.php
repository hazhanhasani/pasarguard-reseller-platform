<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Reseller extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    public function stores(): HasMany { return $this->hasMany(Store::class); }
    public function wallet(): HasOne { return $this->hasOne(Wallet::class); }
    public function subscriptions(): HasMany { return $this->hasMany(MasterSubscription::class); }
}
