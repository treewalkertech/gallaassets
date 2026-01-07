<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ServiceDeskConfig;
use App\Models\Asset;
use App\Services\ServiceDeskConfigService;
use Illuminate\Http\Request;

class ServiceDeskConfigController extends Controller
{
    public function index(ServiceDeskConfigService $configService)
    {
        $companyId = auth()->user()->company_id;

        return view('assets.index', [
            'assets' => Asset::where('company_id', $companyId)->get(),
            'isSyncEnabled' => $configService->isSyncEnabled($companyId),
        ]);
    }

    public function store(Request $request)
    {
        $companyId = auth()->user()->company_id;

        // If enabling sync for this mode, disable other modes
        if ($request->boolean('is_sync_enabled')) {
            ServiceDeskConfig::where('company_id', $companyId)
                ->update(['is_active' => false]);
        }

        ServiceDeskConfig::updateOrCreate(
            [
                'company_id' => $companyId,
                'mode' => $request->mode,
            ],
            [
                'created_by' => auth()->id(),
                'portal_id' => $request->portal_id,
                'base_url' => $request->base_url,
                'api_version' => $request->api_version ?? 'v3',
                'technician_key' => $request->technician_key,
                'session_id' => $request->session_id,
                'csrf_token' => $request->csrf_token,
                'verify_ssl' => $request->boolean('verify_ssl'),
                'timeout' => $request->timeout ?? 60,
                'is_active' => $request->boolean('is_sync_enabled'), // Active only if sync enabled
                'is_sync_enabled' => $request->boolean('is_sync_enabled'),
            ]
        );

        return back()->with('success', 'ServiceDesk config saved');
    }
}