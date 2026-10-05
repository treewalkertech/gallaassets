<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Meaningful RFID asset location history.
 *
 * A row is created only when an EPC is first seen or when its floor/reader
 * changes. The controller retains only the latest three rows per EPC.
 */
class AssetRfidLocationHistory extends Model
{
  use HasFactory;

  protected $table = 'asset_rfid_location_history';

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
    'reader_code',
    'reader_name',
    'reader_ip',
    'gate_name',
    'location_id',
    'antenna_no',
    'rssi',
    'previous_reader_code',
    'previous_gate_name',
    'event_type',
    'scanned_at',
    'received_at',
    'remarks',
  ];

  /**
   * @var array<string, string>
   */
  protected $casts = [
    'id' => 'integer',
    'asset_id' => 'integer',
    'company_id' => 'integer',
    'location_id' => 'integer',
    'antenna_no' => 'integer',
    'rssi' => 'decimal:2',
    'scanned_at' => 'datetime',
    'received_at' => 'datetime',
    'created_at' => 'datetime',
    'updated_at' => 'datetime',
  ];

  public function asset(): BelongsTo
  {
    return $this->belongsTo(Asset::class, 'asset_id', 'id');
  }

  public function currentLocation(): BelongsTo
  {
    return $this->belongsTo(
      AssetRFIDscanEvents::class,
      'rfid_epc',
      'rfid_epc'
    );
  }

  public function scopeRecent(Builder $query): Builder
  {
    return $query
      ->orderByDesc('scanned_at')
      ->orderByDesc('id');
  }

  public function scopeForRfid(
    Builder $query,
    string $rfidEpc
  ): Builder {
    return $query->where('rfid_epc', $rfidEpc);
  }

  public function scopeForAsset(
    Builder $query,
    int $assetId
  ): Builder {
    return $query->where('asset_id', $assetId);
  }

  public function scopeForFloor(
    Builder $query,
    string $gateName
  ): Builder {
    return $query->where('gate_name', $gateName);
  }
}
