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
        Schema::table('suket_tidak_mampu', function (Blueprint $table) {
            $table->enum('pengantar_rt_type', ['offline', 'system'])
                  ->nullable()
                  ->after('file_pengantar_rt');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('suket_tidak_mampu', function (Blueprint $table) {
            $table->dropColumn('pengantar_rt_type');
        });
    }
};
