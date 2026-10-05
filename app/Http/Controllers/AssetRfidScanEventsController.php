<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\AssetRFIDscanEvents;
use App\Models\AssetRfidLocationHistory;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Display the latest known RFID location of each mapped asset.
 *
 * One row in asset_rfid_scan_events represents the current/latest scan state
 * of one RFID EPC. The last four accepted scans are available from
 * asset_rfid_location_history. There is no IN/OUT or working-hours flow.
 *
 * @version v4.2 CSV EXPORT
 */
class AssetRfidScanEventsController extends Controller
{
  private const DEFAULT_GATE_NAME = '3RD Floor';

  private const DEFAULT_READER_CODE = '3RD-READER-001';

  /**
   * Display the RFID asset location dashboard.
   */
  public function index(Request $request): View
  {
    $this->authorize('view', Asset::class);

    return view(
      'asset_rfid_events.index',
      $this->buildPageData($request)
    );
  }

  /**
   * Return refreshed dashboard HTML for AJAX polling.
   */
  public function ajaxStatus(Request $request): JsonResponse
  {
    $this->authorize('view', Asset::class);

    $data = $this->buildPageData($request);

    return response()->json([
      'success' => true,
      'html' => view('asset_rfid_events.index', $data)->render(),
    ]);
  }

  /**
   * Backward-compatible refresh endpoint.
   */
  public function refresh(Request $request): JsonResponse
  {
    return $this->ajaxStatus($request);
  }

  /**
   * Return the latest four scans for the selected asset/EPC popup.
   */
  public function history(
    AssetRFIDscanEvents $scanEvent
  ): JsonResponse {
    $this->authorize('view', Asset::class);

    if (
      !$scanEvent->asset_id
      || !$scanEvent->rfid_epc
      || $scanEvent->scan_result !== 'MAPPED'
    ) {
      return response()->json([
        'success' => false,
        'message' => 'Mapped RFID asset was not found.',
      ], 404);
    }

    $history = AssetRfidLocationHistory::query()
      ->whereRaw(
        'UPPER(TRIM(rfid_epc)) = ?',
        [strtoupper(trim($scanEvent->rfid_epc))]
      )
      ->orderByDesc('scanned_at')
      ->orderByDesc('id')
      ->limit(4)
      ->get()
      ->map(static function (
        AssetRfidLocationHistory $historyItem
      ): array {
        return [
          'id' => $historyItem->id,
          'scanned_at' => $historyItem->scanned_at
            ? $historyItem->scanned_at
            ->format('d-m-Y h:i:s A')
            : '-',
          'gate_name' => $historyItem->gate_name
            ?: self::DEFAULT_GATE_NAME,
          'reader_code' => $historyItem->reader_code
            ?: self::DEFAULT_READER_CODE,
          'reader_name' => $historyItem->reader_name ?: '-',
          'reader_ip' => $historyItem->reader_ip ?: '-',
          'antenna_no' => $historyItem->antenna_no ?? '-',
          'rssi' => $historyItem->rssi ?? '-',
          'event_type' => $historyItem->event_type
            ?: 'LOCATION_SCAN',
        ];
      })
      ->values();

    return response()->json([
      'success' => true,
      'asset' => [
        'id' => $scanEvent->asset_id,
        'asset_name' => $scanEvent->asset_name ?: '-',
        'asset_tag' => $scanEvent->asset_tag ?: '-',
        'serial' => $scanEvent->serial ?: '-',
        'rfid_epc' => $scanEvent->rfid_epc,
      ],
      'history' => $history,
      'count' => $history->count(),
    ]);
  }

  /**
   * Export all retained RFID history rows matching the active filters.
   */
  public function exportCsv(Request $request): StreamedResponse
  {
    $this->authorize('view', Asset::class);
    $this->validateFilters($request);

    $historyQuery = $this->applyFilters(
      AssetRfidLocationHistory::query(),
      $request
    )
      ->orderByDesc('scanned_at')
      ->orderByDesc('id');

    $fileName = 'asset-rfid-history-'
      . now()->format('Y-m-d-His')
      . '.csv';

    return response()->streamDownload(
      function () use ($historyQuery): void {
        $output = fopen('php://output', 'w');

        if ($output === false) {
          throw new \RuntimeException(
            'Unable to open CSV output stream.'
          );
        }

        // UTF-8 BOM allows Microsoft Excel to display text correctly.
        fwrite($output, "\xEF\xBB\xBF");

        fputcsv($output, [
          'Scan Date',
          'Scan Time',
          'Asset Name',
          'Asset Number',
          'Serial Number',
          'RFID EPC',
          'Floor',
          'Reader Code',
          'Reader Name',
          'Reader IP',
          'Location ID',
          'Antenna Number',
          'RSSI',
          'Scan Type',
          'Previous Reader Code',
          'Previous Floor',
          'Received At',
          'Remarks',
        ]);

        foreach ($historyQuery->cursor() as $historyItem) {
          $scannedAt = $historyItem->scanned_at
            ? Carbon::parse($historyItem->scanned_at)
            : null;
          $receivedAt = $historyItem->received_at
            ? Carbon::parse($historyItem->received_at)
            : null;

          fputcsv($output, [
            $scannedAt
              ? $scannedAt->format('Y-m-d')
              : '',
            $scannedAt
              ? $scannedAt->format('h:i:s A')
              : '',
            $this->csvValue($historyItem->asset_name),
            $this->csvValue($historyItem->asset_tag),
            $this->csvValue($historyItem->serial),
            $this->csvValue($historyItem->rfid_epc),
            $this->csvValue(
              $historyItem->gate_name
                ?: self::DEFAULT_GATE_NAME
            ),
            $this->csvValue(
              $historyItem->reader_code
                ?: self::DEFAULT_READER_CODE
            ),
            $this->csvValue($historyItem->reader_name),
            $this->csvValue($historyItem->reader_ip),
            $historyItem->location_id ?? '',
            $historyItem->antenna_no ?? '',
            $historyItem->rssi ?? '',
            $this->csvValue(
              $historyItem->event_type
                ?: 'LOCATION_SCAN'
            ),
            $this->csvValue(
              $historyItem->previous_reader_code
            ),
            $this->csvValue(
              $historyItem->previous_gate_name
            ),
            $receivedAt
              ? $receivedAt->format('Y-m-d h:i:s A')
              : '',
            $this->csvValue($historyItem->remarks),
          ]);
        }

        fclose($output);
      },
      $fileName,
      [
        'Content-Type' => 'text/csv; charset=UTF-8',
        'Cache-Control' => 'no-store, no-cache, must-revalidate',
      ]
    );
  }

  /**
   * Prevent spreadsheet applications from executing exported text as a
   * formula while preserving the displayed value.
   */
  private function csvValue(mixed $value): string
  {
    $text = trim((string) ($value ?? ''));

    if ($text !== '' && preg_match('/^[=+\-@]/', $text) === 1) {
      return "'" . $text;
    }

    return $text;
  }

  /**
   * Build all data required by the page and AJAX refresh endpoint.
   *
   * @return array<string, mixed>
   */
  private function buildPageData(Request $request): array
  {
    $this->validateFilters($request);

    $baseQuery = AssetRFIDscanEvents::query()
      ->whereNotNull('asset_id')
      ->where('scan_result', 'MAPPED');

    $filteredQuery = $this->applyFilters(
      clone $baseQuery,
      $request
    );

    $summary = [
      'tracked_assets' => (clone $filteredQuery)
        ->distinct()
        ->count('asset_id'),

      'rfid_tags' => (clone $filteredQuery)->count(),

      'floors' => (clone $filteredQuery)
        ->whereNotNull('gate_name')
        ->where('gate_name', '<>', '')
        ->distinct()
        ->count('gate_name'),

      'readers' => (clone $filteredQuery)
        ->whereNotNull('reader_code')
        ->where('reader_code', '<>', '')
        ->distinct()
        ->count('reader_code'),

      'scanned_today' => (clone $filteredQuery)
        ->whereBetween('scanned_at', [
          now()->startOfDay(),
          now()->endOfDay(),
        ])
        ->count(),
    ];

    $latestScan = (clone $baseQuery)
      ->orderByDesc('scanned_at')
      ->orderByDesc('id')
      ->first();

    $scanEvents = (clone $filteredQuery)
      ->orderByDesc('scanned_at')
      ->orderByDesc('id')
      ->paginate(
        $request->integer('per_page', 50),
        ['*'],
        'page'
      )
      ->withQueryString();

    return [
      'scanEvents' => $scanEvents,
      'summary' => $summary,
      'latestScan' => $latestScan,
      'defaultGateName' => self::DEFAULT_GATE_NAME,
      'defaultReaderCode' => self::DEFAULT_READER_CODE,
    ];
  }

  /**
   * Validate dashboard filters.
   */
  private function validateFilters(Request $request): void
  {
    $request->validate([
      'date_from' => ['nullable', 'date_format:Y-m-d'],
      'date_to' => [
        'nullable',
        'date_format:Y-m-d',
        'after_or_equal:date_from',
      ],
      'asset_name' => ['nullable', 'string', 'max:191'],
      'asset_tag' => ['nullable', 'string', 'max:191'],
      'rfid_epc' => ['nullable', 'string', 'max:191'],
      'serial' => ['nullable', 'string', 'max:191'],
      'serial_number' => ['nullable', 'string', 'max:191'],
      'reader_code' => ['nullable', 'string', 'max:100'],
      'gate_name' => ['nullable', 'string', 'max:191'],
      'per_page' => ['nullable', 'integer', 'in:10,25,50,100'],
    ]);
  }

  /**
   * Apply dashboard filters to the latest-state query.
   */
  private function applyFilters(
    Builder $query,
    Request $request
  ): Builder {
    if ($request->filled('date_from')) {
      $query->where(
        'scanned_at',
        '>=',
        Carbon::parse($request->input('date_from'))->startOfDay()
      );
    }

    if ($request->filled('date_to')) {
      $query->where(
        'scanned_at',
        '<=',
        Carbon::parse($request->input('date_to'))->endOfDay()
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
      $rfidEpc = strtoupper(trim($request->input('rfid_epc')));

      $query->whereRaw(
        'UPPER(TRIM(rfid_epc)) LIKE ?',
        ['%' . $rfidEpc . '%']
      );
    }

    $serial = $request->input('serial_number')
      ?: $request->input('serial');

    if (!empty($serial)) {
      $query->where(
        'serial',
        'like',
        '%' . trim($serial) . '%'
      );
    }

    if ($request->filled('reader_code')) {
      $query->where(
        'reader_code',
        'like',
        '%' . trim($request->input('reader_code')) . '%'
      );
    }

    if ($request->filled('gate_name')) {
      $query->where(
        'gate_name',
        'like',
        '%' . trim($request->input('gate_name')) . '%'
      );
    }

    return $query;
  }

  /**
   * Clear both latest RFID state and its popup history.
   */
  public function clearEvents(): JsonResponse
  {
    $this->authorize('view', Asset::class);

    try {
      DB::beginTransaction();

      $deletedCount = AssetRFIDscanEvents::query()->count();
      $historyDeletedCount = AssetRfidLocationHistory::query()->count();

      AssetRfidLocationHistory::query()->delete();
      AssetRFIDscanEvents::query()->delete();

      DB::commit();

      Log::warning('All RFID scan events and history were cleared', [
        'deleted_count' => $deletedCount,
        'history_deleted_count' => $historyDeletedCount,
        'user_id' => auth()->id(),
      ]);

      return response()->json([
        'success' => true,
        'message' => $deletedCount
          . ' current RFID event(s) and '
          . $historyDeletedCount
          . ' history event(s) cleared successfully.',
        'deleted_count' => $deletedCount,
        'history_deleted_count' => $historyDeletedCount,
      ]);
    } catch (\Throwable $e) {
      DB::rollBack();

      Log::error('Failed to clear RFID scan events', [
        'user_id' => auth()->id(),
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
      ]);

      return response()->json([
        'success' => false,
        'message' => 'Unable to clear RFID events.',
        'error' => $e->getMessage(),
      ], 500);
    }
  }
}
