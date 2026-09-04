<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
public function up()
{
    Schema::table('assets', function (Blueprint $table) {
        $table->unsignedInteger('purchase_order_id')
              ->nullable()
              ->after('order_number');

        $table->index('purchase_order_id');

        $table->foreign('purchase_order_id', 'assets_po_fk')
              ->references('id')
              ->on('purchase_orders')
              ->onDelete('set null');
    });
}

public function down()
{
    Schema::table('assets', function (Blueprint $table) {
        $table->dropForeign('assets_po_fk');
        $table->dropColumn('purchase_order_id');
    });
}

};
