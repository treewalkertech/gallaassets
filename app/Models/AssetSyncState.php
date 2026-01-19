<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetSyncState extends Model
{
    protected $fillable = [
        'company_id',
        'config_id',
        'status',
        'total_assets',
        'processed_assets',
        'failed_assets',
        'success_count',
        'skip_count',
        'fail_count',
        'current_index',
        'last_success_index',
        'started_at',
        'completed_at',
        'error_message',
        'progress_percentage',
        'batch_size',
        'chunk_size',
        'memory_usage',
        'estimated_time_remaining',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'processed_assets' => 'array',
        'failed_assets' => 'array',
    ];

    const STATUS_PENDING = 'pending';
    const STATUS_RUNNING = 'running';
    const STATUS_PAUSED = 'paused';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';

    public function config(): BelongsTo
    {
        return $this->belongsTo(ServiceDeskConfig::class, 'config_id');
    }
}