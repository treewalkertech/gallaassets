<?php

namespace App\Http\Controllers\Api;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Http\Transformers\GrnTransformer;
use App\Models\Grn;
use App\Models\PurchaseOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GrnController extends Controller
{
    public function index(Request $request): JsonResponse|array
    {
        $this->authorize('view', Grn::class);

        $grns = Grn::with('purchaseOrder', 'location', 'receivedBy');

        if ($request->filled('purchase_order_id')) {
            $grns->where('purchase_order_id', $request->purchase_order_id);
        }

        if ($request->filled('status')) {
            $grns->where('status', $request->status);
        }

        $offset = app('api_offset_value');
        $limit  = app('api_limit_value');

        $total = $grns->count();
        $grns  = $grns->skip($offset)->take($limit)->get();

        return (new GrnTransformer)->transformGrns($grns, $total);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Grn::class);

        $po = PurchaseOrder::findOrFail($request->input('purchase_order_id'));

        if (!$po->canReceiveGrn()) {
            return response()->json(
                Helper::formatStandardApiResponse('error', null, 'This Purchase Order is not in a state that can be received against -- it must be Approved (or Partially Received) first.')
            );
        }

        $grn = new Grn();
        $grn->fill($request->all());
        $grn->purchase_order_id = $po->id;
        $grn->company_id = $po->company_id;
        $grn->received_by = auth()->id();
        $grn->status = Grn::STATUS_DRAFT;
        $grn->grn_number = Grn::generateGrnNumber();

        if ($grn->save()) {
            return response()->json(
                Helper::formatStandardApiResponse('success', $grn, 'GRN created')
            );
        }

        return response()->json(
            Helper::formatStandardApiResponse('error', null, $grn->getErrors())
        );
    }

    public function show($id): JsonResponse|array
    {
        $grn = Grn::with('purchaseOrder', 'location', 'defaultStatus', 'receivedBy', 'lines.item', 'assets')
            ->findOrFail($id);

        $this->authorize('view', $grn);

        return (new GrnTransformer)->transformGrn($grn);
    }

    public function post($id): JsonResponse
    {
        $grn = Grn::findOrFail($id);
        $this->authorize('post', $grn);

        try {
            $grn->postReceipt();
        } catch (\RuntimeException $e) {
            return response()->json(Helper::formatStandardApiResponse('error', null, $e->getMessage()));
        }

        return response()->json(
            Helper::formatStandardApiResponse('success', $grn, 'GRN posted')
        );
    }

    public function cancel($id): JsonResponse
    {
        $grn = Grn::findOrFail($id);
        $this->authorize('update', $grn);

        try {
            $grn->cancel();
        } catch (\RuntimeException $e) {
            return response()->json(Helper::formatStandardApiResponse('error', null, $e->getMessage()));
        }

        return response()->json(
            Helper::formatStandardApiResponse('success', $grn, 'GRN cancelled')
        );
    }
}
