<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Transformers\InventoryTransformer;
use App\Models\Item;
use Illuminate\Http\Request;

/**
 * Inventory report: for every fixed_asset Item, how many units a GRN has
 * ever received against it (Item::assets()->count()), how many of those are
 * now on a status marked Archived (Statuslabel::archived -- "discarded or
 * lost", per the decision behind this feature), and what's left available.
 *
 * Deliberately not a CRUD resource -- there's nothing here to create, edit,
 * or delete; it's a live view over Items + Assets, always correct because
 * it's computed on read rather than a counter that could drift out of sync.
 * Consumables/licenses are out of scope (they already track qty/seats
 * directly and have no individual units to lose) -- see the Inventory
 * feature's delivery notes in the project spec for the full reasoning.
 */
class InventoryController extends Controller
{
    public function index(Request $request): array
    {
        $this->authorize('view', Item::class);

        $allowed_columns = ['name', 'item_code', 'total_received', 'discarded_count', 'reorder_level'];

        $items = Item::query()
            ->where('item_type', Item::TYPE_FIXED_ASSET)
            ->with('category', 'manufacturer', 'assetModel')
            ->withCount([
                'assets as total_received',
                'assets as discarded_count' => function ($query) {
                    $query->whereHas('assetstatus', function ($statusQuery) {
                        $statusQuery->where('archived', 1);
                    });
                },
            ]);

        if ($request->filled('search')) {
            $items = $items->TextSearch($request->input('search'));
        }

        $offset = app('api_offset_value');
        $limit = app('api_limit_value');

        $order = $request->input('order') === 'asc' ? 'asc' : 'desc';
        $sort = in_array($request->input('sort'), $allowed_columns) ? $request->input('sort') : 'name';
        $items->orderBy($sort, $order);

        $total = $items->count();
        $items = $items->skip($offset)->take($limit)->get();

        return (new InventoryTransformer)->transformInventory($items, $total);
    }
}
