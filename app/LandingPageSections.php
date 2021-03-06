<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class LandingPageSections extends Model
{
    protected $fillable = [
        'id',
        'section_name',
        'section_order',
        'content',
        'section_type',
        'default_content',
        'section_demo_image',
        'section_blade_file_name',
    ];
}
