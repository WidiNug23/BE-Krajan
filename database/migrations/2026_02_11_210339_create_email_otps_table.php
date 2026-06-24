<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_otps', function (Blueprint $table) {
            $table->id();

            $table->string('email')->index();
            $table->string('otp', 6);

            $table->boolean('is_used')->default(false);

            $table->timestamp('expired_at');
            $table->timestamps();

            // Optional: agar tidak ada duplikasi OTP aktif untuk email yang sama
            // (tapi ini tidak strict karena expired_at tetap bisa beda)
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_otps');
    }
};
