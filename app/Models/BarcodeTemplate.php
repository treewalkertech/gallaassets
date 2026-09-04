<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BarcodeTemplate extends Model
{
    protected $fillable = [
        'company_id',
        'created_by',
        'name',
        'template',
        'is_active',
    ];
}
