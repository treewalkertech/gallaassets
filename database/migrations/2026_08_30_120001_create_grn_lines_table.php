<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('grn_lines', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('grn_id');
            $table->unsignedInteger('purchase_order_line_id');

            // Denormalized from purchase_order_line_id at add-time, purely so
            // posting doesn't need an extra join per line.
            $table->unsignedInteger('item_id');

            $table->integer('qty_received');

            // Defaults to the PO line's unit_cost when left blank -- lets a
            // receiver record a price variance discovered at receiving time
            // without having to go back and edit the PO itself.
            $table->decimal('unit_cost', 15, 2)->nullable();

            // One serial per unit, for fixed_asset items only. Optional --
            // any unit without a supplied serial just gets an auto-generated
            // asset tag and no serial, same as creating an asset by hand.
            $table->json('serials')->nullable();

            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('grn_id', 'grn_lines_grn_fk')
                  ->references('id')->on('grn')->onDelete('cascade');
            $table->foreign('purchase_order_line_id', 'grn_lines_po_line_fk')
                  ->references('id')->on('purchase_order_lines')->onDelete('restrict');
            $table->foreign('item_id', 'grn_lines_item_fk')
                  ->references('id')->on('items')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grn_lines');
    }
};
