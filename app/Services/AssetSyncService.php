<?php

namespace App\Services;

use App\Models\Asset;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Helpers\BarcodeGenerator;
use App\Models\ServiceDeskConfig;
use Illuminate\Support\Facades\Auth;

class AssetSyncService
{
    private ServiceDeskConfig $config;
    private string $baseApiUrl;
    private array $httpOptions;
    private array $headers;

    public function __construct()
    {
        // 🔹 IMPORTANT: Get config based on company and active status
        $this->config = ServiceDeskConfig::where('company_id', Auth::user()->company_id)
            ->where('is_active', 1)
            ->where('is_sync_enabled', 1)
            ->firstOrFail();

        // 🔹 Build API URL
        $this->baseApiUrl = rtrim($this->config->base_url, '/')
            . '/api/' . $this->config->api_version;

        $this->httpOptions = [
            'verify'  => (bool) $this->config->verify_ssl,
            'timeout' => (int) $this->config->timeout,
            'curl' => [
                CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2,
            ],
        ];

        // 🔹 BASE HEADERS (common for both modes)
        $this->headers = [
            'TECHNICIAN_KEY' => $this->config->technician_key,
            'Accept'         => 'application/json',
        ];

        // 🔹 IMPORTANT: Production mode - ADD ONLY IF AVAILABLE
        // NOT COMPULSORY - Add only if config has these values
        if ($this->config->mode === 'production') {
            // Add PORTALID only if it exists (not compulsory)
            if (!empty($this->config->portal_id)) {
                $this->headers['PORTALID'] = $this->config->portal_id;
            }

            // Add session and CSRF cookies ONLY if available
            $sessionData = $this->getProductionSessionData();
            if (!empty($sessionData['session_id']) && !empty($sessionData['csrf_token'])) {
                $this->headers['Cookie'] =
                    'SDPSESSIONID=' . $sessionData['session_id'] . '; ' .
                    '_zcsr_tmp=' . $sessionData['csrf_token'] . '; ' .
                    'sdpcsrfcookie=' . $sessionData['csrf_token'];
            }
        }
        
        // 🔹 Sandbox mode - only TECHNICIAN_KEY required
        // Nothing extra to add
    }

    /**
     * Get production session data - OPTIONAL, NOT COMPULSORY
     */
    private function getProductionSessionData(): ?array
    {
        // Check if session data exists in cache first
        $cacheKey = 'sdp_production_session_' . $this->config->id;
        $sessionData = cache()->get($cacheKey);
        
        if ($sessionData) {
            return $sessionData;
        }
        
        // Check if stored in config table (optional fields)
        if (!empty($this->config->session_id) && !empty($this->config->csrf_token)) {
            return [
                'session_id' => $this->config->session_id,
                'csrf_token' => $this->config->csrf_token,
            ];
        }
        
        // Return null - it's okay if not available
        return null;
    }

    /**
     * 🔹 UPDATED: Validate config - NOT STRICT, just informative
     */
    public function validateConfig(): array
    {
        $warnings = [];
        $info = [];
        
        if ($this->config->mode === 'production') {
            $info[] = 'Running in PRODUCTION mode';
            
            // These are just WARNINGS, not errors
            if (empty($this->config->portal_id)) {
                $warnings[] = 'PORTALID not configured (may be required by some SDP APIs)';
            }
            
            $sessionData = $this->getProductionSessionData();
            if (empty($sessionData)) {
                $warnings[] = 'Session cookies not configured (may be required by some SDP APIs)';
            } else {
                $info[] = 'Session cookies available';
            }
        } else {
            $info[] = 'Running in SANDBOX mode';
        }
        
        return [
            'is_valid' => true, // Always valid - we'll try anyway
            'mode' => $this->config->mode,
            'info' => $info,
            'warnings' => $warnings,
            'has_technician_key' => !empty($this->config->technician_key),
            'has_portal_id' => !empty($this->config->portal_id),
            'has_session' => !empty($this->getProductionSessionData()),
            'base_url' => $this->config->base_url,
        ];
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
            Log::info('Starting sync in ' . $this->config->mode . ' mode', [
                'company_id' => $this->config->company_id,
                'base_url' => $this->config->base_url,
                'has_portal_id' => !empty($this->config->portal_id),
                'has_session' => !empty($this->getProductionSessionData()),
            ]);

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
             * STEP 2: FETCH AND SYNC ASSETS
             * ------------------------------------------------
             */
            $assets = $this->fetchAllAssets();

            Log::info('Fetched assets from SDP', [
                'count' => count($assets),
                'mode' => $this->config->mode,
            ]);

            $existingIds = Asset::where('external_source', 'SDP')
                ->pluck('external_asset_id')
                ->toArray();

            $inserted = 0;
            $skipped = 0;

            foreach ($assets as $item) {
                if (in_array($item['id'], $existingIds)) {
                    $skipped++;
                    continue;
                }

                // Fetch asset details for attachments
                $assetDetailsResponse = $this->sdpGet('/assets/' . $item['id']);

                if (!$assetDetailsResponse->successful()) {
                    Log::warning('Asset detail fetch failed', [
                        'asset_id' => $item['id'],
                        'mode' => $this->config->mode,
                        'status' => $assetDetailsResponse->status(),
                    ]);
                    $skipped++;
                    continue;
                }

                $assetDetails = $assetDetailsResponse->json('asset', []);

                // Sync vendor if present
                if (!empty($item['vendor'])) {
                    $this->syncVendor($item['vendor']);
                }

                // Sync user if present
                if (!empty($item['user'])) {
                    $this->syncUser($item['user']);
                }

                // Sync location if present
                $locationId = null;
                if (!empty($item['location'])) {
                    $locationId = $this->syncLocation($item['location']);
                }

                $asset = new Asset();
                $asset->unguard();
                $asset->timestamps = false;

                $asset->external_asset_id = $item['id'];
                $asset->external_source = 'SDP';

                $asset->name = $item['name'] ?? 'SDP Asset';
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
                $asset->rtd_location_id = $locationId;

                $asset->purchase_date = $this->date($item['acquisition_date']['value'] ?? null);
                $asset->asset_eol_date = $this->date($item['expiry_date']['value'] ?? null);

                // 🔗 PURCHASE ORDER (SAFE RESOLUTION)
                $poNumber = $item['purchase_order_no'] ?? null;
                $purchaseOrder = null;

                if ($poNumber) {
                    $purchaseOrder = DB::table('purchase_orders')
                        ->where('custom_po_id', (string) $poNumber)
                        ->orWhere('external_po_id', (int) $poNumber)
                        ->first();
                }

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

                // 🔹 ASSIGNED USER (ASSET → PO FALLBACK)
                if (!empty($item['user']['id'])) {
                    $asset->assigned_to = $item['user']['id'];
                    $asset->assigned_type = 'App\\Models\\User';
                } elseif (!empty($purchaseOrder?->requested_by)) {
                    $asset->assigned_to = $purchaseOrder->requested_by;
                    $asset->assigned_type = 'App\\Models\\User';
                } else {
                    $asset->assigned_to = null;
                    $asset->assigned_type = null;
                }

                // 🔹 MODEL
                $modelId = null;
                if (!empty($item['product'])) {
                    $modelId = $this->syncModel($item['product']);
                }
                $asset->model_id = $modelId;

                // 🔹 STATUS
                $statusId = $this->syncStatus($item['state'] ?? []);
                $asset->status_id = $statusId;

                // 🔹 COMPANY
                $asset->company_id = $this->config->company_id;
                $asset->last_audit_date = now();

                // 🔹 DEPARTMENT
                if (!empty($item['department'])) {
                    $this->syncDepartment($item['department']);
                    $asset->department_id = $item['department']['id'];
                }

                $asset->save();

                // 🔹 BARCODE: generate ONLY ONCE
                if (empty($asset->_snipeit_barcode_2)) {
                    if (!empty($item['barcode'])) {
                        $asset->_snipeit_barcode_2 = $item['barcode'];
                    } else {
                        $asset->_snipeit_barcode_2 = BarcodeGenerator::generate($asset);
                    }
                    $asset->save();
                }

                // 🔹 DOWNLOAD ASSET IMAGE
                $this->downloadAssetImage($asset, $assetDetails);

                $inserted++;
            }

            DB::commit();

            Log::info('Sync completed successfully', [
                'mode' => $this->config->mode,
                'inserted' => $inserted,
                'skipped' => $skipped,
                'total' => count($assets),
            ]);

            return [
                'inserted' => $inserted,
                'skipped' => $skipped,
                'total' => count($assets),
                'mode' => $this->config->mode,
            ];
        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('Asset Sync Failed in ' . $this->config->mode . ' mode', [
                'error' => $e->getMessage(),
                'company_id' => $this->config->company_id,
                'config_id' => $this->config->id,
                'trace' => $e->getTraceAsString(),
            ]);

            throw new \Exception('Asset sync failed in ' . $this->config->mode . ' mode: ' . $e->getMessage());
        }
    }

    /**
     * Fetch all assets from SDP with pagination
     */
    private function fetchAllAssets(): array
    {
        $allAssets = [];
        $start = 0;
        $limit = 100;
        $hasMore = true;

        while ($hasMore) {
            $response = $this->sdpGet('/assets', [
                'input_data' => json_encode([
                    'list_info' => [
                        'start_index' => $start,
                        'row_count' => $limit,
                    ]
                ])
            ]);

            if (!$response->successful()) {
                Log::error('SDP Asset fetch failed in ' . $this->config->mode . ' mode', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'headers_sent' => array_keys($this->headers),
                ]);
                throw new \Exception(
                    'SDP Asset fetch failed in ' . $this->config->mode . ' mode: ' . 
                    $response->status() . ' - ' . $response->body()
                );
            }

            $json = $response->json();
            $assets = $json['assets'] ?? [];
            $allAssets = array_merge($allAssets, $assets);

            $listInfo = $json['list_info'] ?? [];
            $hasMore = $listInfo['has_more_rows'] ?? false;
            $start += $limit;
        }

        return $allAssets;
    }

    /**
     * Push barcode back to SDP
     */
    public function pushBarcodeToSdp(Asset $asset): void
    {
        if (empty($asset->external_asset_id) || empty($asset->_snipeit_barcode_2)) {
            throw new \Exception('Asset missing external ID or barcode');
        }

        $response = Http::withOptions($this->httpOptions)
            ->withHeaders($this->headers)
            ->asForm()
            ->put($this->baseApiUrl . '/assets/' . $asset->external_asset_id, [
                'input_data' => json_encode([
                    'asset' => [
                        'barcode' => $asset->_snipeit_barcode_2,
                    ]
                ])
            ]);

        if (!$response->successful()) {
            Log::error('Failed to push barcode in ' . $this->config->mode . ' mode', [
                'status' => $response->status(),
                'body' => $response->body(),
                'asset_id' => $asset->id,
                'headers_sent' => array_keys($this->headers),
            ]);
            throw new \Exception(
                'Failed to push barcode in ' . $this->config->mode . ' mode: ' . 
                $response->status() . ' - ' . $response->body()
            );
        }

        Log::info('Barcode pushed to SDP successfully', [
            'mode' => $this->config->mode,
            'asset_id' => $asset->id,
            'external_asset_id' => $asset->external_asset_id,
        ]);
    }

    /**
     * Generic SDP GET request
     */
    private function sdpGet(string $endpoint, array $params = [])
    {
        $url = $this->baseApiUrl . $endpoint;
        
        Log::debug('SDP API Request', [
            'mode' => $this->config->mode,
            'url' => $url,
            'headers_count' => count($this->headers),
        ]);

        return Http::withOptions($this->httpOptions)
            ->withHeaders($this->headers)
            ->get($url, $params);
    }

    /**
     * 🔹 All your original sync methods remain EXACTLY THE SAME
     * I'm keeping the method signatures only to show they're unchanged
     */
    
    private function syncStatus(array $state): int
    {
        // Your original code unchanged
        if (empty($state['name'])) return 1;
        
        $stateName = trim($state['name']);
        $map = [
            'In Store' => ['deployable' => 1, 'pending' => 0, 'archived' => 0],
            'In Use' => ['deployable' => 1, 'pending' => 0, 'archived' => 0],
            'To Be Returned' => ['deployable' => 0, 'pending' => 1, 'archived' => 0],
            'In Repair' => ['deployable' => 0, 'pending' => 1, 'archived' => 0],
            'Expired' => ['deployable' => 0, 'pending' => 0, 'archived' => 1],
            'Disposed' => ['deployable' => 0, 'pending' => 0, 'archived' => 1],
        ];

        $flags = $map[$stateName] ?? ['deployable' => 0, 'pending' => 1, 'archived' => 0];

        DB::table('status_labels')->updateOrInsert(
            ['name' => $stateName],
            array_merge($flags, ['updated_at' => now(), 'created_at' => now()])
        );

        return DB::table('status_labels')->where('name', $stateName)->value('id') ?? 1;
    }

    private function uniqueSerial(?string $serial, int $externalAssetId): string
    {
        if (!empty($serial)) {
            $exists = DB::table('assets')->where('serial', $serial)->exists();
            if (!$exists) return $serial;
        }
        return 'SDP-SN-' . $externalAssetId . '-' . now()->timestamp;
    }

    private function uniqueAssetTag(?string $tag, int $externalAssetId): string
    {
        if (!empty($tag)) {
            $exists = DB::table('assets')->where('asset_tag', $tag)->exists();
            if (!$exists) return $tag;
        }

        $base = 'SDP-' . $externalAssetId;
        $final = $base;
        $i = 1;

        while (DB::table('assets')->where('asset_tag', $final)->exists()) {
            $final = $base . '-' . $i;
            $i++;
        }
        return $final;
    }

    private function downloadAssetImage(Asset $asset, array $assetDetails): void
    {
        $attachments = $assetDetails['attachments'] ?? [];
        if (empty($attachments)) return;

        foreach ($attachments as $attachment) {
            if (!empty($attachment['content_url']) && str_starts_with($attachment['content_type'] ?? '', 'image/')) {
                $downloadUrl = rtrim($this->config->base_url, '/') . $attachment['content_url'];
                try {
                    $imageResponse = Http::withOptions($this->httpOptions)
                        ->withHeaders($this->headers)
                        ->timeout(30)
                        ->get($downloadUrl);

                    if ($imageResponse->successful()) {
                        $ext = pathinfo($attachment['name'], PATHINFO_EXTENSION) ?: 'jpg';
                        $filename = 'asset-image-' . $asset->id . '.' . $ext;
                        Storage::disk('public')->put(app('assets_upload_path') . $filename, $imageResponse->body());
                        $asset->update(['image' => $filename]);
                        break;
                    }
                } catch (\Throwable $e) {
                    Log::warning('Failed to download asset image', ['asset_id' => $asset->id, 'error' => $e->getMessage()]);
                }
            }
        }
    }

    private function fetchAssetCategories(): array
    {
        $response = $this->sdpGet('/asset_categories');
        if (!$response->successful()) {
            throw new \Exception('Asset Category API failed: ' . $response->status() . ' : ' . $response->body());
        }
        return $response->json('asset_categories', []);
    }

    private function syncAssetCategories(array $categories): void
    {
        foreach ($categories as $category) {
            DB::table('categories')->updateOrInsert(
                ['external_category_id' => $category['id']],
                ['name' => $category['name'], 'category_type' => 'asset', 'created_at' => now(), 'updated_at' => now()]
            );
        }
    }

    private function syncDepartment(array $dept): void
    {
        DB::table('departments')->updateOrInsert(
            ['id' => $dept['id']],
            ['name' => $dept['name'], 'company_id' => $this->config->company_id, 'created_at' => now(), 'updated_at' => now()]
        );
    }

    private function syncModel(array $product): int
    {
        if (empty($product['id'])) throw new \Exception('Product ID missing in asset payload');
        
        $externalModelId = $product['id'];
        $model = DB::table('models')->where('external_model_id', $externalModelId)->first();
        if ($model) return $model->id;

        $categoryId = null;
        if (!empty($product['category']) || !empty($product['asset_category'])) {
            $categoryData = $product['category'] ?? $product['asset_category'];
            $externalCategoryId = $categoryData['id'] ?? null;
            if ($externalCategoryId) {
                $category = DB::table('categories')->where('external_category_id', $externalCategoryId)->first();
                if (!$category) {
                    $categoryId = DB::table('categories')->insertGetId([
                        'name' => $categoryData['name'] ?? 'Unknown Category',
                        'category_type' => 'asset',
                        'external_category_id' => $externalCategoryId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                } else {
                    $categoryId = $category->id;
                }
            }
        }

        return DB::table('models')->insertGetId([
            'name' => $product['name'] ?? 'Unknown Model',
            'model_number' => $product['part_no'] ?? null,
            'category_id' => $categoryId,
            'external_model_id' => $externalModelId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function fetchPurchaseOrders(): array
    {
        $response = $this->sdpGet('/purchase_orders');
        if (!$response->successful()) {
            throw new \Exception('Purchase Order API failed: ' . $response->status() . ' : ' . $response->body());
        }
        return $response->json('purchase_orders', []);
    }

    private function syncVendor(array $vendor): void
    {
        DB::table('suppliers')->updateOrInsert(
            ['id' => $vendor['id']],
            ['name' => $vendor['name'], 'email' => $vendor['email_id'] ?? null, 'created_at' => now(), 'updated_at' => now()]
        );
    }

    private function syncUser(array $user): void
    {
        DB::table('users')->updateOrInsert(
            ['id' => $user['id']],
            ['first_name' => $user['name'], 'email' => $user['email_id'] ?? null, 'phone' => $user['mobile'] ?? null, 'created_at' => now(), 'updated_at' => now()]
        );
    }

    private function syncLocation($location): ?int
    {
        if (is_array($location)) {
            $locationName = $location['name'] ?? null;
        } else {
            $locationName = trim((string) $location);
        }

        if (empty($locationName)) return null;
        
        $invalidValues = ['-', 'NA', 'N/A', 'N\A', ''];
        if (in_array(strtoupper($locationName), array_map('strtoupper', $invalidValues))) return null;

        $existing = DB::table('locations')->where('name', $locationName)->first();
        if ($existing) return $existing->id;

        $city = null;
        $country = null;
        if (is_string($locationName) && str_contains($locationName, ',')) {
            $parts = array_map('trim', explode(',', $locationName));
            $city = $parts[0] ?? null;
            $country = $parts[1] ?? null;
        }

        return DB::table('locations')->insertGetId([
            'name' => $locationName,
            'city' => $city,
            'country' => $country,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function syncPurchaseOrder(array $po): void
    {
        DB::table('purchase_orders')->updateOrInsert(
            ['external_po_id' => $po['id']],
            [
                'custom_po_id' => $po['custom_po_id'] ?? null,
                'po_name' => $po['name'],
                'total_price' => $po['total_price'] ?? 0,
                'base_total_price' => $po['base_total_price'] ?? 0,
                'status_name' => $po['status']['name'] ?? null,
                'status_id' => $po['status']['id'] ?? null,
                'supplier_id' => $po['vendor']['id'] ?? null,
                'requested_by' => $po['requested_by']['id'] ?? null,
                'owner_id' => $po['owner']['id'] ?? null,
                'created_date' => $this->date($po['created_date']['value'] ?? null),
                'required_date' => $this->date($po['required_date']['value'] ?? null),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    private function date($ms): ?string
    {
        return $ms ? date('Y-m-d', $ms / 1000) : null;
    }

    /**
     * 🔹 Get current mode
     */
    public function getCurrentMode(): string
    {
        return $this->config->mode;
    }
}