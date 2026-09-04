<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ServiceDeskConfig extends Model
{
    use HasFactory;

    protected $guarded = [];
    protected $table = 'service_desk_configs';


     protected $fillable = [
        'company_id',
        'created_by',
        'mode',
        'portal_id',
        'base_url',
        'api_version',
        'technician_key',
        'verify_ssl',
        'timeout',
        'is_active',
        'is_sync_enabled',
    ];

     // 🔗 Relations
    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
