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
        $table->dropForeign(['submitted_by_id']);
    });
}

public function down(): void
{
    Schema::table('suket_tidak_mampu', function (Blueprint $table) {
        $table->foreign('submitted_by_id')
              ->references('id')
              ->on('users')
              ->nullOnDelete();
    });
}
};
