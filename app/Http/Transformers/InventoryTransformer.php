<?php

namespace App\Http\Transformers;

use App\Models\Item;
use Illuminate\Database\Eloquent\Collection;

/**
 * Rows for the Inventory report (app/Http/Controllers/Api/InventoryController.php).
 * Each row is a fixed_asset Item plus counts derived from its Assets
 * (Item::assets(), populated by Grn::postReceipt()) -- not a stored,
 * separately-editable record, so unlike the other transformers in this app
 * there's no available_actions block here.
 */
class InventoryTransformer
{
    public function transformInventory(Collection $items, $total)
    {
        $array = [];
        foreach ($items as $item) {
            $array[] = self::transformRow($item);
        }

        return (new DatatablesTransformer)->transformDatatables($array, $total);
    }

    public function transformRow(Item $item): array
    {
        $totalReceived = (int) $item->total_received;
        $discarded = (int) $item->discarded_count;
        $available = $totalReceived - $discarded;

        return [
            'id' => (int) $item->id,
            'item_code' => e($item->item_code),
            'name' => e($item->name),
            'category' => $item->category ? ['id' => $item->category->id, 'name' => e($item->category->name)] : null,
            'manufacturer' => $item->manufacturer ? ['id' => $item->manufacturer->id, 'name' => e($item->manufacturer->name)] : null,
            'asset_model' => $item->assetModel ? ['id' => $item->assetModel->id, 'name' => e($item->assetModel->name)] : null,
            'total_received' => $totalReceived,
            'discarded_count' => $discarded,
            'available_count' => $available,
            'reorder_level' => $item->reorder_level,
            'low_stock' => $item->reorder_level !== null && $available <= (int) $item->reorder_level,
        ];
    }
}
