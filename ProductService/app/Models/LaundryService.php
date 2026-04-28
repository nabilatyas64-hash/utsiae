<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LaundryService extends Model
{
    protected $fillable = [
        'name',
        'type',
        'description',
        'price',
        'unit',
        'estimated_duration',
        'is_available',
    ];
}
