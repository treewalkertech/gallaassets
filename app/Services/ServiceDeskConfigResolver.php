<?php

namespace App\Services;

use App\Models\ServiceDeskConfig;
use Illuminate\Support\Facades\Auth;

class ServiceDeskConfigResolver
{
    public static function get(): ServiceDeskConfig
    {
        $config = ServiceDeskConfig::where('company_id', Auth::user()->company_id)
            ->where('is_active', true)
            ->where('is_sync_enabled', true)
            ->first();

        if (! $config) {
            throw new \Exception('ServiceDesk sync is disabled or not configured');
        }

        return $config;
    }
}
