<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Masyarakat;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Carbon\Carbon;
use App\Mail\SendOtpMail;
use App\Mail\ForgotPasswordOtpMail;
use App\Models\EmailOtp;
use App\Models\PasswordResetOtp;

class MasyarakatAuthController extends Controller
{

public function register(Request $request)
{
    $validated = $request->validate(
        [
            'nama' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:masyarakats',
            'password' => [
                'required',
                'string',
                'min:6',
                'regex:/^[A-Za-z0-9@._-]+$/'
            ],
            'nik' => 'required|string|min:16|max:16|unique:masyarakats',
        ],
        [
            'nama.required' => 'Nama wajib diisi.',
            'nama.string' => 'Nama harus berupa teks.',
            'nama.max' => 'Nama maksimal 255 karakter.',

            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'email.unique' => 'Email sudah terdaftar.',

            'password.required' => 'Password wajib diisi.',
            'password.min' => 'Password minimal 6 karakter.',
            'password.regex' => 'Password hanya boleh huruf, angka, dan simbol @ . _ -',

            'nik.required' => 'NIK wajib diisi.',
            'nik.min' => 'NIK harus 16 karakter.',
            'nik.max' => 'NIK harus 16 karakter.',
            'nik.unique' => 'NIK sudah terdaftar.',
        ]
    );

    // 🔥 buat akun masyarakat dulu (tapi belum verifikasi email)
    $masyarakat = Masyarakat::create([
        'nama' => $validated['nama'],
        'email' => $validated['email'],
        'password' => Hash::make($validated['password']),
        'nik' => $validated['nik'],
        'status_verifikasi' => 'pending',
        'email_verified_at' => null
    ]);

    // 🔥 Generate OTP random 6 digit
    $otp = rand(100000, 999999);

    // expired 5 menit
    $expiredAt = now()->addMinutes(5);

    // hapus OTP lama jika ada
    EmailOtp::where('email', $validated['email'])->delete();

    // simpan OTP ke database
    EmailOtp::create([
        'email' => $validated['email'],
        'otp' => $otp,
        'expired_at' => $expiredAt,
        'is_used' => false
    ]);

    // kirim OTP ke email
    Mail::to($validated['email'])->send(
        new SendOtpMail($validated['nama'], $otp, $expiredAt->format('d-m-Y H:i:s'))
    );

    return response()->json([
        'success' => true,
        'message' => 'Registrasi berhasil. OTP telah dikirim ke email untuk verifikasi.',
        'data' => [
            'id' => $masyarakat->id,
            'email' => $masyarakat->email,
            'expired_at' => $expiredAt
        ]
    ], 201);
}

public function verifyOtp(Request $request)
{
    $validated = $request->validate(
        [
            'email' => 'required|string|email|max:255',
            'otp' => 'required|string|size:6',
        ],
        [
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'otp.required' => 'OTP wajib diisi.',
            'otp.size' => 'OTP harus 6 digit.',
        ]
    );

    // cari masyarakat berdasarkan email
    $masyarakat = Masyarakat::where('email', $validated['email'])->first();

    if (!$masyarakat) {
        return response()->json([
            'success' => false,
            'message' => 'Email tidak ditemukan.'
        ], 404);
    }

    // kalau sudah diverifikasi
    if ($masyarakat->email_verified_at != null) {
        return response()->json([
            'success' => false,
            'message' => 'Email sudah diverifikasi sebelumnya.'
        ], 400);
    }

    // cek otp
    $otpData = EmailOtp::where('email', $validated['email'])
        ->where('otp', $validated['otp'])
        ->where('is_used', false)
        ->first();

    if (!$otpData) {
        return response()->json([
            'success' => false,
            'message' => 'OTP salah atau tidak ditemukan.'
        ], 400);
    }

    // cek expired
    if (now()->greaterThan($otpData->expired_at)) {
        return response()->json([
            'success' => false,
            'message' => 'OTP sudah kadaluarsa. Silakan minta OTP baru.'
        ], 400);
    }

    // update masyarakat -> email verified
    $masyarakat->update([
        'email_verified_at' => now()
    ]);

    // tandai otp sudah dipakai
    $otpData->update([
        'is_used' => true
    ]);

    return response()->json([
        'success' => true,
        'message' => 'OTP berhasil diverifikasi. Email berhasil diverifikasi.',
        'data' => [
            'id' => $masyarakat->id,
            'nama' => $masyarakat->nama,
            'email' => $masyarakat->email,
            'email_verified_at' => $masyarakat->email_verified_at
        ]
    ], 200);
}

public function resendOtp(Request $request)
{
    $validated = $request->validate(
        [
            'email' => 'required|string|email|max:255',
        ],
        [
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
        ]
    );

    // cek masyarakat ada atau tidak
    $masyarakat = Masyarakat::where('email', $validated['email'])->first();

    if (!$masyarakat) {
        return response()->json([
            'success' => false,
            'message' => 'Email tidak ditemukan.'
        ], 404);
    }

    // kalau sudah verified, tidak perlu resend
    if ($masyarakat->email_verified_at != null) {
        return response()->json([
            'success' => false,
            'message' => 'Email sudah diverifikasi.'
        ], 400);
    }

    // hapus otp lama
    EmailOtp::where('email', $validated['email'])->delete();

    // generate otp baru
    $otp = rand(100000, 999999);
    $expiredAt = now()->addMinutes(5);

    // simpan otp baru
    EmailOtp::create([
        'email' => $validated['email'],
        'otp' => $otp,
        'expired_at' => $expiredAt,
        'is_used' => false
    ]);

    // kirim otp baru
    Mail::to($validated['email'])->send(
        new SendOtpMail($masyarakat->nama, $otp, $expiredAt->format('d-m-Y H:i:s'))
    );

    return response()->json([
        'success' => true,
        'message' => 'OTP baru berhasil dikirim ke email.',
        'data' => [
            'email' => $validated['email'],
            'expired_at' => $expiredAt
        ]
    ], 200);
}

public function sendForgotPasswordOtp(Request $request)
{
    $validated = $request->validate(
        [
            'email' => 'required|string|email|max:255',
        ],
        [
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
        ]
    );

    // cek masyarakat
    $masyarakat = Masyarakat::where('email', $validated['email'])->first();

    if (!$masyarakat) {
        return response()->json([
            'success' => false,
            'message' => 'Email tidak ditemukan.'
        ], 404);
    }

    // 🔥 hapus OTP yang expired atau sudah dipakai
PasswordResetOtp::where('email', $validated['email'])
    ->where(function ($query) {
        $query->where('expires_at', '<', now())
              ->orWhere('is_used', true);
    })
    ->delete();

    // hapus OTP reset lama jika ada
    PasswordResetOtp::where('email', $validated['email'])->delete();

    // generate OTP 6 digit
    $otp = rand(100000, 999999);

    // expired 10 menit
    $expiredAt = now()->addMinutes(10);

    // simpan OTP reset
    PasswordResetOtp::create([
        'email' => $validated['email'],
        'otp' => $otp,
        'expires_at' => $expiredAt,
        'is_used' => false
    ]);

    // kirim email
    Mail::to($validated['email'])->send(
        new ForgotPasswordOtpMail ($otp)
    );

    return response()->json([
        'success' => true,
        'message' => 'Kode OTP reset password telah dikirim ke email.',
        'data' => [
            'email' => $validated['email'],
            'expired_at' => $expiredAt
        ]
    ], 200);
}

public function resetPassword(Request $request)
{
    $validated = $request->validate(
        [
            'email' => 'required|string|email|max:255',
            'otp' => 'required|string|size:6',
            'password' => [
                'required',
                'string',
                'min:6',
                'regex:/^[A-Za-z0-9@._-]+$/',
                'confirmed'
            ],
        ],
        [
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'otp.required' => 'OTP wajib diisi.',
            'otp.size' => 'OTP harus 6 digit.',
            'password.required' => 'Password wajib diisi.',
            'password.min' => 'Password minimal 6 karakter.',
            'password.regex' => 'Password hanya boleh huruf, angka, dan simbol @ . _ -',
            'password.confirmed' => 'Konfirmasi password tidak cocok.',
        ]
    );

    // cek masyarakat
    $masyarakat = Masyarakat::where('email', $validated['email'])->first();

    if (!$masyarakat) {
        return response()->json([
            'success' => false,
            'message' => 'Email tidak ditemukan.'
        ], 404);
    }

    // cek OTP
    $otpData = PasswordResetOtp::where('email', $validated['email'])
        ->where('otp', $validated['otp'])
        ->where('is_used', false)
        ->first();

    if (!$otpData) {
        return response()->json([
            'success' => false,
            'message' => 'OTP salah atau tidak ditemukan.'
        ], 400);
    }

    // cek expired
    if (now()->greaterThan($otpData->expires_at)) {
        return response()->json([
            'success' => false,
            'message' => 'OTP sudah kadaluarsa. Silakan minta OTP baru.'
        ], 400);
    }

    // update password
    $masyarakat->update([
        'password' => Hash::make($validated['password'])
    ]);

    // tandai OTP sudah dipakai
    $otpData->update([
        'is_used' => true
    ]);

    return response()->json([
        'success' => true,
        'message' => 'Password berhasil direset. Silakan login dengan password baru.'
    ], 200);
}

public function resendForgotPasswordOtp(Request $request)
{
    $validated = $request->validate(
        [
            'email' => 'required|string|email|max:255',
        ],
        [
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
        ]
    );

    // cek apakah masyarakat ada
    $masyarakat = Masyarakat::where('email', $validated['email'])->first();

    if (!$masyarakat) {
        return response()->json([
            'success' => false,
            'message' => 'Email tidak ditemukan.'
        ], 404);
    }

        $existingOtp = PasswordResetOtp::where('email', $validated['email'])
        ->where('created_at', '>=', now()->subMinute())
        ->first();

    if ($existingOtp) {
        return response()->json([
            'success' => false,
            'message' => 'Tunggu 1 menit sebelum meminta OTP baru.'
        ], 429);
    }

    // 🔥 hapus OTP lama (baik expired atau belum)
    PasswordResetOtp::where('email', $validated['email'])->delete();

    // generate OTP baru
    $otp = rand(100000, 999999);

    // expired 10 menit dari sekarang
    $expiredAt = now()->addMinutes(10);

    // simpan OTP baru
    PasswordResetOtp::create([
        'email' => $validated['email'],
        'otp' => $otp,
        'expires_at' => $expiredAt,
        'is_used' => false
    ]);

    // kirim email ulang
    Mail::to($validated['email'])->send(
        new ForgotPasswordOtpMail($otp)
    );

    return response()->json([
        'success' => true,
        'message' => 'Kode OTP baru telah dikirim ulang ke email.',
        'data' => [
            'email' => $validated['email'],
            'expired_at' => $expiredAt
        ]
    ], 200);
}

public function login(Request $request)
{
    $validated = $request->validate([
        'nik' => ['required','string','size:16','regex:/^[0-9]+$/'],
        'email' => 'required|string|email|max:255',
        'password' => [
            'required',
            'string',
            'min:6',
            'regex:/^[A-Za-z0-9@._-]+$/'
        ],
    ]);

    $user = Masyarakat::where('nik', $validated['nik'])
        ->where('email', $validated['email'])
        ->first();

    if (!$user || !Hash::check($validated['password'], $user->password)) {
        return response()->json([
            'success' => false,
            'message' => 'NIK, Email, atau Password salah'
        ], 401);
    }

    // ✅ cek email sudah diverifikasi
    if ($user->email_verified_at == null) {
        return response()->json([
            'success' => false,
            'message' => 'Email belum diverifikasi. Silakan verifikasi OTP terlebih dahulu.'
        ], 403);
    }

    $user->tokens()->delete();

    $token = $user->createToken(
        'masyarakat_token',
        ['masyarakat']
    )->plainTextToken;

    return response()->json([
        'success' => true,
        'message' => 'Login berhasil',
        'token' => $token,
        'user' => [
            'id' => $user->id,
            'nama' => $user->nama,
            'email' => $user->email,
            'foto_profil' => $user->foto_profil,
            'status_verifikasi' => $user->status_verifikasi,
        ],
        'user_type' => 'masyarakat',
    ]);
}




// public function login(Request $request)
// {
//     $validated = $request->validate([
//         'nik' => 'required|string|size:16',
//         'email' => 'required|email',
//         'password' => 'required|string|min:6',
//     ]);

//     $user = Masyarakat::where('nik', $validated['nik'])
//         ->where('email', $validated['email'])
//         ->first();

//     if (!$user) {
//         return response()->json([
//             'success' => false,
//             'message' => 'NIK atau Email tidak ditemukan / tidak cocok'
//         ], 404);
//     }

//     if (!Hash::check($validated['password'], $user->password)) {
//         return response()->json([
//             'success' => false,
//             'message' => 'Password salah'
//         ], 401);
//     }

//     $token = $user->createToken('masyarakat_token')->plainTextToken;

//     return response()->json([
//         'success' => true,
//         'message' => 'Login berhasil',
//         'token' => $token,
//         'user' => [
//             'id' => $user->id,
//             'nama' => $user->nama,
//             'email' => $user->email,
//             'foto_profil' => $user->foto_profil,
//             'status_verifikasi' => $user->status_verifikasi
//         ],
//         'user_type' => 'masyarakat'
//     ]);
// }



    // LOGOUT
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logout masyarakat berhasil'
        ]);
    }

    // GET PROFILE
    public function me(Request $request)
    {
        return response()->json([
            'success' => true,
            'user' => $request->user()
        ]);
    }
}
