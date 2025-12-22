<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\inventory\Inventory;
use App\Models\inventory\InventoryActivity;
use App\Models\products\Product;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class InventoryApiController extends Controller
{
    /**
     * Map one or many EPC tags to a product. (From Hand Reader)
     */
    public function tagMapping(Request $request)
    {
        $payload = $request->all();
        Log::info('tagMapping raw payload: '.json_encode($payload));

        $mappings = [];

        // Compact format: product_id + epc_codes[]
        if (! empty($payload['product_id']) && ! empty($payload['epc_codes']) && is_array($payload['epc_codes'])) {
            foreach ($payload['epc_codes'] as $epc) {
                $epc = trim((string) $epc);
                if ($epc === '') {
                    continue;
                }
                $mappings[] = [
                    'product_id' => $payload['product_id'],
                    'epc_code' => $epc,
                    'tag_code' => $payload['tag_code'] ?? null,
                    'trolley_id' => $payload['trolley_id'] ?? null,
                ];
            }
        }

        // Legacy format: mappings[]
        if (! empty($payload['mappings']) && is_array($payload['mappings'])) {
            foreach ($payload['mappings'] as $m) {
                $epc = trim($m['epc_code'] ?? '');
                if ($epc === '') {
                    continue;
                }
                $mappings[] = [
                    'product_id' => $m['product_id'] ?? null,
                    'epc_code' => $epc,
                    'tag_code' => $m['tag_code'] ?? null,
                    'trolley_id' => $m['trolley_id'] ?? null,
                ];
            }
        }

        if (empty($mappings)) {
            return response()->json(['success' => false, 'message' => 'No valid mappings provided'], 400);
        }

        $results = [];
        $seenEpcs = [];
        $userId = Auth::id() ?? null;
        $now = Carbon::now();

        DB::beginTransaction();
        try {
            foreach ($mappings as $map) {
                $epc = $map['epc_code'];

                if (isset($seenEpcs[$epc])) {
                    $results[] = ['epc_code' => $epc, 'success' => false, 'message' => 'Duplicate epc in request'];

                    continue;
                }
                $seenEpcs[$epc] = true;

                $product = null;
                if (! empty($map['product_id'])) {
                    $product = Product::find($map['product_id']);
                }
                if (! $product) {
                    $results[] = ['epc_code' => $epc, 'success' => false, 'message' => 'Product not found'];

                    continue;
                }

                $locationId = $product->location_id ?? null;

                // Prevent remapping existing tags
                $existingTag = Inventory::where('epc_code', $epc)->first();
                if ($existingTag) {
                    $results[] = [
                        'epc_code' => $epc,
                        'tag_id' => $existingTag->id,
                        'product_id' => $existingTag->product_id,
                        'success' => false,
                        'message' => 'Tag already mapped — cannot be remapped or replaced',
                    ];

                    continue;
                }

                // Create tag
                $tag = Inventory::create([
                    'epc_code' => $epc,
                    'tag_code' => $map['tag_code'] ?? null,
                    'product_id' => $product->id,
                    'location_id' => $locationId,
                    'trolley_id' => $map['trolley_id'] ?? null,
                    'status' => 'new',
                    'mapped_at' => $now,
                    'last_scanned_at' => $now,
                    'life_cycles' => $product->expected_life_cycles ?? 1000,
                ]);

                // Create an inward activity for assignment
                $activity = InventoryActivity::create([
                    'product_id' => $product->id,
                    'inventory_id' => $tag->id,
                    'adjust_qty' => 1,
                    'inward' => 1,
                    'outward' => 0,
                    'trans_type' => 'tag_mapping',
                    'remarks' => 'Tag created & assigned',
                    'status' => 'new',
                    'location_id' => $locationId,
                    'created_by' => $userId,
                    'updated_by' => $userId,
                ]);

                $results[] = [
                    'epc_code' => $epc,
                    'tag_id' => $tag->id,
                    'product_id' => $product->id,
                    'location_id' => $locationId,
                    'success' => true,
                    'message' => 'Tag created',
                    'movement' => ['assign' => $activity->toArray()],
                ];
            }

            DB::commit();

            return response()->json(['success' => true, 'results' => $results], 200);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('tagMapping error: '.$e->getMessage(), ['exception' => $e]);

            return response()->json(['success' => false, 'message' => 'Mapping failed: '.$e->getMessage()], 500);
        }
    }

    /**
     * Record a single inventory transaction (manual/external).
     */
    public function recordStockMovement(Request $request)
    {
        // Validate basic shape; avoid hard-coded exists table for inventory_id to stay portable
        $validator = Validator::make($request->all(), [
            'product_id' => 'required|exists:products,id',
            'inventory_id' => 'nullable|integer',
            'trans_type' => 'required|string|max:100',
            'inward' => 'nullable|integer|min:0',
            'outward' => 'nullable|integer|min:0',
            'adjust_qty' => 'nullable|integer',
            'location_id' => 'nullable|integer',
            'remarks' => 'nullable|string|max:255',
            'update_tag_status' => 'nullable|string|max:50',
            'status' => 'nullable|string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $userId = Auth::id() ?? null;
        DB::beginTransaction();
        try {
            $productId = intval($request->product_id);
            $inventoryId = $request->inventory_id ? intval($request->inventory_id) : null;
            $inward = intval($request->inward ?? 0);
            $outward = intval($request->outward ?? 0);

            $adjustQty = $request->filled('adjust_qty')
                ? intval($request->adjust_qty)
                : ($inward > 0 ? $inward : ($outward > 0 ? $outward : 0));

            $type = $request->trans_type;
            $remarks = $request->remarks ?? null;
            $locationId = $request->location_id ?? null;
            $status = $request->has('status') ? $request->status : null;

            $activity = InventoryActivity::create([
                'product_id' => $productId,
                'inventory_id' => $inventoryId,
                'adjust_qty' => $adjustQty,
                'inward' => $inward,
                'outward' => $outward,
                'trans_type' => $type,
                'remarks' => $remarks,
                'status' => $status,
                'location_id' => $locationId,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            // If inventory_id provided, update tag record accordingly
            if ($inventoryId) {
                $tag = Inventory::find($inventoryId);
                if ($tag) {
                    $updateTag = [];

                    // Status cycle logic (string lifecycle)
                    if ($request->filled('update_tag_status')) {
                        $current = strtolower($tag->status ?? 'new');
                        $input = strtolower($request->get('update_tag_status'));

                        if (in_array($input, ['inward', 'outward', 'clean', 'dirty', 'new'])) {
                            // prefer explicit override for special values
                            if ($input === 'inward') {
                                // inward usually means dirty (arrived dirty) — map if that is your convention
                                $updateTag['status'] = 'dirty';
                            } elseif ($input === 'outward') {
                                $updateTag['status'] = 'clean';
                            } else {
                                $updateTag['status'] = $input;
                            }
                        } else {
                            // fallback: cycle current
                            if ($current === 'new') {
                                $updateTag['status'] = 'clean';
                            } elseif ($current === 'clean') {
                                $updateTag['status'] = 'dirty';
                            } elseif ($current === 'dirty') {
                                $updateTag['status'] = 'clean';
                            }
                        }
                    }

                    if (! is_null($locationId)) {
                        $updateTag['location_id'] = $locationId;
                    }

                    $updateTag['last_scanned_at'] = Carbon::now();

                    if (! empty($updateTag)) {
                        $tag->update($updateTag);
                    }
                }
            }

            DB::commit();

            return response()->json(['success' => true, 'message' => 'Movement recorded', 'data' => $activity], 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('recordStockMovement error: '.$e->getMessage(), ['exception' => $e]);

            return response()->json(['success' => false, 'message' => 'Failed to record movement: '.$e->getMessage()], 500);
        }
    }

    /**
     * Fetch full details of an EPC tag: inventory record, mapped product,
     * and last 10 inventory activities.
     */
    public function tagDetailsByEpc($epc)
    {
        Log::info('Fetching tag details for EPC: '.$epc);
        try {
            $epc = trim($epc);
            $tag = Inventory::with('products')->where('epc_code', $epc)->first();

            if (! $tag) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tag Not Mapped',
                    'inventory' => null,
                    'product' => null,
                    'history' => [],
                ], 200);
            }

            $history = InventoryActivity::where('inventory_id', $tag->id)
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get();

            $product = $tag->products->first() ?? null;

            // Friendly status text for string statuses; handle numeric cases defensively
            $statusText = 'Unknown';
            $s = $tag->status;
            if (is_string($s)) {
                $map = [
                    'new' => 'New',
                    'clean' => 'Clean',
                    'dirty' => 'Dirty',
                    'out' => 'Out',
                    'lost' => 'Lost',
                    'damaged' => 'Damaged',
                ];
                $statusText = $map[strtolower($s)] ?? ucfirst($s);
            } elseif (is_numeric($s)) {
                $statusText = ((int) $s === 1) ? 'Active' : (((int) $s === 0) ? 'Inactive' : 'Unknown');
            }

            $responseData = [
                'success' => true,
                'message' => 'Tag details loaded',
                'inventory' => [
                    'id' => $tag->id,
                    'epc_code' => $tag->epc_code,
                    'tag_code' => $tag->tag_code,
                    'product_id' => $tag->product_id,
                    'location_id' => $tag->location_id,
                    'status' => $tag->status,
                    'status_text' => $statusText,
                    'last_scanned_at' => $tag->last_scanned_at,
                    'mapped_at' => $tag->mapped_at,
                    'product_name' => $product->product_name ?? '',
                    'product_code' => $product->product_code ?? '',
                    'category' => $product->category ?? '',
                    'sku' => $product->sku ?? '',
                ],
                'product' => $product ? [
                    'id' => $product->id,
                    'product_name' => $product->product_name ?? '',
                    'product_code' => $product->product_code ?? '',
                    'sku' => $product->sku ?? '',
                    'category' => $product->category ?? '',
                ] : null,
                'history' => $history->toArray(),
            ];

            Log::info('tagDetailsByEpc response: '.json_encode($responseData));

            return response()->json($responseData, 200);
        } catch (\Throwable $e) {
            Log::error('tagDetailsByEpc error: '.$e->getMessage(), ['exception' => $e]);

            return response()->json(['success' => false, 'message' => 'Server error: '.$e->getMessage()], 500);
        }
    }

    /**
     * Receive scans from a fixed reader device (multiple tags).
     *
     * Payload:
     * {
     *   "reader_code": "GATE_A_01",
     *   "defaultMovementAction": "inward", // optional (inward|outward)
     *   "reads": [ { "epc":"E200...", "read_time":"2025-12-09 10:12:55", "qty":1 }, ... ]
     * }
     */
    public function fixedReaderScan(Request $request)
    {
        Log::info('fixedReaderScan called '.json_encode($request->all()));

        $validator = Validator::make($request->all(), [
            'reader_code' => 'required|string|max:100',
            'reads' => 'required|array|min:1',
            'reads.*.epc' => 'required|string',
            'reads.*.read_time' => 'nullable|date',
            'reads.*.qty' => 'nullable|integer|min:1',
            'defaultMovementAction' => 'nullable|string|in:inward,outward',
            'min_scan_interval' => 'nullable|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $payload = $request->all();
        Log::info('fixedReaderScan payload: '.json_encode($payload));

        $reads = $payload['reads'];
        $readerCode = $payload['reader_code'];
        $defaultMovementAction = isset($payload['defaultMovementAction']) ? strtolower($payload['defaultMovementAction']) : null;
        $minScanIntervalSeconds = isset($payload['min_scan_interval']) ? intval($payload['min_scan_interval']) : 5;

        $userId = Auth::id() ?? null;
        $now = Carbon::now();
        $results = [];
        $seen = [];

        DB::beginTransaction();
        try {
            foreach ($reads as $r) {
                $epc = trim($r['epc'] ?? '');
                if ($epc === '') {
                    $results[] = ['epc_code' => null, 'success' => false, 'message' => 'Empty EPC'];

                    continue;
                }

                if (isset($seen[$epc])) {
                    $results[] = ['epc_code' => $epc, 'success' => false, 'message' => 'Duplicate EPC - skipped'];

                    continue;
                }
                $seen[$epc] = true;

                $readTime = $now;
                if (! empty($r['read_time'])) {
                    try {
                        $readTime = Carbon::parse($r['read_time']);
                    } catch (\Throwable $e) {
                        $readTime = $now;
                    }
                }

                $tag = Inventory::where('epc_code', $epc)->first();
                if (! $tag) {
                    $results[] = ['epc_code' => $epc, 'success' => false, 'message' => 'Tag not mapped'];

                    continue;
                }

                if (! empty($tag->last_scanned_at)) {
                    try {
                        $last = Carbon::parse($tag->last_scanned_at);
                        $diffSec = $readTime->diffInSeconds($last);
                        if ($diffSec < $minScanIntervalSeconds) {
                            $results[] = ['epc_code' => $epc, 'success' => false, 'message' => "Ignored: scanned {$diffSec}s after previous (min {$minScanIntervalSeconds}s)."];

                            continue;
                        }
                    } catch (\Throwable $e) {
                        // ignore parse failure
                    }
                }

                $currentStatus = strtolower($tag->status ?? 'new');
                $cycle = ['new' => 'clean', 'clean' => 'dirty', 'dirty' => 'clean'];
                $nextStatus = $cycle[$currentStatus] ?? null;

                // If caller provided movement intent, ensure transition would produce the requested movement
                if ($nextStatus !== null) {
                    $autoMovementForTransition = null;
                    if ($currentStatus === 'dirty' && $nextStatus === 'clean') {
                        $autoMovementForTransition = 'outward';
                    }
                    if ($currentStatus === 'clean' && $nextStatus === 'dirty') {
                        $autoMovementForTransition = 'inward';
                    }

                    if ($defaultMovementAction !== null && $autoMovementForTransition !== null && $autoMovementForTransition !== $defaultMovementAction) {
                        $results[] = ['epc_code' => $epc, 'success' => false, 'message' => "Skipped by defaultMovementAction={$defaultMovementAction}. Transition would trigger {$autoMovementForTransition}.", 'current_status' => $currentStatus];

                        continue;
                    }
                }

                // Handle status transition
                if ($nextStatus !== null && $nextStatus !== $currentStatus) {
                    $lifeCycles = $tag->life_cycles;
                    if ($currentStatus === 'dirty' && $nextStatus === 'clean' && is_numeric($lifeCycles)) {
                        $lifeCycles = max(0, intval($lifeCycles) - 1);
                    }

                    $tag->update([
                        'status' => $nextStatus,
                        'life_cycles' => $lifeCycles,
                        'last_scanned_at' => $readTime,
                        'reader_code' => $readerCode,
                        'reader_type' => 'fixed_reader',
                        'updated_by' => $userId,
                    ]);

                    $autoMovement = null;
                    if ($currentStatus === 'dirty' && $nextStatus === 'clean') {
                        $autoMovement = 'outward';
                    }
                    if ($currentStatus === 'clean' && $nextStatus === 'dirty') {
                        $autoMovement = 'inward';
                    }

                    if (! empty($tag->product_id) && $autoMovement !== null) {
                        if ($defaultMovementAction === null || $defaultMovementAction === $autoMovement) {
                            $qty = max(1, intval($r['qty'] ?? 1));
                            $inward = $autoMovement === 'inward' ? $qty : 0;
                            $outward = $autoMovement === 'outward' ? $qty : 0;
                            $transType = $autoMovement === 'inward' ? 'status_dirty_in' : 'status_clean_out';

                            InventoryActivity::create([
                                'product_id' => $tag->product_id,
                                'inventory_id' => $tag->id,
                                'adjust_qty' => $qty,
                                'inward' => $inward,
                                'outward' => $outward,
                                'trans_type' => $transType,
                                'remarks' => "Auto movement due to status transition {$currentStatus}→{$nextStatus}",
                                'status' => $nextStatus,
                                'location_id' => $tag->location_id,
                                'created_by' => $userId,
                                'updated_by' => $userId,
                            ]);
                        }
                    }

                    $results[] = [
                        'epc_code' => $epc,
                        'tag_id' => $tag->id,
                        'product_id' => $tag->product_id,
                        'location_id' => $tag->location_id,
                        'success' => true,
                        'message' => "Status changed: {$currentStatus} → {$nextStatus}",
                        'status' => $nextStatus,
                        'movement' => $autoMovement,
                    ];

                    continue;
                }

                // No status change: derive movement from current status
                $qty = max(1, intval($r['qty'] ?? 1));

                if (empty($tag->product_id)) {
                    $results[] = ['epc_code' => $epc, 'success' => false, 'message' => 'Tag has no product mapping - movement skipped'];

                    continue;
                }

                $movement = null;
                if ($currentStatus === 'clean') {
                    $movement = 'outward';
                } elseif ($currentStatus === 'dirty') {
                    $movement = 'inward';
                }

                if ($defaultMovementAction !== null && $movement !== null && $movement !== $defaultMovementAction) {
                    $results[] = ['epc_code' => $epc, 'success' => false, 'message' => "Skipped by defaultMovementAction={$defaultMovementAction} for movement {$movement}."];

                    continue;
                }

                $tag->update([
                    'last_scanned_at' => $readTime,
                    'reader_code' => $readerCode,
                    'reader_type' => 'fixed_reader',
                    'updated_by' => $userId,
                ]);

                if ($movement !== null) {
                    $inward = $movement === 'inward' ? $qty : 0;
                    $outward = $movement === 'outward' ? $qty : 0;
                    $transType = $movement === 'inward' ? 'fixed_reader_in' : 'fixed_reader_out';

                    $activity = InventoryActivity::create([
                        'product_id' => $tag->product_id,
                        'inventory_id' => $tag->id,
                        'adjust_qty' => $qty,
                        'inward' => $inward,
                        'outward' => $outward,
                        'trans_type' => $transType,
                        'remarks' => "Scanned by fixed reader: {$readerCode}",
                        'status' => $tag->status,
                        'location_id' => $tag->location_id,
                        'created_by' => $userId,
                        'updated_by' => $userId,
                    ]);

                    $results[] = [
                        'epc_code' => $epc,
                        'success' => true,
                        'message' => 'Movement applied based on status',
                        'movement' => $movement,
                        'qty' => $qty,
                        'activity_id' => $activity->trans_id ?? $activity->id ?? null,
                    ];
                } else {
                    $results[] = ['epc_code' => $epc, 'success' => true, 'message' => "Status is '{$currentStatus}' → no movement"];

                }
            }

            DB::commit();
            Log::info('fixedReaderScan processed', ['results' => $results]);

            return response()->json(['success' => true, 'message' => 'Scan processed', 'results' => $results], 200);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('fixedReaderScan error: '.$e->getMessage(), ['exception' => $e]);

            return response()->json(['success' => false, 'message' => 'Server error: '.$e->getMessage()], 500);
        }
    }

    /**
     * Handheld reader endpoint — expects android_id + reads[] + optional defaultMovementAction (inward|outward)
     */
    public function handReaderScan(Request $request)
    {
        Log::info('handReaderScan called '.json_encode($request->all()));

        // ------------------------------
        // VALIDATION
        // ------------------------------
        $validator = Validator::make($request->all(), [
            'android_id' => 'required|string|max:100',
            'reads' => 'required|array|min:1',
            'reads.*.epc' => 'required|string',
            'reads.*.read_time' => 'nullable|date',
            'reads.*.qty' => 'nullable|integer|min:1',

            // Device will send: inward / outward
            'defaultMovementAction' => 'nullable|string|in:inward,outward',

            'min_scan_interval' => 'nullable|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        // ------------------------------
        // PAYLOAD
        // ------------------------------
        $payload = $request->all();
        Log::info('handReaderScan payload: '.json_encode($payload));

        $reads = $payload['reads'];
        $androidId = $payload['android_id'];

        $defaultMovementAction = strtolower($payload['defaultMovementAction'] ?? null);
        $minScanIntervalSeconds = intval($payload['min_scan_interval'] ?? 5);

        $userId = Auth::id() ?? null;
        $now = Carbon::now();

        $results = [];
        $seen = [];

        DB::beginTransaction();
        try {

            foreach ($reads as $r) {

                // ------------------------------
                // EPC VALIDATION
                // ------------------------------
                $epc = trim($r['epc'] ?? '');
                if ($epc === '') {
                    $results[] = ['epc_code' => null, 'success' => false, 'message' => 'Empty EPC'];

                    continue;
                }

                if (isset($seen[$epc])) {
                    $results[] = ['epc_code' => $epc, 'success' => false, 'message' => 'Duplicate EPC - skipped'];

                    continue;
                }
                $seen[$epc] = true;

                // Determine read time
                $readTime = ! empty($r['read_time'])
                    ? Carbon::parse($r['read_time'])
                    : $now;

                // ------------------------------
                // FIND TAG
                // ------------------------------
                $tag = Inventory::where('epc_code', $epc)->first();
                if (! $tag) {
                    $results[] = ['epc_code' => $epc, 'success' => false, 'message' => 'Tag not mapped'];

                    continue;
                }

                // ------------------------------
                // DEBOUNCE SCAN
                // ------------------------------
                if (! empty($tag->last_scanned_at)) {
                    $last = Carbon::parse($tag->last_scanned_at);
                    $diffSec = $readTime->diffInSeconds($last);
                    if ($diffSec < $minScanIntervalSeconds) {
                        $results[] = [
                            'epc_code' => $epc,
                            'success' => false,
                            'message' => "Ignored: scanned {$diffSec}s after previous (min {$minScanIntervalSeconds}s).",
                        ];

                        continue;
                    }
                }

                // ------------------------------
                // STATUS CYCLE LOGIC
                // ------------------------------
                $currentStatus = strtolower($tag->status ?? 'new');
                $cycle = ['new' => 'clean', 'clean' => 'dirty', 'dirty' => 'clean'];
                $nextStatus = $cycle[$currentStatus] ?? null;

                // Determine auto movement for STATUS TRANSITIONS
                $autoMovementForTransition = null;
                if ($currentStatus === 'dirty' && $nextStatus === 'clean') {
                    $autoMovementForTransition = 'outward';
                }
                if ($currentStatus === 'clean' && $nextStatus === 'dirty') {
                    $autoMovementForTransition = 'inward';
                }

                // Reject if movement does not match device mode (inward/outward)
                if (
                    $defaultMovementAction !== null &&
                    $autoMovementForTransition !== null &&
                    $autoMovementForTransition !== $defaultMovementAction
                ) {
                    $results[] = [
                        'epc_code' => $epc,
                        'success' => false,
                        'message' => "Skipped by defaultMovementAction={$defaultMovementAction}. Transition triggers {$autoMovementForTransition}.",
                        'current_status' => $currentStatus,
                    ];

                    continue;
                }

                // ------------------------------
                // HANDLE STATUS TRANSITION
                // ------------------------------
                if ($nextStatus !== null && $nextStatus !== $currentStatus) {

                    // Decrement life cycles on DIRTY → CLEAN
                    $lifeCycles = $tag->life_cycles;
                    if ($currentStatus === 'dirty' && $nextStatus === 'clean') {
                        $lifeCycles = max(0, intval($lifeCycles) - 1);
                    }

                    // Update tag
                    $tag->update([
                        'status' => $nextStatus,
                        'life_cycles' => $lifeCycles,
                        'last_scanned_at' => $readTime,
                        'reader_code' => $androidId,
                        'reader_type' => 'hand_reader',
                        'updated_by' => $userId,
                    ]);

                    // Determine movement
                    $autoMovement = $autoMovementForTransition;

                    // Create stock movement entry only if allowed
                    if (! empty($tag->product_id) && $autoMovement !== null) {

                        if ($defaultMovementAction === null || $defaultMovementAction === $autoMovement) {

                            $qty = intval($r['qty'] ?? 1);

                            InventoryActivity::create([
                                'product_id' => $tag->product_id,
                                'inventory_id' => $tag->id,
                                'adjust_qty' => $qty,
                                'inward' => $autoMovement === 'inward' ? $qty : 0,
                                'outward' => $autoMovement === 'outward' ? $qty : 0,
                                'trans_type' => $autoMovement === 'inward' ? 'status_dirty_in' : 'status_clean_out',
                                'remarks' => "Auto movement: {$currentStatus} → {$nextStatus}",
                                'status' => $nextStatus,
                                'location_id' => $tag->location_id,
                                'created_by' => $userId,
                                'updated_by' => $userId,
                            ]);
                        }
                    }

                    // Append result
                    $results[] = [
                        'epc_code' => $epc,
                        'tag_id' => $tag->id,
                        'product_id' => $tag->product_id,
                        'location_id' => $tag->location_id,
                        'success' => true,
                        'message' => "Status changed: {$currentStatus} → {$nextStatus}",
                        'status' => $nextStatus,
                        'movement' => $autoMovement,
                    ];

                    continue;
                }

                // ------------------------------
                // NO STATUS TRANSITION → NORMAL MOVEMENT
                // ------------------------------
                $movement = null;
                if ($currentStatus === 'clean') {
                    $movement = 'outward';
                }
                if ($currentStatus === 'dirty') {
                    $movement = 'inward';
                }

                // Reject wrong direction
                if (
                    $defaultMovementAction !== null &&
                    $movement !== null &&
                    $movement !== $defaultMovementAction
                ) {
                    $results[] = [
                        'epc_code' => $epc,
                        'success' => false,
                        'message' => "Skipped by defaultMovementAction={$defaultMovementAction} for movement {$movement}.",
                    ];

                    continue;
                }

                // Always update tag scan meta
                $tag->update([
                    'last_scanned_at' => $readTime,
                    'reader_code' => $androidId,
                    'reader_type' => 'hand_reader',
                    'updated_by' => $userId,
                ]);

                // If movement exists → log it
                if ($movement !== null) {

                    $qty = intval($r['qty'] ?? 1);

                    $activity = InventoryActivity::create([
                        'product_id' => $tag->product_id,
                        'inventory_id' => $tag->id,
                        'adjust_qty' => $qty,
                        'inward' => $movement === 'inward' ? $qty : 0,
                        'outward' => $movement === 'outward' ? $qty : 0,
                        'trans_type' => $movement === 'inward' ? 'hand_reader_in' : 'hand_reader_out',
                        'remarks' => "Scanned by hand reader: {$androidId}",
                        'status' => $tag->status,
                        'location_id' => $tag->location_id,
                        'created_by' => $userId,
                        'updated_by' => $userId,
                    ]);

                    $results[] = [
                        'epc_code' => $epc,
                        'success' => true,
                        'message' => 'Movement applied based on status',
                        'movement' => $movement,
                        'qty' => $qty,
                        'activity_id' => $activity->trans_id ?? null,
                    ];
                } else {
                    $results[] = [
                        'epc_code' => $epc,
                        'success' => true,
                        'message' => "Status is '{$currentStatus}' → no movement",
                    ];
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Scan processed',
                'results' => $results,
            ], 200);

        } catch (\Throwable $e) {

            DB::rollBack();
            Log::error('handReaderScan error: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Server error: '.$e->getMessage(),
            ], 500);
        }
    }
}
