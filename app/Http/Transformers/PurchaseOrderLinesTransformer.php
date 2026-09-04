<?php

namespace App\Http\Transformers;

use App\Models\PurchaseOrderLine;
use Illuminate\Support\Collection;

class PurchaseOrderLinesTransformer
{
    public function transformLines(Collection $lines, $total)
    {
        $array = [];
        foreach ($lines as $line) {
            $array[] = self::transformLine($line);
        }

        return (new DatatablesTransformer)->transformDatatables($array, $total);
    }

    public function transformLine(PurchaseOrderLine $line = null)
    {
        if (!$line) {
            return [];
        }

        return [
            'id' => (int) $line->id,
            'purchase_order_id' => (int) $line->purchase_order_id,
            'item' => $line->item ? ['id' => $line->item->id, 'name' => e($line->item->name), 'item_code' => e($line->item->item_code), 'item_type' => $line->item->item_type] : null,
            'description' => e($line->description),
            'qty_ordered' => (int) $line->qty_ordered,
            'qty_received' => (int) $line->qty_received,
            'unit_cost' => $line->unit_cost,
            'discount_amount' => $line->discount_amount,
            'tax_amount' => $line->tax_amount,
            'line_total' => $line->line_total,
        ];
    }
}
