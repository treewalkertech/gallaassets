<?php

namespace App\Services;

use App\Models\Asset;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;
use App\Helpers\BarcodeGenerator;
use App\Models\ServiceDeskConfig;
use Illuminate\Support\Facades\Auth;

class AssetSyncService
{
    private ServiceDeskConfig $config;
    private int $startIndex = 0;
    private string $baseApiUrl;
    private array $httpOptions;
    private array $headers;
    private int $totalAssetsToFetch;
    private int $chunkSize;
    private array $performanceStats;

    public function __construct(int $limit = 0, int $chunkSize = 500)
    {
        // Set performance stats
        $this->performanceStats = [
            'start_time' => microtime(true),
            'memory_start' => memory_get_usage(true),
            'queries_count' => 0,
        ];
        
        // Set limits
        $this->totalAssetsToFetch = $limit > 0 ? $limit : 0; // 0 means fetch all
        $this->chunkSize = $chunkSize;
        
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
        if ($this->config->mode === 'production') {
            if (!empty($this->config->portal_id)) {
                $this->headers['PORTALID'] = $this->config->portal_id;
            }

            $sessionData = $this->getProductionSessionData();
            if (!empty($sessionData['session_id']) && !empty($sessionData['csrf_token'])) {
                $this->headers['Cookie'] =
                    'SDPSESSIONID=' . $sessionData['session_id'] . '; ' .
                    '_zcsr_tmp=' . $sessionData['csrf_token'] . '; ' .
                    'sdpcsrfcookie=' . $sessionData['csrf_token'];
            }
        }

        $this->startIndex = (int) ($this->config->last_asset_sync_index ?? 0);

    }

    /**
     * NEW: Set custom fetch limits
     */
    public function setFetchLimits(int $limit, int $chunkSize = 500): self
    {
        $this->totalAssetsToFetch = $limit;
        $this->chunkSize = $chunkSize;
        return $this;
    }

    /**
     * Get production session data
     */
    private function getProductionSessionData(): ?array
    {
        $cacheKey = 'sdp_production_session_' . $this->config->id;
        $sessionData = cache()->get($cacheKey);
        
        if ($sessionData) {
            return $sessionData;
        }
        
        if (!empty($this->config->session_id) && !empty($this->config->csrf_token)) {
            return [
                'session_id' => $this->config->session_id,
                'csrf_token' => $this->config->csrf_token,
            ];
        }
        
        return null;
    }

    /**
     * 🔹 UPDATED: Validate config
     */
    public function validateConfig(): array
    {
        $warnings = [];
        $info = [];
        
        if ($this->config->mode === 'production') {
            $info[] = 'Running in PRODUCTION mode';
            
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
            'is_valid' => true,
            'mode' => $this->config->mode,
            'info' => $info,
            'warnings' => $warnings,
            'has_technician_key' => !empty($this->config->technician_key),
            'has_portal_id' => !empty($this->config->portal_id),
            'has_session' => !empty($this->getProductionSessionData()),
            'base_url' => $this->config->base_url,
        ];
    }

    /**
     * MAIN SYNC METHOD - OPTIMIZED
     */
    public function sync(): array
    {
        $this->performanceStats['sync_start'] = microtime(true);
        
        // Set memory limit
        ini_set('memory_limit', '1024M');
        gc_enable();
        
        // Disable query log for performance
        DB::disableQueryLog();

        DB::beginTransaction();

        try {
            /**
             * STEP 1: SYNC PURCHASE ORDERS
             */
            Log::info('Starting sync in ' . $this->config->mode . ' mode', [
                'company_id' => $this->config->company_id,
                'fetch_limit' => $this->totalAssetsToFetch,
                'chunk_size' => $this->chunkSize,
            ]);

            $purchaseOrders = $this->fetchPurchaseOrders();

            // ✅ OPTIMIZED: Bulk sync vendors and users
            $this->bulkSyncVendorsAndUsers($purchaseOrders);

            foreach ($purchaseOrders as $po) {
                $this->syncPurchaseOrder($po);
            }

            /**
             * STEP 1.5: SYNC ASSET CATEGORIES
             */
            $assetCategories = $this->fetchAssetCategories();
            $this->syncAssetCategories($assetCategories);

            /**
             * STEP 2: FETCH AND SYNC ASSETS WITH PAGINATION OPTION
             */
            $assets = $this->fetchAssetsWithLimit();
            
            $this->performanceStats['assets_fetched'] = count($assets);
            $this->performanceStats['fetch_time'] = microtime(true) - $this->performanceStats['sync_start'];

            Log::info('Fetched assets from SDP', [
                'count' => count($assets),
                'mode' => $this->config->mode,
                'fetch_limit' => $this->totalAssetsToFetch,
                'fetch_time' => round($this->performanceStats['fetch_time'], 2) . 's',
            ]);

            // ✅ OPTIMIZED: Get all existing IDs in one query
            $existingIds = Asset::where('external_source', 'SDP')
                ->pluck('external_asset_id')
                ->flip()
                ->toArray();

            $inserted = 0;
            $skipped = 0;
            $failed = 0;

            // ✅ OPTIMIZED: Pre-load purchase orders for faster lookup
            $purchaseOrdersMap = $this->getPurchaseOrdersMap();

            // ✅ OPTIMIZED: Process in chunks
            foreach (array_chunk($assets, $this->chunkSize) as $chunkIndex => $assetsChunk) {
                $this->performanceStats['chunk_' . $chunkIndex . '_start'] = microtime(true);
                
                Log::info("Processing chunk {$chunkIndex}", [
                    'chunk_size' => count($assetsChunk),
                    'total_chunks' => ceil(count($assets) / $this->chunkSize),
                    'memory_usage' => round(memory_get_usage(true) / 1024 / 1024, 2) . 'MB',
                ]);

                $assetsToInsert = [];
                $barcodeUpdates = [];
                $assetsDetailsMap = [];

                // ✅ OPTIMIZED: Fetch multiple asset details in parallel (optional)
                if ($this->config->fetch_details_in_bulk) {
                    $assetIds = array_column($assetsChunk, 'id');
                    $assetsDetailsMap = $this->fetchMultipleAssetDetails($assetIds);
                }

                foreach ($assetsChunk as $item) {
                    if (isset($existingIds[$item['id']])) {
                        $skipped++;
                        continue;
                    }

                    // ✅ OPTIMIZED: Get asset details from map or fetch individually
                    $assetDetails = $assetsDetailsMap[$item['id']] ?? $this->fetchAssetDetailsWithCache($item['id']);

                    if (empty($assetDetails)) {
                        $failed++;
                        Log::warning('Failed to fetch asset details', ['asset_id' => $item['id']]);
                        continue;
                    }

                    // ✅ OPTIMIZED: Sync related entities in batch
                    $this->syncRelatedEntitiesForAsset($item);

                    // Prepare asset data
                    $assetData = $this->prepareAssetData($item, $purchaseOrdersMap);
                    
                    if (empty($assetData)) {
                        $failed++;
                        continue;
                    }

                    // Generate barcode
                    $barcode = $this->generateBarcodeForAsset($item, $assetData);
                    if ($barcode) {
                        $assetData['_snipeit_barcode_2'] = $barcode;
                        $barcodeUpdates[$item['id']] = $barcode;
                    }

                    // Add timestamps
                    $assetData['created_at'] = now();
                    $assetData['updated_at'] = now();

                    $assetsToInsert[] = $assetData;
                    $existingIds[$item['id']] = true;

                    // ✅ OPTIMIZED: Queue image download for later
                    $this->queueAssetImageDownload($item['id'], $assetDetails);
                }

                // ✅ OPTIMIZED: Bulk insert
                if (!empty($assetsToInsert)) {
                    try {
                        Asset::insert($assetsToInsert);
                        $inserted += count($assetsToInsert);
                        
                        Log::info("Inserted chunk {$chunkIndex}", [
                            'inserted' => count($assetsToInsert),
                            'total_inserted' => $inserted,
                        ]);
                    } catch (\Exception $e) {
                        $failed += count($assetsToInsert);
                        Log::error('Bulk insert failed for chunk ' . $chunkIndex, [
                            'error' => $e->getMessage(),
                            'count' => count($assetsToInsert),
                        ]);
                    }
                }

                // Clear memory
                unset($assetsToInsert, $assetsDetailsMap);
                gc_collect_cycles();
                
                $this->performanceStats['chunk_' . $chunkIndex . '_time'] = 
                    microtime(true) - $this->performanceStats['chunk_' . $chunkIndex . '_start'];
            }

            DB::commit();

            // Calculate final performance stats
            $this->calculatePerformanceStats($inserted, $skipped, $failed);

            Log::info('Sync completed successfully', [
                'mode' => $this->config->mode,
                'inserted' => $inserted,
                'skipped' => $skipped,
                'failed' => $failed,
                'total_fetched' => count($assets),
                'performance' => $this->performanceStats,
            ]);

            return [
                'inserted' => $inserted,
                'skipped' => $skipped,
                'failed' => $failed,
                'total' => count($assets),
                'mode' => $this->config->mode,
                'fetch_limit' => $this->totalAssetsToFetch,
                'chunk_size' => $this->chunkSize,
                'performance' => $this->performanceStats,
            ];
        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('Asset Sync Failed in ' . $this->config->mode . ' mode', [
                'error' => $e->getMessage(),
                'company_id' => $this->config->company_id,
                'config_id' => $this->config->id,
                'fetch_limit' => $this->totalAssetsToFetch,
                'performance' => $this->performanceStats,
            ]);

            throw new \Exception('Asset sync failed in ' . $this->config->mode . ' mode: ' . $e->getMessage());
        }
    }

    /**
     * NEW: Fetch assets with limit option
     */
   private function fetchAssetsWithLimit(): array
{
    $allAssets = [];
    $start = $this->startIndex;
    $limit = 100;
    $hasMore = true;
    $fetchedCount = 0;

    while ($hasMore && ($this->totalAssetsToFetch === 0 || $fetchedCount < $this->totalAssetsToFetch)) {

        $currentLimit = min(
            $limit,
            $this->totalAssetsToFetch > 0
                ? ($this->totalAssetsToFetch - $fetchedCount)
                : $limit
        );

        $response = $this->sdpGet('/assets', [
            'input_data' => json_encode([
                'list_info' => [
                    'start_index' => $start,
                    'row_count' => $currentLimit,
                ]
            ])
        ]);

        if (!$response->successful()) break;

        $json = $response->json();
        $assets = $json['assets'] ?? [];

        if (empty($assets)) break;

        $allAssets = array_merge($allAssets, $assets);

        $fetchedCount += count($assets);
        $start += count($assets);

        $hasMore = $json['list_info']['has_more_rows'] ?? false;

        usleep(100000);
    }

    // 🔹 SAVE LAST SYNC INDEX
    $this->updateLastSyncIndex($start);

    return $allAssets;
}

private function updateLastSyncIndex(int $index): void
{
    ServiceDeskConfig::where('id', $this->config->id)
        ->update([
            'last_asset_sync_index' => $index,
            'updated_at' => now(),
        ]);
}


    /**
     * NEW: Bulk sync vendors and users
     */
    private function bulkSyncVendorsAndUsers(array $purchaseOrders): void
    {
        $vendors = [];
        $users = [];

        foreach ($purchaseOrders as $po) {
            if (!empty($po['vendor'])) {
                $vendors[$po['vendor']['id']] = $po['vendor'];
            }
            if (!empty($po['requested_by'])) {
                $users[$po['requested_by']['id']] = $po['requested_by'];
            }
            if (!empty($po['owner'])) {
                $users[$po['owner']['id']] = $po['owner'];
            }
        }

        // Bulk insert vendors
        if (!empty($vendors)) {
            $vendorData = [];
            foreach ($vendors as $vendor) {
                $vendorData[] = [
                    'id' => $vendor['id'],
                    'name' => $vendor['name'],
                    'email' => $vendor['email_id'] ?? null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
            DB::table('suppliers')->upsert($vendorData, ['id']);
        }

        // Bulk insert users
        if (!empty($users)) {
            $userData = [];
            foreach ($users as $user) {
                $userData[] = [
                    'id' => $user['id'],
                    'first_name' => $user['name'],
                    'email' => $user['email_id'] ?? null,
                    'phone' => $user['mobile'] ?? null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
            DB::table('users')->upsert($userData, ['id']);
        }
    }

    /**
     * NEW: Get purchase orders map for fast lookup
     */
    private function getPurchaseOrdersMap(): array
    {
        $purchaseOrders = DB::table('purchase_orders')
            ->select('id', 'custom_po_id', 'external_po_id', 'status_name', 'requested_by')
            ->get();

        $map = [];
        foreach ($purchaseOrders as $po) {
            if ($po->custom_po_id) {
                $map[$po->custom_po_id] = $po;
            }
            if ($po->external_po_id) {
                $map[$po->external_po_id] = $po;
            }
        }
        
        return $map;
    }

    /**
     * NEW: Prepare asset data efficiently
     */
    private function prepareAssetData(array $item, array $purchaseOrdersMap): array
    {
        $locationId = null;
        if (!empty($item['location'])) {
            $locationId = $this->syncLocation($item['location']);
        }

        $poNumber = $item['purchase_order_no'] ?? null;
        $purchaseOrder = $purchaseOrdersMap[$poNumber] ?? null;

        // Calculate requestable
        $requestable = 0;
        if (!empty($purchaseOrder)) {
            $poStatus = strtolower($purchaseOrder->status_name ?? '');
            $requestable = in_array($poStatus, ['received', 'completed', 'closed']) ? 1 : 0;
        }

        // Get assigned user
        $assignedTo = null;
        $assignedType = null;
        if (!empty($item['user']['id'])) {
            $assignedTo = $item['user']['id'];
            $assignedType = 'App\\Models\\User';
        } elseif (!empty($purchaseOrder->requested_by)) {
            $assignedTo = $purchaseOrder->requested_by;
            $assignedType = 'App\\Models\\User';
        }

        // Get model ID
        $modelId = null;
        if (!empty($item['product'])) {
            $modelId = $this->syncModel($item['product']);
        }

        // Get status ID
        $statusId = $this->syncStatus($item['state'] ?? []);

        // Get department ID
        $departmentId = null;
        if (!empty($item['department'])) {
            $this->syncDepartment($item['department']);
            $departmentId = $item['department']['id'];
        }

        return [
            'external_asset_id' => $item['id'],
            'external_source' => 'SDP',
            'name' => $item['name'] ?? 'SDP Asset',
            'serial' => $this->uniqueSerial($item['org_serial_number'] ?? null, (int)$item['id']),
            'asset_tag' => $this->uniqueAssetTag($item['asset_tag'] ?? null, (int)$item['id']),
            'order_number' => $poNumber,
            'current_value' => $item['current_cost'] ?? null,
            'purchase_cost' => $item['purchase_cost'] ?? 0,
            'rtd_location_id' => $locationId,
            'purchase_date' => $this->date($item['acquisition_date']['value'] ?? null),
            'asset_eol_date' => $this->date($item['expiry_date']['value'] ?? null),
            'purchase_order_id' => $purchaseOrder->id ?? null,
            'requestable' => $requestable,
            'supplier_id' => $item['vendor']['id'] ?? null,
            'assigned_to' => $assignedTo,
            'assigned_type' => $assignedType,
            'model_id' => $modelId,
            'status_id' => $statusId,
            'company_id' => $this->config->company_id,
            'department_id' => $departmentId,
            'last_audit_date' => now(),
            '_snipeit_barcode_2' => $item['barcode'] ?? null,
        ];
    }

    /**
     * NEW: Generate barcode for asset
     */
    private function generateBarcodeForAsset(array $item, array $assetData): ?string
    {
        if (!empty($item['barcode'])) {
            return $item['barcode'];
        }
        
        try {
            $tempAsset = new Asset($assetData);
            return BarcodeGenerator::generate($tempAsset);
        } catch (\Exception $e) {
            Log::warning('Failed to generate barcode', [
                'asset_id' => $item['id'],
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * NEW: Queue asset image download
     */
    private function queueAssetImageDownload(string $assetId, array $assetDetails): void
    {
        $attachments = $assetDetails['attachments'] ?? [];
        if (empty($attachments)) return;

        foreach ($attachments as $attachment) {
            if (!empty($attachment['content_url']) && str_starts_with($attachment['content_type'] ?? '', 'image/')) {
                // You can implement queue job here
                // dispatch(new DownloadAssetImageJob($assetId, $attachment, $this->config));
                break;
            }
        }
    }

    /**
     * NEW: Sync related entities for asset
     */
    private function syncRelatedEntitiesForAsset(array $item): void
    {
        // Sync vendor
        if (!empty($item['vendor'])) {
            DB::table('suppliers')->updateOrInsert(
                ['id' => $item['vendor']['id']],
                [
                    'name' => $item['vendor']['name'],
                    'email' => $item['vendor']['email_id'] ?? null,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }

        // Sync user
        if (!empty($item['user'])) {
            DB::table('users')->updateOrInsert(
                ['id' => $item['user']['id']],
                [
                    'first_name' => $item['user']['name'],
                    'email' => $item['user']['email_id'] ?? null,
                    'phone' => $item['user']['mobile'] ?? null,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }

    /**
     * NEW: Fetch multiple asset details in parallel
     */
    private function fetchMultipleAssetDetails(array $assetIds): array
    {
        $results = [];
        $batchSize = 10; // Fetch 10 at a time
        
        foreach (array_chunk($assetIds, $batchSize) as $batch) {
            $promises = [];
            
            foreach ($batch as $assetId) {
                $cacheKey = "sdp_asset_details_{$assetId}_{$this->config->id}";
                
                // Try cache first
                $cached = Cache::get($cacheKey);
                if ($cached !== null) {
                    $results[$assetId] = $cached;
                    continue;
                }
                
                // Otherwise, fetch
                $promises[$assetId] = function () use ($assetId, $cacheKey) {
                    $response = $this->sdpGet('/assets/' . $assetId);
                    if ($response->successful()) {
                        $data = $response->json('asset', []);
                        Cache::put($cacheKey, $data, 300); // Cache for 5 minutes
                        return $data;
                    }
                    return [];
                };
            }
            
            // Execute promises (you can use Guzzle for real parallel requests)
            foreach ($promises as $assetId => $promise) {
                try {
                    $results[$assetId] = $promise();
                } catch (\Exception $e) {
                    Log::warning('Failed to fetch asset details in batch', [
                        'asset_id' => $assetId,
                        'error' => $e->getMessage(),
                    ]);
                    $results[$assetId] = [];
                }
            }
            
            // Small delay to avoid rate limiting
            usleep(50000); // 50ms
        }
        
        return $results;
    }

    /**
     * NEW: Fetch asset details with cache
     */
    private function fetchAssetDetailsWithCache(string $assetId): array
    {
        $cacheKey = "sdp_asset_details_{$assetId}_{$this->config->id}";
        
        return Cache::remember($cacheKey, 300, function () use ($assetId) {
            $response = $this->sdpGet('/assets/' . $assetId);
            
            if (!$response->successful()) {
                Log::warning('Asset detail fetch failed', [
                    'asset_id' => $assetId,
                    'status' => $response->status(),
                ]);
                return [];
            }
            
            return $response->json('asset', []);
        });
    }

    /**
     * NEW: Calculate performance statistics
     */
    private function calculatePerformanceStats(int $inserted, int $skipped, int $failed): void
    {
        $endTime = microtime(true);
        
        $this->performanceStats['total_time'] = round($endTime - $this->performanceStats['start_time'], 2);
        $this->performanceStats['memory_peak'] = round(memory_get_peak_usage(true) / 1024 / 1024, 2) . 'MB';
        $this->performanceStats['memory_end'] = round(memory_get_usage(true) / 1024 / 1024, 2) . 'MB';
        $this->performanceStats['inserted'] = $inserted;
        $this->performanceStats['skipped'] = $skipped;
        $this->performanceStats['failed'] = $failed;
        $this->performanceStats['assets_per_second'] = $inserted > 0 
            ? round($inserted / $this->performanceStats['total_time'], 2) 
            : 0;
    }

    /**
     * ORIGINAL METHODS (Optimized where possible)
     */
    
    private function syncStatus(array $state): int
    {
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
            // Check cache first
            $cacheKey = 'serial_exists_' . md5($serial);
            if (!Cache::has($cacheKey)) {
                $exists = DB::table('assets')->where('serial', $serial)->exists();
                Cache::put($cacheKey, $exists, 60); // Cache for 1 minute
                if (!$exists) return $serial;
            }
        }
        return 'SDP-SN-' . $externalAssetId . '-' . now()->timestamp;
    }

    private function uniqueAssetTag(?string $tag, int $externalAssetId): string
    {
        if (!empty($tag)) {
            $cacheKey = 'asset_tag_exists_' . md5($tag);
            if (!Cache::has($cacheKey)) {
                $exists = DB::table('assets')->where('asset_tag', $tag)->exists();
                Cache::put($cacheKey, $exists, 60);
                if (!$exists) return $tag;
            }
        }

        $base = 'SDP-' . $externalAssetId;
        $final = $base;
        $i = 1;

        while (DB::table('assets')->where('asset_tag', $final)->exists()) {
            $final = $base . '-' . $i;
            $i++;
            if ($i > 100) break; // Safety limit
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
        $categoryData = [];
        foreach ($categories as $category) {
            $categoryData[] = [
                'external_category_id' => $category['id'],
                'name' => $category['name'],
                'category_type' => 'asset',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        
        if (!empty($categoryData)) {
            DB::table('categories')->upsert($categoryData, ['external_category_id']);
        }
    }

    private function syncDepartment(array $dept): void
    {
        DB::table('departments')->updateOrInsert(
            ['id' => $dept['id']],
            [
                'name' => $dept['name'],
                'company_id' => $this->config->company_id,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    private function syncModel(array $product): int
    {
        if (empty($product['id'])) {
            throw new \Exception('Product ID missing in asset payload');
        }
        
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
     * Generic SDP GET request
     */
    private function sdpGet(string $endpoint, array $params = [])
    {
        $url = $this->baseApiUrl . $endpoint;
        
        return Http::withOptions($this->httpOptions)
            ->withHeaders($this->headers)
            ->get($url, $params);
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
     * Get current mode
     */
    public function getCurrentMode(): string
    {
        return $this->config->mode;
    }

    /**
     * NEW: Get performance statistics
     */
    public function getPerformanceStats(): array
    {
        return $this->performanceStats;
    }
}