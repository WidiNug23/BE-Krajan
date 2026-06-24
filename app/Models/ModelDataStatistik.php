<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ModelDataStatistik extends Model
{
    use HasFactory;

    protected $table = 'data_statistik';
    protected $primaryKey = 'id_data_statistik';
    public $timestamps = false;

    protected $fillable = [
        'nama_file',
        'file_data',
        'tgl_buat',
        'tgl_edit',
        'users_id'
    ];

    public function uploader()
{
    return $this->belongsTo(User::class, 'users_id');
}
}
