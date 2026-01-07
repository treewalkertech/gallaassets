<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BarcodeTemplateSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('barcode_templates')->updateOrInsert(
            [
                'company_id' => 1,
                'is_active'  => 1,
            ],
            [
                'created_by' => 1,
                'name'       => 'Default Asset Barcode',
                'template'   => '{company}/{location}/{logistics}/{po}/{department}/{asset_seq}/{asset_id}',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }
}
