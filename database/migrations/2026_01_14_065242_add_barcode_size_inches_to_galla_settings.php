<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up()
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->decimal('barcode_width_in', 6, 3)->default(4);  // inches
            $table->decimal('barcode_height_in', 6, 3)->default(0.9); // inches
            $table->decimal('barcode_bar_width_in', 6, 3)->default(0.02); // bar thickness
        });
    }

    public function down()
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn([
                'barcode_width_in',
                'barcode_height_in',
                'barcode_bar_width_in',
            ]);
        });
    }

};
