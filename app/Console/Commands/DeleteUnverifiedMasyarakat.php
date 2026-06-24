<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Masyarakat;
use App\Models\EmailOtp;

class DeleteUnverifiedMasyarakat extends Command
{
    protected $signature = 'masyarakat:delete-unverified';
    protected $description = 'Hapus masyarakat yang belum verifikasi email setelah 1 hari';

    public function handle()
    {
        $expiredUsers = Masyarakat::whereNull('email_verified_at')
            ->where('created_at', '<', now()->subDay())
            ->get();

        foreach ($expiredUsers as $user) {
            // hapus otp terkait
            EmailOtp::where('email', $user->email)->delete();

            // hapus user
            $user->delete();
        }

        $this->info("Berhasil menghapus " . $expiredUsers->count() . " masyarakat yang belum verifikasi.");
    }
}
