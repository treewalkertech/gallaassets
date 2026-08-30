<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the native approval workflow to purchase_orders (Phase 3 of the
 * PO/GRN/Item Master spec). The existing status_name/status_id columns are
 * left untouched -- those stay driven by the ServiceDesk Plus sync for any
 * already-synced rows. The new `status` column is what native (in-app) POs
 * are driven by from here on.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->unsignedInteger('company_id')->nullable()->after('id');
            $table->unsignedInteger('approver_id')->nullable()->after('owner_id');

            // pending/approved/rejected -- who approved it and when.
            $table->string('approval_status', 20)->default('pending')->after('approver_id');
            $table->timestamp('approved_at')->nullable()->after('approval_status');

            // draft/pending_approval/approved/partially_received/received/
            // closed/cancelled/rejected -- see PurchaseOrder::STATUSES.
            // Kept as a plain string, same reasoning as items.item_type.
            $table->string('status', 20)->default('draft')->after('status_id');

            $table->foreign('company_id', 'purchase_orders_company_fk')
                  ->references('id')->on('companies')->onDelete('set null');
            $table->foreign('approver_id', 'purchase_orders_approver_fk')
                  ->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropForeign('purchase_orders_company_fk');
            $table->dropForeign('purchase_orders_approver_fk');
            $table->dropColumn(['company_id', 'approver_id', 'approval_status', 'approved_at', 'status']);
        });
    }
};
