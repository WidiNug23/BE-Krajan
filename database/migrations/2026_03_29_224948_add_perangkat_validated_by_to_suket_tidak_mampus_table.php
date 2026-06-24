<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suket_tidak_mampu', function (Blueprint $table) {
            
            $table->unsignedBigInteger('perangkat_validated_by')->nullable()->after('perangkat_id');

            // Optional: foreign key (recommended)
            $table->foreign('perangkat_validated_by')
                  ->references('id')
                  ->on('users')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('suket_tidak_mampu', function (Blueprint $table) {
            $table->dropForeign(['perangkat_validated_by']);
            $table->dropColumn('perangkat_validated_by');
        });
    }
};
