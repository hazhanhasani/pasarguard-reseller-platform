<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
class Provider extends Model {
    use SoftDeletes;
    protected $fillable=['name','api_url','credentials','group_ids'];
    protected $hidden=['credentials'];
    protected function casts(): array {return ['credentials'=>'encrypted:array','group_ids'=>'array','capabilities'=>'array','last_successful_sync'=>'datetime','last_failed_sync'=>'datetime'];}
}
