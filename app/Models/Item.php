<?php

namespace App\Models;

use App\Models\Traits\Searchable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Watson\Validating\ValidatingTrait;

/**
 * Item Master -- the catalog table Purchase Orders are raised against.
 *
 * See database/migrations/2026_08_30_064308_create_items_table.php for the
 * full column rationale.
 */
class Item extends SnipeModel
{
    use HasFactory;
    use SoftDeletes;
    use CompanyableTrait;
    use Searchable;

    protected $table = 'items';

    public const TYPE_FIXED_ASSET = 'fixed_asset';
    public const TYPE_CONSUMABLE = 'consumable';
    public const TYPE_LICENSE = 'license';

    public const ITEM_TYPES = [
        self::TYPE_FIXED_ASSET => 'Fixed Asset',
        self::TYPE_CONSUMABLE => 'Consumable',
        self::TYPE_LICENSE => 'License',
    ];

    protected $injectUniqueIdentifier = true;
    use ValidatingTrait;

    protected $rules = [
        'name'            => 'required|string|min:1|max:255',
        'item_type'       => 'required|in:fixed_asset,consumable,license',
        'item_code'       => 'nullable|string|max:255',
        'category_id'     => 'nullable|integer|exists:categories,id',
        'manufacturer_id' => 'nullable|integer|exists:manufacturers,id',
        'asset_model_id'  => 'nullable|integer|required_if:item_type,fixed_asset|exists:models,id',
        'consumable_id'   => 'nullable|integer|required_if:item_type,consumable|exists:consumables,id',
        'license_id'      => 'nullable|integer|required_if:item_type,license|exists:licenses,id',
        'default_unit_cost' => 'nullable|numeric|min:0',
        'reorder_level'   => 'nullable|integer|min:0',
    ];

    protected $fillable = [
        'company_id',
        'item_code',
        'name',
        'description',
        'item_type',
        'category_id',
        'manufacturer_id',
        'uom',
        'asset_model_id',
        'consumable_id',
        'license_id',
        'default_unit_cost',
        'reorder_level',
        'is_active',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'is_active'         => 'boolean',
        'default_unit_cost' => 'float',
        'company_id'        => 'integer',
        'category_id'       => 'integer',
        'manufacturer_id'   => 'integer',
    ];

    protected $searchableAttributes = ['item_code', 'name', 'description'];
    protected $searchableRelations = [];

    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function manufacturer()
    {
        return $this->belongsTo(Manufacturer::class, 'manufacturer_id');
    }

    public function assetModel()
    {
        return $this->belongsTo(AssetModel::class, 'asset_model_id');
    }

    public function consumable()
    {
        return $this->belongsTo(Consumable::class, 'consumable_id');
    }

    public function license()
    {
        return $this->belongsTo(License::class, 'license_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function purchaseOrderLines()
    {
        return $this->hasMany(PurchaseOrderLine::class);
    }

    /**
     * The type-specific record this item fulfils into, whichever of
     * assetModel/consumable/license applies to its item_type.
     */
    public function fulfillmentTarget()
    {
        return match ($this->item_type) {
            self::TYPE_FIXED_ASSET => $this->assetModel,
            self::TYPE_CONSUMABLE => $this->consumable,
            self::TYPE_LICENSE => $this->license,
            default => null,
        };
    }

    public function typeLabel(): string
    {
        return self::ITEM_TYPES[$this->item_type] ?? $this->item_type;
    }
}
