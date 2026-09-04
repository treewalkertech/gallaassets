<?php

namespace App\Http\Controllers\Grn;

use App\Http\Controllers\Controller;
use App\Models\Grn;
use App\Models\GrnLine;
use App\Models\PurchaseOrderLine;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Line items are managed inline from the GRN detail page (view.blade.php),
 * same pattern as PurchaseOrderLinesController in Phase 3 -- no separate
 * index/edit view here on purpose.
 */
class GrnLinesController extends Controller
{
    public function store(Request $request, $grn_id): RedirectResponse
    {
        $grn = Grn::findOrFail($grn_id);
        $this->authorize('update', $grn);

        if (!$grn->linesAreEditable()) {
            return back()->with('error', 'This GRN is no longer in Draft, so its line items can no longer be changed.');
        }

        $poLine = PurchaseOrderLine::where('purchase_order_id', $grn->purchase_order_id)
            ->find($request->input('purchase_order_line_id'));

        if (!$poLine) {
            return back()->withInput()->with('error', 'That line item does not belong to this GRN\'s Purchase Order.');
        }

        $remaining = $poLine->qty_ordered - $poLine->qty_received;
        $qty = (int) $request->input('qty_received');

        if ($qty > $remaining) {
            return back()->withInput()->with('error', "Only {$remaining} unit(s) remain to be received on that PO line (not counting any other Draft GRNs also open against this PO).");
        }

        $serials = collect(preg_split('/[\r\n,]+/', (string) $request->input('serials')))
            ->map(fn ($s) => trim($s))
            ->filter()
            ->values()
            ->all();

        $line = new GrnLine();
        $line->grn_id = $grn->id;
        $line->purchase_order_line_id = $poLine->id;
        $line->item_id = $poLine->item_id;
        $line->qty_received = $qty;
        $line->unit_cost = $request->filled('unit_cost') ? $request->input('unit_cost') : null;
        $line->serials = $serials ?: null;
        $line->notes = $request->input('notes');

        if ($line->save()) {
            return back()->with('success', 'Line item added.');
        }

        return back()->withInput()->withErrors($line->getErrors());
    }

    public function destroy($grn_id, $line_id): RedirectResponse
    {
        $grn = Grn::findOrFail($grn_id);
        $this->authorize('update', $grn);

        if (!$grn->linesAreEditable()) {
            return back()->with('error', 'This GRN is no longer in Draft, so its line items can no longer be changed.');
        }

        $line = GrnLine::where('grn_id', $grn->id)->findOrFail($line_id);
        $line->delete();

        return back()->with('success', 'Line item removed.');
    }
}
