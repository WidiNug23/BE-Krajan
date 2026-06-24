<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddValidasiFieldsToSuketTidakMampuTable extends Migration
{
    public function up()
{
    Schema::table('suket_tidak_mampu', function (Blueprint $table) {
        $table->timestamp('rt_validated_at')->nullable()->after('rt_id');
        $table->timestamp('perangkat_validated_at')->nullable()->after('perangkat_id');
        $table->timestamp('kepaladesa_validate_at')->nullable()->after('perangkat_validated_at');
    });
}

public function down()
{
    Schema::table('suket_tidak_mampu', function (Blueprint $table) {
        $table->dropColumn([
            'rt_validated_at',
            'perangkat_validated_at',
            'kepaladesa_validate_at'
        ]);
    });
}

}
