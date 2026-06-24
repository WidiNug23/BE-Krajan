<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suket_tidak_mampu', function (Blueprint $table) {

            // 🔥 TAMBAH FIELD BARU
            $table->unsignedBigInteger('submitted_by_id')
                  ->nullable()
                  ->after('submitted_by');

            // (OPSIONAL tapi disarankan) relasi ke tabel users
            $table->foreign('submitted_by_id')
                  ->references('id')
                  ->on('users')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('suket_tidak_mampu', function (Blueprint $table) {

            // hapus foreign key dulu
            $table->dropForeign(['submitted_by_id']);

            // lalu hapus kolom
            $table->dropColumn('submitted_by_id');
        });
    }
};
