<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\DB;
use Watson\Validating\ValidatingTrait;

/**
 * Goods Receipt Note (GRN) -- Phase 4 of the PO/GRN/Item Master spec. This
 * is what actually receives goods against an approved Purchase Order.
 *
 * Posting a GRN (postReceipt()) is the one place in this whole feature that
 * creates real inventory: one Asset per unit for fixed_asset items (reusing
 * Snipe-IT's existing Asset::autoincrement_asset() tagging, exactly as a
 * manually-created asset would get), a qty bump for consumables, and a
 * seats bump for licenses (which Snipe-IT's own LicenseObserver turns into
 * LicenseSeat rows automatically -- no new seat-creation code needed here).
 */
class Grn extends SnipeModel
{
    use HasFactory;
    use ValidatingTrait;
    use CompanyableTrait;

    protected $table = 'grn';

    public const STATUS_DRAFT = 'draft';
    public const STATUS_POSTED = 'posted';
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_LABELS = [
        self::STATUS_DRAFT => 'Draft',
        self::STATUS_POSTED => 'Posted',
        self::STATUS_CANCELLED => 'Cancelled',
    ];

    protected $injectUniqueIdentifier = true;

    protected $casts = [
        'received_date' => 'date',
        'posted_at'     => 'datetime',
        'company_id'    => 'integer',
        'location_id'   => 'integer',
    ];

    protected $rules = [
        'purchase_order_id' => 'required|integer|exists:purchase_orders,id',
        'location_id'       => 'nullable|integer|exists:locations,id',
        'default_status_id' => 'nullable|integer|exists:status_labels,id',
        'received_date'     => 'nullable|date',
        'status'            => 'nullable|in:draft,posted,cancelled',
    ];

    protected $fillable = [
        'company_id',
        'grn_number',
        'purchase_order_id',
        'location_id',
        'default_status_id',
        'received_by',
        'received_date',
        'status',
        'notes',
    ];

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function location()
    {
        return $this->belongsTo(Location::class, 'location_id');
    }

    public function defaultStatus()
    {
        return $this->belongsTo(Statuslabel::class, 'default_status_id');
    }

    public function receivedBy()
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function lines()
    {
        return $this->hasMany(GrnLine::class);
    }

    public function assets()
    {
        return $this->hasMany(Asset::class, 'grn_id');
    }

    public function linesAreEditable(): bool
    {
        return $this->status === self::STATUS_DRAFT || $this->status === null;
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? ($this->status ?: 'Draft');
    }

    /**
     * A simple per-year sequential GRN number (GRN-2026-00001, ...). Same
     * concurrency caveat as PurchaseOrder::generatePoNumber() -- not safe
     * under simultaneous creates, fine for one-receiver-at-a-time use.
     */
    public static function generateGrnNumber(): string
    {
        $prefix = 'GRN-'.now()->format('Y').'-';
        $last = self::where('grn_number', 'LIKE', $prefix.'%')
            ->orderByDesc('grn_number')
            ->value('grn_number');

        $next = 1;
        if ($last) {
            $next = ((int) substr($last, strlen($prefix))) + 1;
        }

        return $prefix.str_pad((string) $next, 5, '0', STR_PAD_LEFT);
    }

    /**
     * The status id newly created Assets should get. Uses whatever the
     * receiver picked (default_status_id); if they left it blank, falls
     * back to the first deployable status label on file. Returns null if
     * there genuinely isn't one -- callers must treat that as "can't post".
     */
    public function defaultStatusIdOrFallback(): ?int
    {
        if ($this->default_status_id) {
            return $this->default_status_id;
        }

        return Statuslabel::where('deployable', 1)->orderBy('id')->value('id');
    }

    /**
     * Post this GRN: locks it, and for every line, creates the real
     * inventory records (Assets / consumable qty / license seats), advances
     * the parent PO line's qty_received, and recomputes the PO's overall
     * status. Everything happens in one transaction -- a failure partway
     * through (e.g. no deployable status label exists) rolls back cleanly
     * rather than leaving half the GRN's assets created.
     *
     * @throws \RuntimeException if the GRN isn't postable, or a line can't
     *         be posted (no deployable status label on file, etc).
     */
    public function postReceipt(): void
    {
        if ($this->status !== self::STATUS_DRAFT) {
            throw new \RuntimeException('Only a Draft GRN can be posted.');
        }

        if ($this->lines()->count() === 0) {
            throw new \RuntimeException('This GRN has no line items to post.');
        }

        $statusId = $this->defaultStatusIdOrFallback();
        if (!$statusId) {
            throw new \RuntimeException('No deployable status label exists to assign to newly received assets. Create one (or pick one on this GRN) before posting.');
        }

        DB::transaction(function () use ($statusId) {
            $po = $this->purchaseOrder;

            foreach ($this->lines()->with('item', 'purchaseOrderLine')->get() as $line) {
                $item = $line->item;
                $poLine = $line->purchaseOrderLine;
                $unitCost = $line->unit_cost ?? $poLine->unit_cost;

                if ($item->item_type === Item::TYPE_FIXED_ASSET) {
                    $serials = is_array($line->serials) ? $line->serials : [];

                    for ($i = 0; $i < $line->qty_received; $i++) {
                        $asset = new Asset();
                        $asset->model_id = $item->asset_model_id;
                        $asset->status_id = $statusId;
                        $asset->asset_tag = Asset::autoincrement_asset();
                        $asset->serial = $serials[$i] ?? null;
                        $asset->purchase_cost = $unitCost;
                        $asset->purchase_date = $this->received_date;
                        $asset->supplier_id = $po->supplier_id;
                        $asset->purchase_order_id = $po->id;
                        $asset->grn_id = $this->id;
                        $asset->company_id = $po->company_id;
                        $asset->rtd_location_id = $this->location_id;

                        if (!$asset->save()) {
                            throw new \RuntimeException(
                                'Could not create asset unit '.($i + 1).' of '.$line->qty_received.' for "'.$item->name.'": '
                                .implode(' ', $asset->getErrors()->all())
                            );
                        }
                    }
                } elseif ($item->item_type === Item::TYPE_CONSUMABLE && $item->consumable_id) {
                    $consumable = Consumable::find($item->consumable_id);
                    if (!$consumable) {
                        throw new \RuntimeException('Item "'.$item->name.'" is a consumable-type item, but its linked Consumable record no longer exists.');
                    }

                    $consumable->qty = (int) $consumable->qty + (int) $line->qty_received;
                    if (!$consumable->save()) {
                        throw new \RuntimeException('Could not update quantity for consumable "'.$item->name.'": '.implode(' ', $consumable->getErrors()->all()));
                    }
                } elseif ($item->item_type === Item::TYPE_LICENSE && $item->license_id) {
                    $license = License::find($item->license_id);
                    if (!$license) {
                        throw new \RuntimeException('Item "'.$item->name.'" is a license-type item, but its linked License record no longer exists.');
                    }

                    // Assigning + saving trips Snipe-IT's own license
                    // seat-management logic (see License.php / its
                    // observer), which creates the LicenseSeat rows --
                    // no seat-creation code needed here.
                    $license->seats = (int) $license->seats + (int) $line->qty_received;
                    if (!$license->save()) {
                        throw new \RuntimeException('Could not add seats for license "'.$item->name.'": '.implode(' ', $license->getErrors()->all()));
                    }
                } else {
                    throw new \RuntimeException('Item "'.$item->name.'" has type "'.$item->item_type.'" but is missing its linkage to the underlying record -- cannot receive it.');
                }

                $poLine->qty_received = (int) $poLine->qty_received + (int) $line->qty_received;
                $poLine->saveQuietly();
            }

            $po->recomputeStatus();

            $this->status = self::STATUS_POSTED;
            $this->posted_at = now();
            $this->save();
        });
    }

    /**
     * Cancel a still-Draft GRN. No downstream records exist yet at this
     * point (nothing is created until postReceipt() runs), so this is just
     * a status flip -- unlike posting, there's nothing to unwind.
     */
    public function cancel(): void
    {
        if ($this->status !== self::STATUS_DRAFT) {
            throw new \RuntimeException('Only a Draft GRN can be cancelled.');
        }

        $this->status = self::STATUS_CANCELLED;
        $this->save();
    }
}
