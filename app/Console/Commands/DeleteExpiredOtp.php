<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\EmailOtp;
use App\Models\PasswordResetOtp;

class DeleteExpiredOtp extends Command
{
    protected $signature = 'otp:delete-expired';
    protected $description = 'Hapus OTP yang sudah expired atau sudah dipakai';

    public function handle()
    {
        // email verification OTP (pakai expired_at)
        $deletedEmailOtp = EmailOtp::where('expired_at', '<', now())
            ->delete();

        // reset password OTP (pakai expires_at)
        $deletedResetOtp = PasswordResetOtp::where('expires_at', '<', now())
            ->delete();

        // hapus OTP yang sudah dipakai lebih dari 30 menit
        $deletedUsedEmail = EmailOtp::where('is_used', true)
            ->where('updated_at', '<', now()->subMinutes(30))
            ->delete();

        $deletedUsedReset = PasswordResetOtp::where('is_used', true)
            ->where('updated_at', '<', now()->subMinutes(30))
            ->delete();

        $totalDeleted = $deletedEmailOtp + $deletedResetOtp + $deletedUsedEmail + $deletedUsedReset;

        $this->info("Berhasil menghapus total OTP: " . $totalDeleted);
    }
}