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
     
    Schema::create('service_desk_configs', function (Blueprint $table) {
        $table->id();

        // 🔐 Multi-tenant ownership
         $table->integer('company_id')->unsigned()->nullable();
        $table->integer('created_by')->unsigned()->nullable();
        // Environment
        $table->enum('mode', ['sandbox', 'production'])->default('sandbox');

        // SDP Core
        $table->string('portal_id')->nullable();
        $table->string('base_url')->nullable();
        $table->string('api_version')->default('v3');

        // Authentication
        $table->text('technician_key')->nullable();
       
        // Options
        $table->boolean('verify_ssl')->default(true);
        $table->integer('timeout')->default(60);

        // Controls
        $table->boolean('is_active')->default(false);
        $table->boolean('is_sync_enabled')->default(false);

        $table->timestamps();

        // 🔒 Constraints
        $table->unique(['company_id', 'mode']);

        // 🔗 Foreign Keys
        $table->foreign('company_id')
            ->references('id')
            ->on('companies')
            ->onDelete('cascade');

        $table->foreign('created_by')
            ->references('id')
            ->on('users')
            ->onDelete('restrict');
    });


    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_desk_configs');
    }
};
