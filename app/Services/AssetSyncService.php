<?php

namespace App\Services;

use App\Models\Asset;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Helpers\BarcodeGenerator;
use Illuminate\Support\Str;
use App\Services\ServiceDeskConfigResolver;
use App\Models\ServiceDeskConfig;
use Illuminate\Support\Facades\Auth;
class AssetSyncService
{
    private ServiceDeskConfig $config;
    private string $baseUrl;
    private string $baseApiUrl;

    private array $httpOptions;
    private array $headers;

    // private const SDP_BASE_URL = 'https://localhost:8080/api/v3';

        public function __construct()
    {
        $this->config = ServiceDeskConfig::where('company_id', Auth::user()->company_id)
            ->where('is_active', true)
            ->where('is_sync_enabled', true)
            ->firstOrFail();

        // https://host/api/v3
        $this->baseApiUrl = rtrim($this->config->base_url, '/')
            . '/api/' . $this->config->api_version;

        $this->httpOptions = [
            'verify'  => (bool) $this->config->verify_ssl,
            'timeout' => (int) $this->config->timeout,
            'curl' => [
                CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2,
            ],
        ];

        $this->headers = [
            'TECHNICIAN_KEY' => $this->config->technician_key,
            'Accept'         => 'application/json',
        ];

        // Cookies only needed in production
        if ($this->config->mode === 'production') {
            $this->headers['Cookie'] =
                'SDPSESSIONID=' . $this->config->session_id . '; ' .
                '_zcsr_tmp=' . $this->config->csrf_token . '; ' .
                'sdpcsrfcookie=' . $this->config->csrf_token;
        }
    }

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
             * STEP 1.5: SYNC ASSET CATEGORIES (MASTER)
             * ------------------------------------------------
             */
            $assetCategories = $this->fetchAssetCategories();
            $this->syncAssetCategories($assetCategories);

            /**
             * ------------------------------------------------
             * STEP 2: FETCH ASSETS (YOUR EXISTING LOGIC)
             * ------------------------------------------------
             */
           
            // $assetsUrl = 'https://localhost:8080/api/v3/assets';
            $response = $this->sdpGet('/assets');


            // $response = Http::withOptions([
            //     'verify'  => false,
            //     'timeout' => 60,
            //     'curl' => [
            //         CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2,
            //     ],
            // ])
            // ->withHeaders([
            //     'TECHNICIAN_KEY' => env('SDP_TECHNICIAN_KEY'),
            //     'Accept'         => 'application/json',
            // ])
            // ->withHeaders([
            //     'Cookie' =>
            //         'SDPSESSIONID=' . env('SDPSESSIONID') . '; ' .
            //         '_zcsr_tmp=' . env('SDP_CSRF_COOKIE') . '; ' .
            //         'sdpcsrfcookie=' . env('SDP_CSRF_COOKIE'),
            // ])
            // ->get($assetsUrl);

            
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

                // --------------------------------------------------
                // 🔹 FETCH ASSET DETAILS (REQUIRED FOR ATTACHMENTS)
                // --------------------------------------------------
                $assetDetailsResponse = $this->sdpGet('/assets/' . $item['id']);
                // $assetDetailsResponse = Http::withOptions([
                //     'verify'  => false,
                //     'timeout' => 60,
                // ])
                // ->withHeaders([
                //     'TECHNICIAN_KEY' => env('SDP_TECHNICIAN_KEY'),
                //     'Accept'         => 'application/json',
                // ])
                // ->withHeaders([
                //     'Cookie' =>
                //         'SDPSESSIONID=' . env('SDPSESSIONID') . '; ' .
                //         '_zcsr_tmp=' . env('SDP_CSRF_COOKIE') . '; ' .
                //         'sdpcsrfcookie=' . env('SDP_CSRF_COOKIE'),
                // ])
                // ->get(self::SDP_BASE_URL . '/assets/' . $item['id']);

                if (!$assetDetailsResponse->successful()) {
                    Log::warning('Asset detail fetch failed', [
                        'asset_id' => $item['id'],
                    ]);
                    continue;
                }

                $assetDetails = $assetDetailsResponse->json('asset', []);


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

                $asset->order_number = $item['purchase_order_no'] ?? null;
                $asset->current_value = $item['current_cost'] ?? null;


                $asset->purchase_cost = $item['purchase_cost'] ?? 0;
                // $asset->purchase_cost = $item['current_cost'] ?? 0;
                $asset->rtd_location_id = $locationId; 

                $asset->purchase_date  = $this->date($item['acquisition_date']['value'] ?? null);
                $asset->asset_eol_date = $this->date($item['expiry_date']['value'] ?? null);

               // 🔗 PURCHASE ORDER (SAFE RESOLUTION)
                $poNumber = $item['purchase_order_no'] ?? null;

                $purchaseOrder = DB::table('purchase_orders')
                    ->where('custom_po_id', (string) $poNumber)
                    ->orWhere('external_po_id', (int) $poNumber)
                    ->first();

                $asset->purchase_order_id = $purchaseOrder->id ?? null;

                // 🔹 REQUESTABLE LOGIC
                $requestable = 0;

                if (!empty($purchaseOrder)) {
                    $poStatus = strtolower($purchaseOrder->status_name ?? '');

                    if (in_array($poStatus, ['received', 'completed', 'closed'])) {
                        $requestable = 1;
                    }
                }

                $asset->requestable = $requestable;

                // 🔹 SUPPLIER
                if (!empty($item['vendor']['id'])) {
                    $asset->supplier_id = $item['vendor']['id'];
                }

                // 🔹 ASSIGNED USER (IF ANY)
                // if (!empty($item['user']['id'])) {
                //     $asset->assigned_to   = $item['user']['id'];
                //     $asset->assigned_type = 'App\\Models\\User';
                // }

                // --------------------------------------------------
                // 🔹 ASSIGNED USER (ASSET → PO FALLBACK)
                // --------------------------------------------------

                if (!empty($item['user']['id'])) {

                    // Highest priority: asset assigned user
                    $asset->assigned_to   = $item['user']['id'];
                    $asset->assigned_type = 'App\\Models\\User';

                } elseif (!empty($purchaseOrder?->requested_by)) {

                    // Fallback: purchase order requester
                    $asset->assigned_to   = $purchaseOrder->requested_by;
                    $asset->assigned_type = 'App\\Models\\User';

                } else {

                    // Not assigned
                    $asset->assigned_to   = null;
                    $asset->assigned_type = null;
                }



                // $purchaseOrder = DB::table('purchase_orders')
                //                 ->where('custom_po_id', $poNumber)
                //                 ->select('id', 'po_name')
                //                 ->first();

              
                 // --------------------------------------------------
                // 🔹 BARCODE GENERATION (ONLY IF SDP DID NOT SEND ONE)
                // --------------------------------------------------

                // if (empty($item['barcode'])) {

                //     // -----------------------------
                //     // REQUIRED STATIC VALUES
                //     // -----------------------------
                //     $company = 'KWE';

                //     $siteMap = [
                //         'tree walker' => 'BOM-VADAPE',
                //     ];

                //     $siteName = strtolower($item['site']['name'] ?? '');
                //     $location = $siteMap[$siteName] ?? 'BOM-VADAPE';

                //     $logisticsType = 'C&F';
                //     $department    = 'DTP';

                //     // -----------------------------
                //     // PURCHASE ORDER (SAFE)
                //     // -----------------------------
                //     $poRef = $purchaseOrder->name
                //         ?? $purchaseOrder->po_name
                //         ?? ('PO' . ($purchaseOrder->custom_po_id ?? $purchaseOrder->id ?? 'NA')) ?? ('PO-' . ($poNumber ?? 'NA'));

                //     // -----------------------------
                //     // UNIQUE IDENTIFIER (KEY FIX)
                //     // -----------------------------
                //     // SDP Asset ID → always unique
                //     $assetId = $item['id'];

                //     // -----------------------------
                //     // FINAL BARCODE VALUE
                //     // -----------------------------
                //     $barcodeValue = implode('/', [
                //         $company,
                //         $location,
                //         $logisticsType,
                //         $poRef,
                //         $department,
                //         $assetId
                //     ]);

                //     $asset->_snipeit_barcode_2 = $barcodeValue;

                // } else {
                //     // SDP barcode exists → keep it
                //     $asset->_snipeit_barcode_2 = $item['barcode'];
                // }


              


                // REQUIRED FIELDS (SNIPE-IT)
               $modelId = null;

                if (!empty($item['product'])) {
                    $modelId = $this->syncModel($item['product']);
                }

                $asset->model_id = $modelId;

                // $asset->status_id  = 1;
                $statusId = $this->syncStatus($item['state'] ?? []);
                $asset->status_id = $statusId;

                // $asset->company_id = 1;
                $asset->company_id = $this->config->company_id;
                $asset->last_audit_date = now();

                // 🔹 ASSET DEPARTMENT FROM SDP (HIGHEST PRIORITY)
                if (!empty($item['department'])) {
                    $this->syncDepartment($item['department']);
                    $asset->department_id = $item['department']['id'];
                }



                $asset->save();
                // 🔒 BARCODE: generate ONLY ONCE
                if (empty($asset->_snipeit_barcode_2)) {

                    // if SDP sent barcode → trust it
                    if (!empty($item['barcode'])) {
                        $asset->_snipeit_barcode_2 = $item['barcode'];
                    } else {
                        $asset->_snipeit_barcode_2 = BarcodeGenerator::generate($asset);
                    }

                    $asset->save(); // 2️⃣ save barcode only once
                }
                // DB::commit();

                $this->downloadAssetImage($asset, $assetDetails);
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

    

        private function sdpGet(string $endpoint)
    {
        return Http::withOptions($this->httpOptions)
            ->withHeaders($this->headers)
            ->get($this->baseApiUrl . $endpoint);
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



    //     private function uniqueSerial(?string $serial, int $externalAssetId): string
    // {
    //     // Preferred serial from SDP
    //     if (!empty($serial)) {
    //         $exists = DB::table('assets')
    //             ->where('serial', $serial)
    //             ->exists();

    //         if (!$exists) {
    //             return $serial;
    //         }
    //     }

    //     // Guaranteed fallback
    //     return 'SDP-SN-' . $externalAssetId . '-' . now()->timestamp;
    // }

    private function uniqueSerial(?string $serial, int $externalAssetId): string
    {
        if (!empty($serial)) {
            return $serial;
        }

        return 'SDP-SN-' . $externalAssetId;
    }


    //     private function downloadAssetImage(Asset $asset, array $assetDetails): void
    // {
    //     $attachments = $assetDetails['attachments'] ?? [];

    //     if (empty($attachments)) {
    //         return;
    //     }

    //     // Take first attachment (image)
    //     $attachment = $attachments[0];

    //     if (
    //         empty($attachment['content_url']) ||
    //         !str_starts_with($attachment['content_type'] ?? '', 'image/')
    //     ) {
    //         return;
    //     }

    //     // $downloadUrl = 'https://localhost:8080' . $attachment['content_url'];
    //     $downloadUrl = rtrim($this->config->base_url, '/') . $attachment['content_url'];


    //     $imageResponse = Http::withOptions([
    //             'verify'  => false,
    //             'timeout' => 60,
    //         ])
    //         ->withHeaders([
    //             'TECHNICIAN_KEY' => env('SDP_TECHNICIAN_KEY'),
    //             'Cookie' =>
    //                 'SDPSESSIONID=' . env('SDPSESSIONID') . '; ' .
    //                 '_zcsr_tmp=' . env('SDP_CSRF_COOKIE') . '; ' .
    //                 'sdpcsrfcookie=' . env('SDP_CSRF_COOKIE'),
    //         ])
    //         ->get($downloadUrl);

    //     if (!$imageResponse->successful()) {
    //         return;
    //     }

    //     // ---------------------------------------
    //     // ✅ SAVE USING STORAGE (IMPORTANT FIX)
    //     // ---------------------------------------

    //     $ext = pathinfo($attachment['name'], PATHINFO_EXTENSION) ?: 'jpg';
    //     $filename = 'asset-image-' . $asset->id . '.' . $ext;

    //     // assets_upload_path = 'uploads/assets/'
    //     $path = app('assets_upload_path') . $filename;

    //     Storage::disk('public')->put(
    //         $path,
    //         $imageResponse->body()
    //     );

    //     // Save only filename (NOT full path)
    //     $asset->update([
    //         'image' => $filename
    //     ]);
    // }



    private function downloadAssetImage(Asset $asset, array $assetDetails): void
{
    $attachments = $assetDetails['attachments'] ?? [];

    if (empty($attachments)) {
        return;
    }

    // Take first attachment only
    $attachment = $attachments[0];

    if (
        empty($attachment['content_url']) ||
        !str_starts_with($attachment['content_type'] ?? '', 'image/')
    ) {
        return;
    }

    // Build full download URL
    $downloadUrl = rtrim($this->config->base_url, '/') . $attachment['content_url'];

    $imageResponse = Http::withOptions($this->httpOptions)
        ->withHeaders($this->headers)
        ->get($downloadUrl);

    if (!$imageResponse->successful()) {
        Log::warning('Asset image download failed', [
            'asset_id' => $asset->id,
            'status'   => $imageResponse->status(),
        ]);
        return;
    }

    // Determine extension
    $ext = pathinfo($attachment['name'], PATHINFO_EXTENSION) ?: 'jpg';
    $filename = 'asset-image-' . $asset->id . '.' . $ext;

    // assets_upload_path = uploads/assets/
    $path = app('assets_upload_path') . $filename;

    Storage::disk('public')->put($path, $imageResponse->body());

    // Save ONLY filename
    $asset->update([
        'image' => $filename,
    ]);
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

        private function fetchAssetCategories(): array
    {
        // $url = self::SDP_BASE_URL . '/asset_categories';
        $response = $this->sdpGet('/asset_categories');


        // $response = Http::withOptions([
        //     'verify'  => false,
        //     'timeout' => 60,
        //     'curl' => [
        //         CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2,
        //     ],
        // ])
        // ->withHeaders([
        //     'TECHNICIAN_KEY' => env('SDP_TECHNICIAN_KEY'),
        //     'Accept'         => 'application/json',
        // ])
        // ->withHeaders([
        //     'Cookie' =>
        //         'SDPSESSIONID=' . env('SDPSESSIONID') . '; ' .
        //         '_zcsr_tmp=' . env('SDP_CSRF_COOKIE') . '; ' .
        //         'sdpcsrfcookie=' . env('SDP_CSRF_COOKIE'),
        // ])
        // ->get($url);

        Log::error('ASSET CATEGORY API DEBUG', [
            'status' => $response->status(),
            'body'   => $response->body(),
        ]);

        if (!$response->successful()) {
            throw new \Exception(
                'Asset Category API failed → ' .
                $response->status() . ' : ' . $response->body()
            );
        }

        return $response->json('asset_categories', []);
    }

        private function syncAssetCategories(array $categories): void
    {
        foreach ($categories as $category) {
            DB::table('categories')->updateOrInsert(
                ['external_category_id' => $category['id']],
                [
                    'name'          => $category['name'],
                    'category_type' => 'asset',
                    'created_at'    => now(),
                    'updated_at'    => now(),
                ]
            );
        }
    }

        private function syncDepartment(array $dept)
    {
        DB::table('departments')->updateOrInsert(
            ['id' => $dept['id']],
            [
                'name'       => $dept['name'],
                'company_id' => $this->config->company_id,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }




        private function syncCategory(array $product): int
    {
        $externalCategoryId =
            $product['category']['id']
            ?? $product['asset_category']['id']
            ?? null;

        if (!$externalCategoryId) {
            throw new \Exception('Category ID missing in product payload');
        }

        $category = DB::table('categories')
            ->where('external_category_id', $externalCategoryId)
            ->first();

        if (!$category) {
            throw new \Exception(
                'Category not synced for external ID: ' . $externalCategoryId
            );
        }

        return $category->id;
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

        // Category already synced
        $categoryId = $this->syncCategory($product);

        return DB::table('models')->insertGetId([
            'name'              => $product['name'] ?? 'Unknown Model', // iphone 17
            'model_number'      => $product['part_no'] ?? null,
            'category_id'       => $categoryId,
            'external_model_id' => $externalModelId,
            'created_by'        => 1,
            'fieldset_id'       => 1,
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);
    }





  private function fetchPurchaseOrders(): array
{
    // $url = 'https://localhost:8080/api/v3/purchase_orders';
    // $url = self::SDP_BASE_URL . '/purchase_orders';
    
    $response = $this->sdpGet('/purchase_orders');

    // $response = Http::withOptions([
    //     'verify'  => false, // allow self-signed cert
    //     'timeout' => 60,
    //     'curl' => [
    //         CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2,
    //     ],
    // ])
    // ->withHeaders([
    //     // SAME AS POSTMAN
    //     'TECHNICIAN_KEY' => env('SDP_TECHNICIAN_KEY'),
    //     'Accept'         => 'application/json',
    // ])
    // ->withHeaders([
    //     // 🔥 THIS IS IMPORTANT
    //     'Cookie' =>
    //         'SDPSESSIONID=' . env('SDPSESSIONID') . '; ' .
    //         '_zcsr_tmp=' . env('SDP_CSRF_COOKIE') . '; ' .
    //         'sdpcsrfcookie=' . env('SDP_CSRF_COOKIE'),
    // ])
    // ->get($url);

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
        // 🔒 1️⃣ Normalize input
        $location = trim((string) $location);

        // ❌ 2️⃣ Invalid / junk values → skip
        if (
            $location === '' ||
            $location === '-' ||
            strtoupper($location) === 'NA' ||
            strtoupper($location) === 'N/A'
        ) {
            return null;
        }

        // Example: "Bangalore, India"
        $parts = array_map('trim', explode(',', $location));

        $city    = $parts[0] ?? null;
        $country = $parts[1] ?? null;

        // 🔍 3️⃣ Check existing location
        $existing = DB::table('locations')
            ->where('name', $location)
            ->first();

        if ($existing) {
            return $existing->id;
        }

        // ✅ 4️⃣ Create only VALID location
        return DB::table('locations')->insertGetId([
            'name'       => $location,
            'city'       => $city,
            'country'    => $country,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    

        private function resolvePurchaseOrderId(?int $externalPoId): ?int
    {
        if (!$externalPoId) {
            return null;
        }

        return DB::table('purchase_orders')
            ->where('external_po_id', $externalPoId)
            ->value('id');
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
                'created_at'    => now(),
                'updated_at'    => now(),
            ]
        );
    }

    private function date($ms)
    {
        return $ms ? date('Y-m-d', $ms / 1000) : null;
    }
}
