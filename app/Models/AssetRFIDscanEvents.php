<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Latest known fixed-reader scan state for an asset RFID EPC.
 *
 * The table keeps one current row per EPC. It does not manage
 * attendance-style IN/OUT sessions or working-hours calculations.
 *
 * @version v4.1
 */
class AssetRFIDscanEvents extends Model
{
  use HasFactory;

  protected $table = 'asset_rfid_scan_events';

  protected $primaryKey = 'id';

  public $timestamps = true;

  /**
   * @var array<int, string>
   */
  protected $fillable = [
    'asset_id',
    'company_id',
    'rfid_epc',
    'asset_tag',
    'asset_name',
    'serial',
    'assigned_to',
    'assigned_to_name',
    'reader_code',
    'reader_name',
    'reader_ip',
    'gate_name',
    'location_id',
    'antenna_no',
    'rssi',
    'scan_batch_id',
    'source_event_id',
    'event_type',
    'scan_result',
    'processing_status',
    'read_count',
    'scanned_at',
    'last_seen_at',
    'received_at',
    'processed_at',
    'remarks',
  ];

  /**
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
    'scanned_at' => 'datetime',
    'last_seen_at' => 'datetime',
    'received_at' => 'datetime',
    'processed_at' => 'datetime',
    'created_at' => 'datetime',
    'updated_at' => 'datetime',
  ];

  /**
   * The mapped asset. Scan history may remain after an asset is deleted.
   */
  public function asset(): BelongsTo
  {
    return $this->belongsTo(Asset::class, 'asset_id', 'id');
  }

  /**
   * Meaningful location changes for this EPC, newest first.
   * The controller keeps a maximum of three rows per EPC.
   */
  public function locationHistory(): HasMany
  {
    return $this->hasMany(
      AssetRfidLocationHistory::class,
      'rfid_epc',
      'rfid_epc'
    )
      ->orderByDesc('scanned_at')
      ->orderByDesc('id');
  }

  /**
   * Most recently scanned assets first.
   */
  public function scopeRecent(Builder $query): Builder
  {
    return $query
      ->orderByDesc('scanned_at')
      ->orderByDesc('id');
  }

  /**
   * Filter by mapped asset.
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
    return $query->where('rfid_epc', $rfidEpc);
  }

  /**
   * Filter by fixed reader.
   */
  public function scopeForReader(
    Builder $query,
    string $readerCode
  ): Builder {
    return $query->where('reader_code', $readerCode);
  }

  /**
   * Filter by floor. The physical floor is stored in gate_name.
   */
  public function scopeForFloor(
    Builder $query,
    string $gateName
  ): Builder {
    return $query->where('gate_name', $gateName);
  }

  /**
   * Only successfully mapped asset scans.
   */
  public function scopeMapped(Builder $query): Builder
  {
    return $query
      ->whereNotNull('asset_id')
      ->where('scan_result', 'MAPPED');
  }
}
