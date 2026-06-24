<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('rt_pengantar_numbers', function (Blueprint $table) {
            $table->id();

            // 🔑 Nomor pengantar RT (HARUS UNIK GLOBAL)
            $table->string('no_surat_pengantar')->unique();


            // 🧾 Jenis surat (sktm, domisili, usaha, dll)
            $table->string('surat_type', 50);

            // 🆔 ID surat dari tabel masing-masing
            $table->unsignedBigInteger('surat_id');

            // 👤 RT yang memvalidasi (opsional)
            $table->unsignedBigInteger('rt_id')->nullable();

            $table->timestamps();

            // 🔎 Index tambahan (opsional tapi direkomendasikan)
            $table->index(['surat_type', 'surat_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rt_pengantar_numbers');
    }
};
