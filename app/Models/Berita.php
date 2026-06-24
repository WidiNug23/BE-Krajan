<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Berita extends Model
{
    protected $table = 'berita';        // ← WAJIB!
    protected $primaryKey = 'id_berita'; // ← sudah benar

    public $incrementing = true;         // ← WAJIB untuk bigInt
    protected $keyType = 'int';          // ← WAJIB untuk bigInt

    public $timestamps = true;           // ← karena created_at & updated_at ada

    protected $fillable = [
        'judul',
        'isi',
        'image',
        'author',
        'jenis_berita',
        'tanggal_publikasi',
        'tanggal_update',
    ];
}
