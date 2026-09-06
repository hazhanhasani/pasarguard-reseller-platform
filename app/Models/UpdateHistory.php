<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UpdateHistory extends Model
{
    protected $table = 'update_history';
    public const UPDATED_AT = null;
    protected $guarded = [];
    protected $hidden = ['package_path'];

    protected function casts(): array
    {
        return [
            'manifest' => 'array',
            'created_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function backup(): BelongsTo { return $this->belongsTo(Backup::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
}
