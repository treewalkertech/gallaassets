<?php

namespace App\Http\Transformers;

use App\Models\GrnLine;
use Illuminate\Support\Collection;

class GrnLinesTransformer
{
    public function transformLines(Collection $lines, $total)
    {
        $array = [];
        foreach ($lines as $line) {
            $array[] = self::transformLine($line);
        }

        return (new DatatablesTransformer)->transformDatatables($array, $total);
    }

    public function transformLine(GrnLine $line = null)
    {
        if (!$line) {
            return [];
        }

        return [
            'id' => (int) $line->id,
            'grn_id' => (int) $line->grn_id,
            'purchase_order_line_id' => (int) $line->purchase_order_line_id,
            'item' => $line->item ? ['id' => $line->item->id, 'name' => e($line->item->name), 'item_code' => e($line->item->item_code), 'item_type' => $line->item->item_type] : null,
            'qty_received' => (int) $line->qty_received,
            'unit_cost' => $line->unit_cost,
            'serials' => $line->serials,
            'notes' => e($line->notes),
        ];
    }
}
