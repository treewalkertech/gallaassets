<?php
namespace App\Helpers;

use App\Models\Asset;
use Illuminate\Support\Facades\DB;
use App\Helpers\BarcodeCounter;

class BarcodeGenerator
{
    /**
     * Generate barcode using active template
     */
    public static function generate(Asset $asset): string
    {
        $template = DB::table('barcode_templates')
            ->where('is_active', 1)
            ->where(function ($q) use ($asset) {
                $q->where('company_id', $asset->company_id)
                  ->orWhereNull('company_id');
            })
            ->orderByRaw('company_id IS NULL') // company first
            ->value('template');

        if (!$template) {
            return 'ASSET-' . $asset->id;
        }

        $map = self::buildVariableMap($asset);

        foreach ($map as $key => $value) {
            $template = str_replace('{' . $key . '}', $value, $template);
        }

        // Remove unreplaced placeholders
        $template = preg_replace('/\{[a-z_]+\}/i', '', $template);

        // 🔥 remove empty segments
        $parts = array_filter(
            explode('/', $template),
            fn ($p) => trim($p) !== ''
        );

        return implode('/', $parts);

        // // Replace unused placeholders
        // return preg_replace('/\{[a-z_]+\}/i', '', $template);
    }

    /**
     * All dynamic variables
     */
    private static function buildVariableMap(Asset $asset): array
    {

          $date = $asset->created_at ?? now();
        $serial = BarcodeCounter::next($asset->company_id);

        return [
            'company'     => self::companyCode($asset),
            'location'    => self::locationCode($asset),
            'logistics'   => self::logisticsType($asset),
            'po'          => self::purchaseOrder($asset),
            'department'  => self::departmentCode($asset),
            'asset_seq'   => self::assetSequence($asset),
            'asset_id'   => (string) ($asset->external_asset_id ?? $asset->id),
            // 'asset_id'    => (string) $asset->id,
            // 'serial'      => $asset->serial ?? '',
            'serial'     =>    $serial,  
            'year'       => $date->format('Y'),
            'month'      => $date->format('m'),
        ];
    }

    /* -----------------------------
       RESOLVERS
    ------------------------------*/

    private static function companyCode(Asset $asset): string
    {
        $name = DB::table('companies')
            ->where('id', $asset->company_id)
            ->value('name');

        if (empty($name)) {
            return 'COMP';
        }

        // Split name into words
        $words = preg_split('/\s+/', trim($name));

        // Take first letter of each word (max 3 letters)
        $code = '';
        foreach ($words as $word) {
            if ($word !== '') {
                $code .= strtoupper(substr($word, 0, 3));
            }
            if (strlen($code) >= 3) {
                break; // limit to 3 chars like KWE
            }
        }

        return $code ?: 'COMP';
    }


        private static function locationCode(Asset $asset): string
    {
        if (!$asset->rtd_location_id) return '';

        $location = DB::table('locations')
            ->where('id', $asset->rtd_location_id)
            ->value('name');

        if (empty($location) || $location === '-') {
            return '';
        }

        $city = trim(explode(',', $location)[0]);

        return strtoupper($city) . '-WH';
    }



    private static function departmentCode(Asset $asset): string
    {
        if (!$asset->department_id) {
            return '';
        }

        $name = DB::table('departments')
            ->where('id', $asset->department_id)
            ->value('name');

        if (empty($name)) {
            return '';
        }

        return self::shortCode($name);
    }

        private static function shortCode(string $name): string
    {
        $words = preg_split('/\s+/', trim($name));

        // Single word → first 3 letters
        if (count($words) === 1) {
            return strtoupper(substr($words[0], 0, 3));
        }

        // Multi-word → initials
        $code = '';
        foreach ($words as $w) {
            $code .= strtoupper($w[0]);
        }

        return $code;
    }



    private static function purchaseOrder(Asset $asset): string
    {
        /**
         * CASE 1️⃣: Manual override always wins
         */
        if (!empty($asset->order_number)) {
            return (string) $asset->order_number;
        }

        /**
         * CASE 2️⃣: Imported / SDP asset
         */
        if ($asset->purchase_order_id) {
            return DB::table('purchase_orders')
                ->where('id', $asset->purchase_order_id)
                ->value('custom_po_id')
                ?? (string) $asset->purchase_order_id;
        }

        return '';
    }


    private static function logisticsType(Asset $asset): string
    {
        return 'C&F';
        // return DB::table('suppliers')
        //     ->where('id', $asset->supplier_id)
        //     ->value('name') ?? 'C&F';
    }

    /**
     * ✅ PERFECT SEQUENCE LOGIC
     */
    private static function assetSequence(Asset $asset): string
    {
        /**
         * CASE 1: Asset belongs to PO
         */
        if ($asset->purchase_order_id) {

            // 🔥 Prefer external_asset_id, fallback to id
            $ids = DB::table('assets')
                ->where('purchase_order_id', $asset->purchase_order_id)
                ->orderByRaw('COALESCE(external_asset_id, id)')
                ->pluck(DB::raw('COALESCE(external_asset_id, id)'))
                ->values();

            $current = $asset->external_asset_id ?? $asset->id;
            $pos = $ids->search($current);

            return str_pad((string)(($pos === false ? 0 : $pos) + 1), 2, '0', STR_PAD_LEFT);
        }

        /**
         * CASE 2: No PO
         */
        $ids = DB::table('assets')
            ->where('company_id', $asset->company_id)
            ->where('rtd_location_id', $asset->rtd_location_id)
            ->where('department_id', $asset->department_id)
            ->whereNull('purchase_order_id')
            ->orderByRaw('COALESCE(external_asset_id, id)')
            ->pluck(DB::raw('COALESCE(external_asset_id, id)'))
            ->values();

        $current = $asset->external_asset_id ?? $asset->id;
        $pos = $ids->search($current);

        return str_pad((string)(($pos === false ? 0 : $pos) + 1), 2, '0', STR_PAD_LEFT);
    }


}
