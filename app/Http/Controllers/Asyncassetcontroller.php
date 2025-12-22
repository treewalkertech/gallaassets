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

}
