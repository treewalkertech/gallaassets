<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Item Master — the one catalog table Purchase Orders get raised against.
 *
 * Each item points at whichever type-specific Snipe-IT table it fulfils
 * into on receipt, so checkout/depreciation/reporting keep working
 * unmodified for anything a future GRN creates:
 *   - fixed_asset -> asset_model_id -> models      (1 item : many Assets)
 *   - consumable  -> consumable_id  -> consumables (qty incremented)
 *   - license     -> license_id     -> licenses    (seats incremented)
 *
 * See the "PO Generation, GRN Inward & Item Master" technical spec
 * (GallaAsset&Inventory project, §2) for the full design rationale.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('items', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('company_id')->nullable();
            $table->string('item_code')->nullable();
            $table->string('name');
            $table->text('description')->nullable();

            // 'fixed_asset' | 'consumable' | 'license' -- kept as a plain
            // string (not a DB enum) to match this app's existing
            // category_type convention and avoid enum-alter friction later.
            $table->string('item_type', 20);

            $table->unsignedInteger('category_id')->nullable();
            $table->unsignedInteger('manufacturer_id')->nullable();
            $table->string('uom', 20)->nullable()->default('EA');

            // Exactly one of these three should be set, matching item_type.
            $table->unsignedInteger('asset_model_id')->nullable();
            $table->unsignedInteger('consumable_id')->nullable();
            $table->unsignedInteger('license_id')->nullable();

            $table->decimal('default_unit_cost', 15, 2)->nullable();
            $table->integer('reorder_level')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->unsignedInteger('created_by')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('company_id')->references('id')->on('companies')->onDelete('set null');
            $table->foreign('category_id')->references('id')->on('categories')->onDelete('set null');
            $table->foreign('manufacturer_id')->references('id')->on('manufacturers')->onDelete('set null');
            $table->foreign('asset_model_id', 'items_asset_model_fk')->references('id')->on('models')->onDelete('set null');
            $table->foreign('consumable_id', 'items_consumable_fk')->references('id')->on('consumables')->onDelete('set null');
            $table->foreign('license_id', 'items_license_fk')->references('id')->on('licenses')->onDelete('set null');
            $table->foreign('created_by', 'items_created_by_fk')->references('id')->on('users')->onDelete('set null');

            // item_code is only meaningfully unique within a company (or
            // globally, for single-company installs where company_id is
            // always null) -- a composite unique index covers both.
            $table->unique(['company_id', 'item_code'], 'items_company_item_code_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('items');
    }
};
