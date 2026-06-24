<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RtPengantarNumber extends Model
{
    use HasFactory;

    protected $table = 'rt_pengantar_numbers';

    protected $primaryKey = 'id';

    protected $fillable = [
        'no_surat_pengantar',
        'surat_type',
        'surat_id',
        'rt_id',
    ];

    public $timestamps = true;
}
