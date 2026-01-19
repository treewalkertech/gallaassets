<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Add last_asset_sync_index to service_desk_configs
        if (Schema::hasTable('service_desk_configs')) {
            Schema::table('service_desk_configs', function (Blueprint $table) {
                if (!Schema::hasColumn('service_desk_configs', 'last_asset_sync_index')) {
                    $table->integer('last_asset_sync_index')->default(0)->after('is_sync_enabled');
                }
            });
        }

        if (Schema::hasTable('suppliers')) {
            Schema::table('suppliers', function (Blueprint $table) {
                if (!Schema::hasColumn('suppliers', 'company_id')) {
                    $table->unsignedInteger('company_id')->nullable()->after('id');
                    
                     $table->foreign('company_id')
                            ->references('id')
                            ->on('companies')
                            ->onDelete('cascade');
                    
                    $table->index('company_id');
                }
            });
        }
        
        // Add company_id to purchase_orders
        if (Schema::hasTable('purchase_orders')) {
            Schema::table('purchase_orders', function (Blueprint $table) {
                if (!Schema::hasColumn('purchase_orders', 'company_id')) {
                    $table->unsignedInteger('company_id')->nullable()->after('id');
                    
                     $table->foreign('company_id')
                            ->references('id')
                            ->on('companies')
                            ->onDelete('cascade');
                    
                    $table->index('company_id');
                }
            });
        }
         if (Schema::hasTable('categories')) {
            Schema::table('categories', function (Blueprint $table) {
                if (!Schema::hasColumn('categories', 'company_id')) {
                    $table->unsignedInteger('company_id')->nullable()->after('id');
                    
                    $table->foreign('company_id')
                        ->references('id')
                        ->on('companies')
                        ->onDelete('cascade');
                    
                    $table->index('company_id');
                }
            });
        }
    }

    public function down()
    {
        // Remove from purchase_orders
        if (Schema::hasTable('purchase_orders')) {
            Schema::table('purchase_orders', function (Blueprint $table) {
                if (Schema::hasColumn('purchase_orders', 'company_id')) {
                    $table->dropForeign(['company_id']);
                    $table->dropIndex(['company_id']);
                    $table->dropColumn('company_id');
                }
            });
        }
        
        if (Schema::hasTable('categories')) {
            Schema::table('categories', function (Blueprint $table) {
                if (Schema::hasColumn('categories', 'company_id')) {
                    $table->dropForeign(['company_id']);
                    $table->dropIndex(['company_id']);
                    $table->dropColumn('company_id');
                }
            });
        }

        if (Schema::hasTable('suppliers')) {
            Schema::table('suppliers', function (Blueprint $table) {
                if (Schema::hasColumn('suppliers', 'company_id')) {
                    $table->dropForeign(['company_id']);
                    $table->dropIndex(['company_id']);
                    $table->dropColumn('company_id');
                }
            });
        }
        
        // Remove from service_desk_configs
        if (Schema::hasTable('service_desk_configs')) {
            Schema::table('service_desk_configs', function (Blueprint $table) {
                if (Schema::hasColumn('service_desk_configs', 'last_asset_sync_index')) {
                    $table->dropColumn('last_asset_sync_index');
                }
            });
        }
    }
};