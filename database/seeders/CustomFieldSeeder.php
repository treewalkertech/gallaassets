<?php

namespace Database\Seeders;

use App\Models\CustomField;
use App\Models\CustomFieldset;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CustomFieldSeeder extends Seeder
{
    public function run()
    {
        // Remove old snipeit columns only
        $columns = DB::getSchemaBuilder()->getColumnListing('assets');

        foreach ($columns as $column) {
            if (strpos($column, '_snipeit_') !== false) {
                Schema::table('assets', function (Blueprint $table) use ($column) {
                    $table->dropColumn($column);
                });
            }
        }

        // Clean tables
        CustomField::truncate();
        CustomFieldset::truncate();
        DB::table('custom_field_custom_fieldset')->truncate();

        /* -----------------------------------
         | 1️⃣ CREATE FIELDSETS (THIS PART YOU ASKED)
         ----------------------------------- */
        $assetFieldset = CustomFieldset::create([
            'name'       => 'Barcode',
            'created_by' => 1, // admin user id
        ]);

        /* -----------------------------------
         | 2️⃣ CREATE ONLY REQUIRED FIELDS
         ----------------------------------- */
        $macField = CustomField::factory()->macAddress()->create();
        $barcodeField = CustomField::factory()->barcode()->create();

        /* -----------------------------------
         | 3️⃣ MAP FIELDS TO FIELDSET
         ----------------------------------- */
        DB::table('custom_field_custom_fieldset')->insert([
            [
                'custom_field_id'     => $macField->id,
                'custom_fieldset_id'  => $assetFieldset->id,
                'order'               => 0,
                'required'            => 0,
            ],
            [
                'custom_field_id'     => $barcodeField->id,
                'custom_fieldset_id'  => $assetFieldset->id,
                'order'               => 1,
                'required'            => 0,
            ],
        ]);
    }
}
