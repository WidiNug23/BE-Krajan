<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
   public function up()
{
    Schema::table('suket_tidak_mampu', function (Blueprint $table) {
        $table->string('nama_rt')->nullable();
    });
}

public function down()
{
    Schema::table('suket_tidak_mampu', function (Blueprint $table) {
        $table->dropColumn('nama_rt');
    });
}
};
