<?php
namespace App\Http\Controllers\PurchaseOrders;

use App\Http\Controllers\Controller;
use App\Models\PurchaseOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PurchaseOrdersController extends Controller
{
    public function index()
    {
        $this->authorize('view', PurchaseOrder::class);
        return view('purchase_orders/index');
    }

    public function create()
    {
        $this->authorize('create', PurchaseOrder::class);
        return view('purchase_orders/edit')
            ->with('item', new PurchaseOrder);
    }

    public function store(Request $request)
    {
        $this->authorize('create', PurchaseOrder::class);

        $po = new PurchaseOrder();
        $po->fill($request->all());
        $po->created_by = auth()->id();
        $po->company_id = auth()->user()->company_id;

        if (empty($po->custom_po_id)) {
            $po->custom_po_id = PurchaseOrder::generatePoNumber();
        }
        if (empty($po->status)) {
            $po->status = PurchaseOrder::STATUS_DRAFT;
        }

        if ($po->save()) {
            return redirect()
                ->route('purchase-orders.show', ['purchase_order' => $po->id])
                ->with('success', 'Purchase Order created successfully. Add line items below.');
        }

        return back()->withInput()->withErrors($po->getErrors());
    }

    public function edit($id)
    {
        $item = PurchaseOrder::findOrFail($id);
        $this->authorize('update', $item);

        // Note: the shared edit.blade.php view (used for both create and edit)
        // expects the model in an `$item` variable, matching create() above and
        // the convention used by every other module's edit form (suppliers,
        // licenses, etc). Previously this passed `$po`, which the view never
        // received, since edit.blade.php never referenced that name.
        return view('purchase_orders/edit', compact('item'));
    }

    public function update(Request $request, $id)
    {
        $po = PurchaseOrder::findOrFail($id);
        $this->authorize('update', $po);

        if (!$po->linesAreEditable() && $request->filled('status') && $request->input('status') !== $po->status) {
            // Once a PO has left draft, header edits are still fine, but
            // status is only allowed to move through approve()/reject() (or
            // GRN posting, in Phase 4) from here on.
            return back()->withInput()->withErrors(['status' => 'This PO is no longer in Draft, so its status can only be changed via Approve/Reject.']);
        }

        $po->fill($request->all());

        if ($po->save()) {
            return redirect()
                ->route('purchase-orders.show', ['purchase_order' => $po->id])
                ->with('success', 'Purchase Order updated successfully');
        }

        return back()->withInput()->withErrors($po->getErrors());
    }

    public function show($id)
    {
        $po = PurchaseOrder::with('vendor', 'requestedBy', 'owner', 'approver', 'assets', 'lines.item')
            ->findOrFail($id);

        $this->authorize('view', $po);
        return view('purchase_orders/view', compact('po'));
    }

    /**
     * Move a Draft PO to Pending Approval. This was previously only reachable
     * by opening the Edit form and changing the Status dropdown (which still
     * works) -- this is a one-click shortcut from the PO's own page, since
     * "how do I get this to my approver" was reported as hard to find.
     */
    public function submit($id): RedirectResponse
    {
        $po = PurchaseOrder::findOrFail($id);
        $this->authorize('update', $po);

        if ($po->status !== PurchaseOrder::STATUS_DRAFT && $po->status !== null) {
            return back()->with('error', 'Only a Draft PO can be submitted for approval.');
        }

        if (!$po->lines()->exists()) {
            return back()->with('error', 'Add at least one line item before submitting this PO for approval.');
        }

        $po->status = PurchaseOrder::STATUS_PENDING_APPROVAL;
        $po->save();

        return back()->with('success', 'Purchase Order submitted for approval.');
    }

    /**
     * Approve this PO. Only reachable while it's pending_approval, and only
     * by its designated approver (or an admin) -- see PurchaseOrderPolicy.
     */
    public function approve($id): RedirectResponse
    {
        $po = PurchaseOrder::findOrFail($id);
        $this->authorize('approve', $po);

        if ($po->status !== PurchaseOrder::STATUS_PENDING_APPROVAL) {
            return back()->with('error', 'Only a PO that is Pending Approval can be approved.');
        }

        $po->status = PurchaseOrder::STATUS_APPROVED;
        $po->approval_status = 'approved';
        $po->approved_at = now();
        $po->approver_id = $po->approver_id ?: auth()->id();
        $po->save();

        return back()->with('success', 'Purchase Order approved.');
    }

    /**
     * Reject this PO. Same reachability rule as approve().
     */
    public function reject($id): RedirectResponse
    {
        $po = PurchaseOrder::findOrFail($id);
        $this->authorize('reject', $po);

        if ($po->status !== PurchaseOrder::STATUS_PENDING_APPROVAL) {
            return back()->with('error', 'Only a PO that is Pending Approval can be rejected.');
        }

        $po->status = PurchaseOrder::STATUS_REJECTED;
        $po->approval_status = 'rejected';
        $po->approved_at = now();
        $po->approver_id = $po->approver_id ?: auth()->id();
        $po->save();

        return back()->with('success', 'Purchase Order rejected.');
    }
}
