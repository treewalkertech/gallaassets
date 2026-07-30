<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\AssetRFIDscanEvents;
use App\Models\Location;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\JsonResponse;

/**
 * Display RFID asset status and IN/OUT session information.
 *
 * IMPORTANT:
 * This controller works with the existing
 * asset_rfid_scan_events table.
 *
 * One row represents the current/latest state of an RFID EPC.
 *
 * IN/OUT fields are stored on the same row:
 *
 * status
 * in_at
 * out_at
 * expected_out_at
 * working_hours
 * out_type
 *
 * @version v3.0
 */
class AssetRfidScanEventsController extends Controller
{
  /**
   * Display RFID status, working-hours information,
   * current IN assets and RFID event records.
   */
  public function index(Request $request): View
  {
    $this->authorize('view', Asset::class);

    /*
         * ============================================================
         * VALIDATION
         * ============================================================
         */
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

      /*
             * The database column is `serial`.
             *
             * The Blade currently uses serial_number as the
             * request field, so we support both.
             */
      'serial' => [
        'nullable',
        'string',
        'max:191',
      ],

      'serial_number' => [
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

      /*
             * Optional status filter.
             */
      'status' => [
        'nullable',
        'string',
        'in:IN,OUT',
      ],

      'per_page' => [
        'nullable',
        'integer',
        'in:10,25,50,100',
      ],
    ]);


    /*
         * ============================================================
         * WORKING HOURS
         * ============================================================
         *
         * Default is 10 hours.
         *
         * For now we read the value from the settings collection if
         * the setting exists.
         *
         * The API should use the same value when creating a new IN
         * session.
         */
    $settings = Setting::getSettings();

    $workingHours = $this->getWorkingHours($settings);


    /*
         * ============================================================
         * BASE QUERY
         * ============================================================
         *
         * Only mapped RFID records belonging to an asset are shown.
         */
    $baseQuery = AssetRFIDscanEvents::query()
      ->whereNotNull('asset_id')
      ->where('scan_result', 'MAPPED');


    /*
         * ============================================================
         * APPLY FILTERS
         * ============================================================
         */
    $filteredQuery = $this->applyFilters(
      $baseQuery,
      $request
    );


    /*
         * ============================================================
         * TOTAL RFID TAGS
         * ============================================================
         *
         * The project maintains one current row per RFID EPC.
         */
    $totalTags = (clone $filteredQuery)->count();


    /*
         * ============================================================
         * SUMMARY
         * ============================================================
         */
    $summary = [
      'total_tags' => $totalTags,

      /*
             * Kept for compatibility with older Blade code.
             */
      'total_scans' => $totalTags,

      'unique_assets' => (clone $filteredQuery)
        ->distinct()
        ->count('asset_id'),

      'mapped_scans' => $totalTags,

      /*
             * Unique RFID records scanned today.
             */
      'scanned_today' => (clone $filteredQuery)
        ->whereBetween('scanned_at', [
          now()->startOfDay(),
          now()->endOfDay(),
        ])
        ->count(),

      /*
             * Current IN count.
             */
      'currently_in' => (clone $filteredQuery)
        ->where('status', 'IN')
        ->count(),

      /*
             * Current OUT count.
             */
      'currently_out' => (clone $filteredQuery)
        ->where(function (Builder $query) {
          $query
            ->where('status', 'OUT')
            ->orWhereNull('status');
        })
        ->count(),

      /*
             * Retained for compatibility.
             */
      'unknown_scans' => 0,
      'rejected_scans' => 0,
      'duplicate_scans' => 0,
    ];


    /*
         * ============================================================
         * SESSION SUMMARY
         * ============================================================
         */
    $sessionSummary = [
      'currently_in' => (clone $filteredQuery)
        ->where('status', 'IN')
        ->count(),

      'currently_out' => (clone $filteredQuery)
        ->where(function (Builder $query) {
          $query
            ->where('status', 'OUT')
            ->orWhereNull('status');
        })
        ->count(),
    ];


    /*
         * ============================================================
         * LATEST RFID SCAN
         * ============================================================
         *
         * Not affected by filters.
         */
    $latestScan = AssetRFIDscanEvents::query()
      ->whereNotNull('asset_id')
      ->where('scan_result', 'MAPPED')
      ->orderByDesc('scanned_at')
      ->orderByDesc('id')
      ->first();


    /*
         * ============================================================
         * CURRENTLY IN ASSETS
         * ============================================================
         *
         * These are the assets whose current status is IN.
         *
         * expected_out_at is already calculated when the asset
         * enters IN state.
         */
    $currentSessions = AssetRFIDscanEvents::query()
      ->whereNotNull('asset_id')
      ->where('scan_result', 'MAPPED')
      ->where('status', 'IN')
      ->orderByDesc('in_at')
      ->orderByDesc('id')
      ->paginate(
        $request->integer('per_page', 50),
        ['*'],
        'in_page'
      )
      ->withQueryString();


    /*
         * ============================================================
         * MAIN EVENT TABLE
         * ============================================================
         *
         * This table contains:
         *
         * Last Scan
         * Asset
         * RFID
         * Last IN
         * Last OUT
         * Expected OUT
         * Working Hours
         * Status
         */
    $scanEvents = (clone $filteredQuery)
      ->orderByDesc('scanned_at')
      ->orderByDesc('id')
      ->paginate(
        $request->integer('per_page', 50),
        ['*'],
        'page'
      )
      ->withQueryString();


    /*
         * ============================================================
         * LOCATIONS
         * ============================================================
         */
    $locations = Location::query()
      ->orderBy('name')
      ->pluck('name', 'id');


    /*
         * ============================================================
         * SCAN RESULTS
         * ============================================================
         */
    $scanResults = [
      'MAPPED',
    ];


    /*
         * ============================================================
         * STATUS OPTIONS
         * ============================================================
         */
    $statusOptions = [
      'IN',
      'OUT',
    ];


    /*
         * ============================================================
         * RETURN VIEW
         * ============================================================
         */
    return view('asset_rfid_events.index', [
      'scanEvents' => $scanEvents,

      'summary' => $summary,

      'sessionSummary' => $sessionSummary,

      'currentSessions' => $currentSessions,

      'latestScan' => $latestScan,

      'locations' => $locations,

      'scanResults' => $scanResults,

      'statusOptions' => $statusOptions,

      'workingHours' => $workingHours,

      'settings' => $settings,
    ]);
  }

  public function refresh(Request $request)
  {
    // Use the same query logic as index()

    $scanEvents = $this->getScanEvents($request);

    $locations = Location::pluck('name', 'id');

    return response()->json([
      'success' => true,
      'html' => view(
        'asset-rfid-scan-events._table',
        compact(
          'scanEvents',
          'locations'
        )
      )->render(),
    ]);
  }


  /**
   * Resolve configured RFID working hours.
   *
   * Default = 10 hours.
   *
   * We intentionally keep this method isolated because the exact
   * structure returned by Setting::getSettings() may differ between
   * installations.
   *
   * Supported examples:
   *
   * $settings['rfid_working_hours']
   * $settings->rfid_working_hours
   *
   * If no value is found, 10 is returned.
   */
  private function getWorkingHours($settings): int
  {
    $defaultHours = 8;

    /*
         * Array based settings.
         */
    if (is_array($settings)) {

      $value = $settings['rfid_working_hours']
        ?? $settings['working_hours']
        ?? null;

      if (is_numeric($value)) {
        $value = (int) $value;

        if ($value >= 1 && $value <= 24) {
          return $value;
        }
      }
    }


    /*
         * Object based settings.
         */
    if (is_object($settings)) {

      $value = $settings->rfid_working_hours
        ?? $settings->working_hours
        ?? null;

      if (is_numeric($value)) {
        $value = (int) $value;

        if ($value >= 1 && $value <= 24) {
          return $value;
        }
      }
    }


    return $defaultHours;
  }


  /**
   * Apply table filters.
   */
  private function applyFilters(
    Builder $query,
    Request $request
  ): Builder {

    /*
         * ============================================================
         * DATE FROM
         * ============================================================
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


    /*
         * ============================================================
         * DATE TO
         * ============================================================
         */
    if ($request->filled('date_to')) {

      $query->where(
        'scanned_at',
        '<=',
        Carbon::parse(
          $request->input('date_to')
        )->endOfDay()
      );
    }


    /*
         * ============================================================
         * ASSET ID
         * ============================================================
         */
    if ($request->filled('asset_id')) {

      $query->where(
        'asset_id',
        $request->integer('asset_id')
      );
    }


    /*
         * ============================================================
         * ASSET NAME
         * ============================================================
         */
    if ($request->filled('asset_name')) {

      $query->where(
        'asset_name',
        'like',
        '%' . trim(
          $request->input('asset_name')
        ) . '%'
      );
    }


    /*
         * ============================================================
         * ASSET TAG
         * ============================================================
         */
    if ($request->filled('asset_tag')) {

      $query->where(
        'asset_tag',
        'like',
        '%' . trim(
          $request->input('asset_tag')
        ) . '%'
      );
    }


    /*
         * ============================================================
         * RFID EPC
         * ============================================================
         */
    if ($request->filled('rfid_epc')) {

      $rfidEpc = strtoupper(
        trim(
          $request->input('rfid_epc')
        )
      );

      $query->whereRaw(
        'UPPER(TRIM(rfid_epc)) LIKE ?',
        [
          '%' . $rfidEpc . '%',
        ]
      );
    }


    /*
         * ============================================================
         * SERIAL NUMBER
         * ============================================================
         *
         * Database column = serial.
         *
         * Blade/request may send serial_number.
         */
    $serial = $request->input('serial_number')
      ?: $request->input('serial');

    if (!empty($serial)) {

      $query->where(
        'serial',
        'like',
        '%' . trim($serial) . '%'
      );
    }


    /*
         * ============================================================
         * READER CODE
         * ============================================================
         */
    if ($request->filled('reader_code')) {

      $query->where(
        'reader_code',
        'like',
        '%' . trim(
          $request->input('reader_code')
        ) . '%'
      );
    }


    /*
         * ============================================================
         * GATE
         * ============================================================
         *
         * Your current schema does not have a gate_no column.
         *
         * Existing table uses antenna_no.
         */
    if ($request->filled('gate_no')) {

      $query->where(
        'antenna_no',
        $request->input('gate_no')
      );
    }


    /*
         * ============================================================
         * LOCATION
         * ============================================================
         */
    if ($request->filled('location_id')) {

      $query->where(
        'location_id',
        $request->integer('location_id')
      );
    }


    /*
         * ============================================================
         * SCAN RESULT
         * ============================================================
         */
    if ($request->filled('scan_result')) {

      $query->where(
        'scan_result',
        $request->input('scan_result')
      );
    }


    /*
         * ============================================================
         * IN / OUT STATUS
         * ============================================================
         */
    if ($request->filled('status')) {

      $query->where(
        'status',
        strtoupper(
          $request->input('status')
        )
      );
    }


    return $query;
  }
  /**
   * Save RFID working hours configuration.
   *
   * Allowed values:
   * 1, 2, 4, 6, 8, 10, 12, 16, 24
   */
  public function saveWorkingHours(Request $request)
  {
    $this->authorize('view', Asset::class);

    $validated = $request->validate([
      'working_hours' => [
        'required',
        'integer',
        'in:1,2,4,6,8,10,12,16,24',
      ],
    ]);

    $workingHours = (int) $validated['working_hours'];

    /*
     * Save RFID working hours in settings.
     *
     * This assumes your settings table uses:
     *
     * key   = setting name
     * value = setting value
     *
     * If your Setting model uses different columns,
     * we will adjust this part after checking that model.
     */
    Setting::updateOrCreate(
      [
        'key' => 'rfid_working_hours',
      ],
      [
        'value' => (string) $workingHours,
      ]
    );

    return response()->json([
      'success' => true,
      'message' => 'RFID working hours saved successfully.',
      'working_hours' => $workingHours,
    ]);
  }
  /**
   * Clear all RFID scan events.
   *
   * This endpoint is called through AJAX/fetch from the
   * Asset RFID Status page.
   *
   * @return JsonResponse
   */
  public function clearEvents(): JsonResponse
  {
    try {

      DB::beginTransaction();

      /*
         * Count records before deleting them so we can
         * return the deleted count to the frontend.
         */
      $deletedCount = AssetRFIDscanEvents::query()->count();

      /*
         * Delete all RFID event records.
         */
      AssetRFIDscanEvents::query()->delete();

      DB::commit();

      /*
         * Log the clear operation.
         */
      Log::warning('All RFID scan events were cleared', [
        'deleted_count' => $deletedCount,
        'user_id' => auth()->id(),
      ]);

      /*
         * Return JSON because the frontend uses fetch()
         * and expects response.json().
         */
      return response()->json([
        'success' => true,
        'message' => $deletedCount . ' RFID event(s) cleared successfully.',
        'deleted_count' => $deletedCount,
      ], 200);
    } catch (\Throwable $e) {

      /*
         * Roll back if anything goes wrong.
         */
      DB::rollBack();

      Log::error('Failed to clear RFID scan events', [
        'user_id' => auth()->id(),
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
      ]);

      /*
         * Return JSON error instead of redirect.
         */
      return response()->json([
        'success' => false,
        'message' => 'Unable to clear RFID events.',
        'error' => $e->getMessage(),
      ], 500);
    }
  }
  public function status(Request $request): JsonResponse
  {
    $this->authorize('view', Asset::class);

    $request->validate([
      'asset_name' => ['nullable', 'string', 'max:191'],
      'rfid_epc' => ['nullable', 'string', 'max:191'],
      'serial' => ['nullable', 'string', 'max:191'],
      'location_id' => ['nullable', 'integer'],
      'current_status' => ['nullable', 'string', 'in:IN,OUT,AUTO_OUT'],
      'per_page' => ['nullable', 'integer', 'in:10,25,50,100'],
    ]);

    $baseQuery = AssetRFIDscanEvents::query()
      ->whereNotNull('asset_id')
      ->where('scan_result', 'MAPPED');

    $filteredQuery = $this->applyFilters($baseQuery, $request);

    $scanEvents = $filteredQuery
      ->orderByDesc('scanned_at')
      ->orderByDesc('id')
      ->paginate(
        $request->integer('per_page', 50),
        ['*'],
        'page'
      )
      ->withQueryString();

    $locations = Location::query()
      ->orderBy('name')
      ->pluck('name', 'id');

    return response()->json([
      'success' => true,
      'html' => view(
        'asset_rfid_events._table',
        compact('scanEvents', 'locations')
      )->render(),
    ]);
  }
}
