<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Services\AssetSyncService;
use Illuminate\Http\Request;

class Asyncassetcontroller extends Controller
{
    public function index()
    {
        $assets = Asset::latest()->get();
        return view('assets.index', compact('assets'));
    }

    public function sync(AssetSyncService $service)
    {
        try {
            $result = $service->sync();

            return response()->json([
                'status' => 'success',
                'inserted' => $result['inserted'],
                'skipped' => $result['skipped'],
                'message' => 'Asset sync completed'
            ]);
        } catch (\Throwable $e) {

            \Log::error('Asset sync failed', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

        public function pushAllBarcodesToSdp()
    {
        $assets = Asset::where('external_source', 'SDP')
            ->whereNotNull('external_asset_id')
            ->whereNotNull('_snipeit_barcode_2')
            ->get();

        $success = 0;
        $failed  = 0;
        $errors  = [];

        foreach ($assets as $asset) {

            try {
                $payload = [
                    'asset' => [
                        'barcode' => $asset->_snipeit_barcode_2,
                    ]
                ];

                $response = \Illuminate\Support\Facades\Http::withOptions([
                    'verify'  => false,
                    'timeout' => 30,
                ])
                ->withHeaders([
                    'TECHNICIAN_KEY' => env('SDP_TECHNICIAN_KEY'),
                    'Content-Type'   => 'application/x-www-form-urlencoded',
                ])
                ->withHeaders([
                    'Cookie' =>
                        'SDPSESSIONID=' . env('SDPSESSIONID') . '; ' .
                        '_zcsr_tmp=' . env('SDP_CSRF_COOKIE') . '; ' .
                        'sdpcsrfcookie=' . env('SDP_CSRF_COOKIE'),
                ])
                ->asForm()
                ->put(
                    'https://localhost:8080/api/v3/assets/' . $asset->external_asset_id,
                    [
                        'input_data' => json_encode($payload),
                    ]
                );

                if ($response->successful()) {
                    $success++;
                } else {
                    $failed++;
                    $errors[] = [
                        'asset_id' => $asset->id,
                        'sdp_id'   => $asset->external_asset_id,
                        'message'  => $response->json('response_status.messages.0.message')
                            ?? 'Unknown SDP error',
                    ];
                }

            } catch (\Throwable $e) {
                $failed++;
                $errors[] = [
                    'asset_id' => $asset->id,
                    'error'    => $e->getMessage(),
                ];
            }
        }

        return response()->json([
            'status'   => $failed === 0 ? 'success' : 'partial',
            'total'    => $assets->count(),
            'success'  => $success,
            'failed'   => $failed,
            'errors'   => $errors, // keep for logs/debug
        ]);
    }


}
