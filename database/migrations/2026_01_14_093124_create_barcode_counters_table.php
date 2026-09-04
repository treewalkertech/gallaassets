<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Barcode counter table
        Schema::create('barcode_counters', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->unsignedBigInteger('current')->default(0);
            $table->timestamps();

            $table->unique('company_id', 'uniq_barcode_counter_company');
        });

        // Make barcode unique
        Schema::table('assets', function (Blueprint $table) {
            $table->unique('_snipeit_barcode_2', 'uniq_snipeit_barcode_2');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop unique index from assets
        Schema::table('assets', function (Blueprint $table) {
            $table->dropUnique('uniq_snipeit_barcode_2');
        });

        // Drop counter table
        Schema::dropIfExists('barcode_counters');
    }
};
