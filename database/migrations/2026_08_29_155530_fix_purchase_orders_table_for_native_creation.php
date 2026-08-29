<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fixes the purchase_orders table so it can be populated by native (in-app)
 * PO creation, not just the ServiceDesk Plus (SDP) sync:
 *
 *  - external_po_id was NOT NULL + UNIQUE with no default. It's an SDP-only
 *    identifier and isn't (and can't be) supplied by native creation, so
 *    every native insert was failing at the DB layer. Made nullable; MySQL's
 *    unique index still allows any number of NULLs, so this doesn't weaken
 *    uniqueness for rows that DO have an external_po_id.
 *  - created_by didn't exist, even though the web PurchaseOrdersController
 *    already sets it on every store() call.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->string('external_po_id')->nullable()->change();

            $table->unsignedInteger('created_by')->nullable()->after('owner_id');
            $table->foreign('created_by', 'purchase_orders_created_by_fk')
                  ->references('id')
                  ->on('users')
                  ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropForeign('purchase_orders_created_by_fk');
            $table->dropColumn('created_by');

            $table->string('external_po_id')->nullable(false)->change();
        });
    }
};
