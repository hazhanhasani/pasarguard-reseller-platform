<?php
namespace App\Models;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\SoftDeletes;
class User extends Authenticatable {
    use SoftDeletes;
    protected $fillable=['name','email','password'];
    protected $hidden=['password','remember_token'];
    protected function casts(): array { return ['password'=>'hashed']; }
}
