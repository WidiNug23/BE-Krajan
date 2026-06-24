<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SuketPenduduk extends Model
{
    use HasFactory;

    protected $table = 'suket_penduduk';
    protected $primaryKey = 'id_skp';

protected $fillable = [
    'masyarakat_id',
    'nama',
    'jenis_kelamin',
    'ttl',
    'agama',
    'status_perkawinan',
    'pekerjaan',
    'nik',
    'alamat',
    'kewarganegaraan',
    'pendidikan',
    'rt_id',
        'nama_rt',
    'perangkat_id',
    'kepala_desa_id',
    'file',
    'file_pdf',
    'file_pengantar_rt',
     'pengantar_rt_type',
    'status',
    'keterangan',
    'alasan',
    'file_ktp',
    'file_kk',
    'nomor_surat',
    'no_surat_pengantar',
    'poin_ii',
    'keperluan',
    'ttd_masyarakat',
    'ttd_rt',
    'ttd_kades',

     // FIELD VALIDASI
        'rt_validated_at',
        'perangkat_validated_at',
        'kepala_desa_validated_at',

    // FIELD PENOLAK
        'rejected_by',
        'deleted_by',
                'submitted_by',
        'submitted_by_id',
        'perangkat_validated_by'
];

 protected $casts = [
        'rt_validated_at' => 'datetime',
        'perangkat_validated_at' => 'datetime',
        'kepala_desa_validated_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',

        'rejected_by' => 'string',
        'deleted_by'  => 'string',
    ];

    public function rt()
    {
        return $this->belongsTo(User::class, 'rt_id', 'id');
    }

    public function perangkat()
    {
        return $this->belongsTo(User::class, 'perangkat_id', 'id');
    }

     public function perangkatValidator()
    {
        return $this->belongsTo(User::class, 'perangkat_validated_by');
    }

 public function submitterUser()
    {
        return $this->belongsTo(User::class, 'submitted_by_id');
    }

    public function submitterMasyarakat()
    {
        return $this->belongsTo(Masyarakat::class, 'submitted_by_id');
    }


    public function super_admin()
    {
        return $this->belongsTo(User::class, 'super_admin_id', 'id');
    }

     public function kepala_desa()
    {
        return $this->belongsTo(User::class, 'kepala_desa_id', 'id');
    }
    
    public function masyarakat()
    {
        return $this->belongsTo(Masyarakat::class, 'masyarakat_id', 'id');
    }
   
}
