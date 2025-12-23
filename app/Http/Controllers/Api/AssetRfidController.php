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

        // ✅ Find asset by asset_tag
        $asset = Asset::where('asset_tag', $request->asset_tag)->first();

        if (!$asset) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Asset not found',
            ], 404);
        }

        // ✅ Update RFID
        $asset->rfid = $request->rfid;
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
    public function bulkUpdate(Request $request)
    {
        // ✅ Validate structure
        $validator = Validator::make($request->all(), [
            'items' => 'required|array|min:1',
            'items.*.asset_tag' => 'required|string',
            'items.*.rfid' => 'required|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $updated = [];
        $notFound = [];

        DB::beginTransaction();

        try {
            foreach ($request->items as $row) {

                $asset = Asset::where('asset_tag', $row['asset_tag'])->first();

                if (!$asset) {
                    $notFound[] = $row['asset_tag'];
                    continue;
                }

                $asset->rfid = $row['rfid'];
                $asset->save();

                $updated[] = [
                    'asset_id'  => $asset->id,
                    'asset_tag' => $asset->asset_tag,
                    'rfid'      => $asset->rfid,
                ];
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'updated_count' => count($updated),
                'not_found_count' => count($notFound),
                'updated' => $updated,
                'not_found_asset_tags' => $notFound,
            ]);

        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'status' => 'error',
                'message' => 'Bulk update failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

      /**
     * Get all asset details
     */
        public function index()
    {
        // 🔹 Assets
        $assets = Asset::query()
            ->select([
                'id',
                'external_asset_id',
                'external_source',
                'name',
                'asset_tag',
                'rfid',
                'model_id',
                'serial',
                'purchase_date',
                'asset_eol_date',
                'purchase_cost',
                'status_id',
                'company_id',
                'location_id',
                'created_at',
                'updated_at',
            ])
            ->orderBy('id', 'desc')
            ->get();

        // 🔹 Locations
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

}
