<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class UserStore extends Model
{
    protected $fillable = [
        'user_id',
        'store_id',
        'permission',
        'is_active',
    ];
    public function user()
    {
        return $this->hasOne('App\User', 'id', 'user_id');
    }
}
