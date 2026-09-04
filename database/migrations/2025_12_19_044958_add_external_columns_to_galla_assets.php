<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {

            // Columns (add only if not exists in fresh table)
            $table->unsignedBigInteger('external_asset_id')
                  ->nullable()
                  ->after('id');

            $table->string('external_source', 50)
                  ->nullable()
                  ->after('external_asset_id');

            // ✅ UNIQUE on external_asset_id ONLY
            $table->unique('external_asset_id', 'uniq_external_asset_id');

            $table->string('rfid')
                  ->nullable()
                  ->after('asset_tag');
        });
    }

    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->dropUnique('uniq_external_asset_id');
            $table->dropColumn(['external_asset_id', 'external_source', 'rfid']);
        });
    }
};
