<?php
namespace App\Http\Transformers;

use Illuminate\Support\Collection;

class PurchaseOrdersTransformer
{
    public function transformPurchaseOrders(Collection $pos, int $total): array
    {
        return [
            'total' => $total,
            'rows' => $pos->map(fn ($po) => $this->format($po)),
        ];
    }

    public function transformPurchaseOrder($po): array
    {
        return $this->format($po);
    }

    private function format($po): array
    {
        return [
            'id' => $po->id,
            'custom_po_id' => $po->custom_po_id,
            'po_name' => $po->po_name,
            'total_price' => $po->total_price,
            'status' => $po->status,
            'status_label' => $po->statusLabel(),
            'vendor' => $po->vendor?->name,
            'requested_by' => $po->requestedBy?->first_name,
            'approver' => $po->approver?->present()->fullName ?? null,
            'approval_status' => $po->approval_status,
            'approved_at' => $po->approved_at,
            'created_date' => $po->created_date,
            'lines_count' => $po->relationLoaded('lines') ? $po->lines->count() : null,
        ];
    }
}
