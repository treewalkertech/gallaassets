
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add IN/OUT session tracking fields to the existing
     * asset_rfid_scan_events table.
     */
    public function up(): void
    {
        Schema::table('asset_rfid_scan_events', function (Blueprint $table) {

            /*
             * ============================================================
             * CURRENT RFID SESSION STATUS
             * ============================================================
             *
             * IN  = Asset is currently inside
             * OUT = Asset is currently outside
             */
            if (!Schema::hasColumn(
                'asset_rfid_scan_events',
                'status'
            )) {
                $table->string('status', 20)
                    ->default('OUT')
                    ->after('direction');
            }


            /*
             * ============================================================
             * IN TIME
             * ============================================================
             *
             * The first valid scan after the asset is OUT becomes
             * the IN time.
             */
            if (!Schema::hasColumn(
                'asset_rfid_scan_events',
                'in_at'
            )) {
                $table->timestamp('in_at', 3)
                    ->nullable()
                    ->after('scanned_at');
            }


            /*
             * ============================================================
             * OUT TIME
             * ============================================================
             *
             * This is populated when the current session becomes OUT.
             */
            if (!Schema::hasColumn(
                'asset_rfid_scan_events',
                'out_at'
            )) {
                $table->timestamp('out_at', 3)
                    ->nullable()
                    ->after('in_at');
            }


            /*
             * ============================================================
             * EXPECTED OUT TIME
             * ============================================================
             *
             * Example:
             *
             * IN        = 08:00
             * Working   = 10 hours
             * Expected  = 18:00
             *
             * If the tag is repeatedly scanned before 18:00,
             * it remains IN.
             */
            if (!Schema::hasColumn(
                'asset_rfid_scan_events',
                'expected_out_at'
            )) {
                $table->timestamp('expected_out_at', 3)
                    ->nullable()
                    ->after('out_at');
            }


            /*
             * ============================================================
             * WORKING HOURS
             * ============================================================
             *
             * Stores the working-hour duration used for this session.
             *
             * This is intentionally stored with the session so that
             * changing the default from 10 hours to another value does
             * not modify an already-running session.
             */
            if (!Schema::hasColumn(
                'asset_rfid_scan_events',
                'working_hours'
            )) {
                $table->unsignedSmallInteger('working_hours')
                    ->default(10)
                    ->after('expected_out_at');
            }


            /*
             * ============================================================
             * OUT TYPE
             * ============================================================
             *
             * AUTO   = scheduler automatically marked OUT
             * SCAN   = reader/API caused OUT
             * MANUAL = manually changed by user/admin
             */
            if (!Schema::hasColumn(
                'asset_rfid_scan_events',
                'out_type'
            )) {
                $table->string('out_type', 20)
                    ->nullable()
                    ->after('working_hours');
            }


            /*
             * ============================================================
             * IN SOURCE EVENT
             * ============================================================
             *
             * Keeps the original reader/API event ID that caused
             * the current IN state.
             */
            if (!Schema::hasColumn(
                'asset_rfid_scan_events',
                'in_source_event_id'
            )) {
                $table->string('in_source_event_id', 100)
                    ->nullable()
                    ->after('out_type');
            }


            /*
             * ============================================================
             * OUT SOURCE EVENT
             * ============================================================
             *
             * Keeps the reader/API event ID that caused the OUT state.
             *
             * AUTO OUT will normally have this as NULL.
             */
            if (!Schema::hasColumn(
                'asset_rfid_scan_events',
                'out_source_event_id'
            )) {
                $table->string('out_source_event_id', 100)
                    ->nullable()
                    ->after('in_source_event_id');
            }


            /*
             * ============================================================
             * INDEXES
             * ============================================================
             */

            $table->index(
                ['status', 'expected_out_at'],
                'garse_status_expected_out_idx'
            );

            $table->index(
                ['asset_id', 'status'],
                'garse_asset_status_idx'
            );

            $table->index(
                ['rfid_epc', 'status'],
                'garse_epc_status_idx'
            );
        });
    }


    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        Schema::table('asset_rfid_scan_events', function (Blueprint $table) {

            /*
             * Drop indexes first.
             */
            if (Schema::hasColumn(
                'asset_rfid_scan_events',
                'status'
            )) {
                $table->dropIndex(
                    'garse_status_expected_out_idx'
                );

                $table->dropIndex(
                    'garse_asset_status_idx'
                );

                $table->dropIndex(
                    'garse_epc_status_idx'
                );
            }


            /*
             * Drop added columns.
             */
            $columns = [
                'status',
                'in_at',
                'out_at',
                'expected_out_at',
                'working_hours',
                'out_type',
                'in_source_event_id',
                'out_source_event_id',
            ];

            foreach ($columns as $column) {

                if (Schema::hasColumn(
                    'asset_rfid_scan_events',
                    $column
                )) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
