<?php

namespace App\Services;

use App\Models\Asset;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class AssetSyncService
{
    public function sync(): array
    {
        DB::beginTransaction();

        try {

            /**
             * ------------------------------------------------
             * STEP 1: SYNC PURCHASE ORDERS (FIRST)
             * ------------------------------------------------
             */
            $purchaseOrders = $this->fetchPurchaseOrders();

            foreach ($purchaseOrders as $po) {

                if (!empty($po['vendor'])) {
                    $this->syncVendor($po['vendor']);
                }

                if (!empty($po['requested_by'])) {
                    $this->syncUser($po['requested_by']);
                }

                if (!empty($po['owner'])) {
                    $this->syncUser($po['owner']);
                }

                $this->syncPurchaseOrder($po);
            }

            /**
             * ------------------------------------------------
             * STEP 2: FETCH ASSETS (YOUR EXISTING LOGIC)
             * ------------------------------------------------
             */
           
            $assetsUrl = 'https://localhost:8080/api/v3/assets';

            $response = Http::withOptions([
                'verify'  => false,
                'timeout' => 60,
                'curl' => [
                    CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2,
                ],
            ])
            ->withHeaders([
                'TECHNICIAN_KEY' => env('SDP_TECHNICIAN_KEY'),
                'Accept'         => 'application/json',
            ])
            ->withHeaders([
                'Cookie' =>
                    'SDPSESSIONID=' . env('SDPSESSIONID') . '; ' .
                    '_zcsr_tmp=' . env('SDP_CSRF_COOKIE') . '; ' .
                    'sdpcsrfcookie=' . env('SDP_CSRF_COOKIE'),
            ])
            ->get($assetsUrl);

            
            /**
             * 🔍 DEBUG LOG (VERY IMPORTANT)
             */
            Log::error('ASSET API DEBUG', [
                'status'  => $response->status(),
                'body'    => $response->body(),
                'headers' => $response->headers(),
            ]);

           if (!$response->successful()) {
                throw new \Exception(
                    'Asset API failed → ' .
                    $response->status() . ' : ' . $response->body()
                );
            }

            $assets = $response->json('assets', []);

            $existingIds = Asset::where('external_source', 'SDP')
                ->pluck('external_asset_id')
                ->toArray();

            $inserted = 0;
            $skipped  = 0;

            foreach ($assets as $item) {

                if (in_array($item['id'], $existingIds)) {
                    $skipped++;
                    continue;
                }

                if (!empty($item['vendor'])) {
                    $this->syncVendor($item['vendor']);
                }

                if (!empty($item['user'])) {
                    $this->syncUser($item['user']);
                }

                $locationId = null;

                if (!empty($item['location'])) {
                    $locationId = $this->syncLocation($item['location']);
                }


                $asset = new Asset();
                $asset->unguard();
                $asset->timestamps = false;

                $asset->external_asset_id = $item['id'];
                $asset->external_source   = 'SDP';

                $asset->name          = $item['name'] ?? 'SDP Asset';
                $asset->serial = $this->uniqueSerial(
                    $item['org_serial_number'] ?? null,
                    (int) $item['id']
                );

                $asset->asset_tag = $this->uniqueAssetTag(
                    $item['asset_tag'] ?? null,
                    (int) $item['id']
                );

                $asset->_snipeit_barcode_2       = $item['barcode'] ?? null; 
                $asset->purchase_cost = $item['purchase_cost'] ?? 0;
                // $asset->purchase_cost = $item['current_cost'] ?? 0;
                $asset->location_id = $locationId; 

                $asset->purchase_date  = $this->date($item['acquisition_date']['value'] ?? null);
                $asset->asset_eol_date = $this->date($item['expiry_date']['value'] ?? null);

                // 🔗 PO CONNECTION
                $asset->order_number = $item['purchase_order_no'] ?? null;
                $asset->last_audit_date = now();

                // REQUIRED FIELDS (SNIPE-IT)
               $modelId = null;

                if (!empty($item['product'])) {
                    $modelId = $this->syncModel($item['product']);
                }

                $asset->model_id = $modelId;

                // $asset->status_id  = 1;
                $statusId = $this->syncStatus($item['state'] ?? []);
                $asset->status_id = $statusId;

                $asset->company_id = 1;

                $asset->saveQuietly();
                $inserted++;
            }

            DB::commit();

            return [
                'inserted' => $inserted,
                'skipped'  => $skipped,
                'total'    => count($assets),
            ];

        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('Asset Sync Failed', [
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

        private function syncStatus(array $state): int
    {
        if (empty($state['name'])) {
            return 1; // fallback
        }

        $stateName = trim($state['name']);

        // SDP → Local mapping
        $map = [
            'In Store'       => ['deployable' => 1, 'pending' => 0, 'archived' => 0],
            'In Use'         => ['deployable' => 1, 'pending' => 0, 'archived' => 0],
            'To Be Returned' => ['deployable' => 0, 'pending' => 1, 'archived' => 0],
            'In Repair'      => ['deployable' => 0, 'pending' => 1, 'archived' => 0],
            'Expired'        => ['deployable' => 0, 'pending' => 0, 'archived' => 1],
            'Disposed'       => ['deployable' => 0, 'pending' => 0, 'archived' => 1],
        ];

        $flags = $map[$stateName] ?? [
            'deployable' => 0,
            'pending' => 1,
            'archived' => 0,
        ];

        // Insert or reuse existing status
        DB::table('status_labels')->updateOrInsert(
            ['name' => $stateName],
            array_merge($flags, [
                'updated_at' => now(),
                'created_at' => now(),
            ])
        );

        // Return status_id
        return DB::table('status_labels')
            ->where('name', $stateName)
            ->value('id');
    }



        private function uniqueSerial(?string $serial, int $externalAssetId): string
    {
        // Preferred serial from SDP
        if (!empty($serial)) {
            $exists = DB::table('assets')
                ->where('serial', $serial)
                ->exists();

            if (!$exists) {
                return $serial;
            }
        }

        // Guaranteed fallback
        return 'SDP-SN-' . $externalAssetId . '-' . now()->timestamp;
    }

    private function uniqueAssetTag(?string $tag, int $externalAssetId): string
    {
        // Preferred asset tag from SDP
        if (!empty($tag)) {
            $exists = DB::table('assets')
                ->where('asset_tag', $tag)
                ->exists();

            if (!$exists) {
                return $tag;
            }
        }

        // Fallback with incremental safety
        $base = 'SDP-' . $externalAssetId;
        $final = $base;
        $i = 1;

        while (
            DB::table('assets')
                ->where('asset_tag', $final)
                ->exists()
        ) {
            $final = $base . '-' . $i;
            $i++;
        }

        return $final;
    }


    /**
     * ------------------------------------------------
     * API HELPERS
     * ------------------------------------------------
     */

    private function syncCategory(array $product): int
    {
        $externalCategoryId =
            $product['category']['id']
            ?? $product['asset_category']['id']
            ?? null;

        if (!$externalCategoryId) {
            throw new \Exception('Category ID missing in product payload');
        }

        $categoryName =
            $product['product_type']['display_plural_name']
            ?? $product['product_type']['name']
            ?? 'Assets';

        $category = DB::table('categories')
            ->where('external_category_id', $externalCategoryId)
            ->first();

        if ($category) {
            return $category->id;
        }

        return DB::table('categories')->insertGetId([
            'name' => $categoryName,
            'external_category_id' => $externalCategoryId,
            'category_type' => 'asset',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }



    private function syncModel(array $product): int
    {
        if (empty($product['id'])) {
            throw new \Exception('Product ID missing in asset payload');
        }

        $externalModelId = $product['id'];

        $model = DB::table('models')
            ->where('external_model_id', $externalModelId)
            ->first();

        if ($model) {
            return $model->id;
        }

        // category must be resolved from PRODUCT
        $categoryId = $this->syncCategory($product);

        return DB::table('models')->insertGetId([
            'name' => $product['name'] ?? 'Unknown Model',
            'model_number' => $product['part_no'] ?? null,
            'category_id' => $categoryId,
            'external_model_id' => $externalModelId,
            'created_by' => 1,
            'fieldset_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }




  private function fetchPurchaseOrders(): array
{
    $url = 'https://localhost:8080/api/v3/purchase_orders';

    $response = Http::withOptions([
        'verify'  => false, // allow self-signed cert
        'timeout' => 60,
        'curl' => [
            CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2,
        ],
    ])
    ->withHeaders([
        // SAME AS POSTMAN
        'TECHNICIAN_KEY' => env('SDP_TECHNICIAN_KEY'),
        'Accept'         => 'application/json',
    ])
    ->withHeaders([
        // 🔥 THIS IS IMPORTANT
        'Cookie' =>
            'SDPSESSIONID=' . env('SDPSESSIONID') . '; ' .
            '_zcsr_tmp=' . env('SDP_CSRF_COOKIE') . '; ' .
            'sdpcsrfcookie=' . env('SDP_CSRF_COOKIE'),
    ])
    ->get($url);

    // FULL DEBUG (NO MORE GUESSING)
    Log::error('PO API DEBUG', [
        'status'  => $response->status(),
        'body'    => $response->body(),
        'headers' => $response->headers(),
    ]);

    if (!$response->successful()) {
        throw new \Exception(
            'Purchase Order API failed → ' .
            $response->status() . ' : ' . $response->body()
        );
    }

    return $response->json('purchase_orders', []);
}


    /**
     * ------------------------------------------------
     * DB SYNC HELPERS
     * ------------------------------------------------
     */

    private function syncVendor(array $vendor)
    {
        DB::table('suppliers')->updateOrInsert(
            ['id' => $vendor['id']],
            [
                'name'  => $vendor['name'],
                'email' => $vendor['email_id'] ?? null,
            ]
        );
    }

    private function syncUser(array $user)
    {
        DB::table('users')->updateOrInsert(
            ['id' => $user['id']],
            [
                'first_name' => $user['name'],
                'email'      => $user['email_id'] ?? null,
                'phone'      => $user['mobile'] ?? null,
            ]
        );
    }

        private function syncLocation(?string $location): ?int
    {
        if (empty($location)) {
            return null;
        }

        // Example: "Banglore, India"
        $parts = array_map('trim', explode(',', $location));

        $city    = $parts[0] ?? null;
        $country = $parts[1] ?? null;

        $existing = DB::table('locations')
            ->where('name', $location)
            ->first();

        if ($existing) {
            return $existing->id;
        }

        return DB::table('locations')->insertGetId([
            'name'       => $location,
            'city'       => $city,
            'country'    => $country,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }


    private function syncPurchaseOrder(array $po)
    {
        DB::table('purchase_orders')->updateOrInsert(
            ['external_po_id' => $po['id']],
            [
                'custom_po_id'     => $po['custom_po_id'] ?? null,
                'po_name'             => $po['name'],
                'total_price'      => $po['total_price'],
                'base_total_price' => $po['base_total_price'],
                'status_name'      => $po['status']['name'] ?? null,
                'status_id'      => $po['status']['id'] ?? null,
                'supplier_id'        => $po['vendor']['id'] ?? null,
                'requested_by'     => $po['requested_by']['id'] ?? null,
                'owner_id'         => $po['owner']['id'] ?? null,
                'created_date'     => $this->date($po['created_date']['value'] ?? null),
                'required_date'    => $this->date($po['required_date']['value'] ?? null),
            ]
        );
    }

    private function date($ms)
    {
        return $ms ? date('Y-m-d', $ms / 1000) : null;
    }
}
