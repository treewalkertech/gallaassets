<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Fixes a seed-data bug in StatuslabelSeeder/StatuslabelFactory: "Lost/Stolen"
 * and "Broken - Not Fixable" were created with all three status-type flags
 * left at the factory default (deployable=0, pending=0, archived=0), which
 * makes them "undeployable" rather than "archived" -- Snipe-IT's own concept
 * for assets permanently removed from active inventory (see the archived()
 * factory state's own comment: "These assets are permanently undeployable").
 * That's exactly what these two labels represent, and it's what the new
 * Inventory report (Item::assets() filtered by assetstatus.archived) relies
 * on to detect "discarded or lost" assets -- see the Inventory feature's
 * delivery notes in the project spec for the full reasoning.
 *
 * Only touches rows still sitting at that exact default combination: if
 * either label was already edited away from it on this instance, this
 * leaves it alone rather than overriding a deliberate choice.
 */
return new class extends Migration
{
    private const NAMES = ['Lost/Stolen', 'Broken - Not Fixable'];

    public function up(): void
    {
        DB::table('status_labels')
            ->whereIn('name', self::NAMES)
            ->where('deployable', 0)
            ->where('pending', 0)
            ->where('archived', 0)
            ->update(['archived' => 1]);
    }

    public function down(): void
    {
        DB::table('status_labels')
            ->whereIn('name', self::NAMES)
            ->where('deployable', 0)
            ->where('pending', 0)
            ->where('archived', 1)
            ->update(['archived' => 0]);
    }
};
