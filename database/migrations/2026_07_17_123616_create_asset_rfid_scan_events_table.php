<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('asset_rfid_scan_events', function (Blueprint $table) {
            $table->bigIncrements('id');

            /*
             * Asset reference.
             *
             * assets.id is INT UNSIGNED, therefore this column must be
             * unsignedInteger instead of Laravel's default BIGINT foreignId.
             *
             * asset_id remains nullable so unknown/unmapped RFID tags can also
             * be recorded and displayed on the RFID events screen.
             */
            $table->unsignedInteger('asset_id')->nullable();
            $table->unsignedInteger('company_id')->nullable();

            /*
             * RFID and asset snapshot fields.
             *
             * Snapshot fields preserve the values that existed at scan time,
             * even if the asset master is edited later.
             */
            $table->string('rfid_epc', 191);
            $table->string('asset_tag', 191)->nullable();
            $table->string('asset_name', 191)->nullable();
            $table->string('serial', 191)->nullable();
            $table->integer('assigned_to')->nullable();
            $table->string('assigned_to_name', 191)->nullable();

            /*
             * Reader and gate details.
             *
             * These are kept directly in the event table for now. A separate
             * RFID reader master table can be introduced later.
             */
            $table->string('reader_code', 100)->nullable();
            $table->string('reader_name', 191)->nullable();
            $table->string('reader_ip', 45)->nullable();
            $table->string('gate_name', 191)->nullable();
            $table->integer('location_id')->nullable();
            $table->unsignedSmallInteger('antenna_no')->nullable();
            $table->decimal('rssi', 8, 2)->nullable();

            /*
             * External reader/API references.
             */
            $table->string('scan_batch_id', 100)->nullable();
            $table->string('source_event_id', 100)->nullable();

            /*
             * Event classification.
             *
             * direction: IN, OUT, UNKNOWN
             * event_type: GATE_SCAN, MANUAL_SCAN, TEST_SCAN
             * scan_result: MAPPED, UNKNOWN, REJECTED, DUPLICATE
             * processing_status: RECEIVED, PROCESSED, FAILED, IGNORED
             */
            $table->string('direction', 20)->default('UNKNOWN');
            $table->string('event_type', 30)->default('GATE_SCAN');
            $table->string('scan_result', 30)->default('MAPPED');
            $table->string('processing_status', 30)->default('RECEIVED');

            /*
             * Fixed readers normally read the same EPC many times while the
             * asset is passing through the gate. One logical event can retain
             * the number of reads and its first/last seen timestamps.
             */
            $table->unsignedInteger('read_count')->default(1);
            $table->timestamp('scanned_at', 3);
            $table->timestamp('last_seen_at', 3)->nullable();
            $table->timestamp('received_at', 3)->nullable();
            $table->timestamp('processed_at', 3)->nullable();

            /*
             * Store the original reader request for troubleshooting and future
             * changes without adding a column for every vendor-specific field.
             */

            $table->text('remarks')->nullable();

            $table->timestamps(3);

            /*
             * Indexes required by the recent-events screen, filters, summaries,
             * and asset history view.
             */
            $table->index('scanned_at', 'garse_scanned_at_idx');
            $table->index(['company_id', 'scanned_at'], 'garse_company_scanned_idx');
            $table->index(['asset_id', 'scanned_at'], 'garse_asset_scanned_idx');
            $table->index(['rfid_epc', 'scanned_at'], 'garse_epc_scanned_idx');
            $table->index(['reader_code', 'scanned_at'], 'garse_reader_scanned_idx');
            $table->index(['scan_result', 'scanned_at'], 'garse_result_scanned_idx');
            $table->index(['direction', 'scanned_at'], 'garse_direction_scanned_idx');
            $table->index('asset_tag', 'garse_asset_tag_idx');
            $table->index('serial', 'garse_serial_idx');
            $table->index('scan_batch_id', 'garse_batch_idx');

            /*
             * Prevent the same event from the same reader being inserted twice
             * when the reader/API retries a request.
             */
            $table->unique(
                ['reader_code', 'source_event_id'],
                'garse_reader_source_event_unique'
            );

            /*
             * Keep the scan history even when an asset is hard-deleted.
             * The snapshot fields above will still be available.
             */
            $table->foreign('asset_id', 'garse_asset_fk')
                ->references('id')
                ->on('assets')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('asset_rfid_scan_events');
    }
};
