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
    Schema::table('rt_pengantar_numbers', function (Blueprint $table) {
        $table->dropUnique('rt_pengantar_numbers_no_surat_pengantar_unique');

        $table->unique(['no_surat_pengantar', 'surat_type', 'rt_id'], 'unique_pengantar');
    });
}

public function down()
{
    Schema::table('rt_pengantar_numbers', function (Blueprint $table) {
        $table->dropUnique('unique_pengantar');
        $table->unique('no_surat_pengantar');
    });
}
};
