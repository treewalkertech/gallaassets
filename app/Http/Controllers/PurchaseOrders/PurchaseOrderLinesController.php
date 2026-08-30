<?php

namespace App\Http\Controllers\PurchaseOrders;

use App\Http\Controllers\Controller;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Line items are managed inline from the PO detail page (view.blade.php),
 * not a separate CRUD screen -- there's no index/edit view here on purpose.
 */
class PurchaseOrderLinesController extends Controller
{
    public function store(Request $request, $purchase_order_id): RedirectResponse
    {
        $po = PurchaseOrder::findOrFail($purchase_order_id);
        $this->authorize('update', $po);

        if (!$po->linesAreEditable()) {
            return back()->with('error', 'This PO is no longer in Draft, so its line items can no longer be changed.');
        }

        $line = new PurchaseOrderLine();
        $line->purchase_order_id = $po->id;
        $line->fill($request->all());

        if ($line->save()) {
            $po->recalculateTotals();
            return back()->with('success', 'Line item added.');
        }

        return back()->withInput()->withErrors($line->getErrors());
    }

    public function destroy($purchase_order_id, $line_id): RedirectResponse
    {
        $po = PurchaseOrder::findOrFail($purchase_order_id);
        $this->authorize('update', $po);

        if (!$po->linesAreEditable()) {
            return back()->with('error', 'This PO is no longer in Draft, so its line items can no longer be changed.');
        }

        $line = PurchaseOrderLine::where('purchase_order_id', $po->id)->findOrFail($line_id);
        $line->delete();

        $po->recalculateTotals();

        return back()->with('success', 'Line item removed.');
    }
}
