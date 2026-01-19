<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ServiceDeskConfig;
use App\Jobs\ProcessAssetSyncChunk;

class StartAssetSync extends Command
{
    protected $signature = 'assets:sync-start {--chunk=5}';
    protected $description = 'Start SDP asset sync using queued chunks';

    public function handle()
    {
        $config = ServiceDeskConfig::where('is_active', 1)
            ->where('is_sync_enabled', 1)
            ->first();

        if (!$config) {
            $this->error('No active ServiceDesk config found');
            return Command::FAILURE;
        }

        $chunkSize = (int) $this->option('chunk');
        $startIndex = $config->last_asset_sync_index ?? 0;

        // Dispatch ONE chunk only
        ProcessAssetSyncChunk::dispatch(
            $startIndex,
            $chunkSize,
            $config->id
        )->onQueue('asset_sync');

        $this->info("Asset sync job dispatched");
        return Command::SUCCESS;
    }
}
