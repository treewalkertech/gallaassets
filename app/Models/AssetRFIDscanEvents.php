<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Asset;

/**
 * Model for Asset RFID Scan Events.
 *
 * This table stores:
 *
 * 1. RFID scan information
 * 2. Asset snapshot information
 * 3. Reader/gate information
 * 4. IN / OUT session information
 * 5. Automatic OUT information
 *
 * @version v3.0
 */
class AssetRFIDscanEvents extends Model
{
  use HasFactory;

  /**
   * Database table.
   *
   * @var string
   */
  protected $table = 'asset_rfid_scan_events';

  /**
   * Primary key.
   *
   * @var string
   */
  protected $primaryKey = 'id';

  /**
   * Laravel manages created_at and updated_at.
   *
   * @var bool
   */
  public $timestamps = true;

  /**
   * Mass assignable fields.
   *
   * Keep this list synchronized with the migration.
   *
   * @var array<int, string>
   */
  protected $fillable = [

    /*
         * Asset information
         */
    'asset_id',
    'company_id',

    /*
         * RFID / asset snapshot
         */
    'rfid_epc',
    'asset_tag',
    'asset_name',
    'serial',
    'assigned_to',
    'assigned_to_name',

    /*
         * Reader / gate information
         */
    'reader_code',
    'reader_name',
    'reader_ip',
    'gate_name',
    'location_id',
    'antenna_no',
    'rssi',

    /*
         * Reader/API references
         */
    'scan_batch_id',
    'source_event_id',

    /*
         * Event information
         */
    'direction',
    'event_type',
    'scan_result',
    'processing_status',

    /*
         * Read information
         */
    'read_count',
    'scanned_at',
    'last_seen_at',
    'received_at',
    'processed_at',

    /*
         * IN / OUT session information
         *
         * These fields must exist in the new ALTER migration.
         */
    'in_at',
    'out_at',
    'expected_out_at',
    'out_type',

    /*
         * Session status.
         *
         * Expected values:
         * IN
         * OUT
         */
    'status',

    /*
         * Working duration used for this session.
         */
    'working_hours',

    /*
         * Additional information
         */
    'remarks',
  ];

  /**
   * Attribute casting.
   *
   * @var array<string, string>
   */
  protected $casts = [

    'id' => 'integer',

    'asset_id' => 'integer',

    'company_id' => 'integer',

    'assigned_to' => 'integer',

    'location_id' => 'integer',

    'antenna_no' => 'integer',

    'rssi' => 'decimal:2',

    'read_count' => 'integer',

    /*
         * RFID timestamps
         */
    'scanned_at' => 'datetime',
    'last_seen_at' => 'datetime',
    'received_at' => 'datetime',
    'processed_at' => 'datetime',

    /*
         * IN / OUT session timestamps
         */
    'in_at' => 'datetime',
    'out_at' => 'datetime',
    'expected_out_at' => 'datetime',

    /*
         * Working duration.
         */
    'working_hours' => 'integer',

    /*
         * Laravel timestamps
         */
    'created_at' => 'datetime',
    'updated_at' => 'datetime',
  ];

  /**
   * Asset relationship.
   *
   * The asset can be deleted while the RFID history remains.
   */
  public function asset(): BelongsTo
  {
    return $this->belongsTo(
      Asset::class,
      'asset_id',
      'id'
    );
  }

  /**
   * Most recent scans first.
   */
  public function scopeRecent(Builder $query): Builder
  {
    return $query->orderByDesc('scanned_at');
  }

  /**
   * Filter by asset.
   */
  public function scopeForAsset(
    Builder $query,
    int $assetId
  ): Builder {
    return $query->where('asset_id', $assetId);
  }

  /**
   * Filter by RFID EPC.
   */
  public function scopeForRfid(
    Builder $query,
    string $rfidEpc
  ): Builder {
    return $query->where(
      'rfid_epc',
      $rfidEpc
    );
  }

  /**
   * Filter by reader.
   */
  public function scopeForReader(
    Builder $query,
    string $readerCode
  ): Builder {
    return $query->where(
      'reader_code',
      $readerCode
    );
  }

  /**
   * Filter by gate.
   *
   * The current table stores the physical gate/antenna
   * information in antenna_no / gate_name.
   */
  public function scopeForGate(
    Builder $query,
    string $gateNo
  ): Builder {
    return $query->where(
      'antenna_no',
      $gateNo
    );
  }

  /**
   * Filter by scan result.
   */
  public function scopeWithScanResult(
    Builder $query,
    string $scanResult
  ): Builder {
    return $query->where(
      'scan_result',
      $scanResult
    );
  }

  /**
   * Only currently IN sessions.
   *
   * An asset is considered IN when:
   *
   * status = IN
   * and out_at is NULL.
   */
  public function scopeCurrentlyIn(
    Builder $query
  ): Builder {
    return $query
      ->where('status', 'IN')
      ->whereNull('out_at');
  }

  /**
   * Only OUT sessions.
   */
  public function scopeCurrentlyOut(
    Builder $query
  ): Builder {
    return $query
      ->where('status', 'OUT')
      ->whereNotNull('out_at');
  }

  /**
   * Find active session for an RFID tag.
   */
  public function scopeActiveForRfid(
    Builder $query,
    string $rfidEpc
  ): Builder {
    return $query
      ->where('rfid_epc', $rfidEpc)
      ->where('status', 'IN')
      ->whereNull('out_at');
  }

  /**
   * Find active session for an asset.
   */
  public function scopeActiveForAsset(
    Builder $query,
    int $assetId
  ): Builder {
    return $query
      ->where('asset_id', $assetId)
      ->where('status', 'IN')
      ->whereNull('out_at');
  }

  /**
   * Find sessions that have reached their automatic OUT time.
   */
  public function scopeReadyForAutoOut(
    Builder $query
  ): Builder {
    return $query
      ->where('status', 'IN')
      ->whereNull('out_at')
      ->whereNotNull('expected_out_at')
      ->where(
        'expected_out_at',
        '<=',
        now()
      );
  }

  /**
   * Check whether this record is currently IN.
   */
  public function isCurrentlyIn(): bool
  {
    return $this->status === 'IN'
      && is_null($this->out_at);
  }

  /**
   * Check whether this record is currently OUT.
   */
  public function isCurrentlyOut(): bool
  {
    return $this->status === 'OUT'
      && !is_null($this->out_at);
  }

  /**
   * Check whether automatic OUT is due.
   */
  public function isAutoOutDue(): bool
  {
    if (!$this->isCurrentlyIn()) {
      return false;
    }

    if (!$this->expected_out_at) {
      return false;
    }

    return $this->expected_out_at->lte(now());
  }

  /**
   * Mark this session as automatically OUT.
   */
  public function markAutoOut(): bool
  {
    if (!$this->isCurrentlyIn()) {
      return false;
    }

    $this->status = 'OUT';
    $this->direction = 'OUT';
    $this->out_at = now();
    $this->out_type = 'AUTO';

    return $this->save();
  }

  /**
   * Mark this session as manually OUT.
   */
  public function markManualOut(): bool
  {
    if (!$this->isCurrentlyIn()) {
      return false;
    }

    $this->status = 'OUT';
    $this->direction = 'OUT';
    $this->out_at = now();
    $this->out_type = 'MANUAL';

    return $this->save();
  }

  /**
   * Get the working duration in minutes.
   */
  public function getWorkingMinutesAttribute(): ?int
  {
    if (!$this->in_at) {
      return null;
    }

    $end = $this->out_at ?: now();

    return max(
      0,
      $this->in_at->diffInMinutes($end)
    );
  }

  /**
   * Get a human-readable working duration.
   *
   * Example:
   *
   * 8h 25m
   */
  public function getWorkingDurationAttribute(): ?string
  {
    $minutes = $this->working_minutes;

    if ($minutes === null) {
      return null;
    }

    $hours = intdiv(
      $minutes,
      60
    );

    $remainingMinutes = $minutes % 60;

    return $hours . 'h ' . $remainingMinutes . 'm';
  }
}
