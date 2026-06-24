<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'nama',
        'jabatan',
        'email',
        'password',
        'nik',
        'role',
        'no_rt',

        'ttl',
        'nip',
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
        'ttd',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    // protected $casts = [
    //     'email_verified_at' => 'datetime',
    //     'password' => 'hashed',
    // ];
}
