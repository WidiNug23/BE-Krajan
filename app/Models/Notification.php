<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    use HasFactory;

    protected $table = 'notifications';

    protected $fillable = [
        // TARGET
        'user_id',
        'masyarakat_id',
        'role',

        // IDENTITAS SURAT
        'surat_type',
        'surat_id',

        // ISI
        'title',
        'message',

        // STATUS
        'is_read',
    ];

    protected $casts = [
        'is_read' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * ============================
     * RELASI (OPSIONAL)
     * ============================
     */

    // Notifikasi untuk RT (user spesifik)
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    } 
    
   public function masyarakat()
{
    return $this->belongsTo(Masyarakat::class, 'masyarakat_id', 'id');
}

}