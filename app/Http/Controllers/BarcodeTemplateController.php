<?php

namespace App\Http\Controllers;

use App\Models\BarcodeTemplate;
use Illuminate\Http\Request;

class BarcodeTemplateController extends Controller
{
    // public function index()
    // {
    //     $templates = BarcodeTemplate::where(
    //         'company_id',
    //         auth()->user()->company_id
    //     )->get();

    //     return view('admin.barcode_templates.index', compact('templates'));
    // }

    public function index()
    {
        return view('admin.barcode_templates.index');
    }


    public function create()
    {
        return view('admin.barcode_templates.create');
    }

        public function edit($id)
    {
        return view('admin.barcode_templates.edit', compact('id'));
    }


    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required',
            'template' => 'required',
        ]);

        // Deactivate old template
        BarcodeTemplate::where('company_id', auth()->user()->company_id)
            ->update(['is_active' => false]);

        BarcodeTemplate::create([
            'company_id' => auth()->user()->company_id,
            'created_by' => auth()->id(),
            'name'       => $request->name,
            'template'   => $request->template,
            'is_active'  => true,
        ]);

        return redirect()->route('admin.barcode.templates.index')
            ->with('success', 'Barcode template saved');
    }
       
}
