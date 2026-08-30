<?php
namespace App\Http\Transformers;

use Illuminate\Support\Collection;

class GrnTransformer
{
    public function transformGrns(Collection $grns, int $total): array
    {
        return [
            'total' => $total,
            'rows' => $grns->map(fn ($grn) => $this->format($grn)),
        ];
    }

    public function transformGrn($grn): array
    {
        return $this->format($grn);
    }

    private function format($grn): array
    {
        return [
            'id' => $grn->id,
            'grn_number' => $grn->grn_number,
            'status' => $grn->status,
            'status_label' => $grn->statusLabel(),
            'purchase_order' => $grn->purchaseOrder ? [
                'id' => $grn->purchaseOrder->id,
                'custom_po_id' => $grn->purchaseOrder->custom_po_id,
                'po_name' => $grn->purchaseOrder->po_name,
            ] : null,
            'location' => $grn->location?->name,
            'received_by' => $grn->receivedBy?->present()->fullName,
            'received_date' => $grn->received_date,
            'posted_at' => $grn->posted_at,
            'lines_count' => $grn->relationLoaded('lines') ? $grn->lines->count() : null,
        ];
    }
}
