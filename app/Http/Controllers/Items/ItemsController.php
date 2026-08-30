<?php

namespace App\Http\Controllers\Items;

use App\Http\Controllers\Controller;
use App\Models\Item;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * This controller handles all actions related to the Item Master.
 */
class ItemsController extends Controller
{
    public function index(): View
    {
        $this->authorize('view', Item::class);
        return view('items/index');
    }

    public function create(): View
    {
        $this->authorize('create', Item::class);
        return view('items/edit')->with('item', new Item);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Item::class);

        $item = new Item;
        $item->fill($request->all());
        $item->created_by = auth()->id();

        if ($item->save()) {
            return redirect()->route('items.index')->with('success', 'Item created successfully.');
        }

        return redirect()->back()->withInput()->withErrors($item->getErrors());
    }

    public function edit($itemId = null): View|RedirectResponse
    {
        if (is_null($item = Item::find($itemId))) {
            return redirect()->route('items.index')->with('error', 'Item does not exist.');
        }

        $this->authorize('update', $item);

        return view('items/edit', compact('item'));
    }

    public function update(Request $request, $itemId): RedirectResponse
    {
        if (is_null($item = Item::find($itemId))) {
            return redirect()->route('items.index')->with('error', 'Item does not exist.');
        }

        $this->authorize('update', $item);

        $item->fill($request->all());

        if ($item->save()) {
            return redirect()->route('items.index')->with('success', 'Item updated successfully.');
        }

        return redirect()->back()->withInput()->withErrors($item->getErrors());
    }

    public function destroy($itemId): RedirectResponse
    {
        if (is_null($item = Item::withCount('purchaseOrderLines')->find($itemId))) {
            return redirect()->route('items.index')->with('error', 'Item does not exist.');
        }

        $this->authorize('delete', $item);

        if ($item->purchase_order_lines_count > 0) {
            return redirect()->route('items.index')->with('error', 'This item is referenced on '.$item->purchase_order_lines_count.' purchase order line(s) and cannot be deleted.');
        }

        $item->delete();

        return redirect()->route('items.index')->with('success', 'Item deleted successfully.');
    }

    public function show($itemId = null): View|RedirectResponse
    {
        $item = Item::find($itemId);

        if (isset($item->id)) {
            $this->authorize('view', $item);
            return view('items/view', compact('item'));
        }

        return redirect()->route('items.index')->with('error', 'Item does not exist.');
    }
}
