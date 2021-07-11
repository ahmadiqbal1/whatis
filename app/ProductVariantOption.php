<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ProductVariantOption extends Model
{
    protected $fillable = [
        'location_id',
        'product_id',
        'name',
        'cost',
        'price',
        'quantity',
        'created_by',
    ];

    public function location()
    {
        return $this->hasOne('App\Location', 'id', 'location_id');
    }
}
