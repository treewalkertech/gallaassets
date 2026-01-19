<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('asset_sync_states', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('company_id'); 
            // $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->foreignId('config_id')->constrained('service_desk_configs')->onDelete('cascade');
            $table->string('status')->default('pending');
            $table->integer('total_assets')->default(0);
            $table->integer('processed_assets_count')->default(0);
            $table->json('processed_assets')->nullable();
            $table->json('failed_assets')->nullable();
            $table->integer('success_count')->default(0);
            $table->integer('skip_count')->default(0);
            $table->integer('fail_count')->default(0);
            $table->integer('current_index')->default(0);
            $table->integer('last_success_index')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->decimal('progress_percentage', 5, 2)->default(0);
            $table->integer('batch_size')->default(100);
            $table->integer('chunk_size')->default(500);
            $table->string('memory_usage')->nullable();
            $table->string('estimated_time_remaining')->nullable();
            $table->timestamps();

              $table->foreign('company_id')
              ->references('id')
              ->on('companies')
              ->onDelete('cascade');
            
            $table->index(['company_id', 'status']);
            $table->index(['config_id', 'status']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('asset_sync_states');
    }
};