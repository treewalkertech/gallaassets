<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every fixed_asset-type Item Master row can back many individual Assets
 * (§2 of the spec). Until now the only way to trace an Asset back to the
 * Item it was received against was indirectly, via assets.model_id ->
 * models.id -> items.asset_model_id -- workable, but not a direct link,
 * and ambiguous if more than one Item ever pointed at the same Asset
 * Model. This mirrors the existing purchase_order_id/grn_id columns on
 * assets with a direct item_id, set by Grn::postReceipt() when it creates
 * the asset.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->unsignedInteger('item_id')->nullable()->after('grn_id');
            $table->foreign('item_id', 'assets_item_fk')
                  ->references('id')->on('items')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->dropForeign('assets_item_fk');
            $table->dropColumn('item_id');
        });
    }
};
