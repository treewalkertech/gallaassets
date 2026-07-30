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
        Log::info('Asset RFID update request received', [
            'asset_tag' => $request->asset_tag,
            'rfid'      => $request->rfid,
        ]);

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
     * IN / OUT logic:
     *
     * 1. RFID is not currently IN
     *      -> mark/create record as IN
     *
     * 2. RFID is already IN and still inside working hours
     *      -> remain IN
     *      -> update last_seen_at
     *      -> DO NOT mark OUT
     *
     * 3. RFID is already IN but working hours have expired
     *      -> mark OUT
     *      -> current scan becomes a new IN
     *
     * 4. Scheduler can independently mark expired IN records as AUTO OUT.
     *
     * Temporary configuration:
     *      Working hours = 8 hours
     *
     * Later this value will come from application Settings.
     *
     * Current design intentionally keeps one row per RFID EPC.
     */
    public function recordFixedReaderRfidEvent(Request $request): JsonResponse
    {
        /*
     * ---------------------------------------------------------
     * DEFAULT WORKING HOURS
     * ---------------------------------------------------------
     *
     * Temporary fixed value.
     *
     * Later replace this with the value from application
     * Settings.
     */
        $defaultWorkingHours = 8;

        Log::info('Asset fixed-reader RFID event request received', [
            'reader_code' => $request->input('reader_code'),
            'read_count' => is_array($request->input('reads'))
                ? count($request->input('reads'))
                : 0,
        ]);

        /*
     * ---------------------------------------------------------
     * VALIDATION
     * ---------------------------------------------------------
     *
     * Working hours are intentionally NOT accepted from the
     * Android application.
     *
     * The API currently always uses the fixed default value
     * defined above.
     */
        $validator = Validator::make($request->all(), [

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

            'min_scan_interval' => [
                'nullable',
                'integer',
                'min:0',
                'max:3600',
            ],

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

        /*
     * ---------------------------------------------------------
     * WORKING HOURS
     * ---------------------------------------------------------
     *
     * For now, always use 8 hours.
     *
     * Later:
     *
     * $workingHours = Setting::get(...);
     */
        $workingHours = $defaultWorkingHours;

        $readerCode = trim(
            $validated['reader_code']
        );

        $gateNo = !empty($validated['gate_no'])
            ? trim($validated['gate_no'])
            : null;

        $requestLocationId =
            $validated['location_id'] ?? null;

        $defaultRemarks =
            $validated['remarks'] ?? null;

        $reads = $validated['reads'];

        Log::info('Asset fixed-reader RFID event request validated', [
            'reader_code' => $readerCode,
            'working_hours' => $workingHours,
            'scanned_epcs' => array_map(
                fn($read) => strtoupper(
                    trim($read['epc'] ?? '')
                ),
                $reads
            ),
        ]);

        $results = [];

        /*
     * ---------------------------------------------------------
     * SUMMARY
     * ---------------------------------------------------------
     */
        $summary = [
            'received' => count($reads),
            'unique_received' => 0,

            'created' => 0,
            'updated' => 0,

            'mapped' => 0,
            'unknown' => 0,
            'rejected' => 0,

            'in_created' => 0,
            'in_existing' => 0,

            'auto_out_before_new_in' => 0,

            'duplicates_collapsed' => 0,
            'older_scans_ignored' => 0,
        ];

        /*
     * ---------------------------------------------------------
     * COLLAPSE DUPLICATE EPCs INSIDE REQUEST
     * ---------------------------------------------------------
     *
     * Example:
     *
     * EPC1
     * EPC1
     * EPC1
     * EPC2
     *
     * Only latest EPC1 + EPC2 are processed.
     */
        $latestReadsByEpc = [];

        foreach ($reads as $read) {

            $epc = strtoupper(
                trim($read['epc'] ?? '')
            );

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

            $scannedAt = !empty($read['read_time'])
                ? Carbon::parse($read['read_time'])
                : now();

            $normalizedRead = $read;

            $normalizedRead['epc'] = $epc;

            if (!isset($latestReadsByEpc[$epc])) {

                $latestReadsByEpc[$epc] = [
                    'read' => $normalizedRead,
                    'scanned_at' => $scannedAt,
                ];

                continue;
            }

            /*
         * Keep only the latest scan for the same EPC.
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

        $summary['unique_received'] =
            count($latestReadsByEpc);

        /*
     * ---------------------------------------------------------
     * DATABASE TRANSACTION
     * ---------------------------------------------------------
     */
        DB::beginTransaction();

        try {

            foreach (
                $latestReadsByEpc as $epc => $preparedRead
            ) {

                $read = $preparedRead['read'];

                /** @var Carbon $scannedAt */
                $scannedAt = $preparedRead['scanned_at'];

                /*
             * -------------------------------------------------
             * FIND ASSET
             * -------------------------------------------------
             */
                $asset = Asset::query()
                    ->whereRaw(
                        'UPPER(TRIM(rfid)) = ?',
                        [$epc]
                    )
                    ->first();

                /*
             * Unknown RFID must NOT be stored.
             */
                if (!$asset) {

                    $summary['unknown']++;

                    $results[] = [
                        'epc' => $epc,
                        'success' => false,
                        'event_id' => null,
                        'asset_id' => null,
                        'asset_name' => null,
                        'asset_tag' => null,
                        'scan_result' => 'NOT_MAPPED',
                        'direction' => 'UNKNOWN',
                        'status' => null,
                        'action' => 'NOT_SAVED',
                        'message' =>
                        'RFID tag is not mapped to any asset.',
                    ];

                    continue;
                }

                $summary['mapped']++;

                /*
             * -------------------------------------------------
             * READER DATA
             * -------------------------------------------------
             */
                $sourceEventId =
                    !empty($read['source_event_id'])
                    ? trim($read['source_event_id'])
                    : null;

                $locationId =
                    $requestLocationId
                    ?? $asset->location_id;

                $readRemarks =
                    $read['remarks']
                    ?? $defaultRemarks;

                /*
             * -------------------------------------------------
             * FIND EXISTING RFID RECORD
             * -------------------------------------------------
             *
             * One row per RFID EPC.
             */
                $event = AssetRFIDscanEvents::query()
                    ->whereRaw(
                        'UPPER(TRIM(rfid_epc)) = ?',
                        [$epc]
                    )
                    ->orderByDesc('id')
                    ->lockForUpdate()
                    ->first();

                /*
             * -------------------------------------------------
             * CREATE NEW RFID RECORD
             * -------------------------------------------------
             */
                if (!$event) {

                    $event = new AssetRFIDscanEvents();

                    $event->rfid_epc =
                        $epc;

                    /*
                 * First scan = IN.
                 */
                    $event->in_at =
                        $scannedAt;

                    $event->out_at =
                        null;

                    /*
                 * 8 hours from current scan.
                 */
                    $event->expected_out_at =
                        $scannedAt->copy()->addHours(
                            $workingHours
                        );

                    $event->working_hours =
                        $workingHours;

                    $event->status =
                        'IN';

                    $event->direction =
                        'IN';

                    $event->out_type =
                        null;

                    $summary['created']++;
                    $summary['in_created']++;

                    $action =
                        'IN';

                    $message =
                        'RFID asset marked IN successfully.';
                } else {

                    /*
                 * -------------------------------------------------
                 * EXISTING RECORD
                 * -------------------------------------------------
                 */

                    $currentStatus =
                        strtoupper(
                            trim(
                                (string) $event->status
                            )
                        );

                    /*
                 * -------------------------------------------------
                 * CASE 1:
                 * CURRENTLY IN
                 * -------------------------------------------------
                 */
                    if (
                        $currentStatus === 'IN'
                        && !$event->out_at
                    ) {

                        /*
                     * Determine expected OUT time.
                     *
                     * Existing records already having
                     * expected_out_at keep that time.
                     *
                     * If it is missing, calculate it using
                     * the current default working hours = 8.
                     */
                        $expectedOutAt =
                            $event->expected_out_at;

                        if (
                            !$expectedOutAt
                            && $event->in_at
                        ) {

                            $expectedOutAt =
                                Carbon::parse(
                                    $event->in_at
                                )->addHours(
                                    $workingHours
                                );

                            $event->expected_out_at =
                                $expectedOutAt;
                        }

                        /*
                     * -------------------------------------------------
                     * STILL WITHIN WORKING HOURS
                     * -------------------------------------------------
                     */
                        if (
                            $expectedOutAt
                            && $scannedAt->lt(
                                Carbon::parse(
                                    $expectedOutAt
                                )
                            )
                        ) {

                            /*
                         * DO NOT change IN.
                         *
                         * DO NOT create OUT.
                         */
                            $event->status =
                                'IN';

                            $event->direction =
                                'IN';

                            /*
                         * Reader information.
                         */
                            $event->reader_code =
                                $readerCode;

                            $event->location_id =
                                $locationId;

                            $event->antenna_no =
                                $read['antenna_no']
                                ?? $event->antenna_no;

                            $event->rssi =
                                $read['rssi']
                                ?? $event->rssi;

                            $event->source_event_id =
                                $sourceEventId
                                ?? $event->source_event_id;

                            /*
                         * Keep original IN time.
                         */
                            $event->in_at =
                                Carbon::parse(
                                    $event->in_at
                                );

                            /*
                         * Keep expected OUT.
                         */
                            $event->expected_out_at =
                                $expectedOutAt;

                            /*
                         * Latest scan.
                         */
                            $event->scanned_at =
                                $scannedAt;

                            /*
                         * Asset was detected again.
                         */
                            $event->last_seen_at =
                                $scannedAt;

                            $event->remarks =
                                $readRemarks;

                            $event->save();

                            $summary['updated']++;
                            $summary['in_existing']++;

                            $action =
                                'STILL_IN';

                            $message =
                                'RFID asset is already IN. Repeated scan kept the asset IN.';
                        } else {

                            /*
                         * -------------------------------------------------
                         * WORKING HOURS EXPIRED
                         * -------------------------------------------------
                         *
                         * If the scheduler has not yet marked this
                         * record OUT and a new scan arrives after
                         * expected_out_at:
                         *
                         * OLD SESSION:
                         *     OUT = expected_out_at
                         *     OUT TYPE = AUTO
                         *
                         * NEW SESSION:
                         *     IN = current scan
                         */
                            $automaticOutAt =
                                $expectedOutAt
                                ? Carbon::parse(
                                    $expectedOutAt
                                )
                                : $scannedAt;

                            /*
                         * Close previous session.
                         *
                         * IMPORTANT:
                         * Do not use current scan time as the old
                         * session's OUT time.
                         */
                            $event->out_at =
                                $automaticOutAt;

                            $event->out_type =
                                'AUTO';

                            /*
                         * Start NEW IN session.
                         */
                            $event->in_at =
                                $scannedAt;

                            /*
                         * New session uses fixed 8 hours.
                         */
                            $event->working_hours =
                                $workingHours;

                            $event->expected_out_at =
                                $scannedAt->copy()->addHours(
                                    $workingHours
                                );

                            $event->status =
                                'IN';

                            $event->direction =
                                'IN';

                            $event->last_seen_at =
                                $scannedAt;

                            $event->scanned_at =
                                $scannedAt;

                            $event->reader_code =
                                $readerCode;

                            $event->location_id =
                                $locationId;

                            $event->antenna_no =
                                $read['antenna_no']
                                ?? $event->antenna_no;

                            $event->rssi =
                                $read['rssi']
                                ?? $event->rssi;

                            $event->source_event_id =
                                $sourceEventId
                                ?? $event->source_event_id;

                            $event->remarks =
                                $readRemarks;

                            $event->save();

                            $summary['updated']++;
                            $summary['auto_out_before_new_in']++;
                            $summary['in_created']++;

                            $action =
                                'AUTO_OUT_THEN_IN';

                            $message =
                                'Previous working period expired. RFID asset was automatically OUT and the current scan started a new IN session.';
                        }
                    } else {

                        /*
                     * -------------------------------------------------
                     * CASE 2:
                     * CURRENTLY OUT
                     * -------------------------------------------------
                     *
                     * This scan starts a NEW IN session.
                     */
                        $event->in_at =
                            $scannedAt;

                        /*
                     * Keep the previous out_at.
                     *
                     * It represents the latest OUT time.
                     */
                        $event->working_hours =
                            $workingHours;

                        $event->expected_out_at =
                            $scannedAt->copy()->addHours(
                                $workingHours
                            );

                        $event->status =
                            'IN';

                        $event->direction =
                            'IN';

                        /*
                     * New IN session means current out_type
                     * is no longer relevant.
                     */
                        $event->out_type =
                            null;

                        $event->last_seen_at =
                            $scannedAt;

                        $event->scanned_at =
                            $scannedAt;

                        $event->reader_code =
                            $readerCode;

                        $event->location_id =
                            $locationId;

                        $event->antenna_no =
                            $read['antenna_no']
                            ?? $event->antenna_no;

                        $event->rssi =
                            $read['rssi']
                            ?? $event->rssi;

                        $event->source_event_id =
                            $sourceEventId
                            ?? $event->source_event_id;

                        $event->remarks =
                            $readRemarks;

                        $event->save();

                        $summary['updated']++;
                        $summary['in_created']++;

                        $action =
                            'OUT_TO_IN';

                        $message =
                            'RFID asset was OUT and is now marked IN.';
                    }
                }

                /*
             * -------------------------------------------------
             * REFRESH ASSET SNAPSHOT
             * -------------------------------------------------
             */
                $event->asset_id =
                    $asset->id;

                $event->asset_name =
                    $asset->name;

                $event->asset_tag =
                    $asset->asset_tag;

                $event->serial =
                    $asset->serial;

                $event->company_id =
                    $asset->company_id;

                $event->scan_result =
                    'MAPPED';

                /*
             * Ensure reader information exists.
             */
                if (!$event->reader_code) {
                    $event->reader_code =
                        $readerCode;
                }

                if (!$event->location_id) {
                    $event->location_id =
                        $locationId;
                }

                /*
             * Update read count.
             */
                $event->read_count =
                    max(
                        1,
                        (int) (
                            $event->read_count
                            ?? 1
                        )
                    );

                $event->save();

                /*
             * -------------------------------------------------
             * RESPONSE ITEM
             * -------------------------------------------------
             */
                $results[] = [

                    'epc' =>
                    $epc,

                    'success' =>
                    true,

                    'event_id' =>
                    $event->id,

                    'asset_id' =>
                    $asset->id,

                    'asset_name' =>
                    $asset->name,

                    'asset_tag' =>
                    $asset->asset_tag,

                    'rfid' =>
                    $asset->rfid,

                    'scan_result' =>
                    $event->scan_result,

                    'direction' =>
                    $event->direction,

                    'status' =>
                    $event->status,

                    'action' =>
                    $action,

                    'working_hours' =>
                    $event->working_hours,

                    'in_at' =>
                    $event->in_at
                        ? Carbon::parse(
                            $event->in_at
                        )->toDateTimeString()
                        : null,

                    'out_at' =>
                    $event->out_at
                        ? Carbon::parse(
                            $event->out_at
                        )->toDateTimeString()
                        : null,

                    'expected_out_at' =>
                    $event->expected_out_at
                        ? Carbon::parse(
                            $event->expected_out_at
                        )->toDateTimeString()
                        : null,

                    'out_type' =>
                    $event->out_type,

                    'last_seen_at' =>
                    $event->last_seen_at
                        ? Carbon::parse(
                            $event->last_seen_at
                        )->toDateTimeString()
                        : null,

                    'scanned_at' =>
                    $event->scanned_at
                        ? Carbon::parse(
                            $event->scanned_at
                        )->toDateTimeString()
                        : null,

                    'message' =>
                    $message,
                ];
            }

            DB::commit();

            /*
         * ---------------------------------------------------------
         * FINAL MESSAGE
         * ---------------------------------------------------------
         */
            if (
                $summary['mapped'] === 0
                && $summary['unknown'] > 0
            ) {

                $message =
                    'RFID reads were received, but no mapped assets were found.';
            } elseif (
                $summary['auto_out_before_new_in'] > 0
            ) {

                $message =
                    'RFID events processed. Expired sessions were automatically OUT and new scans were marked IN.';
            } elseif (
                $summary['in_created'] > 0
            ) {

                $message =
                    'RFID events processed successfully.';
            } else {

                $message =
                    'RFID events processed. Existing IN assets remained IN.';
            }

            Log::info(
                'Asset fixed-reader RFID events processed',
                [
                    'reader_code' =>
                    $readerCode,

                    'working_hours' =>
                    $workingHours,

                    'summary' =>
                    $summary,
                ]
            );

            return response()->json([

                'success' =>
                true,

                'message' =>
                $message,

                'reader_code' =>
                $readerCode,

                'gate_no' =>
                $gateNo,

                'location_id' =>
                $requestLocationId,

                /*
             * Currently always 8.
             *
             * Later this will come from Settings.
             */
                'working_hours' =>
                $workingHours,

                'summary' =>
                $summary,

                'results' =>
                $results,

            ], 200);
        } catch (\Throwable $e) {

            DB::rollBack();

            Log::error(
                'Asset fixed-reader RFID event failed',
                [
                    'reader_code' =>
                    $readerCode,

                    'working_hours' =>
                    $workingHours,

                    'message' =>
                    $e->getMessage(),

                    'file' =>
                    $e->getFile(),

                    'line' =>
                    $e->getLine(),

                    'trace' =>
                    $e->getTraceAsString(),
                ]
            );

            return response()->json([

                'success' =>
                false,

                'message' =>
                'Unable to process RFID scan events.',

                'error' =>
                $e->getMessage(),

            ], 500);
        }
    }
}
