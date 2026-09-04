<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {

              // PO / Finance
            if (!Schema::hasColumn('assets', 'order_number')) {
                $table->string('order_number')->nullable()->after('purchase_order_id');
            }

            if (!Schema::hasColumn('assets', 'current_value')) {
                $table->decimal('current_value', 15, 2)->nullable()->after('purchase_cost');
            }

            // Warranty
            if (!Schema::hasColumn('assets', 'warranty_months')) {
                $table->integer('warranty_months')->nullable()->after('current_value');
            }

            if (!Schema::hasColumn('assets', 'warranty_expires_at')) {
                $table->date('warranty_expires_at')->nullable()->after('warranty_months');
            }

            // Explicit EOL override
            if (!Schema::hasColumn('assets', 'eol_explicit')) {
                $table->date('eol_explicit')->nullable()->after('asset_eol_date');
            }
        });
    }

    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->dropColumn([
                'order_number',
                'current_value',
                'warranty_months',
                'warranty_expires_at',
                'eol_explicit',
            ]);
        });
    }
};
