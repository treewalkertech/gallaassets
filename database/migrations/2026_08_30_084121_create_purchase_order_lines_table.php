<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('purchase_order_lines', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('purchase_order_id');
            $table->unsignedInteger('item_id');
            $table->string('description')->nullable();
            $table->integer('qty_ordered');
            $table->decimal('unit_cost', 15, 2)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('line_total', 15, 2)->default(0);

            // Denormalized running total, updated as GRNs (Phase 4) post
            // against this line -- this is what lets a PO stay open until
            // every line is fully received without re-summing GRN lines on
            // every read.
            $table->integer('qty_received')->default(0);

            $table->timestamps();

            $table->foreign('purchase_order_id', 'po_lines_po_fk')
                  ->references('id')->on('purchase_orders')->onDelete('cascade');
            $table->foreign('item_id', 'po_lines_item_fk')
                  ->references('id')->on('items')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_lines');
    }
};
