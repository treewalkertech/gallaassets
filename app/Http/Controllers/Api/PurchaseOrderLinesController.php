<?php

namespace App\Http\Controllers\Api;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Http\Transformers\PurchaseOrderLinesTransformer;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PurchaseOrderLinesController extends Controller
{
    public function index($purchase_order_id): array
    {
        $po = PurchaseOrder::findOrFail($purchase_order_id);
        $this->authorize('view', $po);

        $lines = $po->lines()->with('item')->get();

        return (new PurchaseOrderLinesTransformer)->transformLines($lines, $lines->count());
    }

    public function store(Request $request, $purchase_order_id): JsonResponse
    {
        $po = PurchaseOrder::findOrFail($purchase_order_id);
        $this->authorize('update', $po);

        if (!$po->linesAreEditable()) {
            return response()->json(
                Helper::formatStandardApiResponse('error', null, 'This PO is no longer in Draft, so its line items can no longer be changed.')
            );
        }

        $line = new PurchaseOrderLine();
        $line->purchase_order_id = $po->id;
        $line->fill($request->all());

        if ($line->save()) {
            $po->recalculateTotals();
            return response()->json(
                Helper::formatStandardApiResponse('success', $line, 'Line item added')
            );
        }

        return response()->json(
            Helper::formatStandardApiResponse('error', null, $line->getErrors())
        );
    }

    public function update(Request $request, $purchase_order_id, $line_id): JsonResponse
    {
        $po = PurchaseOrder::findOrFail($purchase_order_id);
        $this->authorize('update', $po);

        if (!$po->linesAreEditable()) {
            return response()->json(
                Helper::formatStandardApiResponse('error', null, 'This PO is no longer in Draft, so its line items can no longer be changed.')
            );
        }

        $line = PurchaseOrderLine::where('purchase_order_id', $po->id)->findOrFail($line_id);
        $line->fill($request->all());

        if ($line->save()) {
            $po->recalculateTotals();
            return response()->json(
                Helper::formatStandardApiResponse('success', $line, 'Line item updated')
            );
        }

        return response()->json(
            Helper::formatStandardApiResponse('error', null, $line->getErrors())
        );
    }

    public function destroy($purchase_order_id, $line_id): JsonResponse
    {
        $po = PurchaseOrder::findOrFail($purchase_order_id);
        $this->authorize('update', $po);

        if (!$po->linesAreEditable()) {
            return response()->json(
                Helper::formatStandardApiResponse('error', null, 'This PO is no longer in Draft, so its line items can no longer be changed.')
            );
        }

        $line = PurchaseOrderLine::where('purchase_order_id', $po->id)->findOrFail($line_id);
        $line->delete();

        $po->recalculateTotals();

        return response()->json(
            Helper::formatStandardApiResponse('success', null, 'Line item deleted')
        );
    }
}
