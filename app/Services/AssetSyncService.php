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
use App\Models\AssetSyncState;
use Illuminate\Support\Facades\Auth;

class AssetSyncService
{
    private AssetSyncState $syncState;
    private ServiceDeskConfig $config;
    private int $startIndex = 0;
    private string $baseApiUrl;
    private array $httpOptions;
    private array $headers;
    private int $totalAssetsToFetch;
    private int $chunkSize;
    private array $performanceStats;
    private array $successfulAssets = [];
    private int $lastProcessedIndex = 0;

  public function __construct(int $limit = 0, int $chunkSize = 100)
{
    // Set performance stats
    $this->performanceStats = [
        'start_time' => microtime(true),
        'memory_start' => memory_get_usage(true),
        'queries_count' => 0,
        'last_saved_index' => 0,
    ];
    
    // Set limits
    $this->totalAssetsToFetch = $limit > 0 ? $limit : 0;
    $this->chunkSize = $chunkSize > 0 ? $chunkSize : 100;
    
    // Get config
   $this->config = ServiceDeskConfig::where('company_id', 1)
                    ->where('is_active', 1)
                    ->where('is_sync_enabled', 1)
                    ->firstOrFail();


    // Build API URL
    $this->baseApiUrl = rtrim($this->config->base_url, '/') . '/api/' . $this->config->api_version;

    // HTTP options - CRITICAL FIX!
    $this->httpOptions = [
        'verify' => false, // IMPORTANT: Set to false for localhost with SSL
        'timeout' => 120,
        'connect_timeout' => 30,
    ];

    // Headers - EXACTLY like Postman
    $this->headers = [
        'TECHNICIAN_KEY' => $this->config->technician_key,
        'Accept' => 'application/json',
     ];

// Add dynamic session cookies for production mode
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

    // Start from last saved index
    $this->startIndex = (int) ($this->config->last_asset_sync_index ?? 0);
    $this->lastProcessedIndex = $this->startIndex;
    
    Log::info('AssetSyncService initialized', [
        'config_id' => $this->config->id,
        'base_url' => $this->baseApiUrl,
        'start_index' => $this->startIndex,
        'headers_count' => count($this->headers),
    ]);
}

public function initForConfig(int $configId, int $limit = 0, int $chunkSize = 100): self
{
    // Fetch config WITHOUT Auth
    $this->config = ServiceDeskConfig::findOrFail($configId);

    $this->totalAssetsToFetch = $limit;
    $this->chunkSize = $chunkSize;

    // Build API URL
    $this->baseApiUrl = rtrim($this->config->base_url, '/')
        . '/api/' . $this->config->api_version;

    // HTTP options
    $this->httpOptions = [
        'verify' => (bool) ($this->config->verify_ssl ?? false),
        'timeout' => (int) ($this->config->timeout ?? 120),
        'connect_timeout' => 30,
    ];

    // Headers
    $this->headers = [
        'TECHNICIAN_KEY' => $this->config->technician_key,
        'Accept' => 'application/json',
    ];

    if ($this->config->mode === 'production' && !empty($this->config->portal_id)) {
        $this->headers['PORTALID'] = $this->config->portal_id;
    }

    // Sync index
    $this->startIndex = (int) ($this->config->last_asset_sync_index ?? 0);
    $this->lastProcessedIndex = $this->startIndex;

    Log::info('AssetSyncService initialized for async', [
        'config_id' => $this->config->id,
        'mode' => $this->config->mode,
        'start_index' => $this->startIndex,
    ]);

    return $this;
}


    /**
     * MAIN SYNC METHOD - INCREMENTAL SAVE
     */
    public function sync(): array
    {
          $this->performanceStats['sync_start'] = microtime(true);
    
    ini_set('memory_limit', '512M');
    gc_enable();
    DB::disableQueryLog();

    Log::info('Starting sync in ' . $this->config->mode . ' mode', [
        'company_id' => $this->config->company_id,
        'fetch_limit' => $this->totalAssetsToFetch,
        'chunk_size' => $this->chunkSize,
        'start_index' => $this->startIndex,
    ]);

    // 🔹 STEP 1: First, sync asset categories from API
    // This is IMPORTANT - categories must be synced before assets
    try {
        Log::info('Step 1: Syncing asset categories from SDP API');
        $assetCategories = $this->fetchAssetCategories();
        
        Log::info('Fetched categories from API', [
            'count' => count($assetCategories),
            'categories' => $assetCategories
        ]);
        
        $this->syncAssetCategories($assetCategories);
        
        // Verify categories were synced
        $this->verifyCategoriesInDatabase();
        
    } catch (\Throwable $e) {
        Log::error('Asset category sync failed', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        // Don't stop, continue with sync
    }

    // 🔹 STEP 2: SYNC PURCHASE ORDERS
    $purchaseOrdersMap = [];
    
    // try {
    //     Log::info('Step 2: Syncing purchase orders');
    //     $purchaseOrders = $this->fetchPurchaseOrders();
    //     $purchaseOrdersMap = $this->bulkSyncVendorsAndUsers($purchaseOrders);
        
    //     Log::info('Purchase orders synced', [
    //         'count' => count($purchaseOrders),
    //         'map_count' => count($purchaseOrdersMap)
    //     ]);
        
    // } catch (\Throwable $e) {
    //     Log::error('Purchase order sync failed', [
    //         'error' => $e->getMessage(),
    //         'trace' => $e->getTraceAsString()
    //     ]);
    // }

    // 🔹 STEP 3: SYNC ASSETS
    Log::info('Step 3: Fetching and syncing assets');
    $assets = $this->fetchAssetsWithLimit();
    $totalAssets = count($assets);
    
    Log::info('Assets fetched from SDP', [
        'count' => $totalAssets,
        'mode' => $this->config->mode,
        'fetch_time' => round(microtime(true) - $this->performanceStats['sync_start'], 2) . 's',
    ]);

        // Performance counters
        $inserted = 0;
        $skipped = 0;
        $failed = 0;

        // 🔹 Get existing assets to skip
        $existingIds = Asset::where('external_source', 'SDP')
            ->where('company_id', $this->config->company_id)
            ->pluck('external_asset_id')
            ->flip()
            ->toArray();

        // 🔹 Pre-load purchase orders for faster lookup
        // $purchaseOrdersMap = $this->getPurchaseOrdersMap();

        // 🔹 PROCESS ASSETS IN SMALL BATCHES WITH INDIVIDUAL SAVE
        $batchSize = 50; // Small batch for incremental save
        $currentIndex = 0;
        
        foreach (array_chunk($assets, $batchSize) as $batchIndex => $assetsBatch) {
            $batchStartTime = microtime(true);
            
            Log::info("Processing batch {$batchIndex}", [
                'batch_size' => count($assetsBatch),
                'total_batches' => ceil($totalAssets / $batchSize),
                'memory_usage' => round(memory_get_usage(true) / 1024 / 1024, 2) . 'MB',
            ]);
            
            $batchInserted = 0;
            $batchFailed = 0;
            
            // 🔹 EACH ASSET IN ITS OWN TRANSACTION
            foreach ($assetsBatch as $assetIndex => $item) {
                $currentIndex++;
                // 🔹 Resolve purchase order for this asset
                $poNumber = $item['purchase_order_no'] ?? null;
                $purchaseOrder = $poNumber && isset($purchaseOrdersMap[$poNumber])
                    ? $purchaseOrdersMap[$poNumber]
                    : null;

                $assetStartTime = microtime(true);
                
                try {
                    // Check if already exists
                    if (isset($existingIds[$item['id']])) {
                        $skipped++;
                        Log::debug('Asset already exists, skipping', ['asset_id' => $item['id']]);
                        continue;
                    }
                    
                    // Fetch asset details
                    $assetDetails = $this->fetchAssetDetailsWithCache($item['id']);
                    if (empty($assetDetails)) {
                        $failed++;
                        $batchFailed++;
                        Log::warning('Failed to fetch asset details', ['asset_id' => $item['id']]);
                        continue;
                    }
                    
                    // 🔹 START TRANSACTION FOR THIS SINGLE ASSET
                    DB::beginTransaction();
                    
                    try {
                        // Sync related entities
                        if (!empty($item['vendor'])) {
                            $this->syncVendor($item['vendor']);
                        }
                        if (!empty($item['user'])) {
                            $this->syncUser($item['user']);
                        }
                        // if (!empty($purchaseOrder)) {
                        //     if (!empty($purchaseOrder->requested_by)) {
                        //         $this->syncUser(['id' => $purchaseOrder->requested_by]);
                        //     }
                        // }
                        
                        // Prepare asset data
                        $assetData = $this->prepareAssetData($item, $purchaseOrdersMap);
                        if (empty($assetData)) {
                            throw new \Exception('Asset data preparation failed');
                        }
                        
                        // Generate barcode
                        $barcode = $this->generateBarcodeForAsset($item, $assetData);
                        if ($barcode) {
                            $assetData['_snipeit_barcode_2'] = $barcode;
                        }
                        
                        // Add timestamps
                        $assetData['created_at'] = now();
                        $assetData['updated_at'] = now();
                        
                        // Create asset
                        $assetId = DB::table('assets')->insertGetId($assetData);

                        // 🔹 IMPORTANT: Sync asset images now
                        $this->syncAssetImages($assetDetails, $assetId);

                        // 🔹 IMPORTANT: IMMEDIATELY SAVE PROGRESS INDEX
                        $this->lastProcessedIndex = $this->startIndex + $currentIndex;
                        $this->updateLastSyncIndex($this->lastProcessedIndex);
                        // 🔹 STEP 4: Sync Purchase Orders AFTER assets (SAFE)
                        $this->syncPurchaseOrdersAfterAssets();

                        // 🔹 COMMIT THIS ASSET
                        DB::commit();

                        
                        $inserted++;
                        $batchInserted++;
                        $existingIds[$item['id']] = true;
                        $this->successfulAssets[] = $item['id'];
                        
                        Log::debug('Asset saved successfully', [
                            'asset_id' => $item['id'],
                            'local_id' => $assetId,
                            'current_index' => $this->lastProcessedIndex,
                            'time_taken' => round(microtime(true) - $assetStartTime, 3) . 's'
                        ]);
                        
                    } catch (\Exception $e) {
                        // 🔹 ROLLBACK ONLY THIS ASSET
                        DB::rollBack();
                        
                        $failed++;
                        $batchFailed++;
                        
                        Log::error('Failed to save asset', [
                            'asset_id' => $item['id'],
                            'error' => $e->getMessage(),
                            'trace' => $e->getTraceAsString()
                        ]);
                    }
                    
                } catch (\Throwable $e) {
                    $failed++;
                    $batchFailed++;
                    
                    Log::error('Asset processing failed', [
                        'asset_id' => $item['id'],
                        'error' => $e->getMessage()
                    ]);
                }
                
                // Small delay to prevent API rate limiting
                usleep(300000); 
            }
            
            // Batch summary
            Log::info("Batch {$batchIndex} completed", [
                'inserted' => $batchInserted,
                'failed' => $batchFailed,
                'batch_time' => round(microtime(true) - $batchStartTime, 2) . 's',
                'last_saved_index' => $this->lastProcessedIndex,
            ]);
            
            // Clear memory after each batch
            unset($assetsBatch);
            if ($batchIndex % 5 == 0) {
                gc_collect_cycles();
            }
            
            // Check memory usage
            $memoryUsage = memory_get_usage(true) / 1024 / 1024;
            if ($memoryUsage > 256) {
                Log::warning('High memory usage detected', [
                    'memory_mb' => round($memoryUsage, 2),
                    'batch_index' => $batchIndex
                ]);
            }
        }

        // 🔹 FINAL: Update sync status
        $this->updateLastSyncIndex($this->lastProcessedIndex, true);
        
        // Calculate performance stats
        $this->calculatePerformanceStats($inserted, $skipped, $failed);
        
        Log::info('Sync completed', [
            'inserted' => $inserted,
            'skipped' => $skipped,
            'failed' => $failed,
            'total_fetched' => $totalAssets,
            'last_saved_index' => $this->lastProcessedIndex,
            'success_rate' => $totalAssets > 0 ? round(($inserted / $totalAssets) * 100, 2) . '%' : '0%',
            'performance' => $this->performanceStats,
        ]);
        
        return [
            'success' => true,
            'inserted' => $inserted,
            'skipped' => $skipped,
            'failed' => $failed,
            'total' => $totalAssets,
            'last_saved_index' => $this->lastProcessedIndex,
            'mode' => $this->config->mode,
            'performance' => $this->performanceStats,
        ];
    }


    /**
 * Process a single chunk asynchronously
 */
public function processSingleChunk(int $startIndex, int $chunkSize, int $configId): array
{
    // $purchaseOrders = $this->fetchPurchaseOrders();
    // foreach ($purchaseOrders as $po) {
    //     $this->syncPurchaseOrder($po);
    // }

    // Set config
    $this->config = ServiceDeskConfig::findOrFail($configId);
    
    // Setup API connection
    $this->setupApiConnection();
    
    // Fetch just this chunk
    $assets = $this->fetchSingleChunk($startIndex, $chunkSize);
    
    if (empty($assets)) {
        return [
            'success' => 0,
            'skipped' => 0,
            'failed' => 0,
            'processed' => 0,
        ];
    }
    
    // Process this chunk
    return $this->processChunkAssets($assets);
}

/**
 * Setup API connection
 */
private function setupApiConnection(): void
{
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

    if ($this->config->mode === 'production') {
        if (!empty($this->config->portal_id)) {
            $this->headers['PORTALID'] = $this->config->portal_id;
        }
        $sessionData = $this->getProductionSessionData();
        if (!empty($sessionData)) {
            $this->headers['Cookie'] =
                'SDPSESSIONID=' . $sessionData['session_id'] . '; ' .
                '_zcsr_tmp=' . $sessionData['csrf_token'] . '; ' .
                'sdpcsrfcookie=' . $sessionData['csrf_token'];
        }
    }
}

/**
 * Fetch single chunk
 */
private function fetchSingleChunk(int $startIndex, int $chunkSize): array
{
    try {
        $response = Http::withOptions($this->httpOptions)
            ->withHeaders($this->headers)
            ->get($this->baseApiUrl . '/assets', [
                'input_data' => json_encode([
                    'list_info' => [
                        'start_index' => $startIndex,
                        'row_count' => $chunkSize,
                        'sort_field' => 'id',
                        'sort_order' => 'asc'
                    ]
                ])
            ]);
        
        if ($response->successful()) {
            $json = $response->json();
            return $json['assets'] ?? [];
        }
        
        Log::warning('Failed to fetch chunk', [
            'start_index' => $startIndex,
            'status' => $response->status(),
        ]);
        
    } catch (\Throwable $e) {
        Log::error('Exception fetching chunk', [
            'start_index' => $startIndex,
            'error' => $e->getMessage(),
        ]);
    }
    
    return [];
}

/**
 * Process chunk assets
 */
private function processChunkAssets(array $assets): array
{
    $success = 0;
    $skipped = 0;
    $failed = 0;
    $jobStart = microtime(true);
    // Get existing asset IDs
    $existingIds = DB::table('assets')
        ->where('external_source', 'SDP')
        ->where('company_id', $this->config->company_id)
        ->pluck('external_asset_id')
        ->flip()
        ->toArray();
    
    // Get purchase orders map
    $purchaseOrdersMap = $this->getPurchaseOrdersMap();
    foreach ($assets as $item) {
         if (microtime(true) - $jobStart > 90) {
            Log::info('Job exiting early to avoid worker timeout');
            break;
        }
        try {
            // Check if exists
            if (isset($existingIds[$item['id']])) {
                $skipped++;
                continue;
            }
            
            // Process single asset
            $processed = $this->processSingleAsset($item, $purchaseOrdersMap);
            
            if ($processed) {
                $success++;
                $existingIds[$item['id']] = true;
            } else {
                $failed++;
            }
            
        } catch (\Throwable $e) {
            $failed++;
            Log::error('Failed to process asset in chunk', [
                'asset_id' => $item['id'],
                'error' => $e->getMessage(),
            ]);
        }
    }
    
    return [
        'success' => $success,
        'skipped' => $skipped,
        'failed' => $failed,
        'processed' => count($assets),
    ];
}
/**
 * Estimate total assets from SDP
 */

/**
 * Process and save a single asset (used by async chunk jobs)
 */
private function processSingleAsset(array $item, array $purchaseOrdersMap): bool
{
    try {
        // Get PO number for this asset
        $poNumber = $item['purchase_order_no'] ?? null;
        $purchaseOrder = $purchaseOrdersMap[$poNumber] ?? null;

        DB::beginTransaction();

        try {
            // Sync vendor if exists
            if (!empty($item['vendor'])) {
                $this->syncVendor($item['vendor']);
            }
            
            // Sync user if exists
            if (!empty($item['user'])) {
                $this->syncUser($item['user']);
            }
            
            // Also sync user from purchase order if applicable
            // if (!empty($purchaseOrder) && !empty($purchaseOrder->requested_by)) {
            //     $this->syncUser([
            //         'id' => $purchaseOrder->requested_by,
            //         'name' => 'PO Requestor'
            //     ]);
            // }

            // Prepare and save asset data
            $assetData = $this->prepareAssetData($item, $purchaseOrdersMap);
            if (empty($assetData)) {
                throw new \Exception('Asset data preparation failed');
            }

            // Generate barcode if missing
            $barcode = $this->generateBarcodeForAsset($item, $assetData);
            if ($barcode) {
                $assetData['_snipeit_barcode_2'] = $barcode;
            }

            // Insert asset
           $assetId = DB::table('assets')->insertGetId($assetData);
            // 🔹 ADD THIS
            $assetDetails = $this->fetchAssetDetailsWithCache($item['id']);
            $this->syncAssetImages($assetDetails, $assetId);
            DB::commit();
            return true;

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('processSingleAsset failed', [
                'asset_id' => $item['id'],
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }

    } catch (\Throwable $e) {
        Log::error('processSingleAsset outer failure', [
            'asset_id' => $item['id'],
            'error' => $e->getMessage(),
        ]);
        return false;
    }
}

private function syncVendor(array $vendor): void
{
    if (empty($vendor['id'])) return;

    DB::table('suppliers')->updateOrInsert(
        ['id' => $vendor['id']],
        [
            'name'       => $vendor['name'] ?? null,
            'email'      => $vendor['email_id'] ?? $vendor['email'] ?? null,
            'phone'      => $vendor['phone'] ?? $vendor['mobile'] ?? null,
            'company_id' => $this->config->company_id, // ← ADD THIS
            'created_at' => now(),
            'updated_at' => now(),
        ]
    );
}

private function syncUser(array $user): void
{
    if (empty($user['id'])) return;

    DB::table('users')->updateOrInsert(
        ['id' => $user['id']],
        [
            'first_name' => $user['name'] ?? $user['first_name'] ?? 'Unknown',
            'last_name'  => $user['last_name'] ?? '',
            'email'      => $user['email_id'] ?? $user['email'] ?? null,
            'phone'      => $user['mobile'] ?? $user['phone'] ?? null,
            'company_id' => $this->config->company_id, // ← ADD THIS
            'created_at' => now(),
            'updated_at' => now(),
        ]
    );
}

public function estimateTotalAssets(): int
{
    try {
        Log::info('Estimating total assets', [
            'config_id' => $this->config->id,
            'base_url' => $this->config->base_url,
        ]);
        
        $url = $this->baseApiUrl . '/assets';
        
        $response = Http::withOptions($this->httpOptions)
            ->withHeaders($this->headers)
            ->get($url, [
                'input_data' => json_encode([
                    'list_info' => [
                        'start_index' => 0,
                        'row_count' => 1,
                        'get_total_count' => true,
                        'sort_field' => 'id',
                        'sort_order' => 'asc'
                    ]
                ])
            ]);
        
        if ($response->successful()) {
            $data = $response->json();
            $total = $data['list_info']['total_count'] ?? 0;
            
            Log::info('Total assets estimated', [
                'total' => $total,
                'config_id' => $this->config->id,
            ]);
            
            return $total;
        } else {
            Log::error('Failed to estimate total assets', [
                'status' => $response->status(),
                'body' => $response->body(),
                'url' => $url,
            ]);
            return 0;
        }
        
    } catch (\Throwable $e) {
        Log::error('Exception estimating total assets', [
            'error' => $e->getMessage(),
            'config_id' => $this->config->id,
            'trace' => $e->getTraceAsString(),
        ]);
        return 0;
    }
}
/**
 * Get config
 */
public function getConfig()
{
    return $this->config;
}

    /**
     * Fetch assets with pagination and save progress
     */
    private function fetchAssetsWithLimit(): array
    {
        $allAssets = [];
        $start = $this->startIndex;
        $limit = 100; // API limit per request
        $fetchedCount = 0;
        $maxRequests = 100; // Safety limit
        
        Log::info('Starting asset fetch', [
            'start_index' => $start,
            'fetch_limit' => $this->totalAssetsToFetch,
            'api_chunk_size' => $limit
        ]);
        
        for ($requestCount = 0; $requestCount < $maxRequests; $requestCount++) {
            // Calculate how many to fetch in this request
            if ($this->totalAssetsToFetch > 0) {
                $remaining = $this->totalAssetsToFetch - $fetchedCount;
                if ($remaining <= 0) {
                    Log::info('Fetch limit reached', [
                        'fetched' => $fetchedCount,
                        'limit' => $this->totalAssetsToFetch
                    ]);
                    break;
                }
                $currentLimit = min($limit, $remaining);
            } else {
                $currentLimit = $limit;
            }
            
            Log::debug('Fetching assets from API', [
                'start_index' => $start,
                'limit' => $currentLimit,
                'request_count' => $requestCount + 1
            ]);
            
            try {
                $response = $this->sdpGet('/assets', [
                    'input_data' => json_encode([
                        'list_info' => [
                            'start_index' => $start,
                            'row_count' => $currentLimit,
                            'sort_field' => 'id',
                            'sort_order' => 'asc'
                        ]
                    ])
                ]);
                
                if (!$response->successful()) {
                    Log::error('API request failed', [
                        'status' => $response->status(),
                        'body' => $response->body(),
                        'start' => $start,
                        'limit' => $currentLimit
                    ]);
                    break;
                }
                
                $json = $response->json();
                $assets = $json['assets'] ?? [];
                
                if (empty($assets)) {
                    Log::info('No more assets to fetch', [
                        'total_fetched' => $fetchedCount,
                        'start_index' => $start
                    ]);
                    break;
                }
                
                $allAssets = array_merge($allAssets, $assets);
                $fetchedCount += count($assets);
                $start += count($assets);
                
                Log::debug('Assets fetched', [
                    'count' => count($assets),
                    'total_fetched' => $fetchedCount,
                    'next_start' => $start
                ]);
                
                // Check if we have more data
                $hasMore = $json['list_info']['has_more_rows'] ?? false;
                if (!$hasMore) {
                    Log::info('API indicates no more rows', [
                        'total_fetched' => $fetchedCount,
                        'final_index' => $start
                    ]);
                    break;
                }
                
                // Rate limiting
                usleep(200000); // 200ms between API calls
                
            } catch (\Throwable $e) {
                Log::error('Exception during asset fetch', [
                    'error' => $e->getMessage(),
                    'start_index' => $start,
                    'request_count' => $requestCount
                ]);
                break;
            }
            
            // Memory check
            $memoryUsage = memory_get_usage(true) / 1024 / 1024;
            if ($memoryUsage > 256) {
                Log::warning('Memory limit reached during fetch', [
                    'memory_mb' => round($memoryUsage, 2),
                    'fetched' => $fetchedCount
                ]);
                break;
            }
        }
        
        // 🔹 SAVE THE LAST FETCHED INDEX (even if not all saved yet)
        $this->updateLastSyncIndex($start, false);
        
        Log::info('Asset fetch completed', [
            'total_fetched' => $fetchedCount,
            'start_index' => $this->startIndex,
            'end_index' => $start,
            'memory_usage' => round(memory_get_usage(true) / 1024 / 1024, 2) . 'MB'
        ]);
        
        return $allAssets;
    }

    /**
     * Update last sync index in database
     */
    private function updateLastSyncIndex(int $index, bool $final = false): void
    {
        try {
            ServiceDeskConfig::where('id', $this->config->id)
                ->update([
                    'last_asset_sync_index' => $index,
                    'updated_at' => now(),
                ]);
            
            if ($final) {
                Log::info('Final sync index saved', [
                    'index' => $index,
                    'config_id' => $this->config->id
                ]);
            } else {
                Log::debug('Intermediate sync index saved', [
                    'index' => $index,
                    'config_id' => $this->config->id
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to save sync index', [
                'index' => $index,
                'error' => $e->getMessage()
            ]);
        }
    }

private function bulkSyncVendorsAndUsers(array $purchaseOrders): array
{
    $purchaseOrderMap = []; // Store PO ID mapping
    
    foreach ($purchaseOrders as $po) {
        // Sync vendor और user
        if (!empty($po['vendor'])) {
            $this->syncVendor($po['vendor']);
        }
        
        if (!empty($po['requested_by']['id']) && !empty($po['requested_by']['name'])) {
            $this->syncUser($po['requested_by']);
        }

        
        if (!empty($po['owner'])) {
            $this->syncUser($po['owner']);
        }
        
        // Sync purchase order और ID पाएं
        $poId = $this->syncPurchaseOrder($po);
        
        if ($poId > 0) {
            // Map external PO ID to internal PO ID
            $externalPoId = (string)($po['id'] ?? '');
            $customPoId = (string)($po['custom_po_id'] ?? '');
            
            if ($externalPoId) {
                $purchaseOrderMap[$externalPoId] = $poId;
            }
            if ($customPoId) {
                $purchaseOrderMap[$customPoId] = $poId;
            }
        }
    }
    
    Log::info('Purchase order map created', ['map' => $purchaseOrderMap]);
    
    return $purchaseOrderMap;
}
private function syncAssetImages(array $assetDetails, int $assetId): void
{
    if (empty($assetDetails['attachments'])) return;

    foreach ($assetDetails['attachments'] as $file) {
        if (!str_starts_with($file['file_name'], 'image')) continue;

        try {
            $image = Http::withOptions(['verify' => false])
                ->get($file['content_url'])
                ->body();

           $path = "public/assets/{$assetId}_" . uniqid() . ".jpg";
                    Storage::put($path, $image);


            DB::table('assets')
                ->where('id', $assetId)
                ->update(['image' => "assets/{$assetId}.jpg"]);

        } catch (\Throwable $e) {
            Log::warning('Image sync failed', [
                'asset_id' => $assetId,
                'error' => $e->getMessage()
            ]);
        }
    }
}

    /**
     * Get purchase orders map
     */
   private function getPurchaseOrdersMap(): array
{
    $purchaseOrders = DB::table('purchase_orders')
        ->select('id', 'custom_po_id', 'external_po_id', 'status_name', 'requested_by', 'supplier_id')
        ->where('company_id', $this->config->company_id)
        ->get();

    $map = [];

    foreach ($purchaseOrders as $po) {
        // Map by external_po_id (API से आने वाला ID)
        if (!empty($po->external_po_id)) {
            $map[(string) $po->external_po_id] = $po;
        }
        
        // Map by custom_po_id
        if (!empty($po->custom_po_id)) {
            $map[(string) $po->custom_po_id] = $po;
        }
    }

    Log::debug('Purchase order map loaded', [
        'count' => count($map),
        'keys' => array_keys($map)
    ]);

    return $map;
}

    /**
     * Prepare asset data
     */
 private function prepareAssetData(array $item, array $purchaseOrdersMap): array
{
    // Get location ID
    $locationId = null;
    if (!empty($item['location'])) {
        $locationId = $this->syncLocation($item['location']);
    }

    // Get PO number और find purchase order - FIXED LOGIC
    $poNumber = $item['purchase_order_no'] ?? $item['order_number'] ?? null;
    $purchaseOrder = null;
    $purchaseOrderId = null; // यह internal ID होगी
    
    if ($poNumber && isset($purchaseOrdersMap[$poNumber])) {
        $purchaseOrder = $purchaseOrdersMap[$poNumber];
        $purchaseOrderId = $purchaseOrder->id; // यहाँ internal ID लें
        
        Log::debug('PO found for asset', [
            'asset_id' => $item['id'],
            'po_number' => $poNumber,
            'internal_po_id' => $purchaseOrderId,
            'external_po_id' => $purchaseOrder->external_po_id ?? 'N/A'
        ]);
    } else {
        Log::debug('No PO found for asset', [
            'asset_id' => $item['id'],
            'po_number' => $poNumber,
            'available_keys' => array_keys($purchaseOrdersMap)
        ]);
    }

    // Calculate requestable - FIXED LOGIC
    $requestable = 0;
    if (!empty($purchaseOrder)) {
        $poStatus = strtolower($purchaseOrder->status_name ?? '');
        $requestable = in_array($poStatus, ['received', 'completed', 'closed', 'fulfilled', 'items received']) ? 1 : 0;
        
        Log::debug('Requestable status', [
            'po_status' => $poStatus,
            'requestable' => $requestable
        ]);
    }

    // Get assigned user
    $assignedTo = null;
    $assignedType = null;
    if (!empty($item['user']['id'])) {
        $this->syncUser($item['user']);
        $assignedTo = $item['user']['id'];
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

    // Get supplier ID
    $supplierId = null;
    if (!empty($item['vendor']['id'])) {
        $supplierId = $item['vendor']['id'];
        $this->syncVendor($item['vendor']);
    } elseif (!empty($purchaseOrder) && !empty($purchaseOrder->supplier_id)) {
        $supplierId = $purchaseOrder->supplier_id;
    }

    return [
        'external_asset_id' => $item['id'],
        'external_source' => 'SDP',
        'name' => $item['name'] ?? 'SDP Asset',
        'serial' => $this->uniqueSerial($item['org_serial_number'] ?? $item['serial_no'] ?? null, (int)$item['id']),
        'asset_tag' => $this->uniqueAssetTag($item['asset_tag'] ?? $item['asset_id'] ?? null, (int)$item['id']),
        'order_number' => $poNumber,
        'current_value' => $item['current_cost'] ?? $item['price'] ?? null,
        'purchase_cost' => $item['purchase_cost'] ?? $item['cost'] ?? 0,
        'rtd_location_id' => $locationId,
        'purchase_date' => $this->date($item['acquisition_date']['value'] ?? $item['purchase_date'] ?? null),
        'asset_eol_date' => $this->date($item['expiry_date']['value'] ?? $item['eol_date'] ?? null),
        'purchase_order_id' => $purchaseOrderId, // ← यहाँ INTERNAL ID सेव होगी
        'requestable' => $requestable,
        'supplier_id' => $supplierId,
        'assigned_to' => $assignedTo,
        'assigned_type' => $assignedType,
        'model_id' => $modelId,
        'status_id' => $statusId,
        'company_id' => $this->config->company_id,
        'department_id' => $departmentId,
        'last_audit_date' => now(),
        '_snipeit_barcode_2' => $item['barcode'] ?? null,
        'notes' => $item['description'] ?? $item['notes'] ?? null,
        'warranty_months' => $item['warranty_period'] ?? null,
        'created_at' => now(),
        'updated_at' => now(),
    ];
}

    /**
     * Generate barcode for asset
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
     * Fetch asset details with cache
     */
    private function fetchAssetDetailsWithCache(string $assetId): array
    {
        $cacheKey = "sdp_asset_details_{$assetId}_{$this->config->id}";
        
        return Cache::remember($cacheKey, 300, function () use ($assetId) {
            try {
                $response = $this->sdpGet('/assets/' . $assetId);
                
                if (!$response->successful()) {
                    Log::warning('Asset detail fetch failed', [
                        'asset_id' => $assetId,
                        'status' => $response->status(),
                        'body' => $response->body()
                    ]);
                    return [];
                }
                
                return $response->json('asset', []);
            } catch (\Throwable $e) {
                Log::error('Exception fetching asset details', [
                    'asset_id' => $assetId,
                    'error' => $e->getMessage()
                ]);
                return [];
            }
        });
    }

    /**
     * Calculate performance statistics
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
        $this->performanceStats['last_saved_index'] = $this->lastProcessedIndex;
        $this->performanceStats['assets_per_second'] = $inserted > 0 
            ? round($inserted / $this->performanceStats['total_time'], 2) 
            : 0;
        $this->performanceStats['success_rate'] = ($inserted + $skipped) > 0 
            ? round(($inserted / ($inserted + $skipped + $failed)) * 100, 2) . '%'
            : '0%';
    }

    /**
     * ORIGINAL METHODS
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
                $exists = DB::table('assets')
                    ->where('serial', $serial)
                    ->where('company_id', $this->config->company_id)
                    ->exists();
                Cache::put($cacheKey, $exists, 60);
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
                $exists = DB::table('assets')
                    ->where('asset_tag', $tag)
                    ->where('company_id', $this->config->company_id)
                    ->exists();
                Cache::put($cacheKey, $exists, 60);
                if (!$exists) return $tag;
            }
        }

        $base = 'SDP-' . $externalAssetId;
        $final = $base;
        $i = 1;

        while (DB::table('assets')
            ->where('asset_tag', $final)
            ->where('company_id', $this->config->company_id)
            ->exists()) {
            $final = $base . '-' . $i;
            $i++;
            if ($i > 100) break;
        }
        return $final;
    }

    private function fetchAssetCategories(): array
{
    
    $response = $this->sdpGet('/asset_categories');
    
    if (!$response->successful()) {
        Log::error('Asset Category API failed', [
            'status' => $response->status(),
            'body' => $response->body(),
        ]);
        throw new \Exception('Asset Category API failed: ' . $response->status() . ' : ' . $response->body());
    }
    
    $data = $response->json();
    Log::debug('Asset categories response', ['response' => $data]);
    
    return $data['asset_categories'] ?? [];
}

private function syncAssetCategories(array $categories): void
{
    Log::info('Syncing asset categories from SDP API', ['count' => count($categories)]);
    
    if (empty($categories)) {
        Log::warning('No asset categories to sync');
        return;
    }
    
    // Log what we're getting
    Log::debug('Raw categories from API', ['categories' => $categories]);
    
    $categoryData = [];
    foreach ($categories as $category) {
        if (empty($category['id']) || empty($category['name'])) {
            Log::warning('Skipping invalid category', ['category' => $category]);
            continue;
        }
        
        $externalId = (string)$category['id']; // "1" or "2"
        $categoryName = $category['name']; // "Non-IT" or "IT"
        
        $categoryData[] = [
            'external_category_id' => $externalId,
            'name' => $categoryName,
            'category_type' => 'asset',
            'company_id' => $this->config->company_id,
            'created_at' => now(),
            'updated_at' => now(),
        ];
        
        Log::debug('Prepared category', [
            'external_id' => $externalId,
            'name' => $categoryName,
        ]);
    }
    
    if (!empty($categoryData)) {
        try {
            // Use upsert with composite unique key
            DB::table('categories')->upsert(
                $categoryData,
                ['external_category_id', 'company_id'], // Unique constraint
                ['name', 'updated_at'] // Update these on duplicate
            );
            
            Log::info('Asset categories synced successfully', [
                'count' => count($categoryData),
                'categories' => array_map(function($cat) {
                    return $cat['external_category_id'] . ': ' . $cat['name'];
                }, $categoryData)
            ]);
            
            // Verify in database
            $this->verifyCategoriesInDatabase();
            
        } catch (\Exception $e) {
            Log::error('Failed to sync asset categories', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    } else {
        Log::warning('No valid asset categories to sync after processing');
    }
}

/**
 * Verify categories are in database
 */
private function verifyCategoriesInDatabase(): void
{
    $dbCategories = DB::table('categories')
        ->where('category_type', 'asset')
        ->where('company_id', $this->config->company_id)
        ->get();
    
    Log::info('Categories in database after sync', [
        'count' => $dbCategories->count(),
        'categories' => $dbCategories->map(function($cat) {
            return [
                'id' => $cat->id,
                'name' => $cat->name,
                'external_id' => $cat->external_category_id
            ];
        })->toArray()
    ]);
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

        private function getAssetCategoryMap(): array
    {
        return DB::table('categories')
            ->where('category_type', 'asset')
            ->pluck('id', 'external_category_id')
            ->toArray();
    }

   private function syncCategory(array $product): int
{
    Log::debug('Syncing category for product', [
        'product_id' => $product['id'] ?? 'N/A',
        'product_name' => $product['name'] ?? 'N/A'
    ]);

    // Step 1: Extract external category ID from product
    $externalCategoryId = $this->extractCategoryIdFromProduct($product);
    
    if (!$externalCategoryId) {
        Log::debug('No category ID found in product, using default');
        return $this->getDefaultCategoryId();
    }
    
    // Step 2: Try to find category by external ID
    $category = DB::table('categories')
        ->where('external_category_id', $externalCategoryId)
        ->where('company_id', $this->config->company_id)
        ->first();
    
    if ($category) {
        Log::debug('Category found by external ID', [
            'external_id' => $externalCategoryId,
            'category_id' => $category->id,
            'category_name' => $category->name
        ]);
        return $category->id;
    }
    
    // Step 3: If not found, check if we have synced categories from API
    // First, fetch categories from API and sync them
    try {
        Log::debug('Category not found, fetching from API');
        $assetCategories = $this->fetchAssetCategories();
        $this->syncAssetCategories($assetCategories);
        
        // Try again after sync
        $category = DB::table('categories')
            ->where('external_category_id', $externalCategoryId)
            ->where('company_id', $this->config->company_id)
            ->first();
        
        if ($category) {
            Log::debug('Category found after API sync', [
                'external_id' => $externalCategoryId,
                'category_id' => $category->id,
                'category_name' => $category->name
            ]);
            return $category->id;
        }
        
    } catch (\Throwable $e) {
        Log::error('Failed to fetch categories from API', [
            'error' => $e->getMessage()
        ]);
    }
    
    // Step 4: Still not found, create a new one
    Log::debug('Creating new category for external ID', ['external_id' => $externalCategoryId]);
    
    // Try to get name from product data
    $categoryName = $this->getCategoryNameFromProduct($product);
    if (!$categoryName) {
        $categoryName = 'SDP Category ' . $externalCategoryId;
    }
    
    $categoryId = DB::table('categories')->insertGetId([
        'name' => $categoryName,
        'external_category_id' => $externalCategoryId,
        'category_type' => 'asset',
        'company_id' => $this->config->company_id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    
    Log::debug('Created new category', [
        'category_id' => $categoryId,
        'name' => $categoryName,
        'external_id' => $externalCategoryId
    ]);
    
    return $categoryId;
}

/**
 * Extract category ID from product data
 */
private function extractCategoryIdFromProduct(array $product): ?string
{
    // Check all possible locations
    $sources = [
        'asset_category.id',
        'category.id',
        'product_type.category.id',
        'all_product_type.category.id',
        'category_id'
    ];
    
    foreach ($sources as $source) {
        $value = data_get($product, $source);
        if (!empty($value)) {
            Log::debug('Found category ID', [
                'source' => $source,
                'value' => $value,
                'type' => gettype($value)
            ]);
            return (string)$value;
        }
    }
    
    return null;
}

/**
 * Get category name from product data
 */
private function getCategoryNameFromProduct(array $product): ?string
{
    // Try category name first
    if (!empty($product['category']['name'])) {
        return $product['category']['name'];
    }
    
    // Try asset_category name
    if (!empty($product['asset_category']['name'])) {
        return $product['asset_category']['name'];
    }
    
    // Try product type
    if (!empty($product['product_type']['display_plural_name'])) {
        return $product['product_type']['display_plural_name'];
    }
    
    if (!empty($product['product_type']['display_name'])) {
        return $product['product_type']['display_name'];
    }
    
    if (!empty($product['product_type']['name'])) {
        return $product['product_type']['name'];
    }
    
    return null;
}

/**
 * Get default category ID
 */
private function getDefaultCategoryId(): int
{
    $defaultCategory = DB::table('categories')
        ->where('name', 'Assets')
        ->where('company_id', $this->config->company_id)
        ->first();
    
    if ($defaultCategory) {
        return $defaultCategory->id;
    }
    
    Log::info('Creating default Assets category');
    
    return DB::table('categories')->insertGetId([
        'name' => 'Assets',
        'category_type' => 'asset',
        'company_id' => $this->config->company_id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

private function debugProductData(array $product): void
{
    Log::debug('PRODUCT DATA STRUCTURE:', [
        'product_id' => $product['id'] ?? 'N/A',
        'product_name' => $product['name'] ?? 'N/A',
        'has_category' => isset($product['category']),
        'has_asset_category' => isset($product['asset_category']),
        'has_product_type' => isset($product['product_type']),
        'category_data' => $product['category'] ?? 'N/A',
        'asset_category_data' => $product['asset_category'] ?? 'N/A',
        'product_type_data' => $product['product_type'] ?? 'N/A',
        'full_product_structure' => array_keys($product),
    ]);
}


   private function syncModel(array $product): int
{
    $this->debugProductData($product);

    if (empty($product['id'])) {
        throw new \Exception('Product ID missing in asset payload');
    }

    $externalModelId = $product['id'];

    $model = DB::table('models')
        ->where('external_model_id', $externalModelId)
        ->first();

    if ($model) {
        Log::debug('Model found by external ID', [
            'external_model_id' => $externalModelId,
            'model_id' => $model->id,
            'model_name' => $model->name
        ]);
        return $model->id;
    }

    // Get category ID - THIS IS WHERE THE ISSUE IS
    $categoryId = $this->syncCategory($product);
    
    Log::debug('Category resolved for model', [
        'product_id' => $product['id'],
        'category_id' => $categoryId,
        'category' => DB::table('categories')->find($categoryId)
    ]);

    $modelId = DB::table('models')->insertGetId([
        'name' => $product['name'] ?? 'Unknown Model',
        'model_number' => $product['part_no'] ?? null,
        'category_id' => $categoryId,
        'external_model_id' => $externalModelId,
        'created_by' => 1,
        'fieldset_id' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    
    Log::debug('Created new model', [
        'model_id' => $modelId,
        'model_name' => $product['name'] ?? 'Unknown Model',
        'category_id' => $categoryId
    ]);
    
    return $modelId;
}

public function syncPurchaseOrdersAfterAssets(): void
{
    $poNumbers = DB::table('assets')
        ->where('company_id', $this->config->company_id)
        ->whereNotNull('order_number')
        ->pluck('order_number')
        ->unique()
        ->values()
        ->toArray();

    Log::info('Post asset PO sync started', [
        'po_count' => count($poNumbers)
    ]);

    foreach (array_chunk($poNumbers, 30) as $chunk) {
        try {
            $response = $this->sdpGet('/purchase_orders', [
                'input_data' => json_encode([
                    'list_info' => [
                        'search_fields' => [
                            'id' => $chunk
                        ]
                    ]
                ])
            ]);

            if (!$response->successful()) {
                Log::warning('PO API blocked, stopping', [
                    'status' => $response->status()
                ]);
                break;
            }

            foreach ($response->json('purchase_orders', []) as $po) {
                $this->syncPurchaseOrder($po);
            }

            sleep(1); // RATE LIMIT SAFE

        } catch (\Throwable $e) {
            Log::error('Post asset PO sync failed', [
                'error' => $e->getMessage()
            ]);
            break;
        }
    }
}


private function fetchPurchaseOrders(): array
{
    $all = [];
    $start = 0;
    $limit = 50;
    $maxLoops = 20;

    for ($i = 0; $i < $maxLoops; $i++) {

        $response = $this->sdpGet('/purchase_orders', [
            'input_data' => json_encode([
                'list_info' => [
                    'start_index' => $start,
                    'row_count' => $limit,
                ]
            ])
        ]);

        if (!$response->successful()) {
            Log::warning('PO API stopped', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);
            break;
        }

        if (
            $response->status() == 400 &&
            str_contains($response->body(), 'maximum access limit')
        ) {
            Log::error('SDP rate limit hit, stopping PO fetch');
            break;
        }

        $chunk = $response->json('purchase_orders', []);
        if (empty($chunk)) break;

        $all = array_merge($all, $chunk);
        $start += count($chunk);

        usleep(500000); // 500ms delay
    }

    return $all;
}



    //     private function fetchPurchaseOrders(): array
    // {
    //     $all = [];
    //     $start = 0;
    //     $limit = 100;

    //     while (true) {
    //         $response = $this->sdpGet('/purchase_orders', [
    //             'input_data' => json_encode([
    //                 'list_info' => [
    //                     'start_index' => $start,
    //                     'row_count' => $limit,
    //                 ]
    //             ])
    //         ]);

    //         if (!$response->successful()) break;

    //         $chunk = $response->json('purchase_orders', []);
    //         if (empty($chunk)) break;

    //         $all = array_merge($all, $chunk);
    //         $start += count($chunk);
    //     }
    //     Log::error('PO DEBUG COUNT', [
    //         'count' => count($all),
    //         'sample' => array_slice($all, 0, 1),
    //     ]);


    //     return $all;
    // }


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

  private function syncPurchaseOrder(array $po): int
{
    if (empty($po['id'])) {
        Log::warning('Skipping purchase order without ID', ['po' => $po]);
        return 0;
    }
    
    try {
        $vendorId = isset($po['vendor']['id']) ? (int)$po['vendor']['id'] : null;
        
        // Vendor को पहले sync करें
        if (!empty($po['vendor'])) {
            $this->syncVendor($po['vendor']);
        }
        
        // User को sync करें
       if (!empty($po['requested_by']['id']) && !empty($po['requested_by']['name'])) {
            $this->syncUser([
                'id' => $po['requested_by']['id'],
                'name' => $po['requested_by']['name']
            ]);
        }

        
        // IMPORTANT: updateOrInsert और inserted ID पाएं
        $exists = DB::table('purchase_orders')
            ->where('external_po_id', (int)$po['id'])
            ->where('company_id', $this->config->company_id)
            ->first();
        
        if ($exists) {
            // Update existing record
            DB::table('purchase_orders')
                ->where('id', $exists->id)
                ->update([
                    'custom_po_id' => $po['custom_po_id'] ?? null,
                    'po_name' => $po['name'] ?? 'Unnamed PO',
                    'total_price' => (float)($po['total_price'] ?? 0),
                    'base_total_price' => (float)($po['base_total_price'] ?? 0),
                    'status_name' => $po['status']['name'] ?? null,
                    'status_id' => isset($po['status']['id']) ? (int)$po['status']['id'] : null,
                    'supplier_id' => $vendorId,
                    'requested_by' => isset($po['requested_by']['id']) ? (int)$po['requested_by']['id'] : null,
                    'owner_id' => isset($po['owner']['id']) ? (int)$po['owner']['id'] : null,
                    'created_date' => $this->date($po['created_date']['value'] ?? null),
                    'required_date' => $this->date($po['required_date']['value'] ?? null),
                    'updated_at' => now(),
                ]);
            
            $purchaseOrderId = $exists->id;
            
            Log::debug('Purchase order updated', [
                'id' => $purchaseOrderId,
                'external_po_id' => $po['id'],
                'name' => $po['name'] ?? 'N/A'
            ]);
            
        } else {
            // Insert new record और ID पाएं
            $purchaseOrderId = DB::table('purchase_orders')->insertGetId([
                'company_id' => $this->config->company_id,
                'external_po_id' => (int)$po['id'],
                'custom_po_id' => $po['custom_po_id'] ?? null,
                'po_name' => $po['name'] ?? 'Unnamed PO',
                'total_price' => (float)($po['total_price'] ?? 0),
                'base_total_price' => (float)($po['base_total_price'] ?? 0),
                'status_name' => $po['status']['name'] ?? null,
                'status_id' => isset($po['status']['id']) ? (int)$po['status']['id'] : null,
                'supplier_id' => $vendorId,
                'requested_by' => isset($po['requested_by']['id']) ? (int)$po['requested_by']['id'] : null,
                'owner_id' => isset($po['owner']['id']) ? (int)$po['owner']['id'] : null,
                'created_date' => $this->date($po['created_date']['value'] ?? null),
                'required_date' => $this->date($po['required_date']['value'] ?? null),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            
            Log::debug('Purchase order created', [
                'id' => $purchaseOrderId,
                'external_po_id' => $po['id'],
                'name' => $po['name'] ?? 'N/A'
            ]);
        }
        
        return $purchaseOrderId;
        
    } catch (\Exception $e) {
        Log::error('Failed to sync purchase order', [
            'po_id' => $po['id'] ?? 'unknown',
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        return 0;
    }
}

    // private function syncPurchaseOrder(array $po): void
    // {
    //     DB::table('purchase_orders')->updateOrInsert(
    //         ['external_po_id' => $po['id']],
    //         [
    //             'company_id' => $this->config->company_id,
    //             'custom_po_id' => $po['custom_po_id'] ?? null,
    //             'po_name' => $po['name'],
    //             'total_price' => $po['total_price'] ?? 0,
    //             'base_total_price' => $po['base_total_price'] ?? 0,
    //             'status_name' => $po['status']['name'] ?? null,
    //             'status_id' => $po['status']['id'] ?? null,
    //             'supplier_id' => $po['vendor']['id'] ?? null,
    //             'requested_by' => $po['requested_by']['id'] ?? null,
    //             'owner_id' => $po['owner']['id'] ?? null,
    //             'created_date' => $this->date($po['created_date']['value'] ?? null),
    //             'required_date' => $this->date($po['required_date']['value'] ?? null),
    //             'created_at' => now(),
    //             'updated_at' => now(),
    //         ]
    //     );
    // }

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
        
        Log::debug('SDP API Request', [
            'url' => $url,
            'params' => $params,
            'mode' => $this->config->mode
        ]);
        
        return Http::withOptions($this->httpOptions)
            ->withHeaders($this->headers)
            ->get($url, $params);
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
     * Validate config
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
            'last_asset_sync_index' => $this->config->last_asset_sync_index,
        ];
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
     * Get performance statistics
     */
    public function getPerformanceStats(): array
    {
        return $this->performanceStats;
    }

    /**
     * Get last processed index
     */
    public function getLastProcessedIndex(): int
    {
        return $this->lastProcessedIndex;
    }

    /**
     * Get successful assets
     */
    public function getSuccessfulAssets(): array
    {
        return $this->successfulAssets;
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
     * Reset sync index
     */
    public function resetSyncIndex(): void
    {
        ServiceDeskConfig::where('id', $this->config->id)
            ->update(['last_asset_sync_index' => 0]);
        
        $this->startIndex = 0;
        $this->lastProcessedIndex = 0;
    }
}