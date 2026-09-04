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
        Schema::create('barcode_templates', function (Blueprint $table) {
            $table->id();

            $table->integer('company_id')->unsigned()->nullable();
            $table->integer('created_by')->unsigned()->nullable();
      

            $table->string('name'); // Template name
            $table->string('template'); 
            // {company}/{location}/{logistics}/{po}/{department}/{asset_id}

            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index(['company_id', 'is_active']);

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
        Schema::dropIfExists('barcode_templates');
    }
};
