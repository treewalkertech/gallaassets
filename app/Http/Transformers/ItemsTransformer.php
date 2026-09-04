<?php

namespace App\Http\Transformers;

use App\Helpers\Helper;
use App\Models\Item;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;

class ItemsTransformer
{
    public function transformItems(Collection $items, $total)
    {
        $array = [];
        foreach ($items as $item) {
            $array[] = self::transformItem($item);
        }

        return (new DatatablesTransformer)->transformDatatables($array, $total);
    }

    public function transformItem(Item $item = null)
    {
        if (!$item) {
            return [];
        }

        $array = [
            'id' => (int) $item->id,
            'item_code' => e($item->item_code),
            'name' => e($item->name),
            'description' => e($item->description),
            'item_type' => $item->item_type,
            'item_type_label' => $item->typeLabel(),
            'category' => $item->category ? ['id' => $item->category->id, 'name' => e($item->category->name)] : null,
            'manufacturer' => $item->manufacturer ? ['id' => $item->manufacturer->id, 'name' => e($item->manufacturer->name)] : null,
            'asset_model' => $item->assetModel ? ['id' => $item->assetModel->id, 'name' => e($item->assetModel->name)] : null,
            'consumable' => $item->consumable ? ['id' => $item->consumable->id, 'name' => e($item->consumable->name)] : null,
            'license' => $item->license ? ['id' => $item->license->id, 'name' => e($item->license->name)] : null,
            'default_unit_cost' => $item->default_unit_cost,
            'reorder_level' => $item->reorder_level,
            'is_active' => (bool) $item->is_active,
            'notes' => $item->notes ? Helper::parseEscapedMarkedownInline($item->notes) : null,
            'created_at' => Helper::getFormattedDateObject($item->created_at, 'datetime'),
            'updated_at' => Helper::getFormattedDateObject($item->updated_at, 'datetime'),
        ];

        $array['available_actions'] = [
            'update' => Gate::allows('update', $item),
            'delete' => Gate::allows('delete', $item),
        ];

        return $array;
    }
}
