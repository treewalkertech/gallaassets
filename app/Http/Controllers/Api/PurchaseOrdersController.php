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

        $pos = PurchaseOrder::with('vendor', 'requestedBy', 'owner');

        // Filters (same pattern as licenses)
        if ($request->filled('status')) {
            $pos->where('status_name', $request->status);
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
        $po = PurchaseOrder::with('vendor', 'requestedBy', 'owner', 'assets')
            ->findOrFail($id);

        $this->authorize('view', $po);

        return (new PurchaseOrdersTransformer)
            ->transformPurchaseOrder($po);
    }

    public function update(Request $request, $id): JsonResponse | array
    {
        $po = PurchaseOrder::findOrFail($id);
        $this->authorize('update', $po);

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
}
