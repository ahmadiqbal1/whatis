<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ProductTax extends Model
{
    protected $fillable = [
        'tax_name',
        'rate',
    ];
}
