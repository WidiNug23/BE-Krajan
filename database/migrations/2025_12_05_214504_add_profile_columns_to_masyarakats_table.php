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
        Schema::table('masyarakats', function (Blueprint $table) {

            // 🔹 Data profil
            $table->string('ttl')->nullable();
            $table->string('agama')->nullable();
            $table->string('kewarganegaraan')->nullable();
            $table->string('pendidikan')->nullable();
            $table->string('status_perkawinan')->nullable();
            $table->text('alamat')->nullable();

            // 🔹 Foto profil
            $table->string('foto_profil')->nullable();

            // 🔹 Upload dokumen
            $table->string('file_ktp')->nullable();
            $table->string('file_kk')->nullable();

            // 🔹 Status verifikasi oleh perangkat desa
            $table->enum('status_verifikasi', ['pending', 'disetujui', 'ditolak'])
                  ->default('pending');

            // 🔹 Alasan jika ditolak
            $table->text('keterangan_verifikasi')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('masyarakats', function (Blueprint $table) {

            $table->dropColumn([
                'ttl',
                'agama',
                'kewarganegaraan',
                'pendidikan',
                'status_perkawinan',
                'alamat',
                'foto_profil',
                'file_ktp',
                'file_kk',
                'status_verifikasi',
                'keterangan_verifikasi',
            ]);
        });
    }
};
