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
    /**
     * Create a new asset and map RFID EPC at the same time.
     *
     * Mobile terminology:
     *
     * asset_tag = Asset Number
     * name      = Asset Name
     * serial    = Serial Number
     * rfid      = EPC Tag
     */
    public function createWithRfid(Request $request): JsonResponse
    {
        /*
     * ============================================================
     * VALIDATION
     * ============================================================
     */
        $validator = Validator::make($request->all(), [

            // Asset Number
            'asset_tag' => [
                'required',
                'string',
                'max:255',
            ],

            // Asset Name
            'name' => [
                'required',
                'string',
                'max:255',
            ],

            // Serial Number - optional
            'serial' => [
                'nullable',
                'string',
                'max:255',
            ],

            // EPC Tag
            'rfid' => [
                'required',
                'string',
                'max:200',
            ],

            /*
         * Required by Asset model.
         *
         * We can later hide these from the mobile UI
         * and use configured defaults if required.
         */
            'model_id' => [
                'required',
                'integer',
                'exists:models,id',
            ],

            'status_id' => [
                'required',
                'integer',
                'exists:status_labels,id',
            ],

            // Optional
            'company_id' => [
                'nullable',
                'integer',
                'exists:companies,id',
            ],

            'location_id' => [
                'nullable',
                'integer',
                'exists:locations,id',
            ],
        ]);

        if ($validator->fails()) {

            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }


        /*
     * ============================================================
     * NORMALIZE VALUES
     * ============================================================
     */

        $assetTag = trim($request->input('asset_tag'));

        $assetName = trim($request->input('name'));

        $serial = $request->filled('serial')
            ? trim($request->input('serial'))
            : null;

        $rfid = strtoupper(
            trim($request->input('rfid'))
        );


        /*
     * ============================================================
     * BASIC EMPTY CHECK
     * ============================================================
     */

        if ($assetTag === '') {

            return response()->json([
                'status' => 'error',
                'message' => 'Asset Number is required.',
            ], 422);
        }


        if ($assetName === '') {

            return response()->json([
                'status' => 'error',
                'message' => 'Asset Name is required.',
            ], 422);
        }


        if ($rfid === '') {

            return response()->json([
                'status' => 'error',
                'message' => 'RFID EPC Tag is required.',
            ], 422);
        }


        /*
     * ============================================================
     * CHECK ASSET NUMBER DUPLICATE
     * ============================================================
     */

        $existingAsset = Asset::withTrashed()
            ->where('asset_tag', $assetTag)
            ->first();

        if ($existingAsset) {

            return response()->json([
                'status' => 'error',
                'message' => 'Asset Number already exists.',
                'data' => [
                    'asset_id' => $existingAsset->id,
                    'asset_tag' => $existingAsset->asset_tag,
                ],
            ], 409);
        }


        /*
     * ============================================================
     * CHECK SERIAL DUPLICATE
     * ============================================================
     *
     * Serial is optional, but if provided it must remain unique
     * because the Asset model already requires unique serial values.
     */

        if ($serial !== null && $serial !== '') {

            $serialExists = Asset::withTrashed()
                ->where('serial', $serial)
                ->first();

            if ($serialExists) {

                return response()->json([
                    'status' => 'error',
                    'message' => 'Serial Number already exists.',
                    'data' => [
                        'asset_id' => $serialExists->id,
                        'asset_tag' => $serialExists->asset_tag,
                        'serial' => $serialExists->serial,
                    ],
                ], 409);
            }
        }


        /*
     * ============================================================
     * CHECK RFID DUPLICATE
     * ============================================================
     *
     * One EPC can only belong to one asset.
     */

        $rfidExists = Asset::query()
            ->whereNotNull('rfid')
            ->whereRaw(
                'UPPER(TRIM(rfid)) = ?',
                [$rfid]
            )
            ->first();

        if ($rfidExists) {

            return response()->json([
                'status' => 'error',
                'message' => 'RFID EPC is already mapped to another asset.',
                'data' => [
                    'asset_id' => $rfidExists->id,
                    'asset_tag' => $rfidExists->asset_tag,
                    'asset_name' => $rfidExists->name,
                    'rfid' => $rfidExists->rfid,
                ],
            ], 409);
        }


        /*
     * ============================================================
     * DATABASE TRANSACTION
     * ============================================================
     */

        DB::beginTransaction();

        try {

            /*
         * ------------------------------------------------------------
         * CREATE ASSET
         * ------------------------------------------------------------
         */

            $asset = new Asset();

            $asset->asset_tag = $assetTag;
            $asset->name = $assetName;
            $asset->serial = $serial;

            /*
         * Permanent RFID mapping.
         */
            $asset->rfid = $rfid;

            /*
         * Required Asset fields.
         */
            $asset->model_id = (int) $request->input('model_id');
            $asset->status_id = (int) $request->input('status_id');

            /*
         * Optional fields.
         */
            $asset->company_id = $request->filled('company_id')
                ? (int) $request->input('company_id')
                : null;

            $asset->location_id = $request->filled('location_id')
                ? (int) $request->input('location_id')
                : null;

            /*
         * Same location can be used as RTD/default location.
         */
            $asset->rtd_location_id = $request->filled('location_id')
                ? (int) $request->input('location_id')
                : null;

            /*
         * If authenticated API user exists.
         */
            $asset->created_by = auth()->id();


            /*
         * ============================================================
         * MODEL VALIDATION
         * ============================================================
         *
         * Important because Asset uses ValidatingTrait.
         */

            if (!$asset->isValid()) {

                DB::rollBack();

                return response()->json([
                    'status' => 'error',
                    'message' => 'Asset validation failed.',
                    'errors' => $asset->getErrors(),
                ], 422);
            }


            /*
         * ============================================================
         * SAVE ASSET
         * ============================================================
         */

            if (!$asset->save()) {

                DB::rollBack();

                return response()->json([
                    'status' => 'error',
                    'message' => 'Unable to create asset.',
                    'errors' => $asset->getErrors(),
                ], 422);
            }


            DB::commit();


            /*
         * ============================================================
         * SUCCESS LOG
         * ============================================================
         */

            Log::info(
                'Asset created with RFID mapping',
                [
                    'asset_id' => $asset->id,
                    'asset_tag' => $asset->asset_tag,
                    'name' => $asset->name,
                    'serial' => $asset->serial,
                    'rfid' => $asset->rfid,
                    'model_id' => $asset->model_id,
                    'status_id' => $asset->status_id,
                ]
            );


            /*
         * ============================================================
         * SUCCESS RESPONSE
         * ============================================================
         */

            return response()->json([
                'status' => 'success',
                'message' => 'Asset created and RFID mapped successfully.',
                'data' => [

                    'asset_id' => $asset->id,

                    // Asset Number
                    'asset_tag' => $asset->asset_tag,

                    'name' => $asset->name,

                    'serial' => $asset->serial,

                    'rfid' => $asset->rfid,

                    'model_id' => $asset->model_id,

                    'status_id' => $asset->status_id,

                    'company_id' => $asset->company_id,

                    'location_id' => $asset->location_id,

                    'created_at' => $asset->created_at,
                ],
            ], 201);
        } catch (\Throwable $e) {

            DB::rollBack();

            Log::error(
                'Create asset with RFID failed',
                [
                    'asset_tag' => $assetTag,
                    'name' => $assetName,
                    'serial' => $serial,
                    'rfid' => $rfid,
                    'error' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]
            );


            return response()->json([
                'status' => 'error',
                'message' => 'Unable to create asset.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
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

    /**
     * Record the latest known floor and reader for mapped RFID assets.
     *
     * One row is maintained per RFID EPC. Repeated scans update that row.
     * There is no IN/OUT, automatic OUT, or working-hours processing.
     */
    public function recordFixedReaderRfidEvent(Request $request): JsonResponse
    {
        $defaultGateName = '3RD Floor';
        $defaultReaderCode = '3RD-READER-001';

        $validator = Validator::make($request->all(), [
            'reader_code' => ['nullable', 'string', 'max:100'],
            'reader_name' => ['nullable', 'string', 'max:191'],
            'reader_ip' => ['nullable', 'ip'],
            'location_id' => ['nullable', 'integer', 'min:1'],

            // gate_name is the floor displayed on the dashboard.
            'gate_name' => ['nullable', 'string', 'max:191'],

            // Kept temporarily for older reader clients.
            'gate_no' => ['nullable', 'string', 'max:191'],

            'scan_batch_id' => ['nullable', 'string', 'max:100'],
            'remarks' => ['nullable', 'string', 'max:1000'],
            'min_scan_interval' => [
                'nullable',
                'integer',
                'min:0',
                'max:3600',
            ],

            'reads' => ['required', 'array', 'min:1', 'max:500'],
            'reads.*.epc' => ['required', 'string', 'max:191'],
            'reads.*.read_time' => ['nullable', 'date'],
            'reads.*.antenna_no' => ['nullable', 'integer', 'min:0'],
            'reads.*.rssi' => ['nullable', 'numeric'],
            // 'reads.*.source_event_id' => [
            //     'nullable',
            //     'string',
            //     'max:100',
            // ],
            'reads.*.remarks' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

        $readerCode = trim((string) ($validated['reader_code'] ?? ''));

        if ($readerCode === '') {
            $readerCode = $defaultReaderCode;
        }

        $gateName = trim((string) ($validated['gate_name'] ?? ''));

        if ($gateName === '') {
            $gateName = trim((string) ($validated['gate_no'] ?? ''));
        }

        if ($gateName === '') {
            $gateName = $defaultGateName;
        }

        $readerName = isset($validated['reader_name'])
            ? trim((string) $validated['reader_name'])
            : null;

        $readerIp = $validated['reader_ip'] ?? null;
        $readerLocationId = $validated['location_id'] ?? null;
        $scanBatchId = $validated['scan_batch_id'] ?? null;
        $defaultRemarks = $validated['remarks'] ?? null;
        $reads = $validated['reads'];
        $receivedAt = now();

        Log::info('Asset fixed-reader RFID event request received', [
            'reader_code' => $readerCode,
            'gate_name' => $gateName,
            'used_default_reader_code' => empty($validated['reader_code']),
            'used_default_gate_name' => empty($validated['gate_name'])
                && empty($validated['gate_no']),
            'read_count' => count($reads),
        ]);

        $results = [];
        $summary = [
            'received' => count($reads),
            'unique_received' => 0,
            'created' => 0,
            'updated' => 0,
            'mapped' => 0,
            'unknown' => 0,
            'rejected' => 0,
            'older_scans_latest_unchanged' => 0,
            'history_created' => 0,
            'history_deleted' => 0,
            'first_seen_history' => 0,
            'location_changed_history' => 0,
            'same_location_history' => 0,
        ];

        /*
         * Keep every valid read. Repeated EPCs in the same request must also
         * be stored in history, even when the reader and floor are unchanged.
         */
        $preparedReads = [];
        $uniqueEpcs = [];

        foreach ($reads as $read) {
            $epc = strtoupper(trim((string) ($read['epc'] ?? '')));

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
                ->setTimezone(config('app.timezone', 'Asia/Kolkata'))
                : now();

            // Log::info('RFID scan time check', [
            //     'epc' => $epc,
            //     'raw_read_time' => $read['read_time'] ?? null,
            //     'parsed_scanned_at' => $scannedAt->format('Y-m-d h:i:s A P'),
            //     'received_at' => $receivedAt->format('Y-m-d h:i:s A P'),
            //     'timezone' => config('app.timezone'),
            // ]);
            $read['epc'] = $epc;
            $preparedReads[] = [
                'read' => $read,
                'scanned_at' => $scannedAt,
            ];
            $uniqueEpcs[$epc] = true;
        }

        $summary['unique_received'] = count($uniqueEpcs);

        DB::beginTransaction();

        try {
            foreach ($preparedReads as $preparedRead) {
                $read = $preparedRead['read'];
                $scannedAt = $preparedRead['scanned_at'];
                $epc = $read['epc'];

                // Permanent RFID mapping is stored on assets.rfid.
                $asset = Asset::query()
                    ->whereNotNull('rfid')
                    ->whereRaw('UPPER(TRIM(rfid)) = ?', [$epc])
                    ->first();

                if (!$asset) {
                    $summary['unknown']++;

                    Log::warning('RFID tag is not mapped to any asset', [
                        'rfid_epc' => $epc,
                        'reader_code' => $readerCode,
                        'gate_name' => $gateName,
                    ]);

                    $results[] = [
                        'epc' => $epc,
                        'success' => false,
                        'event_id' => null,
                        'asset_id' => null,
                        'asset_name' => null,
                        'asset_tag' => null,
                        'serial' => null,
                        'scan_result' => 'NOT_MAPPED',
                        'action' => 'NOT_SAVED',
                        'reader_code' => $readerCode,
                        'gate_name' => $gateName,
                        'message' => 'RFID tag is not mapped to any asset.',
                    ];
                    continue;
                }

                $summary['mapped']++;

                $event = AssetRFIDscanEvents::query()
                    ->whereRaw('UPPER(TRIM(rfid_epc)) = ?', [$epc])
                    ->orderByDesc('id')
                    ->lockForUpdate()
                    ->first();

                $previousReaderCode = $event
                    ? trim((string) $event->reader_code)
                    : null;
                $previousGateName = $event
                    ? trim((string) $event->gate_name)
                    : null;

                $isOlderScan = $event
                    && $event->scanned_at
                    && $scannedAt->lt(Carbon::parse($event->scanned_at));

                $isLocationChanged = $event && (
                    strcasecmp(
                        (string) $previousReaderCode,
                        $readerCode
                    ) !== 0
                    || strcasecmp(
                        (string) $previousGateName,
                        $gateName
                    ) !== 0
                );

                $historyEventType = !$event
                    ? 'FIRST_SEEN'
                    : ($isLocationChanged
                        ? 'LOCATION_CHANGED'
                        : 'LOCATION_SCAN');

                /*
                 * Every mapped read is inserted into history, including a
                 * repeated read from the same reader and floor.
                 */
                $history = \App\Models\AssetRfidLocationHistory::query()
                    ->create([
                        'asset_id' => $asset->id,
                        'company_id' => $asset->company_id,
                        'rfid_epc' => $epc,
                        'asset_tag' => $asset->asset_tag,
                        'asset_name' => $asset->name,
                        'serial' => $asset->serial,
                        'reader_code' => $readerCode,
                        'reader_name' => $readerName,
                        'reader_ip' => $readerIp,
                        'gate_name' => $gateName,
                        'location_id' => $readerLocationId
                            ?? $asset->location_id,
                        'antenna_no' => $read['antenna_no'] ?? null,
                        'rssi' => $read['rssi'] ?? null,
                        'previous_reader_code' => $event
                            ? $previousReaderCode
                            : null,
                        'previous_gate_name' => $event
                            ? $previousGateName
                            : null,
                        'event_type' => $historyEventType,
                        'scanned_at' => $scannedAt,
                        'received_at' => $receivedAt,
                        'remarks' => $read['remarks'] ?? $defaultRemarks,
                    ]);

                /*
                 * Keep the new scan plus the previous three scans. When a
                 * fifth row arrives, the oldest row is deleted.
                 */
                $historyIdsToKeep = \App\Models\AssetRfidLocationHistory::query()
                    ->forRfid($epc)
                    ->orderByDesc('scanned_at')
                    ->orderByDesc('id')
                    ->limit(4)
                    ->pluck('id');

                $deletedHistoryCount = \App\Models\AssetRfidLocationHistory::query()
                    ->forRfid($epc)
                    ->whereNotIn('id', $historyIdsToKeep)
                    ->delete();

                $summary['history_created']++;
                $summary['history_deleted'] += $deletedHistoryCount;

                if (!$event) {
                    $summary['first_seen_history']++;
                } elseif ($isLocationChanged) {
                    $summary['location_changed_history']++;
                } else {
                    $summary['same_location_history']++;
                }

                /*
                 * An older packet belongs in history, but it must not replace
                 * the newer current location in asset_rfid_scan_events.
                 */
                if ($isOlderScan) {
                    $summary['older_scans_latest_unchanged']++;

                    $results[] = [
                        'epc' => $epc,
                        'success' => true,
                        'event_id' => $event->id,
                        'history_id' => $history->id,
                        'asset_id' => $event->asset_id,
                        'asset_name' => $event->asset_name,
                        'asset_tag' => $event->asset_tag,
                        'serial' => $event->serial,
                        'scan_result' => $event->scan_result,
                        'action' => 'HISTORY_SAVED_LATEST_UNCHANGED',
                        'history_event_type' => $historyEventType,
                        'reader_code' => $event->reader_code,
                        'gate_name' => $event->gate_name,
                        'history_scanned_at' => $scannedAt
                            ->toDateTimeString(),
                        'scanned_at' => $event->scanned_at
                            ? Carbon::parse($event->scanned_at)
                            ->toDateTimeString()
                            : null,
                        'message' => 'Scan saved in history; newer latest location was kept.',
                    ];
                    continue;
                }

                $isNew = !$event;

                if ($isNew) {
                    $event = new AssetRFIDscanEvents();
                    $event->rfid_epc = $epc;
                }

                // Refresh the asset snapshot on every accepted scan.
                $event->asset_id = $asset->id;
                $event->asset_name = $asset->name;
                $event->asset_tag = $asset->asset_tag;
                $event->serial = $asset->serial;
                $event->company_id = $asset->company_id;

                // Store the latest physical reader location.
                $event->reader_code = $readerCode;
                $event->reader_name = $readerName;
                $event->reader_ip = $readerIp;
                $event->gate_name = $gateName;
                $event->location_id = $readerLocationId
                    ?? $asset->location_id;
                $event->antenna_no = $read['antenna_no'] ?? null;
                $event->rssi = $read['rssi'] ?? null;

                $event->scan_batch_id = $scanBatchId;
                // $event->source_event_id = !empty($read['source_event_id'])
                //     ? trim($read['source_event_id'])
                //     : null;

                $event->event_type = 'LOCATION_SCAN';
                $event->scan_result = 'MAPPED';
                $event->processing_status = 'PROCESSED';
                $event->read_count = $isNew
                    ? 1
                    : ((int) $event->read_count + 1);
                $event->scanned_at = $scannedAt;
                $event->last_seen_at = $scannedAt;
                $event->received_at = $receivedAt;
                $event->processed_at = now();
                $event->remarks = $read['remarks'] ?? $defaultRemarks;

                /*
                 * Neutral compatibility values for legacy NOT NULL columns.
                 * The location-tracking controller and dashboard do not use
                 * these values for any IN/OUT or working-hours processing.
                 */
                $event->direction = 'LOCATION';
                $event->status = 'TRACKED';
                $event->in_at = null;
                $event->out_at = null;
                $event->expected_out_at = null;
                $event->out_type = null;
                $event->working_hours = 0;

                $event->save();

                if ($isNew) {
                    $summary['created']++;
                } else {
                    $summary['updated']++;
                }

                $results[] = [
                    'epc' => $epc,
                    'success' => true,
                    'event_id' => $event->id,
                    'history_id' => $history->id,
                    'asset_id' => $asset->id,
                    'asset_name' => $asset->name,
                    'asset_tag' => $asset->asset_tag,
                    'serial' => $asset->serial,
                    'rfid' => $asset->rfid,
                    'scan_result' => $event->scan_result,
                    'action' => $isNew
                        ? 'FIRST_SEEN'
                        : ($isLocationChanged
                            ? 'LOCATION_CHANGED'
                            : 'LOCATION_SCAN'),
                    'history_created' => true,
                    'history_event_type' => $historyEventType,
                    'previous_gate_name' => $event
                        ? $previousGateName
                        : null,
                    'previous_reader_code' => $event
                        ? $previousReaderCode
                        : null,
                    'gate_name' => $event->gate_name,
                    'reader_code' => $event->reader_code,
                    'reader_name' => $event->reader_name,
                    'reader_ip' => $event->reader_ip,
                    'antenna_no' => $event->antenna_no,
                    'rssi' => $event->rssi,
                    'read_count' => $event->read_count,
                    'last_seen_at' => $event->last_seen_at
                        ? Carbon::parse($event->last_seen_at)
                        ->toDateTimeString()
                        : null,
                    'scanned_at' => $event->scanned_at
                        ? Carbon::parse($event->scanned_at)
                        ->toDateTimeString()
                        : null,
                    'message' => $isNew
                        ? 'RFID asset location and first history scan saved successfully.'
                        : 'RFID latest location and scan history saved successfully.',
                ];
            }

            DB::commit();

            $message = $summary['mapped'] === 0
                && $summary['unknown'] > 0
                ? 'RFID reads were received, but no mapped assets were found.'
                : 'RFID asset locations processed successfully.';

            Log::info('Asset fixed-reader RFID locations processed', [
                'reader_code' => $readerCode,
                'gate_name' => $gateName,
                'summary' => $summary,
            ]);

            return response()->json([
                'success' => true,
                'message' => $message,
                'reader_code' => $readerCode,
                'gate_name' => $gateName,
                'summary' => $summary,
                'results' => $results,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('Asset fixed-reader RFID event failed', [
                'reader_code' => $readerCode,
                'gate_name' => $gateName,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to process RFID scan events.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
