<?php

namespace App\Jobs;

use App\Models\AssetSyncState;
use App\Services\AssetSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessAssetSyncChunk implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 900;
    public int $tries = 3;

    public function __construct(
        public int $syncStateId,
        public int $startIndex,
        public int $chunkSize
    ) {}

    public function handle(AssetSyncService $service)
    {
        $syncState = AssetSyncState::find($this->syncStateId);

        if (!$syncState) {
            return;
        }

        // 🔴 STOP if not running
        if ($syncState->status !== AssetSyncState::STATUS_RUNNING) {
            return;
        }

        // 🔴 INIT SERVICE WITHOUT AUTH
        $service->initForConfig($syncState->config_id);

        $result = $service->processSingleChunk(
            $this->startIndex,
            $this->chunkSize,
            $syncState->config_id
        );

        // 🔴 STOP if nothing processed
        if (($result['processed'] ?? 0) === 0) {
            $syncState->update([
                'status' => AssetSyncState::STATUS_COMPLETED,
                'completed_at' => now(),
                'progress_percentage' => 100,
            ]);
            return;
        }

        $currentIndex = $this->startIndex + $result['processed'];

        $syncState->update([
            'current_index' => $currentIndex,
            'processed_assets_count' => DB::raw('processed_assets_count + ' . $result['processed']),
            'success_count' => DB::raw('success_count + ' . $result['success']),
            'skip_count' => DB::raw('skip_count + ' . $result['skipped']),
            'fail_count' => DB::raw('fail_count + ' . $result['failed']),
            'progress_percentage' => min(
                100,
                round(($currentIndex / max(1, $syncState->total_assets)) * 100, 2)
            ),
        ]);

        // 🔴 DISPATCH NEXT CHUNK
        self::dispatch(
            $syncState->id,
            $currentIndex,
            $this->chunkSize
        )->onQueue('asset_sync');
    }


    public function failed(\Throwable $e)
    {
        AssetSyncState::where('id', $this->syncStateId)->update([
            'status' => AssetSyncState::STATUS_FAILED,
            'error_message' => $e->getMessage(),
        ]);
    }
}
