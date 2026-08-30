<?php

namespace App\Http\Controllers\Grn;

use App\Http\Controllers\Controller;
use App\Models\Grn;
use App\Models\PurchaseOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class GrnController extends Controller
{
    public function index()
    {
        $this->authorize('view', Grn::class);
        return view('grn/index');
    }

    /**
     * Start a new GRN against a specific, already-approved PO. There's no
     * separate "edit" screen -- header fields are set once here, and line
     * items are added/removed from show() while the GRN is still Draft
     * (same pattern as PO line items in Phase 3).
     */
    public function create($purchase_order_id)
    {
        $po = PurchaseOrder::findOrFail($purchase_order_id);
        $this->authorize('create', Grn::class);

        if (!$po->canReceiveGrn()) {
            return redirect()
                ->route('purchase-orders.show', ['purchase_order' => $po->id])
                ->with('error', 'This Purchase Order is not in a state that can be received against -- it must be Approved (or Partially Received) first.');
        }

        return view('grn/create', ['po' => $po, 'item' => new Grn]);
    }

    public function store(Request $request, $purchase_order_id): RedirectResponse
    {
        $po = PurchaseOrder::findOrFail($purchase_order_id);
        $this->authorize('create', Grn::class);

        if (!$po->canReceiveGrn()) {
            return redirect()
                ->route('purchase-orders.show', ['purchase_order' => $po->id])
                ->with('error', 'This Purchase Order is not in a state that can be received against.');
        }

        $grn = new Grn();
        $grn->fill($request->all());
        $grn->purchase_order_id = $po->id;
        $grn->company_id = $po->company_id;
        $grn->received_by = auth()->id();
        $grn->status = Grn::STATUS_DRAFT;
        $grn->grn_number = Grn::generateGrnNumber();

        if ($grn->save()) {
            return redirect()
                ->route('grn.show', ['grn_id' => $grn->id])
                ->with('success', 'Goods Receipt Note created. Add line items below, then Post to receive.');
        }

        return back()->withInput()->withErrors($grn->getErrors());
    }

    public function show($grn_id)
    {
        $grn = Grn::with('purchaseOrder.vendor', 'location', 'defaultStatus', 'receivedBy', 'lines.item', 'lines.purchaseOrderLine', 'assets')
            ->findOrFail($grn_id);

        $this->authorize('view', $grn);

        // Open PO lines (still have qty left to receive), for the add-line
        // picker. Ignores what other still-Draft GRNs against this same PO
        // might already be planning to receive -- see the Phase 4 delivery
        // notes for why that's a disclosed limitation, not a bug.
        $openLines = $grn->purchaseOrder->lines()
            ->with('item')
            ->get()
            ->filter(fn ($line) => $line->qty_received < $line->qty_ordered)
            ->values();

        return view('grn/view', compact('grn', 'openLines'));
    }

    public function post($grn_id): RedirectResponse
    {
        $grn = Grn::findOrFail($grn_id);
        $this->authorize('post', $grn);

        try {
            $grn->postReceipt();
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('grn.show', ['grn_id' => $grn->id])
            ->with('success', 'Goods Receipt Note posted. Assets/quantities have been created.');
    }

    public function cancel($grn_id): RedirectResponse
    {
        $grn = Grn::findOrFail($grn_id);
        $this->authorize('update', $grn);

        try {
            $grn->cancel();
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('grn.show', ['grn_id' => $grn->id])
            ->with('success', 'Goods Receipt Note cancelled.');
    }
}
