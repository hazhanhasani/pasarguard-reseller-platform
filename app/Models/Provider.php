<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Provider extends Model
{
    use SoftDeletes;

    protected $guarded = [];
    protected $hidden = ['credentials'];

    protected function casts(): array
    {
        return [
            'credentials' => 'encrypted:array',
            'group_ids' => 'array',
            'capabilities' => 'array',
            'latency_ms' => 'integer',
            'error_counter' => 'integer',
            'last_successful_sync' => 'datetime',
            'last_failed_sync' => 'datetime',
        ];
    }

    public function mappings(): HasMany { return $this->hasMany(ProviderUserMapping::class); }
    public function operations(): HasMany { return $this->hasMany(ProviderOperation::class); }
}
