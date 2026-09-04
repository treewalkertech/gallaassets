<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mirrors the existing purchase_order_id column on assets: every asset a
 * GRN creates traces back to both its PO (already possible) and the exact
 * receiving batch that brought it in.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->unsignedInteger('grn_id')->nullable()->after('purchase_order_id');
            $table->foreign('grn_id', 'assets_grn_fk')
                  ->references('id')->on('grn')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->dropForeign('assets_grn_fk');
            $table->dropColumn('grn_id');
        });
    }
};
