<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Store extends Model
{
    protected     $fillable     = [
        'name',
        'email',
        'domains',
        'about',
        'tagline',
        'slug',
        'lang',
        'currency',
        'currency_code',
        'whatsapp',
        'facebook',
        'instagram',
        'twitter',
        'footer_note',
        'address',
        'city',
        'state',
        'zipcode',
        'country',
        'logo',
        'is_stripe_enabled',
        'stripe_key',
        'stripe_secret',
        'is_paypal_enabled',
        'paypal_mode',
        'paypal_client_id',
        'paypal_secret_key',
        'invoice_template',
        'invoice_color',
        'invoice_footer_title',
        'invoice_footer_notes',
        'is_active',
        'created_by',
    ];

    public static function create($data)
    {
        $obj          = new Utility();
        $table        = with(new Store)->getTable();
        $data['slug'] = $obj->createSlug($table, $data['name']);
        $store        = static::query()->create($data);

        return $store;
    }

    public function currentLanguage()
    {
        return $this->lang;
    }

    public function store_user()
    {
        return $this->hasOne('App\UserStore', 'store_id', 'id');
    }

    public function category()
    {
        return $this->hasOne('App\ProductCategorie', 'id', 'id');
    }


}
