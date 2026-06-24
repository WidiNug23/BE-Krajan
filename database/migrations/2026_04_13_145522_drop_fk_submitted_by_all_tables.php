<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $tables = [
        'suket_skck',
        'suket_belum_menikah',
        'suket_imunisasi_tt',
        'suket_janda',
        'suket_penduduk',
        'suket_usaha',
    ];

    public function up(): void
    {
        foreach ($this->tables as $tableName) {

            Schema::table($tableName, function (Blueprint $table) {

                try {
                    $table->dropForeign(['submitted_by_id']); // ✅ FIX DI SINI
                } catch (\Exception $e) {
                    // skip kalau FK tidak ada
                }

            });

        }
    }

    public function down(): void
    {
        foreach ($this->tables as $tableName) {

            Schema::table($tableName, function (Blueprint $table) {

                $table->foreign('submitted_by_id')
                      ->references('id')
                      ->on('users')
                      ->nullOnDelete();

            });

        }
    }
};