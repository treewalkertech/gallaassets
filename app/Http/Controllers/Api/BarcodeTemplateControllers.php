<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BarcodeTemplate;
use Illuminate\Http\Request;

class BarcodeTemplateControllers extends Controller
{
   public function index()
    {
        return BarcodeTemplate::where('company_id', auth()->user()->company_id)
            ->orderByDesc('created_at')
            ->get();
    }

    public function toggle($id)
    {
        // Only one active per company
        BarcodeTemplate::where('company_id', auth()->user()->company_id)
            ->update(['is_active' => false]);

        $template = BarcodeTemplate::where('company_id', auth()->user()->company_id)
            ->findOrFail($id);

        $template->update(['is_active' => true]);

        return response()->json(['success' => true]);
    }

    public function destroy($id)
    {
        $template = BarcodeTemplate::where('company_id', auth()->user()->company_id)
            ->findOrFail($id);

        $template->delete();

        return response()->json(['success' => true]);
    }

        public function update(Request $request, $id)
    {
        $request->validate([
            'name'     => 'required|string|max:100',
            'template' => 'required|string|max:255',
        ]);

        $template = BarcodeTemplate::where('company_id', auth()->user()->company_id)
            ->findOrFail($id);

        $template->update([
            'name'     => $request->name,
            'template' => $request->template,
        ]);

        return response()->json(['success' => true]);
    }

     /**
     * POST /api/barcode-templates
     * Create new barcode template
     */
    public function store(Request $request)
    {
        $request->validate([
            'name'     => 'required|string|max:100',
            'template' => 'required|string|max:255',
        ]);

        // Deactivate existing templates for this company
        BarcodeTemplate::where('company_id', $request->user()->company_id)
            ->update(['is_active' => false]);

        $template = BarcodeTemplate::create([
            'company_id' => $request->user()->company_id,
            'created_by' => $request->user()->id,
            'name'       => $request->name,
            'template'   => $request->template,
            'is_active'  => true,
        ]);

        return response()->json($template, 201);
    }

    
    public function show($id)
    {
        return BarcodeTemplate::where('company_id', auth()->user()->company_id)
            ->findOrFail($id);
    }
}
