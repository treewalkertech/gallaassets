<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->increments('id'); // INT UNSIGNED AUTO_INCREMENT PRIMARY KEY
            
            // SDP identifiers
            $table->string('external_po_id')->unique();
            $table->string('custom_po_id')->nullable();
            $table->string('po_name')->nullable();

            // Define foreign key columns
            $table->integer('supplier_id')->unsigned()->nullable();
            $table->integer('requested_by')->unsigned()->nullable();
            $table->integer('owner_id')->unsigned()->nullable();

            // Financials
            $table->decimal('total_price', 15, 2)->default(0);
            $table->decimal('base_total_price', 15, 2)->default(0);
            $table->decimal('discount', 15, 2)->default(0);
            $table->decimal('sales_tax', 15, 2)->default(0);
            $table->decimal('additional_tax', 15, 2)->default(0);
            $table->decimal('shipping_price', 15, 2)->default(0);

            // Status
            $table->string('status_name')->nullable();
            $table->string('status_id')->nullable();

            // Dates
            $table->timestamp('created_date')->nullable();
            $table->timestamp('required_date')->nullable();

            // Currency
            $table->string('currency_code', 10)->nullable();

            $table->timestamps();
            
            // Add foreign key constraints WITHOUT the prefix
            // Laravel will automatically add 'galla_' prefix
            $table->foreign('supplier_id')
                  ->references('id')
                  ->on('suppliers')  // Just 'suppliers' - Laravel adds 'galla_'
                  ->onDelete('set null');
                  
            $table->foreign('requested_by')
                  ->references('id')
                  ->on('users')      // Just 'users' - Laravel adds 'galla_'
                  ->onDelete('set null');
                  
            $table->foreign('owner_id')
                  ->references('id')
                  ->on('users')      // Just 'users' - Laravel adds 'galla_'
                  ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_orders');
    }
};