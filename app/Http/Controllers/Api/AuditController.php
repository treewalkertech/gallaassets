<?php

namespace App\Http\Controllers\Api;

use App\Events\NoteAdded;
use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Models\Asset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class AuditController extends Controller
{
    public function store(Request $request)
    {
        /*$validated = $request->validate([
            'assettag' => 'required|string|max:500',
            'type' => [
                'required',
                Rule::in(['asset']),
            ],
        ]);

        // This can be made dynamic by using $request->input('type') to determine which model type to add the note to.
        // For now, we are only placing this on Assets
        $item = Asset::findOrFail($request->input("id"));
        $this->authorize('update', $item);

	event(new NoteAdded($item, Auth::user(), $validated['note']));*/
	    
        $rules = [
            'location_id' => 'exists:locations,id|nullable|numeric',
            'next_audit_date' => 'date|nullable',
        ];

	$validator = Validator::make($request->all(), $rules);

	if ($validator->fails()) {
   		 return response()->json(Helper::formatStandardApiResponse('error', null, $validator->errors()->all()));
	}

	$id = $request->input('asset_id');

        $asset = Asset::findOrFail($id);

	$asset->next_audit_date = $request->input('next_audit_date');
        $asset->last_audit_date = date('Y-m-d H:i:s');

        // Check to see if they checked the box to update the physical location,
        // not just note it in the audit notes
        if ($request->input('update_location') == '1') {
            $asset->location_id = $request->input('location_id');
        }


        /**
         * Invoke Watson Validating to check the asset itself and check to make sure it saved correctly.
         * We have to invoke this manually because of the unsetEventDispatcher() above.)
         */
        if ($asset->isValid() && $asset->save()) {

            $file_name = null;
            // Create the image (if one was chosen.)
            //if ($request->hasFile('image')) {
                //$file_name = $request->handleFile('private_uploads/audits/', 'audit-'.$asset->id, $request->file('image'));
            //}

	    $asset->logAudit($request->input('note'), $request->input('location_id'), $file_name);
	    return response()->json(Helper::formatStandardApiResponse('success'));
	}    
         return response()->json(Helper::formatStandardApiResponse('error', null, $asset->getErrors()), 200);

        //return response()->json(Helper::formatStandardApiResponse('success'));
    }

    public function update(Request $request)
    {

    }
    public function destroy(Request $request)
    {

    }
}
