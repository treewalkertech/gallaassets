<?php

namespace App\Http\Controllers\Api;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Http\Transformers\GrnLinesTransformer;
use App\Models\Grn;
use App\Models\GrnLine;
use App\Models\PurchaseOrderLine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GrnLinesController extends Controller
{
    public function index($grn_id): array
    {
        $grn = Grn::findOrFail($grn_id);
        $this->authorize('view', $grn);

        $lines = $grn->lines()->with('item')->get();

        return (new GrnLinesTransformer)->transformLines($lines, $lines->count());
    }

    public function store(Request $request, $grn_id): JsonResponse
    {
        $grn = Grn::findOrFail($grn_id);
        $this->authorize('update', $grn);

        if (!$grn->linesAreEditable()) {
            return response()->json(
                Helper::formatStandardApiResponse('error', null, 'This GRN is no longer in Draft, so its line items can no longer be changed.')
            );
        }

        $poLine = PurchaseOrderLine::where('purchase_order_id', $grn->purchase_order_id)
            ->find($request->input('purchase_order_line_id'));

        if (!$poLine) {
            return response()->json(
                Helper::formatStandardApiResponse('error', null, 'That line item does not belong to this GRN\'s Purchase Order.')
            );
        }

        $remaining = $poLine->qty_ordered - $poLine->qty_received;
        $qty = (int) $request->input('qty_received');

        if ($qty > $remaining) {
            return response()->json(
                Helper::formatStandardApiResponse('error', null, "Only {$remaining} unit(s) remain to be received on that PO line.")
            );
        }

        $line = new GrnLine();
        $line->grn_id = $grn->id;
        $line->purchase_order_line_id = $poLine->id;
        $line->item_id = $poLine->item_id;
        $line->qty_received = $qty;
        $line->unit_cost = $request->input('unit_cost');
        $line->serials = $request->input('serials');
        $line->notes = $request->input('notes');

        if ($line->save()) {
            return response()->json(
                Helper::formatStandardApiResponse('success', $line, 'Line item added')
            );
        }

        return response()->json(
            Helper::formatStandardApiResponse('error', null, $line->getErrors())
        );
    }

    public function update(Request $request, $grn_id, $line_id): JsonResponse
    {
        $grn = Grn::findOrFail($grn_id);
        $this->authorize('update', $grn);

        if (!$grn->linesAreEditable()) {
            return response()->json(
                Helper::formatStandardApiResponse('error', null, 'This GRN is no longer in Draft, so its line items can no longer be changed.')
            );
        }

        $line = GrnLine::where('grn_id', $grn->id)->findOrFail($line_id);
        $line->fill($request->all());

        if ($line->save()) {
            return response()->json(
                Helper::formatStandardApiResponse('success', $line, 'Line item updated')
            );
        }

        return response()->json(
            Helper::formatStandardApiResponse('error', null, $line->getErrors())
        );
    }

    public function destroy($grn_id, $line_id): JsonResponse
    {
        $grn = Grn::findOrFail($grn_id);
        $this->authorize('update', $grn);

        if (!$grn->linesAreEditable()) {
            return response()->json(
                Helper::formatStandardApiResponse('error', null, 'This GRN is no longer in Draft, so its line items can no longer be changed.')
            );
        }

        $line = GrnLine::where('grn_id', $grn->id)->findOrFail($line_id);
        $line->delete();

        return response()->json(
            Helper::formatStandardApiResponse('success', null, 'Line item deleted')
        );
    }
}
