<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Watson\Validating\ValidatingTrait;

class GrnLine extends SnipeModel
{
    use HasFactory;
    use ValidatingTrait;

    protected $table = 'grn_lines';

    protected $injectUniqueIdentifier = true;

    protected $casts = [
        'qty_received' => 'integer',
        'unit_cost'    => 'float',
        'serials'      => 'array',
    ];

    protected $rules = [
        'grn_id'                  => 'required|integer|exists:grn,id',
        'purchase_order_line_id'  => 'required|integer|exists:purchase_order_lines,id',
        'item_id'                 => 'required|integer|exists:items,id',
        'qty_received'            => 'required|integer|min:1',
        'unit_cost'               => 'nullable|numeric|min:0',
    ];

    protected $fillable = [
        'grn_id',
        'purchase_order_line_id',
        'item_id',
        'qty_received',
        'unit_cost',
        'serials',
        'notes',
    ];

    public function grn()
    {
        return $this->belongsTo(Grn::class);
    }

    public function purchaseOrderLine()
    {
        return $this->belongsTo(PurchaseOrderLine::class);
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }
}
