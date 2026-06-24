<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{

public function login(Request $request)
{
    $validated = $request->validate([
        'nik' => [
            'required',
            'string',
            'min:8',
            'max:20',
            'regex:/^[0-9]+$/'
        ],
        'email' => 'required|string|email|max:255',
        'password' => [
            'required',
            'string',
            'min:6',
            'regex:/^[A-Za-z0-9@._-]+$/'
        ],
    ]);

    $user = User::where('nik', $validated['nik'])
        ->where('email', $validated['email'])
        ->first();

    if (!$user || !Hash::check($validated['password'], $user->password)) {
        return response()->json([
            'success' => false,
            'message' => 'NIK, Email, atau password salah',
        ], 401);
    }

    $allowedRoles = ['super_admin', 'kepala_desa', 'perangkat_desa', 'rt'];

    if (!in_array($user->role, $allowedRoles)) {
        return response()->json([
            'success' => false,
            'message' => 'Akses ditolak',
        ], 403);
    }

    $user->tokens()->delete();

    switch ($user->role) {
        case 'super_admin':
            $abilities = ['super_admin'];
            break;
        case 'perangkat_desa':
            $abilities = ['perangkat_desa'];
            break;
        case 'kepala_desa':
            $abilities = ['kepala_desa'];
            break;
        case 'rt':
            $abilities = ['rt'];
            break;
        default:
            return response()->json([
                'success' => false,
                'message' => 'Role tidak dikenali',
            ], 403);
    }

    $token = $user->createToken('auth_token', $abilities)->plainTextToken;

    $roleMessages = [
        'super_admin' => 'Selamat datang Super Admin!',
        'kepala_desa' => 'Selamat datang Lurah !',
        'perangkat_desa' => 'Selamat Datang!',
        'rt' => 'Selamat datang Ketua RT!',
    ];

    return response()->json([
        'success' => true,
        'message' => $roleMessages[$user->role],
        'token' => $token,
        'user' => [
            'id' => $user->id,
            'nama' => $user->nama,
            'jabatan' => $user->jabatan,
            'email' => $user->email,
            'role' => $user->role,
        ],
    ]);
}


    // LOGOUT
    public function logout(Request $request)
    {
        $user = $request->user();
        $role = $user->role;

        // Hapus token saat logout
        $user->currentAccessToken()->delete();

        // Pesan khusus berdasarkan role
        $logoutMessages = [
            'super_admin' => 'Super Admin berhasil logout.',
            'kepala_desa' => 'Kepala Desa telah logout.',
            'perangkat_desa' => 'Perangkat Desa telah keluar dari sistem.',
            'rt' => 'RT berhasil logout, sampai jumpa!',
            'masyarakat' => 'Logout berhasil, terima kasih sudah menggunakan layanan!',
        ];

        $logoutMessage = $logoutMessages[$role] ?? 'Logout berhasil.';

        return response()->json([
            'success' => true,
            'message' => $logoutMessage,
        ]);
    }

    // GET PROFILE USER LOGIN
    public function me(Request $request)
    {
        return response()->json([
            'success' => true,
            'user' => $request->user()
        ]);
    }

}
