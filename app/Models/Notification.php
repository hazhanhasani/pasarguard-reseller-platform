<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notification extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'occurrence_count' => 'integer',
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'read_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function reseller(): BelongsTo { return $this->belongsTo(Reseller::class); }
}
