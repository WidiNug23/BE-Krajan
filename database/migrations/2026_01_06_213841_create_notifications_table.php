<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();

            // ============================
            // TARGET NOTIFIKASI
            // ============================

            // Untuk RT (spesifik user)
            $table->unsignedBigInteger('user_id')->nullable();

            // Untuk perangkat desa / kepala desa (global per role)
            $table->string('role')->nullable();

            // ============================
            // IDENTITAS SURAT
            // ============================
            $table->string('surat_type'); // SKTM, SKU, dll
            $table->unsignedBigInteger('surat_id');

            // ============================
            // ISI NOTIFIKASI
            // ============================
            $table->string('title');
            $table->text('message')->nullable();

            // ============================
            // STATUS
            // ============================
            $table->boolean('is_read')->default(false);

            $table->timestamps();

            // ============================
            // FOREIGN KEY (AMAN)
            // ============================
            $table->foreign('user_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
