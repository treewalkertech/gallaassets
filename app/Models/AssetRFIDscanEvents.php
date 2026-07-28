<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model for Asset RFID Scan Events.
 *
 * @version v1.0
 */
class AssetRFIDscanEvents extends Model
{
  use HasFactory;

  /**
   * Database table used by this model.
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
   * Laravel should manage created_at and updated_at.
   *
   * @var bool
   */
  public $timestamps = true;

  /**
   * Mass assignable columns.
   *
   * @var array<int, string>
   */
  protected $fillable = [
    'asset_id',
    'asset_name',
    'asset_tag',
    'rfid_epc',
    'serial',
    'reader_code',
    'gate_no',
    'location_id',
    'scan_result',
    'scanned_at',
    'antenna_no',
    'rssi',
    'source_event_id',
    'remarks',
  ];

  /**
   * Attribute type casting.
   *
   * @var array<string, string>
   */
  protected $casts = [
    'id'             => 'integer',
    'asset_id'       => 'integer',
    'location_id'    => 'integer',
    'antenna_no'     => 'integer',
    'rssi'           => 'decimal:2',
    'scanned_at'     => 'datetime',
    'created_at'     => 'datetime',
    'updated_at'     => 'datetime',
  ];

  /**
   * Get the asset connected to this scan event.
   *
   * Change Asset::class if your asset model has a different class name.
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
   * Display the most recently scanned events first.
   */
  public function scopeRecent(Builder $query): Builder
  {
    return $query->orderByDesc('scanned_at');
  }

  /**
   * Filter events by asset.
   */
  public function scopeForAsset(Builder $query, int $assetId): Builder
  {
    return $query->where('asset_id', $assetId);
  }

  /**
   * Filter events by RFID EPC.
   */
  public function scopeForRfid(Builder $query, string $rfidEpc): Builder
  {
    return $query->where('rfid_epc', $rfidEpc);
  }

  /**
   * Filter events by reader code.
   */
  public function scopeForReader(Builder $query, string $readerCode): Builder
  {
    return $query->where('reader_code', $readerCode);
  }

  /**
   * Filter events by gate number.
   */
  public function scopeForGate(Builder $query, string $gateNo): Builder
  {
    return $query->where('gate_no', $gateNo);
  }

  /**
   * Filter events by scan result.
   */
  public function scopeWithScanResult(
    Builder $query,
    string $scanResult
  ): Builder {
    return $query->where('scan_result', $scanResult);
  }
}
