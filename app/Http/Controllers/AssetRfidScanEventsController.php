<?php

namespace App\Http\Controllers;

use App\Models\AssetRFIDscanEvents;
use App\Models\Location;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Display the latest fixed-reader RFID status for every unique mapped tag.
 *
 * The API maintains only one AssetRFIDscanEvents row per RFID EPC.
 * A repeated scan updates that same row instead of creating another row.
 *
 * @version v2.0
 */
class AssetRfidScanEventsController extends Controller
{
  /**
   * Display unique mapped RFID scan records with filters and summaries.
   */
  public function index(Request $request): View
  {
    $request->validate([
      'date_from' => [
        'nullable',
        'date_format:Y-m-d',
      ],

      'date_to' => [
        'nullable',
        'date_format:Y-m-d',
        'after_or_equal:date_from',
      ],

      'asset_id' => [
        'nullable',
        'integer',
      ],

      'asset_name' => [
        'nullable',
        'string',
        'max:191',
      ],

      'asset_tag' => [
        'nullable',
        'string',
        'max:191',
      ],

      'rfid_epc' => [
        'nullable',
        'string',
        'max:191',
      ],

      'serial' => [
        'nullable',
        'string',
        'max:191',
      ],

      'reader_code' => [
        'nullable',
        'string',
        'max:100',
      ],

      'gate_no' => [
        'nullable',
        'string',
        'max:100',
      ],

      'location_id' => [
        'nullable',
        'integer',
      ],

      'scan_result' => [
        'nullable',
        'string',
        'in:MAPPED',
      ],

      'per_page' => [
        'nullable',
        'integer',
        'in:10,25,50,100',
      ],
    ]);

    /*
         * Only mapped asset RFID records are displayed.
         *
         * Unknown RFID tags are not saved by the API.
         * Historical UNKNOWN rows, if any, are excluded.
         */
    $baseQuery = AssetRFIDscanEvents::query()
      ->whereNotNull('asset_id')
      ->where('scan_result', 'MAPPED');

    /*
         * Apply all screen filters.
         */
    $filteredQuery = $this->applyFilters(
      $baseQuery,
      $request
    );

    /*
         * Because one row is maintained per RFID EPC:
         *
         * total_tags represents the number of unique RFID tags.
         * total_scans is retained for compatibility with the existing Blade.
         */
    $totalTags = (clone $filteredQuery)->count();

    $summary = [
      /*
             * New preferred summary key.
             */
      'total_tags' => $totalTags,

      /*
             * Retained so the existing Blade does not break.
             * It now represents current unique RFID records.
             */
      'total_scans' => $totalTags,

      'unique_assets' => (clone $filteredQuery)
        ->distinct()
        ->count('asset_id'),

      'mapped_scans' => $totalTags,

      /*
             * Number of unique mapped tags scanned today.
             */
      'scanned_today' => (clone $filteredQuery)
        ->whereBetween('scanned_at', [
          now()->startOfDay(),
          now()->endOfDay(),
        ])
        ->count(),

      /*
             * These are no longer stored.
             * Retained temporarily for Blade compatibility.
             */
      'unknown_scans' => 0,
      'rejected_scans' => 0,
      'duplicate_scans' => 0,
    ];

    /*
         * Latest mapped RFID scan in the complete system.
         * This is not affected by selected filters.
         */
    $latestScan = AssetRFIDscanEvents::query()
      ->whereNotNull('asset_id')
      ->where('scan_result', 'MAPPED')
      ->orderByDesc('scanned_at')
      ->orderByDesc('id')
      ->first();

    /*
         * Load the current unique mapped RFID records.
         *
         * A repeated scan updates scanned_at on the same row, so the
         * most recently scanned tags automatically appear first.
         */
    $scanEvents = (clone $filteredQuery)
      ->orderByDesc('scanned_at')
      ->orderByDesc('id')
      ->paginate(
        $request->integer('per_page', 50)
      )
      ->withQueryString();

    /*
         * Location options for filtering and display.
         */
    $locations = Location::query()
      ->orderBy('name')
      ->pluck('name', 'id');

    /*
         * Only MAPPED records are persisted.
         */
    $scanResults = [
      'MAPPED',
    ];

    return view('asset_rfid_events.index', [
      'scanEvents' => $scanEvents,
      'summary' => $summary,
      'latestScan' => $latestScan,
      'locations' => $locations,
      'scanResults' => $scanResults,
      'settings' => Setting::getSettings(),
    ]);
  }

  /**
   * Apply table filters to the unique mapped RFID records.
   */
  private function applyFilters(
    Builder $query,
    Request $request
  ): Builder {
    /*
         * Filter by the latest scan date stored on the RFID row.
         */
    if ($request->filled('date_from')) {
      $query->where(
        'scanned_at',
        '>=',
        Carbon::parse(
          $request->input('date_from')
        )->startOfDay()
      );
    }

    if ($request->filled('date_to')) {
      $query->where(
        'scanned_at',
        '<=',
        Carbon::parse(
          $request->input('date_to')
        )->endOfDay()
      );
    }

    if ($request->filled('asset_id')) {
      $query->where(
        'asset_id',
        $request->integer('asset_id')
      );
    }

    if ($request->filled('asset_name')) {
      $query->where(
        'asset_name',
        'like',
        '%' . trim($request->input('asset_name')) . '%'
      );
    }

    if ($request->filled('asset_tag')) {
      $query->where(
        'asset_tag',
        'like',
        '%' . trim($request->input('asset_tag')) . '%'
      );
    }

    if ($request->filled('rfid_epc')) {
      $rfidEpc = strtoupper(
        trim($request->input('rfid_epc'))
      );

      $query->whereRaw(
        'UPPER(TRIM(rfid_epc)) LIKE ?',
        ['%' . $rfidEpc . '%']
      );
    }

    /*
         * Keep this filter only if serial exists in the
         * AssetRFIDscanEvents table.
         */
    if ($request->filled('serial')) {
      $query->where(
        'serial',
        'like',
        '%' . trim($request->input('serial')) . '%'
      );
    }

    if ($request->filled('reader_code')) {
      $query->where(
        'reader_code',
        'like',
        '%' . trim($request->input('reader_code')) . '%'
      );
    }

    /*
         * The API currently stores gate_no in the antenna_no column.
         */
    if ($request->filled('gate_no')) {
      $query->where(
        'antenna_no',
        'like',
        '%' . trim($request->input('gate_no')) . '%'
      );
    }

    if ($request->filled('location_id')) {
      $query->where(
        'location_id',
        $request->integer('location_id')
      );
    }

    /*
         * Only MAPPED is accepted, but this is retained so the existing
         * filter form continues to work.
         */
    if ($request->filled('scan_result')) {
      $query->where(
        'scan_result',
        $request->input('scan_result')
      );
    }

    return $query;
  }
}
