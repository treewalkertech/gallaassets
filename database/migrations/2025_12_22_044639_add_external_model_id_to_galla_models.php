<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::table('models', function (Blueprint $table) {
            $table->unsignedBigInteger('external_model_id')
                  ->nullable()
                  ->unique()
                  ->after('id');
        });
    }

    public function down()
    {
        Schema::table('models', function (Blueprint $table) {
            $table->dropColumn('external_model_id');
        });
    }
};
