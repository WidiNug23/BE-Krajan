<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmailOtp extends Model
{
    use HasFactory;

    protected $table = 'email_otps';

    protected $fillable = [
        'email',
        'otp',
        'expired_at',
        'is_used'
    ];

    protected $casts = [
        'expired_at' => 'datetime',
        'is_used' => 'boolean'
    ];
}
