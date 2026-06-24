<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class LaporanModel extends Model
{
    use HasFactory;

    protected $table = 'laporan_masyarakat';
    protected $primaryKey = 'id_laporan';

    protected $fillable = [
        'nama',
        'isi_laporan',
        'status_laporan',
        'jawaban_laporan',
        'users_id',
        'ip_address',
    ];

    /**
     * User (perangkat_desa / super_admin) yang memproses laporan
     */
    public function petugas()
    {
        return $this->belongsTo(User::class, 'users_id');
    }
}
