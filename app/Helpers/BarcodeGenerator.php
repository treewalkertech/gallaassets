<?php

namespace App\Helpers;

use App\Models\Asset;
use Illuminate\Support\Facades\DB;

class BarcodeGenerator
{
    /**
     * Generate barcode for asset using active template
     */
   public static function generate(Asset $asset): string
{
    \Log::error('BARCODE DEBUG', [
        'asset_id'   => $asset->id,
        'company_id' => $asset->company_id,
        'templates'  => DB::table('barcode_templates')->get(),
    ]);

    $template = DB::table('barcode_templates')
        ->where('is_active', 1)
        ->where(function ($q) use ($asset) {
            $q->where('company_id', $asset->company_id)
              ->orWhereNull('company_id');
        })
        ->orderByRaw('company_id IS NULL')
        ->value('template');

    \Log::error('BARCODE TEMPLATE FOUND', [
        'template' => $template
    ]);

    if (!$template) {
        return 'ASSET-' . $asset->id;
    }

    $data = self::buildVariableMap($asset);

    foreach ($data as $key => $value) {
        $template = str_replace('{' . $key . '}', $value, $template);
    }

    return preg_replace('/\{[a-z_]+\}/i', 'NA', $template);
}


    /**
     * Map variables to asset values
     */
    private static function buildVariableMap(Asset $asset): array
    {
        return [
            'company'     => self::companyCode($asset->company_id),
            'location'    => self::locationCode($asset),
            'logistics'   => 'C&F', // configurable later
            'po'          => self::purchaseOrder($asset),
            'department'  => self::departmentCode($asset),
            'asset_id'    => (string) $asset->id,
            'serial'      => $asset->serial ?? 'NA',
            'year'        => $asset->created_at->format('Y'),
            'month'       => $asset->created_at->format('m'),
        ];
    }

    /* -----------------------------
       VALUE RESOLVERS (SaaS SAFE)
    ------------------------------*/

    private static function companyCode(int $companyId): string
    {
        return DB::table('companies')
            ->where('id', $companyId)
            ->value('name') ?? 'COMP';
    }

    private static function locationCode(Asset $asset): string
    {
        if (!$asset->location_id) return 'NA';

        return DB::table('locations')
            ->where('id', $asset->location_id)
            ->value('name') ?? 'NA';
    }

    private static function departmentCode(Asset $asset): string
    {
        if (!$asset->assigned_to) return 'NA';

        return DB::table('users')
            ->where('id', $asset->assigned_to)
            ->value('department_id') ?? 'NA';
    }

    private static function purchaseOrder(Asset $asset): string
    {
        if (!$asset->purchase_order_id) return 'NA';

        return DB::table('purchase_orders')
            ->where('id', $asset->purchase_order_id)
            ->value('custom_po_id')
            ?? ('PO-' . $asset->purchase_order_id);
    }
}
