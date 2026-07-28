<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Events\NoteAdded;
use App\Helpers\Helper;
use App\Models\Location;
use App\Models\Asset;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use App\Models\AssetModel;
use App\Http\Requests\StoreAssetRequest;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Models\AssetRFIDscanEvents;
use Carbon\Carbon;

class AssetRfidController extends Controller
{
    public function update(Request $request)
    {
        // ✅ Validate request
        $validator = Validator::make($request->all(), [
            'asset_tag' => 'required|string',
            'rfid'      => 'required|string|max:200',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Validation failed',
                'errors'  => $validator->errors(),
            ], 422);
        }

        // ✅ Normalize RFID (avoid case / space duplicates)
        $rfid = strtoupper(trim($request->rfid));

        if ($rfid === '') {
            return response()->json([
                'status'  => 'error',
                'message' => 'Invalid RFID value',
            ], 422);
        }

        // ✅ Find asset by asset_tag
        $asset = Asset::where('asset_tag', $request->asset_tag)->first();

        if (! $asset) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Asset not found',
            ], 404);
        }

        /**
         * ✅ CHECK 1: SAME ASSET, SAME RFID (ALLOW)
         * This avoids false conflict when user re-submits same RFID
         */
        $currentRfid = strtoupper(trim((string) $asset->rfid));

        if ($currentRfid === $rfid && $currentRfid !== '') {
            return response()->json([
                'status'  => 'success',
                'message' => 'RFID already mapped to this asset',
                'data'    => [
                    'asset_id'  => $asset->id,
                    'asset_tag' => $asset->asset_tag,
                    'rfid'      => $asset->rfid,
                ],
            ]);
        }

        /**
         * ✅ CHECK 2: GLOBAL DUPLICATE (BLOCK)
         * RFID must NOT exist on ANY other asset
         */
        $duplicate = Asset::whereRaw('UPPER(TRIM(rfid)) = ?', [$rfid])
            ->where('id', '!=', $asset->id)
            ->first();

        if ($duplicate) {
            return response()->json([
                'status'  => 'error',
                'message' => 'RFID already mapped to another asset',
                'data'    => [
                    'conflict_asset_id'  => $duplicate->id,
                    'conflict_asset_tag' => $duplicate->asset_tag,
                    'conflict_rfid'      => $duplicate->rfid,
                ],
            ], 409);
        }

        // ✅ UPDATE RFID
        $asset->rfid = $rfid;
        $asset->save();

        return response()->json([
            'status'  => 'success',
            'message' => 'RFID updated successfully',
            'data'    => [
                'asset_id'  => $asset->id,
                'asset_tag' => $asset->asset_tag,
                'rfid'      => $asset->rfid,
            ],
        ]);
    }



    /**
     * BULK RFID UPDATE
     */
    // public function bulkUpdate(Request $request)
    // {
    //     // ✅ Validate structure
    //     $validator = Validator::make($request->all(), [
    //         'items' => 'required|array|min:1',
    //         'items.*.asset_tag' => 'required|string',
    //         'items.*.rfid' => 'required|string|max:100',
    //     ]);

    //     if ($validator->fails()) {
    //         return response()->json([
    //             'status' => 'error',
    //             'message' => 'Validation failed',
    //             'errors' => $validator->errors(),
    //         ], 422);
    //     }

    //     $updated = [];
    //     $notFound = [];

    //     DB::beginTransaction();

    //     try {
    //         foreach ($request->items as $row) {

    //             $asset = Asset::where('asset_tag', $row['asset_tag'])->first();

    //             if (!$asset) {
    //                 $notFound[] = $row['asset_tag'];
    //                 continue;
    //             }

    //             $asset->rfid = $row['rfid'];
    //             $asset->save();

    //             $updated[] = [
    //                 'asset_id'  => $asset->id,
    //                 'asset_tag' => $asset->asset_tag,
    //                 'rfid'      => $asset->rfid,
    //             ];
    //         }

    //         DB::commit();

    //         return response()->json([
    //             'status' => 'success',
    //             'updated_count' => count($updated),
    //             'not_found_count' => count($notFound),
    //             'updated' => $updated,
    //             'not_found_asset_tags' => $notFound,
    //         ]);

    //     } catch (\Throwable $e) {
    //         DB::rollBack();

    //         return response()->json([
    //             'status' => 'error',
    //             'message' => 'Bulk update failed',
    //             'error' => $e->getMessage(),
    //         ], 500);
    //     }
    // }

    /**
     * Get all asset details
     */
    public function index()
    {
        $assets = Asset::query()
            ->leftJoin('locations', 'assets.location_id', '=', 'locations.id')
            ->leftJoin('models', 'assets.model_id', '=', 'models.id')
            ->select([
                'assets.id',
                'assets.external_asset_id',
                'assets.external_source',

                'assets.name',
                'assets.asset_tag',
                'assets.rfid',

                'assets.model_id',
                'models.name as model_name',

                'assets.serial',

                'assets.purchase_date',
                'assets.asset_eol_date',
                'assets.purchase_cost',

                'assets.status_id',
                'assets.company_id',

                'assets.location_id',
                'locations.name as location_name',
                'locations.city as location_city',
                'locations.country as location_country',

                'assets.created_at',
                'assets.updated_at',
            ])
            ->orderBy('assets.id', 'desc')
            ->get();

        $locations = Location::query()
            ->select([
                'id',
                'name',
                'city',
                'country',
                'address',
                'created_at',
                'updated_at',
            ])
            ->orderBy('name')
            ->get();

        return response()->json([
            'status' => 'success',
            'assets_count' => $assets->count(),
            'locations_count' => $locations->count(),
            'assets' => $assets,
            'locations' => $locations,
        ]);
    }
    public function auditStoreRaw(Request $request): JsonResponse
    {
        /* ----------------------------------
        | Validate array input
        ---------------------------------- */
        $validator = Validator::make($request->all(), [
            '*.asset_tag'   => 'required|string|max:255',
            '*.rfid'        => 'required|string|max:255',
            '*.location_id' => 'required|integer|exists:locations,id',
        ]);

        if ($validator->fails()) {
            return response()->json(
                Helper::formatStandardApiResponse(
                    'error',
                    null,
                    $validator->errors()->all()
                ),
                422
            );
        }

        $success = [];
        $notMatched = [];

        foreach ($request->all() as $row) {

            /* ----------------------------------
            | Try matching asset_tag + rfid
            ---------------------------------- */
            $asset = Asset::where('asset_tag', $row['asset_tag'])
                ->where('rfid', $row['rfid'])
                ->first();

            if (!$asset) {
                // ❌ Collect but DO NOT stop
                $notMatched[] = [
                    'asset_tag' => $row['asset_tag'],
                    'rfid'      => $row['rfid'],
                    'reason'    => 'Asset tag and RFID mismatch',
                ];
                continue;
            }

            /* ----------------------------------
            | Update audit for matched asset
            ---------------------------------- */
            $asset->last_audit_date = now();
            $asset->location_id     = $row['location_id'];
            $asset->save();

            /* ----------------------------------
            | Log audit
            ---------------------------------- */
            $asset->logAudit(
                'Audit completed via bulk RFID scan',
                $row['location_id'],
                null
            );

            $success[] = [
                'asset_id'        => $asset->id,
                'asset_tag'       => $asset->asset_tag,
                'rfid'            => $asset->rfid,
                'location_id'     => $asset->location_id,
                'last_audit_date' => $asset->last_audit_date,
                'created_by'      => 1,
            ];
        }

        /* ----------------------------------
        | FINAL RESPONSE (NO ERRORS)
        ---------------------------------- */
        return response()->json(
            Helper::formatStandardApiResponse(
                'success',
                [
                    'total_received' => count($request->all()),
                    'updated'        => count($success),
                    'not_matched'    => $notMatched,
                    'updated_assets' => $success,
                ],
                'Bulk audit completed'
            )
        );
    }
    // {
    //   "tags": [
    //     "E280689400004030ACC344F1",
    //     "E110973190410A1000841B28",
    //     "E2806A9600004016A4D34675",
    //     "E2806A9600004016A4D34675",
    //     "E110973190410A1000824B28"
    //   ]
    // }
    /**
     * Record RFID events received from a fixed RFID reader.
     *
     * One request may contain multiple RFID EPC reads.
     * One event row is saved for every unique EPC.
     */


    public function recordFixedReaderRfidEvent(Request $request): JsonResponse
    {
        Log::info('Asset fixed-reader RFID event request received', [
            'reader_code' => $request->input('reader_code'),
            'read_count' => is_array($request->input('reads'))
                ? count($request->input('reads'))
                : 0,
        ]);

        $validator = Validator::make($request->all(), [
            /*
         * Reader-level information.
         */
            'reader_code' => [
                'required',
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

            'remarks' => [
                'nullable',
                'string',
                'max:1000',
            ],

            /*
         * Kept for compatibility with the Java application.
         *
         * Because only one row is maintained per EPC, repeated scans
         * update the existing row instead of creating extra rows.
         */
            'min_scan_interval' => [
                'nullable',
                'integer',
                'min:0',
                'max:3600',
            ],

            /*
         * RFID reads.
         */
            'reads' => [
                'required',
                'array',
                'min:1',
                'max:500',
            ],

            'reads.*.epc' => [
                'required',
                'string',
                'max:191',
            ],

            'reads.*.read_time' => [
                'nullable',
                'date',
            ],

            'reads.*.antenna_no' => [
                'nullable',
                'integer',
                'min:0',
            ],

            'reads.*.rssi' => [
                'nullable',
                'numeric',
            ],

            'reads.*.source_event_id' => [
                'nullable',
                'string',
                'max:100',
            ],

            'reads.*.remarks' => [
                'nullable',
                'string',
                'max:1000',
            ],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

        $readerCode = trim($validated['reader_code']);

        $gateNo = ! empty($validated['gate_no'])
            ? trim($validated['gate_no'])
            : null;

        $requestLocationId = $validated['location_id'] ?? null;

        $defaultRemarks = $validated['remarks'] ?? null;

        $reads = $validated['reads'];

        $results = [];

        $summary = [
            'received' => count($reads),
            'unique_received' => 0,
            'created' => 0,
            'updated' => 0,
            'mapped' => 0,
            'unknown' => 0,
            'duplicates_collapsed' => 0,
            'older_scans_ignored' => 0,
            'rejected' => 0,
        ];

        /*
        * Keep only the newest read for every EPC inside this request.
        *
        * Example:
        * If the same tag appears 10 times in one batch, only its latest
        * read_time is processed.
        */
        $latestReadsByEpc = [];

        foreach ($reads as $read) {
            $epc = strtoupper(trim($read['epc'] ?? ''));

            if ($epc === '') {
                $summary['rejected']++;

                $results[] = [
                    'epc' => null,
                    'success' => false,
                    'event_id' => null,
                    'asset_id' => null,
                    'scan_result' => 'REJECTED',
                    'action' => 'REJECTED',
                    'message' => 'Empty RFID EPC was rejected.',
                ];

                continue;
            }

            $scannedAt = ! empty($read['read_time'])
                ? Carbon::parse($read['read_time'])
                : now();

            /*
            * Store the normalized EPC inside the read data.
            */
            $normalizedRead = $read;
            $normalizedRead['epc'] = $epc;

            if (! isset($latestReadsByEpc[$epc])) {
                $latestReadsByEpc[$epc] = [
                    'read' => $normalizedRead,
                    'scanned_at' => $scannedAt,
                ];

                continue;
            }

            /*
         * Replace the previously stored read only when this scan is newer.
         */
            if (
                $scannedAt->greaterThan(
                    $latestReadsByEpc[$epc]['scanned_at']
                )
            ) {
                $latestReadsByEpc[$epc] = [
                    'read' => $normalizedRead,
                    'scanned_at' => $scannedAt,
                ];
            }

            $summary['duplicates_collapsed']++;
        }

        $summary['unique_received'] = count($latestReadsByEpc);

        DB::beginTransaction();

        try {
            foreach ($latestReadsByEpc as $epc => $preparedRead) {
                $read = $preparedRead['read'];

                /** @var Carbon $scannedAt */
                $scannedAt = $preparedRead['scanned_at'];

                /*
                * Check whether the EPC is mapped to an asset.
                *
                * RFID values are normalized before comparison to avoid
                * problems caused by spaces or letter case.
                */
                $asset = Asset::query()
                    ->whereRaw(
                        'UPPER(TRIM(rfid)) = ?',
                        [$epc]
                    )
                    ->first();

                /*
                * Unknown RFID tags must never be inserted into the
                * AssetRFIDscanEvents table.
                */
                if (! $asset) {
                    $summary['unknown']++;

                    $results[] = [
                        'epc' => $epc,
                        'success' => false,
                        'event_id' => null,
                        'asset_id' => null,
                        'asset_name' => null,
                        'asset_tag' => null,
                        'scan_result' => 'NOT_MAPPED',
                        'action' => 'NOT_SAVED',
                        'message' => 'RFID tag is not mapped to any asset.',
                    ];

                    continue;
                }

                $summary['mapped']++;

                $sourceEventId = ! empty($read['source_event_id'])
                    ? trim($read['source_event_id'])
                    : null;

                $locationId = $requestLocationId
                    ?? $asset->location_id;

                /*
             * Find the existing row for this RFID EPC.
             *
             * lockForUpdate prevents two simultaneous API requests from
             * updating the same existing row at the same time.
             */
                $event = AssetRFIDscanEvents::query()
                    ->whereRaw(
                        'UPPER(TRIM(rfid_epc)) = ?',
                        [$epc]
                    )
                    ->orderByDesc('scanned_at')
                    ->orderByDesc('id')
                    ->lockForUpdate()
                    ->first();

                $eventAlreadyExists = $event !== null;

                if (! $event) {
                    $event = new AssetRFIDscanEvents();
                    $event->rfid_epc = $epc;
                }

                /*
             * These asset details can always be refreshed because the tag
             * may have been remapped or the asset details may have changed.
             */
                if ($asset->serial) {
                    $event->serial = $asset->serial;
                }
                $event->asset_id = $asset->id;
                $event->asset_name = $asset->name;
                $event->asset_tag = $asset->asset_tag;
                $event->scan_result = 'MAPPED';

                /*
             * If a delayed Java queue sends an older scan after a newer
             * scan has already been saved, do not move scanned_at backwards.
             */
                $existingScannedAt = null;

                if ($eventAlreadyExists && $event->scanned_at) {
                    $existingScannedAt = Carbon::parse(
                        $event->scanned_at
                    );
                }

                $olderThanExistingScan = $existingScannedAt !== null
                    && $existingScannedAt->greaterThan($scannedAt);

                if (! $olderThanExistingScan) {
                    /*
                 * Latest scan information.
                 */
                    $event->reader_code = $readerCode;

                    /*
                 * Keeping the current project behavior where gate_no is
                 * stored in antenna_no.
                 */
                    $event->antenna_no = $gateNo;

                    $event->location_id = $locationId;
                    $event->scanned_at = $scannedAt;
                    $event->rssi = $read['rssi'] ?? null;
                    $event->source_event_id = $sourceEventId;
                    $event->remarks = $read['remarks']
                        ?? $defaultRemarks;
                } else {
                    /*
                 * Preserve the newer scan data already in the database.
                 */
                    $summary['older_scans_ignored']++;
                }

                $event->save();

                if ($eventAlreadyExists) {
                    $summary['updated']++;

                    $action = $olderThanExistingScan
                        ? 'EXISTING_NEWER_SCAN_RETAINED'
                        : 'UPDATED';

                    $message = $olderThanExistingScan
                        ? 'RFID event already contained a newer scan. The latest stored scan time was retained.'
                        : 'Existing RFID event updated successfully.';
                } else {
                    $summary['created']++;

                    $action = 'CREATED';
                    $message = 'New RFID event created successfully.';
                }

                $results[] = [
                    'epc' => $epc,
                    'success' => true,
                    'event_id' => $event->id,
                    'asset_id' => $asset->id,
                    'asset_name' => $asset->name,
                    'asset_tag' => $asset->asset_tag,
                    'rfid' => $asset->rfid,
                    'scan_result' => $event->scan_result,
                    'action' => $action,
                    'scanned_at' => $event->scanned_at
                        ? Carbon::parse($event->scanned_at)
                        ->toDateTimeString()
                        : null,
                    'message' => $message,
                ];
            }

            DB::commit();

            if (
                $summary['created'] === 0
                && $summary['updated'] === 0
            ) {
                $message = $summary['unknown'] > 0
                    ? 'RFID reads were processed, but no mapped tags were found.'
                    : 'RFID reads were processed, but no event rows were changed.';
            } elseif ($summary['unknown'] > 0) {
                $message =
                    'Mapped RFID events were created or updated. Unmapped tags were skipped.';
            } else {
                $message =
                    'RFID scan events were created or updated successfully.';
            }

            /*
         * Return HTTP 200 for both creates and updates.
         *
         * This allows the Java application to mark the complete local
         * batch as processed and prevents unmapped tags from retrying forever.
         */
            return response()->json([
                'success' => true,
                'message' => $message,
                'reader_code' => $readerCode,
                'gate_no' => $gateNo,
                'location_id' => $requestLocationId,
                'summary' => $summary,
                'results' => $results,
            ], 200);
        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('Asset fixed-reader RFID event failed', [
                'reader_code' => $readerCode,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to create or update RFID scan events.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
