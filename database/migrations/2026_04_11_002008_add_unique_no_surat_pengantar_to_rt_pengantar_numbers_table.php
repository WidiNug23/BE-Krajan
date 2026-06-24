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
        Schema::table('rt_pengantar_numbers', function (Blueprint $table) {
            $table->unique('no_surat_pengantar', 'rt_pengantar_unique_no_surat');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rt_pengantar_numbers', function (Blueprint $table) {
            $table->dropUnique('rt_pengantar_unique_no_surat');
        });
    }
};
