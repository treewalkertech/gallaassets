<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\AssetSyncState;
use App\Jobs\ProcessAssetSyncChunk;
use App\Services\AssetSyncService;
use Illuminate\Http\Request;
use App\Models\ServiceDeskConfig;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;


class AsyncAssetController extends Controller
{
    /**
     * Display a listing of assets
     */
    public function index()
    {
        $assets = Asset::with(['model', 'status', 'location'])
            ->latest()
            ->paginate(20);
            
        return view('assets.index', compact('assets'));
    }

 public function startAsyncSync(Request $request)
{
    try {
        $request->validate([
            'batch_size' => 'nullable|integer|min:100|max:10000',
            'chunk_size' => 'nullable|integer|min:50|max:1000',
            'skip_estimation' => 'boolean', // Add this
        ]);

        $batchSize = $request->input('batch_size', 1000);
        $chunkSize = $request->input('chunk_size', 200);
        $skipEstimation = $request->input('skip_estimation', false);

        // Get active config
        $config = ServiceDeskConfig::where('company_id', Auth::user()->company_id)
            ->where('is_active', 1)
            ->where('is_sync_enabled', 1)
            ->firstOrFail();

        Log::info('Starting async asset sync', [
            'user_id' => Auth::id(),
            'config_id' => $config->id,
            'batch_size' => $batchSize,
            'chunk_size' => $chunkSize,
            'skip_estimation' => $skipEstimation,
        ]);

        $totalAssets = 0;
        
        if (!$skipEstimation) {
            // Try to estimate total assets
            try {
                // Create a fresh service instance with the config
                $service = new AssetSyncService();
                $totalAssets = 0;
                
                if ($totalAssets === 0) {
                    Log::warning('Asset estimation returned 0', [
                        'config_id' => $config->id,
                        'mode' => $config->mode,
                    ]);
                    
                    // Don't fail immediately - let the sync try anyway
                    // We'll use a default large number
                    $totalAssets = 10000; // Default assumption
                }
            } catch (\Throwable $e) {
                Log::error('Failed to estimate total assets, using default', [
                    'error' => $e->getMessage(),
                    'config_id' => $config->id,
                ]);
                $totalAssets = 10000; // Default assumption
            }
        } else {
            $totalAssets = 10000; // Use default if skipping estimation
        }

        // Create sync state
        $syncState = AssetSyncState::create([
            'company_id' => Auth::user()->company_id,
            'config_id' => $config->id,
            'status' => AssetSyncState::STATUS_RUNNING,
            'total_assets' => $totalAssets,
            'batch_size' => $batchSize,
            'chunk_size' => $chunkSize,
            'started_at' => now(),
            'progress_percentage' => 0,
        ]);

        // Dispatch first chunk
       ProcessAssetSyncChunk::dispatch(
                        syncStateId: $syncState->id,
                        startIndex: 0,
                        chunkSize: $chunkSize
                    )->onQueue('asset_sync');


        Log::info('Async sync started successfully', [
            'sync_state_id' => $syncState->id,
            'total_assets' => $totalAssets,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Async asset sync started!',
            'data' => [
                'sync_state_id' => $syncState->id,
                'total_assets' => $totalAssets,
                'chunk_size' => $chunkSize,
                'queue' => 'asset_sync',
                'estimated_chunks' => ceil($totalAssets / $chunkSize),
                'estimated_time' => $this->estimateSyncTime($totalAssets, $chunkSize),
                'monitor_url' => url("/assets/sync/status/{$syncState->id}"),
                'note' => $skipEstimation ? 'Estimation skipped, using default value' : null,
            ],
        ]);

    } catch (\Throwable $e) {
        Log::error('Failed to start async sync', [
            'error' => $e->getMessage(),
            'user_id' => Auth::id(),
            'trace' => $e->getTraceAsString(),
        ]);

        return response()->json([
            'status' => 'error',
            'message' => 'Failed to start sync: ' . $e->getMessage(),
        ], 500);
    }
}

/**
 * Estimate total assets
 */
private function estimateTotalAssets(ServiceDeskConfig $config): int
{
    try {
        $service = app(AssetSyncService::class);
        return $service->estimateTotalAssets();
    } catch (\Throwable $e) {
        Log::error('Failed to estimate total assets', [
            'error' => $e->getMessage(),
            'config_id' => $config->id,
        ]);
        return 0;
    }
}

/**
 * Estimate sync time
 */
private function estimateSyncTime($totalAssets, $chunkSize): string
{
    // Very conservative estimate: 10 seconds per chunk
    $chunks = ceil($totalAssets / $chunkSize);
    $totalSeconds = $chunks * 10;
    
    $hours = floor($totalSeconds / 3600);
    $minutes = floor(($totalSeconds % 3600) / 60);
    
    if ($hours > 0) {
        return sprintf('%d hours %d minutes', $hours, $minutes);
    }
    
    return sprintf('%d minutes', $minutes);
}

    /**
     * NEW: Sync with custom limits
     */
    public function syncWithLimit(Request $request, AssetSyncService $service)
    {
        try {
            // Validate request
            $request->validate([
                'limit' => 'nullable|integer|min:1|max:10000',
                'chunk_size' => 'nullable|integer|min:10|max:1000',
                'force' => 'boolean',
                'config_id' => 'nullable|exists:service_desk_configs,id',
            ]);

            $limit = $request->input('limit', 0); // 0 means fetch all
            $chunkSize = $request->input('chunk_size', 500);
            $force = $request->input('force', false);

            Log::info('Starting asset sync with custom limits', [
                'user_id' => Auth::id(),
                'limit' => $limit,
                'chunk_size' => $chunkSize,
                'force' => $force,
            ]);

            // Set custom limits
            $service->setFetchLimits($limit, $chunkSize);

            // Check config
            $configInfo = $service->validateConfig();
            
            if (!empty($configInfo['warnings'])) {
                Log::warning('Configuration warnings', [
                    'warnings' => $configInfo['warnings'],
                ]);
            }

            // Perform sync
            $result = $service->sync();

            return response()->json([
                'status' => 'success',
                'message' => 'Assets synced successfully in ' . $result['mode'] . ' mode',
                'data' => $result,
                'limits' => [
                    'requested_limit' => $limit,
                    'chunk_size' => $chunkSize,
                    'actual_fetched' => $result['total'],
                ],
                'performance' => $result['performance'] ?? null,
            ]);
        } catch (\Throwable $e) {
            Log::error('Asset sync with limit failed', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
                'limit' => $request->input('limit', 0),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Asset sync failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Original sync method (for backward compatibility)
     */
    public function sync(Request $request, AssetSyncService $service)
    {
        try {
            $request->validate([
                'force' => 'boolean',
                'config_id' => 'nullable|exists:service_desk_configs,id',
            ]);

            // Use default limits
            $service->setFetchLimits(0, 500); // Fetch all with chunk size 500

            $configInfo = $service->validateConfig();
            
            Log::info('Starting asset sync (default)', [
                'user_id' => Auth::id(),
                'warnings' => $configInfo['warnings'],
            ]);

            $result = $service->sync();

            return response()->json([
                'status' => 'success',
                'message' => 'Assets synced successfully in ' . $result['mode'] . ' mode',
                'data' => $result,
                'warnings' => $configInfo['warnings'],
            ]);
        } catch (\Throwable $e) {
            Log::error('Asset sync failed', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Asset sync failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * NEW: Progressive sync - fetch in batches
     */
    public function progressiveSync(Request $request, AssetSyncService $service)
    {
        try {
            $request->validate([
                'batch_size' => 'required|integer|min:100|max:5000',
                'offset' => 'nullable|integer|min:0',
            ]);

            $batchSize = $request->input('batch_size', 1000);
            $offset = $request->input('offset', 0);

            Log::info('Starting progressive sync', [
                'user_id' => Auth::id(),
                'batch_size' => $batchSize,
                'offset' => $offset,
            ]);

            // Set to fetch specific batch
            $service->setFetchLimits($batchSize, 500);

            $result = $service->sync();

            $nextOffset = $offset + $batchSize;
            
            return response()->json([
                'status' => 'success',
                'message' => 'Batch sync completed',
                'data' => $result,
                'pagination' => [
                    'current_batch' => $batchSize,
                    'current_offset' => $offset,
                    'next_offset' => $nextOffset,
                    'has_more' => $result['total'] >= $batchSize,
                ],
                'performance' => $result['performance'] ?? null,
            ]);
        } catch (\Throwable $e) {
            Log::error('Progressive sync failed', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
                'batch_size' => $request->input('batch_size'),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Progressive sync failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * NEW: Test sync with small batch
     */
    public function testSync(Request $request, AssetSyncService $service)
    {
        try {
            $request->validate([
                'test_size' => 'nullable|integer|min:1|max:100',
            ]);

            $testSize = $request->input('test_size', 10);

            Log::info('Starting test sync', [
                'user_id' => Auth::id(),
                'test_size' => $testSize,
            ]);

            // Set to fetch only test size
            $service->setFetchLimits($testSize, 50);

            $result = $service->sync();

            return response()->json([
                'status' => 'success',
                'message' => 'Test sync completed',
                'data' => $result,
                'test_info' => [
                    'test_size' => $testSize,
                    'actual_processed' => $result['inserted'] + $result['skipped'],
                    'performance' => $result['performance'] ?? null,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('Test sync failed', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Test sync failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Push all barcodes to SDP
     */
    public function pushAllBarcodesToSdp(AssetSyncService $service)
    {
        try {
            $success = 0;
            $failed = 0;
            $failedAssets = [];

            $mode = $service->getCurrentMode();
            
            Log::info('Starting barcode push in ' . $mode . ' mode', [
                'user_id' => Auth::id(),
            ]);

            Asset::where('external_source', 'SDP')
                ->whereNotNull('external_asset_id')
                ->whereNotNull('_snipeit_barcode_2')
                ->chunk(50, function ($assets) use (&$success, &$failed, &$failedAssets, $service, $mode) {
                    foreach ($assets as $asset) {
                        try {
                            $service->pushBarcodeToSdp($asset);
                            $success++;
                        } catch (\Throwable $e) {
                            $failed++;
                            $failedAssets[] = [
                                'id' => $asset->id,
                                'asset_tag' => $asset->asset_tag,
                                'error' => $e->getMessage(),
                                'mode' => $mode,
                            ];
                        }
                    }
                });

            return response()->json([
                'status' => $failed === 0 ? 'success' : 'partial',
                'message' => 'Barcode push completed in ' . $mode . ' mode',
                'data' => [
                    'success' => $success,
                    'failed' => $failed,
                    'failed_assets' => $failedAssets,
                    'mode' => $mode,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('Barcode push failed', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Barcode push failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Push single asset barcode to SDP
     */
    public function pushBarcodeToSdp(Asset $asset, AssetSyncService $service)
    {
        try {
            $mode = $service->getCurrentMode();
            
            Log::info('Pushing single barcode in ' . $mode . ' mode', [
                'asset_id' => $asset->id,
                'user_id' => Auth::id(),
            ]);

            $service->pushBarcodeToSdp($asset);

            return response()->json([
                'status' => 'success',
                'message' => 'Barcode pushed to SDP successfully in ' . $mode . ' mode',
                'asset' => [
                    'id' => $asset->id,
                    'asset_tag' => $asset->asset_tag,
                    'barcode' => $asset->_snipeit_barcode_2,
                    'mode' => $mode,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('Single barcode push failed', [
                'asset_id' => $asset->id,
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to push barcode: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Show sync status
     */
    public function syncStatus()
    {
        $stats = [
            'total_assets' => Asset::count(),
            'sdp_assets' => Asset::where('external_source', 'SDP')->count(),
            'assets_without_barcode' => Asset::whereNull('_snipeit_barcode_2')->count(),
            'last_synced' => Asset::max('created_at'),
        ];

        return response()->json([
            'status' => 'success',
            'data' => $stats,
        ]);
    }

    /**
     * Get available sync modes/configs
     */
    public function getSyncConfigs()
    {
        $configs = ServiceDeskConfig::where('company_id', Auth::user()->company_id)
            ->where('is_active', 1)
            ->where('is_sync_enabled', 1)
            ->get(['id', 'mode', 'base_url', 'api_version', 'portal_id', 'created_at']);

        return response()->json([
            'status' => 'success',
            'data' => $configs,
        ]);
    }

    /**
     * Validate configuration before sync
     */
    public function validateConfig(AssetSyncService $service)
    {
        try {
            $validation = $service->validateConfig();
            
            return response()->json([
                'status' => 'success',
                'message' => 'Configuration check completed',
                'data' => $validation,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Configuration check failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * NEW: Get sync performance metrics
     */
    public function getPerformanceMetrics(AssetSyncService $service)
    {
        try {
            $stats = $service->getPerformanceStats();
            
            return response()->json([
                'status' => 'success',
                'data' => $stats,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to get performance metrics: ' . $e->getMessage(),
            ], 500);
        }
    }
}