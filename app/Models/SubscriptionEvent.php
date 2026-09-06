<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubscriptionEvent extends Model
{
    public const UPDATED_AT = null;
    protected $guarded = [];
    protected $hidden = ['payload'];

    protected function casts(): array
    {
        return ['payload' => 'encrypted:array', 'created_at' => 'datetime'];
    }
}
