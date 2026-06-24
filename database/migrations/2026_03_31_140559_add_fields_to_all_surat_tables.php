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

                // =========================
                // 🔥 1. submitted_by (WAJIB PALING AWAL)
                // =========================
                if (!Schema::hasColumn($table->getTable(), 'submitted_by')) {
                    $table->string('submitted_by')
                          ->default('masyarakat')
                          ->after('masyarakat_id');
                }

                // =========================
                // 🔥 2. submitted_by_id
                // =========================
                if (!Schema::hasColumn($table->getTable(), 'submitted_by_id')) {
                    $table->unsignedBigInteger('submitted_by_id')
                          ->nullable()
                          ->after('submitted_by');

                    $table->foreign('submitted_by_id')
                          ->references('id')
                          ->on('users')
                          ->nullOnDelete();
                }

                // =========================
                // 🔥 3. pengantar_rt_type
                // =========================
                if (!Schema::hasColumn($table->getTable(), 'pengantar_rt_type')) {
                    $table->enum('pengantar_rt_type', ['offline', 'system'])
                          ->nullable()
                          ->after('file_pengantar_rt');
                }

                // =========================
                // 🔥 4. nama_rt
                // =========================
                if (!Schema::hasColumn($table->getTable(), 'nama_rt')) {
                    $table->string('nama_rt')
                          ->nullable()
                          ->after('rt_id');
                }

                // =========================
                // 🔥 5. perangkat_validated_by
                // =========================
                if (!Schema::hasColumn($table->getTable(), 'perangkat_validated_by')) {
                    $table->unsignedBigInteger('perangkat_validated_by')
                          ->nullable()
                          ->after('perangkat_id');

                    $table->foreign('perangkat_validated_by')
                          ->references('id')
                          ->on('users')
                          ->nullOnDelete();
                }

            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {

                // perangkat_validated_by
                if (Schema::hasColumn($table->getTable(), 'perangkat_validated_by')) {
                    $table->dropForeign([$table->getTable().'_perangkat_validated_by_foreign']);
                    $table->dropColumn('perangkat_validated_by');
                }

                // nama_rt
                if (Schema::hasColumn($table->getTable(), 'nama_rt')) {
                    $table->dropColumn('nama_rt');
                }

                // pengantar_rt_type
                if (Schema::hasColumn($table->getTable(), 'pengantar_rt_type')) {
                    $table->dropColumn('pengantar_rt_type');
                }

                // submitted_by_id
                if (Schema::hasColumn($table->getTable(), 'submitted_by_id')) {
                    $table->dropForeign([$table->getTable().'_submitted_by_id_foreign']);
                    $table->dropColumn('submitted_by_id');
                }

                // submitted_by
                if (Schema::hasColumn($table->getTable(), 'submitted_by')) {
                    $table->dropColumn('submitted_by');
                }

            });
        }
    }
};