<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ProductCoupon extends Model
{
    protected $fillable = [
        'name',
        'code',
        'discount',
        'limit',
        'description',
        'store_id',
        'created_by',
    ];

    public function product_coupon()
    {
        return $this->hasMany('App\Order', 'coupon', 'id')->count();
    }
}
