<?php
namespace App\Http\Controllers\Api;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Http\Transformers\PurchaseOrdersTransformer;
use App\Models\PurchaseOrder;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class PurchaseOrdersController extends Controller
{
    public function index(Request $request): JsonResponse | array
    {
        $this->authorize('view', PurchaseOrder::class);

        $pos = PurchaseOrder::with('vendor', 'requestedBy', 'owner', 'approver');

        // Filters (same pattern as licenses)
        if ($request->filled('status')) {
            $pos->where('status', $request->status);
        }

        if ($request->filled('vendor_id')) {
            $pos->where('supplier_id', $request->vendor_id);
        }

        if ($request->filled('custom_po_id')) {
            $pos->where('custom_po_id', $request->custom_po_id);
        }

        if ($request->filled('search')) {
            $pos->where('po_name', 'LIKE', '%'.$request->search.'%');
        }

        $offset = app('api_offset_value');
        $limit  = app('api_limit_value');

        $total = $pos->count();
        $pos   = $pos->skip($offset)->take($limit)->get();

        return (new PurchaseOrdersTransformer)
            ->transformPurchaseOrders($pos, $total);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', PurchaseOrder::class);

        $po = new PurchaseOrder();
        $po->fill($request->all());
        $po->created_by = auth()->id();
        $po->company_id = $po->company_id ?: auth()->user()->company_id;

        if (empty($po->custom_po_id)) {
            $po->custom_po_id = PurchaseOrder::generatePoNumber();
        }
        if (empty($po->status)) {
            $po->status = PurchaseOrder::STATUS_DRAFT;
        }

        if ($po->save()) {
            return response()->json(
                Helper::formatStandardApiResponse('success', $po, 'PO created')
            );
        }

        return response()->json(
            Helper::formatStandardApiResponse('error', null, $po->getErrors())
        );
    }

    public function show($id): JsonResponse | array
    {
        $po = PurchaseOrder::with('vendor', 'requestedBy', 'owner', 'approver', 'assets', 'lines.item')
            ->findOrFail($id);

        $this->authorize('view', $po);

        return (new PurchaseOrdersTransformer)
            ->transformPurchaseOrder($po);
    }

    public function update(Request $request, $id): JsonResponse | array
    {
        $po = PurchaseOrder::findOrFail($id);
        $this->authorize('update', $po);

        if (!$po->linesAreEditable() && $request->filled('status') && $request->input('status') !== $po->status) {
            return response()->json(
                Helper::formatStandardApiResponse('error', null, 'This PO is no longer in Draft, so its status can only be changed via approve/reject.')
            );
        }

        $po->fill($request->all());

        if ($po->save()) {
            return response()->json(
                Helper::formatStandardApiResponse('success', $po, 'PO updated')
            );
        }

        return response()->json(
            Helper::formatStandardApiResponse('error', null, $po->getErrors())
        );
    }

    public function destroy($id): JsonResponse
    {
        $po = PurchaseOrder::findOrFail($id);
        $this->authorize('delete', $po);

        $po->delete();

        return response()->json(
            Helper::formatStandardApiResponse('success', null, 'PO deleted')
        );
    }

    public function approve($id): JsonResponse
    {
        $po = PurchaseOrder::findOrFail($id);
        $this->authorize('approve', $po);

        if ($po->status !== PurchaseOrder::STATUS_PENDING_APPROVAL) {
            return response()->json(
                Helper::formatStandardApiResponse('error', null, 'Only a PO that is Pending Approval can be approved.')
            );
        }

        $po->status = PurchaseOrder::STATUS_APPROVED;
        $po->approval_status = 'approved';
        $po->approved_at = now();
        $po->approver_id = $po->approver_id ?: auth()->id();
        $po->save();

        return response()->json(
            Helper::formatStandardApiResponse('success', $po, 'PO approved')
        );
    }

    public function reject($id): JsonResponse
    {
        $po = PurchaseOrder::findOrFail($id);
        $this->authorize('reject', $po);

        if ($po->status !== PurchaseOrder::STATUS_PENDING_APPROVAL) {
            return response()->json(
                Helper::formatStandardApiResponse('error', null, 'Only a PO that is Pending Approval can be rejected.')
            );
        }

        $po->status = PurchaseOrder::STATUS_REJECTED;
        $po->approval_status = 'rejected';
        $po->approved_at = now();
        $po->approver_id = $po->approver_id ?: auth()->id();
        $po->save();

        return response()->json(
            Helper::formatStandardApiResponse('success', $po, 'PO rejected')
        );
    }
}
