<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create the RFID asset location movement history table.
     *
     * One row should be inserted only when an asset is first seen or when
     * its floor/reader changes. Continuous reads from the same location
     * should update the current-location table instead of this table.
     */
    public function up(): void
    {
        Schema::create('asset_rfid_location_history', function (Blueprint $table) {
            $table->bigIncrements('id');

            // Asset and RFID snapshot at the time of the movement.
            $table->unsignedBigInteger('asset_id')->nullable();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->string('rfid_epc', 191);
            $table->string('asset_tag', 191)->nullable();
            $table->string('asset_name', 191)->nullable();
            $table->string('serial', 191)->nullable();

            // New/current physical location. gate_name represents the floor.
            $table->string('reader_code', 100);
            $table->string('reader_name', 191)->nullable();
            $table->string('reader_ip', 45)->nullable();
            $table->string('gate_name', 191);
            $table->unsignedBigInteger('location_id')->nullable();
            $table->unsignedSmallInteger('antenna_no')->nullable();
            $table->decimal('rssi', 8, 2)->nullable();

            // Previous location is null when the asset is seen for the first time.
            $table->string('previous_reader_code', 100)->nullable();
            $table->string('previous_gate_name', 191)->nullable();

            // Expected values: FIRST_SEEN or LOCATION_CHANGED.
            $table->string('event_type', 50);
            $table->dateTime('scanned_at');
            $table->dateTime('received_at')->nullable();
            $table->text('remarks')->nullable();

            $table->timestamps();

            // Short explicit names avoid MySQL index-name length issues.
            $table->index(
                ['rfid_epc', 'scanned_at'],
                'arlh_epc_scanned_idx'
            );
            $table->index(
                ['asset_id', 'scanned_at'],
                'arlh_asset_scanned_idx'
            );
            $table->index(
                ['gate_name', 'scanned_at'],
                'arlh_floor_scanned_idx'
            );
            $table->index(
                ['reader_code', 'scanned_at'],
                'arlh_reader_scanned_idx'
            );
        });
    }

    /**
     * Drop the RFID asset location movement history table.
     */
    public function down(): void
    {
        Schema::dropIfExists('asset_rfid_location_history');
    }
};