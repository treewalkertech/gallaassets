<?php

namespace App\Http\Controllers\Items;

use App\Http\Controllers\Controller;
use App\Models\Item;

/**
 * A single read-only report page over the Item Master + the Assets each
 * fixed_asset Item's GRNs have created (Item::assets(), populated by
 * Grn::postReceipt()). Nothing here is created/edited/deleted, so this is
 * one index() action rather than a Route::resource -- the actual numbers
 * are fetched client-side from Api\InventoryController via bootstrap-table,
 * same as every other list screen in this app.
 */
class InventoryController extends Controller
{
    public function index()
    {
        $this->authorize('view', Item::class);

        return view('inventory/index');
    }
}
