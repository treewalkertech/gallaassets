<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Services\AssetSyncService;
use Illuminate\Http\Request;
use App\Models\ServiceDeskConfig;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

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

    /**
     * Sync assets from SDP
     */
    public function sync(Request $request, AssetSyncService $service)
    {
        try {
            // Validate request
            $request->validate([
                'force' => 'boolean',
                'config_id' => 'nullable|exists:service_desk_configs,id',
            ]);

            // Optional: Use specific config
            if ($request->has('config_id')) {
                // You can implement logic to use specific config
            }

            // 🔹 JUST CHECK CONFIG (NO STRICT VALIDATION)
            $configInfo = $service->validateConfig();
            
            Log::info('Starting asset sync', [
                'mode' => $configInfo['mode'],
                'user_id' => Auth::id(),
                'warnings' => $configInfo['warnings'],
            ]);

            // Show warnings but don't block sync
            if (!empty($configInfo['warnings'])) {
                Log::warning('Configuration warnings (not blocking)', [
                    'warnings' => $configInfo['warnings'],
                ]);
            }

            $result = $service->sync();

            return response()->json([
                'status' => 'success',
                'message' => 'Assets synced successfully in ' . $result['mode'] . ' mode',
                'data' => $result,
                'warnings' => $configInfo['warnings'], // Informative only
            ]);
        } catch (\Throwable $e) {
            \Log::error('Asset sync failed', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Asset sync failed: ' . $e->getMessage(),
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
            \Log::error('Barcode push failed', [
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
            \Log::error('Single barcode push failed', [
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
     * Validate configuration before sync - INFORMATIVE ONLY
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
}