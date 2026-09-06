<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PriceRate extends Model
{
    protected $table = 'price_history';
    public const UPDATED_AT = null;
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'price_minor_per_gb' => 'integer',
            'effective_from' => 'datetime',
            'effective_to' => 'datetime',
            'created_at' => 'datetime',
        ];
    }
}
