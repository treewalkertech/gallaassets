<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Watson\Validating\ValidatingTrait;

class PurchaseOrder extends SnipeModel
{
    use HasFactory;
    use ValidatingTrait;
    use CompanyableTrait;

    protected $table = 'purchase_orders';

    // Native (in-app) workflow states -- driven by the `status` column.
    // status_name/status_id are left alone for ServiceDesk-Plus-synced rows.
    public const STATUS_DRAFT = 'draft';
    public const STATUS_PENDING_APPROVAL = 'pending_approval';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_PARTIALLY_RECEIVED = 'partially_received';
    public const STATUS_RECEIVED = 'received';
    public const STATUS_CLOSED = 'closed';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_REJECTED = 'rejected';

    public const STATUS_LABELS = [
        self::STATUS_DRAFT => 'Draft',
        self::STATUS_PENDING_APPROVAL => 'Pending Approval',
        self::STATUS_APPROVED => 'Approved',
        self::STATUS_PARTIALLY_RECEIVED => 'Partially Received',
        self::STATUS_RECEIVED => 'Received',
        self::STATUS_CLOSED => 'Closed',
        self::STATUS_CANCELLED => 'Cancelled',
        self::STATUS_REJECTED => 'Rejected',
    ];

    // The only statuses a PO creator may set directly from the edit form.
    // approved/rejected only happen via the approve()/reject() actions;
    // partially_received/received only happen once GRN (Phase 4) posts
    // receipts against this PO's lines; closed is left for a later phase.
    public const CREATOR_SELECTABLE_STATUSES = [
        self::STATUS_DRAFT => 'Draft',
        self::STATUS_PENDING_APPROVAL => 'Pending Approval',
        self::STATUS_CANCELLED => 'Cancelled',
    ];

    /**
     * Whether the model should inject its identifier into the unique
     * validation rules before attempting validation.
     *
     * @var bool
     */
    protected $injectUniqueIdentifier = true;

    protected $casts = [
        'created_date'  => 'datetime',
        'required_date' => 'datetime',
        'approved_at'   => 'datetime',
        'total_price'   => 'float',
        'company_id'    => 'integer',
        'approver_id'   => 'integer',
    ];

    protected $rules = [
        'po_name'       => 'nullable|string|max:255',
        'supplier_id'   => 'nullable|integer|exists:suppliers,id',
        'requested_by'  => 'nullable|integer|exists:users,id',
        'owner_id'      => 'nullable|integer|exists:users,id',
        'approver_id'   => 'nullable|integer|exists:users,id',
        'status'        => 'nullable|in:draft,pending_approval,approved,partially_received,received,closed,cancelled,rejected',
        'total_price'   => 'nullable|numeric',
        'currency_code' => 'nullable|string|max:10',
    ];

    protected $fillable = [
        'external_po_id',
        'custom_po_id',
        'po_name',
        'company_id',
        'supplier_id',
        'requested_by',
        'owner_id',
        'approver_id',
        'created_by',
        'status',
        'total_price',
        'base_total_price',
        'discount',
        'sales_tax',
        'additional_tax',
        'shipping_price',
        'status_name',
        'status_id',
        'created_date',
        'required_date',
        'currency_code',
    ];

    /**
     * The supplier (vendor) this PO was raised against.
     *
     * Named `vendor()` (rather than `supplier()`) because the API transformer
     * and both PO controllers already call `->vendor` / `with('vendor')` --
     * this is the fix, not a rename of those call sites.
     */
    public function vendor()
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    /**
     * The user who requested this PO.
     *
     * Named `requestedBy()` to match existing controller/transformer calls
     * (see note on vendor() above).
     */
    public function requestedBy()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approver_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function assets()
    {
        return $this->hasMany(
            Asset::class,
            'purchase_order_id'
        );
    }

    public function lines()
    {
        return $this->hasMany(PurchaseOrderLine::class);
    }

    /**
     * Whether this specific PO's line items may still be added, edited, or
     * removed. Once it's out of draft (submitted for approval or beyond),
     * line changes are blocked rather than silently allowed to drift from
     * whatever was actually approved.
     */
    public function linesAreEditable(): bool
    {
        return $this->status === self::STATUS_DRAFT || $this->status === null;
    }

    /**
     * Human label for this PO's status. For rows that came in via the
     * ServiceDesk Plus sync (external_po_id set), the new `status` column
     * defaults to 'draft' by migration backfill and doesn't reflect their
     * real (SDP-driven) state -- show their legacy status_name instead.
     */
    public function statusLabel(): string
    {
        if ($this->external_po_id) {
            return $this->status_name ?: 'Unknown';
        }

        return self::STATUS_LABELS[$this->status] ?? ($this->status ?: 'Draft');
    }

    /**
     * Recalculate total_price/base_total_price from this PO's line items.
     * Call after any line is added, edited, or removed. Single-currency
     * assumption for now, matching the rest of this table.
     */
    public function recalculateTotals(): void
    {
        $sum = $this->lines()->sum('line_total');
        $this->total_price = $sum;
        $this->base_total_price = $sum;
        $this->saveQuietly();
    }

    /**
     * Recompute status from line receipt progress. A no-op until GRN
     * (Phase 4) starts posting receipts against lines (qty_received stays 0
     * until then), but this is the hook Phase 4 calls into rather than
     * duplicating the "is every line fully received" logic elsewhere.
     */
    /**
     * A simple per-year sequential PO number (PO-2026-00001, ...). Not
     * concurrency-safe under simultaneous creates (no locking counter table
     * the way asset tags have via settings.next_auto_tag_base) -- fine for
     * now, worth revisiting if POs start being created from more than one
     * place at once.
     */
    public static function generatePoNumber(): string
    {
        $prefix = 'PO-'.now()->format('Y').'-';
        $last = self::where('custom_po_id', 'LIKE', $prefix.'%')
            ->orderByDesc('custom_po_id')
            ->value('custom_po_id');

        $next = 1;
        if ($last) {
            $next = ((int) substr($last, strlen($prefix))) + 1;
        }

        return $prefix.str_pad((string) $next, 5, '0', STR_PAD_LEFT);
    }

    public function recomputeStatus(): void
    {
        $lines = $this->lines()->get(['qty_ordered', 'qty_received']);

        if ($lines->isEmpty() || !in_array($this->status, [self::STATUS_APPROVED, self::STATUS_PARTIALLY_RECEIVED, self::STATUS_RECEIVED], true)) {
            return;
        }

        if ($lines->every(fn ($line) => $line->qty_received >= $line->qty_ordered)) {
            $this->status = self::STATUS_RECEIVED;
        } elseif ($lines->contains(fn ($line) => $line->qty_received > 0)) {
            $this->status = self::STATUS_PARTIALLY_RECEIVED;
        }

        $this->saveQuietly();
    }
}
