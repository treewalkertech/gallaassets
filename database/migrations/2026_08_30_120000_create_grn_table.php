<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Goods Receipt Note (GRN) header. Phase 4 of the PO/GRN/Item Master spec --
 * this is what actually receives goods against an approved Purchase Order
 * and (on posting) creates the real Assets / bumps consumable qty / adds
 * license seats. See Grn::postReceipt() for the posting algorithm.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('grn', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('company_id')->nullable();
            $table->string('grn_number')->nullable();
            $table->unsignedInteger('purchase_order_id');

            // Where received goods land -- becomes rtd_location_id on any
            // Assets this GRN creates.
            $table->unsignedInteger('location_id')->nullable();

            // Status newly created Assets get. Left nullable so posting can
            // fall back to "first deployable status label" if the receiver
            // didn't pick one -- see Grn::defaultStatusIdOrFallback().
            $table->unsignedInteger('default_status_id')->nullable();

            $table->unsignedInteger('received_by')->nullable();
            $table->date('received_date')->nullable();

            // draft = still editable, nothing created yet.
            // posted = locked, triggered Asset/consumable/license creation.
            // cancelled = voided before posting, no downstream records.
            $table->string('status', 20)->default('draft');

            $table->text('notes')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();

            $table->foreign('company_id', 'grn_company_fk')
                  ->references('id')->on('companies')->onDelete('set null');
            $table->foreign('purchase_order_id', 'grn_po_fk')
                  ->references('id')->on('purchase_orders')->onDelete('cascade');
            $table->foreign('location_id', 'grn_location_fk')
                  ->references('id')->on('locations')->onDelete('set null');
            $table->foreign('default_status_id', 'grn_status_fk')
                  ->references('id')->on('status_labels')->onDelete('set null');
            $table->foreign('received_by', 'grn_received_by_fk')
                  ->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grn');
    }
};
