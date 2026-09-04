<?php
namespace App\Services;

use App\Models\ServiceDeskConfig;

class ServiceDeskConfigService
{
    public function activeForCompany(int $companyId): ServiceDeskConfig
    {
        return ServiceDeskConfig::where('company_id', $companyId)
            ->where('is_active', true)
            ->firstOrFail();
    }

    public function isSyncEnabled(int $companyId): bool
    {
        return (bool) ServiceDeskConfig::where('company_id', $companyId)
            ->where('is_active', true)
            ->value('is_sync_enabled');
    }
}
