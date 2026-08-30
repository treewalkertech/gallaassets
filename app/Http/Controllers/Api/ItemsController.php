<?php

namespace App\Http\Controllers\Api;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Http\Transformers\ItemsTransformer;
use App\Http\Transformers\SelectlistTransformer;
use App\Models\Item;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ItemsController extends Controller
{
    /**
     * Display a listing of items (the Item Master catalog).
     */
    public function index(Request $request): array
    {
        $this->authorize('view', Item::class);

        $allowed_columns = ['id', 'item_code', 'name', 'item_type', 'default_unit_cost', 'reorder_level', 'is_active', 'created_at'];

        $items = Item::with('category', 'manufacturer', 'assetModel', 'consumable', 'license', 'creator');

        if ($request->filled('search')) {
            $items = $items->TextSearch($request->input('search'));
        }

        if ($request->filled('item_type')) {
            $items->where('item_type', '=', $request->input('item_type'));
        }

        if ($request->filled('category_id')) {
            $items->where('category_id', '=', $request->input('category_id'));
        }

        if ($request->filled('manufacturer_id')) {
            $items->where('manufacturer_id', '=', $request->input('manufacturer_id'));
        }

        if ($request->filled('is_active')) {
            $items->where('is_active', '=', $request->boolean('is_active'));
        }

        $offset = app('api_offset_value');
        $limit = app('api_limit_value');

        $order = $request->input('order') === 'asc' ? 'asc' : 'desc';
        $sort = in_array($request->input('sort'), $allowed_columns) ? $request->input('sort') : 'created_at';
        $items->orderBy($sort, $order);

        $total = $items->count();
        $items = $items->skip($offset)->take($limit)->get();

        return (new ItemsTransformer)->transformItems($items, $total);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Item::class);

        $item = new Item();
        $item->fill($request->all());
        $item->created_by = auth()->id();

        if ($item->save()) {
            return response()->json(Helper::formatStandardApiResponse('success', $item, 'Item created successfully.'));
        }

        return response()->json(Helper::formatStandardApiResponse('error', null, $item->getErrors()));
    }

    public function show($id): array
    {
        $item = Item::with('category', 'manufacturer', 'assetModel', 'consumable', 'license', 'creator')
            ->findOrFail($id);

        $this->authorize('view', $item);

        return (new ItemsTransformer)->transformItem($item);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $item = Item::findOrFail($id);
        $this->authorize('update', $item);

        $item->fill($request->all());

        if ($item->save()) {
            return response()->json(Helper::formatStandardApiResponse('success', $item, 'Item updated successfully.'));
        }

        return response()->json(Helper::formatStandardApiResponse('error', null, $item->getErrors()));
    }

    public function destroy($id): JsonResponse
    {
        $item = Item::findOrFail($id);
        $this->authorize('delete', $item);

        // Once purchase_order_lines exists (Phase 3), block deletion of an
        // item that's already been ordered, the same way Suppliers blocks
        // deletion while assets/licenses still reference it.
        $item->delete();

        return response()->json(Helper::formatStandardApiResponse('success', null, 'Item deleted successfully.'));
    }

    /**
     * Paginated collection for the select2 menus (used by the PO line-item
     * picker once purchase_order_lines exists).
     */
    public function selectlist(Request $request): array
    {
        $this->authorize('view.selectlists');

        $items = Item::select(['id', 'name', 'item_code']);

        if ($request->filled('search')) {
            $items = $items->where('name', 'LIKE', '%'.$request->get('search').'%');
        }

        $items = $items->orderBy('name', 'ASC')->paginate(50);

        foreach ($items as $item) {
            $item->use_text = $item->item_code ? "{$item->name} ({$item->item_code})" : $item->name;
            $item->use_image = null;
        }

        return (new SelectlistTransformer)->transformSelectlist($items);
    }
}
