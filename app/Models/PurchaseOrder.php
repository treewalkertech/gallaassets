<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseOrder extends Model
{
    use HasFactory;
    protected $fillable = [
        'external_po_id',
        'custom_po_id',
        'po_name',
        'supplier_id',
        'requested_by',
        'owner_id',
        'total_price',
        'base_total_price',
        'discount',
        'sales_tax',
        'additional_tax',
        'shipping_price',
        'status_name',
        'status_id',
        'created_date',
        'required_date',
        'currency_code',
    ];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function assets()
    {
        return $this->hasMany(Asset::class, 'order_number', 'external_po_id');
    }
}
