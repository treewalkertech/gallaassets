<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Watson\Validating\ValidatingTrait;

class PurchaseOrder extends SnipeModel
{
    use HasFactory;
    use ValidatingTrait;

    protected $table = 'purchase_orders';

    /**
     * Whether the model should inject its identifier into the unique
     * validation rules before attempting validation.
     *
     * @var bool
     */
    protected $injectUniqueIdentifier = true;

    protected $casts = [
        'created_date'  => 'datetime',
        'required_date' => 'datetime',
        'total_price'   => 'float',
    ];

    protected $rules = [
        'po_name'     => 'nullable|string|max:255',
        'supplier_id' => 'nullable|integer|exists:suppliers,id',
        'requested_by' => 'nullable|integer|exists:users,id',
        'owner_id'    => 'nullable|integer|exists:users,id',
        'total_price' => 'nullable|numeric',
        'currency_code' => 'nullable|string|max:10',
    ];

    protected $fillable = [
        'external_po_id',
        'custom_po_id',
        'po_name',
        'supplier_id',
        'requested_by',
        'owner_id',
        'created_by',
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

    /**
     * The supplier (vendor) this PO was raised against.
     *
     * Named `vendor()` (rather than `supplier()`) because the API transformer
     * and both PO controllers already call `->vendor` / `with('vendor')` --
     * this is the fix, not a rename of those call sites.
     */
    public function vendor()
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    /**
     * The user who requested this PO.
     *
     * Named `requestedBy()` to match existing controller/transformer calls
     * (see note on vendor() above).
     */
    public function requestedBy()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assets()
    {
        return $this->hasMany(
            Asset::class,
            'purchase_order_id'
        );
    }
}
