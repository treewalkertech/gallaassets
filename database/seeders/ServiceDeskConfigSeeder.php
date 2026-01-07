<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ServiceDeskConfigSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('service_desk_configs')->updateOrInsert(
            [
                'company_id' => 1,
                'mode'       => 'sandbox',
            ],
            [
                'created_by'     => 1,
                'portal_id'      => null,
                'base_url'       => 'https://localhost:8080/',
                'api_version'    => 'v3',
                'technician_key' => '626A029D-1506-44E6-89C3-4353BB8574A3',
                'verify_ssl'     => 0,
                'timeout'        => 60,
                'is_active'      => 1,
                'is_sync_enabled'=> 1,
                'created_at'     => now(),
                'updated_at'     => now(),
            ]
        );
    }
}
