<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {

            // Data tambahan untuk profile users
            $table->string('ttl')->nullable();
            $table->string('jenis_kelamin')->nullable();
            $table->string('no_hp')->nullable();
            $table->string('agama')->nullable();
            $table->string('kewarganegaraan')->nullable();
            $table->string('pendidikan')->nullable();
            $table->string('status_perkawinan')->nullable();
            $table->text('alamat')->nullable();
            $table->string('NIP')->nullable();

            // Upload dokumen
            $table->string('foto_profil')->nullable();
            $table->string('file_ktp')->nullable();
            $table->string('file_kk')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {

            // rollback kolom-kolom tambahan
            $table->dropColumn([
                'ttl',
                'jenis_kelamin',
                'no_hp',
                'agama',
                'kewarganegaraan',
                'pendidikan',
                'status_perkawinan',
                'alamat',
                'NIP',
                'foto_profil',
                'file_ktp',
                'file_kk',
            ]);
        });
    }
};
