<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->unsignedBigInteger('external_asset_id')
                  ->nullable()
                  ->after('id');

            $table->string('external_source', 50)
                  ->nullable()
                  ->after('external_asset_id');

            // Composite index
            $table->index(
                ['external_asset_id', 'external_source'],
                'idx_external_asset'
            );
             
            $table->string('rfid')
                  ->nullable()
                  ->after('asset_tag');

        });
    }

    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->dropIndex('idx_external_asset');
            $table->dropColumn(['external_asset_id', 'external_source']);
            $table->dropColumn('rfid');
        });
    }
};
