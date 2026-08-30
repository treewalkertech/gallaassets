<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Watson\Validating\ValidatingTrait;

class PurchaseOrderLine extends SnipeModel
{
    use HasFactory;
    use ValidatingTrait;

    protected $table = 'purchase_order_lines';

    protected $injectUniqueIdentifier = true;

    protected $rules = [
        'purchase_order_id' => 'required|integer|exists:purchase_orders,id',
        'item_id'           => 'required|integer|exists:items,id',
        'qty_ordered'       => 'required|integer|min:1',
        'unit_cost'         => 'required|numeric|min:0',
        'discount_amount'   => 'nullable|numeric|min:0',
        'tax_amount'        => 'nullable|numeric|min:0',
    ];

    protected $fillable = [
        'purchase_order_id',
        'item_id',
        'description',
        'qty_ordered',
        'unit_cost',
        'discount_amount',
        'tax_amount',
    ];

    protected $casts = [
        'qty_ordered'      => 'integer',
        'unit_cost'        => 'float',
        'discount_amount'  => 'float',
        'tax_amount'       => 'float',
        'line_total'       => 'float',
        'qty_received'     => 'integer',
    ];

    protected static function booted()
    {
        static::saving(function (PurchaseOrderLine $line) {
            $line->line_total = round(
                ((float) $line->qty_ordered * (float) $line->unit_cost)
                - (float) ($line->discount_amount ?? 0)
                + (float) ($line->tax_amount ?? 0),
                2
            );
        });
    }

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    public function isFullyReceived(): bool
    {
        return $this->qty_received >= $this->qty_ordered;
    }
}
