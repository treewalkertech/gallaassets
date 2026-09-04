<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {

            // Make sure columns are indexed uniquely
            $table->unique('asset_tag', 'galla_assets_asset_tag_unique');
            $table->unique('rfid', 'galla_assets_rfid_unique');
        });
    }

    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {

            $table->dropUnique('galla_assets_asset_tag_unique');
            $table->dropUnique('galla_assets_rfid_unique');
        });
    }
};
