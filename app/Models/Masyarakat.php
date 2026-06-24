<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Notifications\Notifiable;

class Masyarakat extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $table = 'masyarakats';
    protected $primaryKey = 'id';

    protected $fillable = [
        'nama',
        'email',
        'email_verified_at',
        'nik',
        'password',

        // 🔹 Tambahan profil
        'ttl',
        'jenis_kelamin',
        'no_hp',
        'agama',
        'kewarganegaraan',
        'pendidikan',
        'status_perkawinan',
        'alamat',

        // 🔹 Upload dokumen
        'foto_profil',
        'file_ktp',
        'file_kk',

        // 🔹 Verifikasi
        'status_verifikasi',
        'keterangan_verifikasi',

        // Tambahan baru
        'users_id',
        'users_validated_at',

        'remember_token',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];
    protected $casts = [
    'email_verified_at' => 'datetime',
];

  public function perangkat()
    {
        return $this->belongsTo(User::class, 'id_perangkat');
    }
public function validator()
{
    return $this->belongsTo(User::class, 'users_id');
}


}
