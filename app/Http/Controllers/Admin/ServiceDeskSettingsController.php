<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;

class ServiceDeskSettingsController extends Controller
{
    public function index()
    {
        if (!auth()->user()->canManageServiceDesk()) {
            abort(403, 'Unauthorized');
        }
        return view('admin.servicedesk.index');
    }
}
