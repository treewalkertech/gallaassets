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
            // discount_amount/tax_amount are validated as nullable (an empty
            // Add Line Item field is a legitimate "no discount/tax"), but the
            // column itself is NOT NULL with a default of 0 -- that default
            // only applies when a column is omitted from the INSERT
            // entirely, not when it's explicitly bound as NULL. Laravel's
            // ConvertEmptyStringsToNull middleware turns a blank form field
            // into exactly that explicit null, which validation happily lets
            // through (nullable) but the database then rejects with a raw
            // "Column cannot be null" error instead of a friendly validation
            // message. Coercing here, before either validation or the
            // line_total math below, is what actually prevents that.
            $line->discount_amount = (float) ($line->discount_amount ?? 0);
            $line->tax_amount = (float) ($line->tax_amount ?? 0);

            $line->line_total = round(
                ((float) $line->qty_ordered * (float) $line->unit_cost)
                - $line->discount_amount
                + $line->tax_amount,
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
