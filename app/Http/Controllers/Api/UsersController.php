<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UsersController extends Controller
{

 public function countUsers(Request $request)
    {
        try {
            // ===============================
            // 1. VALIDASI TOKEN
            // ===============================
            $user = $request->user();

            if (
                !$user ||
                !(
                    $user->tokenCan('super_admin') ||
                    $user->tokenCan('kepala_desa') ||
                    $user->tokenCan('perangkat_desa') ||
                    $user->tokenCan('rt')
                )
            ) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized',
                ], 401);
            }

            // ===============================
            // 2. HITUNG DATA
            // ===============================
            $totalUsers = User::count();

            $totalSuperAdmin   = User::where('role', 'super_admin')->count();
            $totalKepalaDesa   = User::where('role', 'kepala_desa')->count();
            $totalPerangkat    = User::where('role', 'perangkat_desa')->count();
            $totalRT           = User::where('role', 'rt')->count();

            // ===============================
            // 3. RESPONSE
            // ===============================
            return response()->json([
                'success' => true,
                'data' => [
                    'total_users'       => $totalUsers,
                    'super_admin'       => $totalSuperAdmin,
                    'kepala_desa'       => $totalKepalaDesa,
                    'perangkat_desa'    => $totalPerangkat,
                    'rt'                => $totalRT,
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data users',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    
    public function updateProfileUsers(Request $request, $id)
    {
        $authUser = auth()->user();

        if (!$authUser) {
            return response()->json([
                'success' => false,
                'message' => 'Token tidak valid'
            ], 401);
        }

        // hanya boleh edit profil dirinya sendiri
        if ($authUser->id != $id) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak boleh mengedit profil orang lain'
            ], 403);
        }

        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak ditemukan'
            ], 404);
        }

       // ===============================
// VALIDASI
// ===============================
$request->validate([
    'nama' => 'required|string|max:255',
    'jabatan' => 'nullable|string|max:255',
    'no_hp' => 'nullable|string|max:20',

    'ttl' => 'required|string',
    'jenis_kelamin' => 'nullable|in:Laki-laki,Perempuan',
    'agama' => 'required|string',
    'kewarganegaraan' => 'required|string',
    'pendidikan' => 'required|string',
    'status_perkawinan' => 'nullable|in:Belum Menikah,Sudah Menikah,Cerai',
    'alamat' => 'required|string',
    'nip' => 'nullable|string|max:18',

    // dokumen
    'foto_profil' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
    'file_ktp' => 'nullable|mimes:jpg,jpeg,png,pdf|max:4096',
    'file_kk' => 'nullable|mimes:jpg,jpeg,png,pdf|max:4096',
    'ttd' => 'nullable|image|mimes:png,jpg,jpeg|max:2048',
]);

// ===============================
// UPDATE FIELD TEXT
// ===============================
$user->nama = $request->nama ?? $user->nama;
$user->jabatan = $request->jabatan ?? $user->jabatan;
$user->no_hp = $request->no_hp ?? $user->no_hp;

$user->ttl = $request->ttl ?? $user->ttl;
$user->jenis_kelamin = $request->jenis_kelamin ?? $user->jenis_kelamin;
$user->agama = $request->agama ?? $user->agama;
$user->kewarganegaraan = $request->kewarganegaraan ?? $user->kewarganegaraan;
$user->pendidikan = $request->pendidikan ?? $user->pendidikan;
$user->status_perkawinan = $request->status_perkawinan ?? $user->status_perkawinan;
$user->alamat = $request->alamat ?? $user->alamat;
$user->nip = $request->nip ?? $user->nip;


        // ===============================
        // FOLDER UPLOAD
        // ===============================
        $basePath = public_path('uploads');
        if (!file_exists($basePath)) {
            mkdir($basePath, 0777, true);
        }

        // ===========================================
        // 1. FOTO PROFIL
        // ===========================================
        if ($request->hasFile('foto_profil')) {

            $folder = $basePath . '/foto_profil_user';
            if (!file_exists($folder)) mkdir($folder, 0777, true);

            // hapus file lama
            if ($user->foto_profil) {
                $oldFile = str_replace(url('/'), public_path(), $user->foto_profil);
                if (file_exists($oldFile)) unlink($oldFile);
            }

            $filename = time() . '_' . $request->file('foto_profil')->getClientOriginalName();
            $request->file('foto_profil')->move($folder, $filename);

            $user->foto_profil = url('uploads/foto_profil_user/' . $filename);
        }

        // ===========================================
        // 2. FILE KTP
        // ===========================================
        if ($request->hasFile('file_ktp')) {

            $folder = $basePath . '/file_ktp_user';
            if (!file_exists($folder)) mkdir($folder, 0777, true);

            if ($user->file_ktp) {
                $oldFile = str_replace(url('/'), public_path(), $user->file_ktp);
                if (file_exists($oldFile)) unlink($oldFile);
            }

            $filename = time() . '_' . $request->file('file_ktp')->getClientOriginalName();
            $request->file('file_ktp')->move($folder, $filename);

            $user->file_ktp = url('uploads/file_ktp_user/' . $filename);
        }

        // ===========================================
        // 3. FILE KK
        // ===========================================
        if ($request->hasFile('file_kk')) {

            $folder = $basePath . '/file_kk_user';
            if (!file_exists($folder)) mkdir($folder, 0777, true);

            if ($user->file_kk) {
                $oldFile = str_replace(url('/'), public_path(), $user->file_kk);
                if (file_exists($oldFile)) unlink($oldFile);
            }

            $filename = time() . '_' . $request->file('file_kk')->getClientOriginalName();
            $request->file('file_kk')->move($folder, $filename);

            $user->file_kk = url('uploads/file_kk_user/' . $filename);
        }

        // ===========================================
        // 4. FILE TTD
        // ===========================================
        if ($request->hasFile('ttd')) {

            $folder = $basePath . '/ttd_user';
            if (!file_exists($folder)) mkdir($folder, 0777, true);

            // hapus TTD lama
            if ($user->ttd) {
                $oldFile = str_replace(url('/'), public_path(), $user->ttd);
                if (file_exists($oldFile)) unlink($oldFile);
            }

            $filename = time() . '_ttd_' . $request->file('ttd')->getClientOriginalName();
            $request->file('ttd')->move($folder, $filename);

            $user->ttd = url('uploads/ttd_user/' . $filename);
        }


        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Profil berhasil diperbarui',
            'data' => $user
        ]);
    }

    public function getDetailUsers(Request $request, $id)
{
    // 🔹 Cek token (autentikasi sanctum)
    $authUser = $request->user();

    if (!$authUser) {
        return response()->json([
            'success' => false,
            'message' => 'Token tidak valid'
        ], 401);
    }

    // 🔹 Validasi: hanya bisa melihat profil dirinya sendiri
    if ($authUser->id != $id) {
        return response()->json([
            'success' => false,
            'message' => 'Anda tidak memiliki izin untuk melihat data ini.'
        ], 403);
    }

    // 🔹 Ambil data user (role: super_admin, kepala_desa, perangkat_desa, rt)
    $data = User::find($id);

    if (!$data) {
        return response()->json([
            'success' => false,
            'message' => 'Data user tidak ditemukan'
        ], 404);
    }

    // 🔹 Konversi path file menjadi URL penuh
    $data->foto_profil = $data->foto_profil ? url($data->foto_profil) : null;
    $data->file_ktp = $data->file_ktp ? url($data->file_ktp) : null;
    $data->file_kk = $data->file_kk ? url($data->file_kk) : null;
    $data->ttd = $data->ttd ? url($data->ttd) : null;

    return response()->json([
        'success' => true,
        'message' => 'Data profil user berhasil diambil',
        'data' => $data
    ]);
}

//==================== MANAGEMENT RT DATAS ============================
public function getRT(Request $request)
{
    // ============================
    // 🔐 AUTH CHECK
    // ============================
    $user = auth()->user();

    if (!$user) {
        return response()->json([
            'success' => false,
            'message' => 'Unauthorized'
        ], 401);
    }

    // ============================
    // 🔒 ROLE VALIDATION
    // ============================
    if (!in_array($user->role, ['super_admin', 'perangkat_desa'])) {
        return response()->json([
            'success' => false,
            'message' => 'Akses ditolak'
        ], 403);
    }

    // ============================
    // 📥 GET DATA RT (LIST)
    // ============================
    $data = User::where('role', 'rt')
        ->orderBy('no_rt', 'asc')
        ->get()
        ->map(function ($rt) {
            return [
                'id'         => $rt->id,
                'nama'       => $rt->nama,
                'email'      => $rt->email,
                'nik'        => $rt->nik,
                'jabatan'    => $rt->jabatan,
                'no_rt'      => $rt->no_rt,
                'no_hp'      => $rt->no_hp,
                'foto_profil' => $rt->foto_profil,
                'created_at' => $rt->created_at,
                'updated_at' => $rt->updated_at,
            ];
        });

    return response()->json([
        'success' => true,
        'count'   => $data->count(),
        'data'    => $data
    ]);
}

public function addRT(Request $request)
{
    // ============================
    // 🔐 AUTH CHECK
    // ============================
    $user = auth()->user();

    if (!$user) {
        return response()->json([
            'success' => false,
            'message' => 'Unauthorized'
        ], 401);
    }

    // ============================
    // 🔒 ROLE VALIDATION
    // ============================
    if (!in_array($user->role, ['super_admin', 'perangkat_desa'])) {
        return response()->json([
            'success' => false,
            'message' => 'Akses ditolak'
        ], 403);
    }

    // ============================
    // ✅ VALIDASI INPUT
    // ============================
    $validated = $request->validate([
        'nama'     => 'required|string|max:255',
        'email'    => [
            'required',
            'email',
            'max:255',
            'unique:users,email',
            'regex:/^[a-zA-Z0-9._%+-]+@gmail\.com$/'
        ],
         'password' => [
                'required',
                'string',
                'min:6',
                'regex:/^[A-Za-z0-9@._-]+$/'
            ],
        'nik'      => 'required|string|min:16|max:16|unique:users,nik',
        'nip'      => 'nullable|string|min:8|max:20|unique:users,nip',
        'no_rt'    => 'required|string|min:1|max:3',
        'jabatan'  => 'required|string|max:255',
    ], [
        'email.regex' => 'Email harus menggunakan domain @gmail.com',
        'password.regex' => 'Password hanya boleh huruf, angka, dan simbol @ . _ -',
    ]);


    // ============================
    // ➕ CREATE RT
    // ============================
    $rt = User::create([
        'nama'     => $validated['nama'],
        'email'    => $validated['email'],
        'password' => Hash::make($validated['password']),
        'nik'      => $validated['nik'],
        'nip'      => $validated['nip'],
        'role'     => 'rt',
        'no_rt'    => $validated['no_rt'],
        'jabatan'  => $validated['jabatan'],
    ]);

    // ============================
    // 📤 RESPONSE
    // ============================
    return response()->json([
        'success' => true,
        'message' => 'Data RT berhasil ditambahkan',
        'data' => [
            'id'       => $rt->id,
            'nama'     => $rt->nama,
            'email'    => $rt->email,
            'nik'      => $rt->nik,
            'nip'      => $rt->nip,
            'role'     => $rt->role,
            'no_rt'    => $rt->no_rt,
            'jabatan'  => $rt->jabatan,
        ]
    ], 201);
}

public function getDetailRT(Request $request, $id)
{
    // ============================
    // 🔐 AUTH CHECK
    // ============================
    $authUser = auth()->user();

    if (!$authUser) {
        return response()->json([
            'success' => false,
            'message' => 'Unauthorized'
        ], 401);
    }

    // ============================
    // 🔒 ROLE VALIDATION
    // ============================
    if (!in_array($authUser->role, ['super_admin', 'perangkat_desa'])) {
        return response()->json([
            'success' => false,
            'message' => 'Akses ditolak'
        ], 403);
    }

    // ============================
    // 🔎 GET DATA RT
    // ============================
    $rt = User::where('role', 'rt')->find($id);

    if (!$rt) {
        return response()->json([
            'success' => false,
            'message' => 'Data RT tidak ditemukan'
        ], 404);
    }

    // ============================
    // 📤 RESPONSE DETAIL LENGKAP
    // ============================
    return response()->json([
        'success' => true,
        'data' => [
            'id'                => $rt->id,
            'nama'              => $rt->nama,
            'email'             => $rt->email,
            'nik'               => $rt->nik,
            'role'              => $rt->role,
            'jabatan'           => $rt->jabatan,
            'no_rt'             => $rt->no_rt,
            'no_hp'             => $rt->no_hp,

            'ttl'               => $rt->ttl,
            'jenis_kelamin'     => $rt->jenis_kelamin,
            'agama'             => $rt->agama,
            'kewarganegaraan'   => $rt->kewarganegaraan,
            'pendidikan'        => $rt->pendidikan,
            'status_perkawinan' => $rt->status_perkawinan,
            'alamat'            => $rt->alamat,
            'nip'               => $rt->nip,

            // DOKUMEN
            'foto_profil' => $rt->foto_profil,
            'file_ktp'    => $rt->file_ktp,
            'file_kk'     => $rt->file_kk,
            'ttd'     => $rt->ttd,

            'created_at'  => $rt->created_at,
            'updated_at'  => $rt->updated_at,
        ]
    ]);
}

public function updateRTByAdmin(Request $request, $id)
{
    $authUser = auth()->user();

    if (!$authUser) {
        return response()->json([
            'success' => false,
            'message' => 'Token tidak valid'
        ], 401);
    }

    // ===============================
    // 🔐 ROLE VALIDATION
    // ===============================
    if (!in_array($authUser->role, ['super_admin', 'perangkat_desa'])) {
        return response()->json([
            'success' => false,
            'message' => 'Akses ditolak'
        ], 403);
    }

    // ===============================
    // 🔎 AMBIL DATA RT
    // ===============================
    $user = User::where('role', 'rt')->find($id);

    if (!$user) {
        return response()->json([
            'success' => false,
            'message' => 'Data RT tidak ditemukan'
        ], 404);
    }

    // ===============================
    // VALIDASI
    // ===============================
    $request->validate([
        'nama' => 'required|string|max:255',
        'jabatan' => 'nullable|string|max:255',
        'no_rt' => 'nullable|string|max:255',
        'no_hp' => 'nullable|string|max:20',

        'ttl' => 'required|string',
        'jenis_kelamin' => 'nullable|in:Laki-laki,Perempuan',
        'agama' => 'required|string',
        'kewarganegaraan' => 'required|string',
        'pendidikan' => 'required|string',
        'status_perkawinan' => 'nullable|in:Belum Menikah,Sudah Menikah,Cerai',
        'alamat' => 'required|string',
        'nip' => 'nullable|string|max:18',

        // dokumen
        'foto_profil' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        'file_ktp' => 'nullable|mimes:jpg,jpeg,png,pdf|max:4096',
        'file_kk' => 'nullable|mimes:jpg,jpeg,png,pdf|max:4096',
        'ttd' => 'nullable|image|mimes:png,jpg,jpeg|max:2048',
    ]);

    // ===============================
    // 🔐 VALIDASI EMAIL (SUPER ADMIN SAJA + GMAIL)
    // ===============================
    if ($request->has('email')) {

        // selain super_admin dilarang ubah email
        if ($authUser->role !== 'super_admin') {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki izin mengubah email'
            ], 403);
        }

        // validasi email khusus gmail
        $request->validate([
            'email' => [
                'required',
                'email',
                'regex:/^[a-zA-Z0-9._%+-]+@gmail\.com$/',
                'unique:users,email,' . $user->id
            ]
        ], [
            'email.regex' => 'Email harus menggunakan domain @gmail.com'
        ]);

        $user->email = $request->email;
    }

// ===============================
    // 🔐 VALIDASI & UPDATE PASSWORD (SUPER ADMIN SAJA)
    // ===============================
    if ($request->filled('password')) {
        
        // 1. Cek izin
        if ($authUser->role !== 'super_admin') {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki izin mengubah password'
            ], 403);
        }

        // 2. Validasi (Perhatikan letak kurung siku array untuk custom message)
        $request->validate([
            'password' => [
                'required',
                'string',
                'min:6',
                'regex:/^[A-Za-z0-9@._-]+$/',
                'confirmed' // Membutuhkan input password_confirmation dari frontend
            ]
        ], [
            // Parameter kedua untuk custom messages
            'password.regex' => 'Password hanya boleh huruf, angka, dan simbol @ . _ -',
            'password.min' => 'Password minimal harus 6 karakter',
            'password.confirmed' => 'Konfirmasi password tidak cocok'
        ]);

        // 3. Update password jika lolos validasi
        $user->password = Hash::make($request->password);
    }

// ===============================
// 🔐 UPDATE PASSWORD (SUPER ADMIN SAJA)
// ===============================
if ($request->filled('password') && $authUser->role === 'super_admin') {
    $user->password = Hash::make($request->password);
}


    // ===============================
    // UPDATE FIELD TEXT
    // ===============================
    $user->nama = $request->nama;
    $user->jabatan = $request->jabatan;
    $user->no_hp = $request->no_hp;

    $user->ttl = $request->ttl;
    $user->jenis_kelamin = $request->jenis_kelamin;
    $user->agama = $request->agama;
    $user->kewarganegaraan = $request->kewarganegaraan;
    $user->pendidikan = $request->pendidikan;
    $user->status_perkawinan = $request->status_perkawinan;
    $user->alamat = $request->alamat;

    // ===============================
    // FOLDER UPLOAD
    // ===============================
    $basePath = public_path('uploads');
    if (!file_exists($basePath)) {
        mkdir($basePath, 0777, true);
    }

    // ===========================================
    // FOTO PROFIL
    // ===========================================
    if ($request->hasFile('foto_profil')) {

        $folder = $basePath . '/foto_profil_user';
        if (!file_exists($folder)) mkdir($folder, 0777, true);

        if ($user->foto_profil) {
            $oldFile = str_replace(url('/'), public_path(), $user->foto_profil);
            if (file_exists($oldFile)) unlink($oldFile);
        }

        $filename = time() . '_' . $request->file('foto_profil')->getClientOriginalName();
        $request->file('foto_profil')->move($folder, $filename);

        $user->foto_profil = url('uploads/foto_profil_user/' . $filename);
    }

    // ===========================================
    // FILE KTP
    // ===========================================
    if ($request->hasFile('file_ktp')) {

        $folder = $basePath . '/file_ktp_user';
        if (!file_exists($folder)) mkdir($folder, 0777, true);

        if ($user->file_ktp) {
            $oldFile = str_replace(url('/'), public_path(), $user->file_ktp);
            if (file_exists($oldFile)) unlink($oldFile);
        }

        $filename = time() . '_' . $request->file('file_ktp')->getClientOriginalName();
        $request->file('file_ktp')->move($folder, $filename);

        $user->file_ktp = url('uploads/file_ktp_user/' . $filename);
    }

    // ===========================================
    // FILE KK
    // ===========================================
    if ($request->hasFile('file_kk')) {

        $folder = $basePath . '/file_kk_user';
        if (!file_exists($folder)) mkdir($folder, 0777, true);

        if ($user->file_kk) {
            $oldFile = str_replace(url('/'), public_path(), $user->file_kk);
            if (file_exists($oldFile)) unlink($oldFile);
        }

        $filename = time() . '_' . $request->file('file_kk')->getClientOriginalName();
        $request->file('file_kk')->move($folder, $filename);

        $user->file_kk = url('uploads/file_kk_user/' . $filename);
    }

    // ===========================================
    // FILE TTD
    // ===========================================
    if ($request->hasFile('ttd')) {

        $folder = $basePath . '/ttd_user';
        if (!file_exists($folder)) mkdir($folder, 0777, true);

        if ($user->ttd) {
            $oldFile = str_replace(url('/'), public_path(), $user->ttd);
            if (file_exists($oldFile)) unlink($oldFile);
        }

        $filename = time() . '_' . $request->file('ttd')->getClientOriginalName();
        $request->file('ttd')->move($folder, $filename);

        $user->ttd = url('uploads/ttd_user/' . $filename);
    }

    $user->save();

    return response()->json([
        'success' => true,
        'message' => 'Data RT berhasil diperbarui oleh admin',
        'data' => $user
    ]);
}

public function deleteRT(Request $request, $id)
{
    // ============================
    // 🔐 AUTH CHECK
    // ============================
    $authUser = auth()->user();

    if (!$authUser) {
        return response()->json([
            'success' => false,
            'message' => 'Unauthorized'
        ], 401);
    }

    // ============================
    // 🔒 ROLE VALIDATION
    // ============================
    if (!in_array($authUser->role, ['super_admin', 'perangkat_desa'])) {
        return response()->json([
            'success' => false,
            'message' => 'Akses ditolak'
        ], 403);
    }

    // ============================
    // 🔎 GET DATA RT
    // ============================
    $rt = User::where('role', 'rt')->find($id);

    if (!$rt) {
        return response()->json([
            'success' => false,
            'message' => 'Data RT tidak ditemukan'
        ], 404);
    }

    // ============================
    // 🧹 DELETE FILE (OPTIONAL)
    // ============================
    if ($rt->foto_profil && file_exists(public_path($rt->foto_profil))) {
        unlink(public_path($rt->foto_profil));
    }

    if ($rt->file_ktp && file_exists(public_path($rt->file_ktp))) {
        unlink(public_path($rt->file_ktp));
    }

    if ($rt->file_kk && file_exists(public_path($rt->file_kk))) {
        unlink(public_path($rt->file_kk));
    }

    if ($rt->ttd && file_exists(public_path($rt->ttd))) {
        unlink(public_path($rt->ttd));
    }

    // ============================
    // ❌ DELETE USER RT
    // ============================
    $rt->delete();

    // ============================
    // 📤 RESPONSE
    // ============================
    return response()->json([
        'success' => true,
        'message' => 'Data RT berhasil dihapus'
    ], 200);
}


//==================== MANAGEMENT PERANGKAT DESA DATAS ============================

public function getPerangkatDesa(Request $request)
{
    // ============================
    // 🔐 AUTH CHECK
    // ============================
    $authUser = auth()->user();

    if (!$authUser) {
        return response()->json([
            'success' => false,
            'message' => 'Unauthorized'
        ], 401);
    }

    // ============================
    // 🔒 ROLE VALIDATION
    // ============================
    if (!in_array($authUser->role, ['super_admin', 'perangkat_desa'])) {
        return response()->json([
            'success' => false,
            'message' => 'Akses ditolak'
        ], 403);
    }

    // ============================
    // 📥 GET DATA PERANGKAT DESA
    // ============================
    $data = User::where('role', 'perangkat_desa')
        ->orderBy('created_at', 'asc')
        ->get()
        ->map(function ($perangkat) {
            return [
                'id'         => $perangkat->id,
                'nama'       => $perangkat->nama,
                'email'      => $perangkat->email,
                'nik'        => $perangkat->nik,
                'jabatan'    => $perangkat->jabatan,
                'no_hp'      => $perangkat->no_hp,
                'foto_profil' => $perangkat->foto_profil,
                'created_at' => $perangkat->created_at,
                'updated_at' => $perangkat->updated_at,
            ];
        });

    return response()->json([
        'success' => true,
        'count'   => $data->count(),
        'data'    => $data
    ], 200);
}

  public function addPerangkatDesa(Request $request)
{
    // ============================
    // 🔐 AUTH CHECK
    // ============================
    $authUser = auth()->user();

    if (!$authUser) {
        return response()->json([
            'success' => false,
            'message' => 'Unauthorized'
        ], 401);
    }

    // ============================
    // 🔒 ROLE VALIDATION
    // ============================
    if ($authUser->role !== 'super_admin') {
        return response()->json([
            'success' => false,
            'message' => 'Akses ditolak'
        ], 403);
    }

    // ============================
    // ✅ VALIDASI INPUT
    // ============================
        $validated = $request->validate([
            'nama'     => 'required|string|max:255',
            'email'    => [
                'required',
                'email',
                'max:255',
                'unique:users,email',
                'regex:/^[a-zA-Z0-9._%+-]+@gmail\.com$/'
            ],
            'password' => [
                'required',
                'string',
                'min:6',
                'regex:/^[A-Za-z0-9@._-]+$/'
            ],
            'nik'      => 'required|string|min:16|max:16|unique:users,nik',
            'nip'      => 'nullable|string|min:18|max:20|unique:users,nip',
            'jabatan'  => 'required|string|max:255',
        ], [
            'email.regex' => 'Email harus menggunakan domain @gmail.com',
            'password.regex' => 'Password hanya boleh huruf, angka, dan simbol @ . _ -',
        ]);
        ;

    // ============================
    // ➕ CREATE PERANGKAT DESA
    // ============================
    $perangkat = User::create([
        'nama'     => $validated['nama'],
        'email'    => $validated['email'],
        'password' => Hash::make($validated['password']),
        'nik'      => $validated['nik'],
        'nip'      => $validated['nip'],
        'role'     => 'perangkat_desa',
        'jabatan'  => $validated['jabatan'],
    ]);

    // ============================
    // 📤 RESPONSE
    // ============================
    return response()->json([
        'success' => true,
        'message' => 'Data Perangkat Desa berhasil ditambahkan',
        'data' => [
            'id'      => $perangkat->id,
            'nama'    => $perangkat->nama,
            'email'   => $perangkat->email,
            'nik'     => $perangkat->nik,
             'nip'      => $perangkat->nip,
            'role'    => $perangkat->role,
            'jabatan' => $perangkat->jabatan,
        ]
    ], 201);
}

public function getDetailPerangkatDesa(Request $request, $id)
{
    // ============================
    // 🔐 AUTH CHECK
    // ============================
    $authUser = auth()->user();

    if (!$authUser) {
        return response()->json([
            'success' => false,
            'message' => 'Unauthorized'
        ], 401);
    }

    // ============================
    // 🔒 ROLE VALIDATION
    // ============================
    if (!in_array($authUser->role, ['super_admin', 'perangkat_desa'])) {
        return response()->json([
            'success' => false,
            'message' => 'Akses ditolak'
        ], 403);
    }

    // ============================
    // 🔎 GET DETAIL PERANGKAT DESA
    // ============================
    $perangkat = User::where('role', 'perangkat_desa')
        ->where('id', $id)
        ->first();

    if (!$perangkat) {
        return response()->json([
            'success' => false,
            'message' => 'Data Perangkat Desa tidak ditemukan'
        ], 404);
    }

    // ============================
    // 📤 RESPONSE DETAIL LENGKAP
    // ============================
    return response()->json([
        'success' => true,
        'data' => [
            'id'                => $perangkat->id,
            'nama'              => $perangkat->nama,
            'email'             => $perangkat->email,
            'nik'               => $perangkat->nik,
            'role'              => $perangkat->role,
            'jabatan'           => $perangkat->jabatan,
            'no_hp'             => $perangkat->no_hp,

            'ttl'               => $perangkat->ttl,
            'jenis_kelamin'     => $perangkat->jenis_kelamin,
            'agama'             => $perangkat->agama,
            'kewarganegaraan'   => $perangkat->kewarganegaraan,
            'pendidikan'        => $perangkat->pendidikan,
            'status_perkawinan' => $perangkat->status_perkawinan,
            'alamat'            => $perangkat->alamat,
            'nip'               => $perangkat->nip,

            // 📎 DOKUMEN
            'foto_profil' => $perangkat->foto_profil,
            'file_ktp'    => $perangkat->file_ktp,
            'file_kk'     => $perangkat->file_kk,

            'created_at' => $perangkat->created_at,
            'updated_at' => $perangkat->updated_at,
        ]
    ], 200);
}


public function updatePerangkatDesaByAdmin(Request $request, $id)
{
    $authUser = auth()->user();

    if (!$authUser) {
        return response()->json([
            'success' => false,
            'message' => 'Token tidak valid'
        ], 401);
    }

    // ===============================
    // 🔐 ROLE VALIDATION
    // ===============================
    if (!in_array($authUser->role, ['super_admin', 'perangkat_desa'])) {
        return response()->json([
            'success' => false,
            'message' => 'Akses ditolak'
        ], 403);
    }

    // ===============================
    // 🔎 AMBIL DATA PERANGKAT DESA
    // ===============================
    $user = User::where('role', 'perangkat_desa')
        ->where('id', $id)
        ->first();

    if (!$user) {
        return response()->json([
            'success' => false,
            'message' => 'Data Perangkat Desa tidak ditemukan'
        ], 404);
    }

    // ===============================
    // VALIDASI
    // ===============================
    $request->validate([
        'nama' => 'required|string|max:255',
        'jabatan' => 'nullable|string|max:255',
        'no_hp' => 'nullable|string|max:20',

        'ttl' => 'required|string',
        'jenis_kelamin' => 'nullable|in:Laki-laki,Perempuan',
        'agama' => 'required|string',
        'kewarganegaraan' => 'required|string',
        'pendidikan' => 'required|string',
        'status_perkawinan' => 'nullable|in:Belum Menikah,Sudah Menikah,Cerai',
        'alamat' => 'required|string',
        'nip' => 'nullable|string|max:18',

        // dokumen
        'foto_profil' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        'file_ktp' => 'nullable|mimes:jpg,jpeg,png,pdf|max:4096',
        'file_kk' => 'nullable|mimes:jpg,jpeg,png,pdf|max:4096',
    ]);

    // ===============================
// 🔐 VALIDASI EMAIL (SUPER ADMIN SAJA + GMAIL)
// ===============================
if ($request->has('email')) {

    // selain super_admin dilarang ubah email
    if ($authUser->role !== 'super_admin') {
        return response()->json([
            'success' => false,
            'message' => 'Anda tidak memiliki izin mengubah email'
        ], 403);
    }

    // validasi email khusus gmail
    $request->validate([
        'email' => [
            'required',
            'email',
            'regex:/^[a-zA-Z0-9._%+-]+@gmail\.com$/',
            'unique:users,email,' . $user->id
        ]
    ], [
        'email.regex' => 'Email harus menggunakan domain @gmail.com'
    ]);

    $user->email = $request->email;
}

   // ===============================
    // 🔐 VALIDASI & UPDATE PASSWORD (SUPER ADMIN SAJA)
    // ===============================
    if ($request->filled('password')) {
        
        // 1. Cek izin
        if ($authUser->role !== 'super_admin') {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki izin mengubah password'
            ], 403);
        }

        // 2. Validasi (Perhatikan letak kurung siku array untuk custom message)
        $request->validate([
            'password' => [
                'required',
                'string',
                'min:6',
                'regex:/^[A-Za-z0-9@._-]+$/',
                'confirmed' // Membutuhkan input password_confirmation dari frontend
            ]
        ], [
            // Parameter kedua untuk custom messages
            'password.regex' => 'Password hanya boleh huruf, angka, dan simbol @ . _ -',
            'password.min' => 'Password minimal harus 6 karakter',
            'password.confirmed' => 'Konfirmasi password tidak cocok'
        ]);

        // 3. Update password jika lolos validasi
        $user->password = Hash::make($request->password);
    }

// ===============================
// 🔐 UPDATE PASSWORD (SUPER ADMIN SAJA)
// ===============================
if ($request->filled('password') && $authUser->role === 'super_admin') {
    $user->password = Hash::make($request->password);
}


    // ===============================
    // UPDATE FIELD TEXT
    // ===============================
    $user->nama = $request->nama;
    $user->jabatan = $request->jabatan;
    $user->no_hp = $request->no_hp;

    $user->ttl = $request->ttl;
    $user->jenis_kelamin = $request->jenis_kelamin;
    $user->agama = $request->agama;
    $user->kewarganegaraan = $request->kewarganegaraan;
    $user->pendidikan = $request->pendidikan;
    $user->status_perkawinan = $request->status_perkawinan;
    $user->alamat = $request->alamat;

    // ===============================
    // FOLDER UPLOAD
    // ===============================
    $basePath = public_path('uploads');
    if (!file_exists($basePath)) {
        mkdir($basePath, 0777, true);
    }

    // ===============================
    // FOTO PROFIL
    // ===============================
    if ($request->hasFile('foto_profil')) {
        $folder = $basePath . '/foto_profil_user';
        if (!file_exists($folder)) mkdir($folder, 0777, true);

        if ($user->foto_profil) {
            $oldFile = str_replace(url('/'), public_path(), $user->foto_profil);
            if (file_exists($oldFile)) unlink($oldFile);
        }

        $filename = time().'_'.$request->file('foto_profil')->getClientOriginalName();
        $request->file('foto_profil')->move($folder, $filename);
        $user->foto_profil = url('uploads/foto_profil_user/'.$filename);
    }

    // ===============================
    // FILE KTP
    // ===============================
    if ($request->hasFile('file_ktp')) {
        $folder = $basePath . '/file_ktp_user';
        if (!file_exists($folder)) mkdir($folder, 0777, true);

        if ($user->file_ktp) {
            $oldFile = str_replace(url('/'), public_path(), $user->file_ktp);
            if (file_exists($oldFile)) unlink($oldFile);
        }

        $filename = time().'_'.$request->file('file_ktp')->getClientOriginalName();
        $request->file('file_ktp')->move($folder, $filename);
        $user->file_ktp = url('uploads/file_ktp_user/'.$filename);
    }

    // ===============================
    // FILE KK
    // ===============================
    if ($request->hasFile('file_kk')) {
        $folder = $basePath . '/file_kk_user';
        if (!file_exists($folder)) mkdir($folder, 0777, true);

        if ($user->file_kk) {
            $oldFile = str_replace(url('/'), public_path(), $user->file_kk);
            if (file_exists($oldFile)) unlink($oldFile);
        }

        $filename = time().'_'.$request->file('file_kk')->getClientOriginalName();
        $request->file('file_kk')->move($folder, $filename);
        $user->file_kk = url('uploads/file_kk_user/'.$filename);
    }

    $user->save();

    return response()->json([
        'success' => true,
        'message' => 'Data Perangkat Desa berhasil diperbarui',
        'data' => $user
    ]);
}


public function deletePerangkatDesa(Request $request, $id)
{
    // ============================
    // 🔐 AUTH CHECK
    // ============================
    $authUser = auth()->user();

    if (!$authUser) {
        return response()->json([
            'success' => false,
            'message' => 'Unauthorized'
        ], 401);
    }

    // ============================
    // 🔒 ROLE VALIDATION
    // ============================
    if (!in_array($authUser->role, ['super_admin', 'perangkat_desa'])) {
        return response()->json([
            'success' => false,
            'message' => 'Akses ditolak'
        ], 403);
    }

    // ============================
    // 🔎 GET DATA PERANGKAT DESA
    // ============================
    $user = User::where('role', 'perangkat_desa')
        ->where('id', $id)
        ->first();

    if (!$user) {
        return response()->json([
            'success' => false,
            'message' => 'Data Perangkat Desa tidak ditemukan'
        ], 404);
    }

    // ============================
    // 🧹 DELETE FILE (AMAN)
    // ============================
    $files = ['foto_profil', 'file_ktp', 'file_kk'];

    foreach ($files as $field) {
        if ($user->$field) {
            $oldFile = str_replace(url('/'), public_path(), $user->$field);
            if (file_exists($oldFile)) {
                unlink($oldFile);
            }
        }
    }

    // ============================
    // ❌ DELETE USER
    // ============================
    $user->delete();

    // ============================
    // 📤 RESPONSE
    // ============================
    return response()->json([
        'success' => true,
        'message' => 'Data Perangkat Desa berhasil dihapus'
    ], 200);
}


//==================== MANAGEMENT KEPALA DESA DATAS ============================

public function getKepalaDesa(Request $request)
{
    // ============================
    // 🔐 AUTH CHECK
    // ============================
    $authUser = auth()->user();

    if (!$authUser) {
        return response()->json([
            'success' => false,
            'message' => 'Unauthorized'
        ], 401);
    }

    // ============================
    // 🔒 ROLE VALIDATION
    // ============================
    if (!in_array($authUser->role, ['super_admin', 'perangkat_desa'])) {
        return response()->json([
            'success' => false,
            'message' => 'Akses ditolak'
        ], 403);
    }

    // ============================
    // 📥 GET DATA KEPALA DESA
    // ============================
    $data = User::where('role', 'kepala_desa')
        ->orderBy('created_at', 'asc')
        ->get()
        ->map(function ($kepalaDesa) {
            return [
                'id'         => $kepalaDesa->id,
                'nama'       => $kepalaDesa->nama,
                'email'      => $kepalaDesa->email,
                'nik'        => $kepalaDesa->nik,
                'jabatan'    => $kepalaDesa->jabatan,
                'role'       => $kepalaDesa->role,
                'no_hp'      => $kepalaDesa->no_hp,
                'foto_profil' => $kepalaDesa->foto_profil,
                'created_at' => $kepalaDesa->created_at,
                'updated_at' => $kepalaDesa->updated_at,
            ];
        });

    // ============================
    // 📤 RESPONSE
    // ============================
    return response()->json([
        'success' => true,
        'count'   => $data->count(),
        'data'    => $data
    ], 200);
}

public function addKepalaDesa(Request $request)
{
    // ============================
    // 🔐 AUTH CHECK
    // ============================
    $authUser = auth()->user();

    if (!$authUser) {
        return response()->json([
            'success' => false,
            'message' => 'Unauthorized'
        ], 401);
    }

    // ============================
    // 🔒 ROLE VALIDATION
    // ============================
    if ($authUser->role !== 'super_admin') {
        return response()->json([
            'success' => false,
            'message' => 'Akses ditolak. Hanya Super Admin yang dapat menambahkan Kepala Desa.'
        ], 403);
    }

    // ============================
    // ✅ VALIDASI INPUT
    // ============================
    $validated = $request->validate([
        'nama'     => 'required|string|max:255',
        'email'    => [
            'required',
            'email',
            'max:255',
            'unique:users,email',
            'regex:/^[a-zA-Z0-9._%+-]+@gmail\.com$/'
        ],
         'password' => [
                'required',
                'string',
                'min:6',
                'regex:/^[A-Za-z0-9@._-]+$/'
            ],
        'nik'      => 'required|string|min:16|max:16|unique:users,nik',
        'nip'      => 'nullable|string|min:18|max:20|unique:users,nip',
        'jabatan'  => 'required|string|max:255',
    ], [
        'email.regex' => 'Email harus menggunakan domain @gmail.com',
        'password.regex' => 'Password hanya boleh huruf, angka, dan simbol @ . _ -',
    ]);


    // ============================
    // ➕ CREATE KEPALA DESA
    // ============================
    $kepalaDesa = User::create([
        'nama'     => $validated['nama'],
        'email'    => $validated['email'],
        'password' => Hash::make($validated['password']),
        'nik'      => $validated['nik'],
        'nip'      => $validated['nip'],
        'jabatan'  => $validated['jabatan'],
        'role'     => 'kepala_desa',
    ]);

    // ============================
    // 📤 RESPONSE
    // ============================
    return response()->json([
        'success' => true,
        'message' => 'Data Kepala Desa berhasil ditambahkan',
        'data' => [
            'id'      => $kepalaDesa->id,
            'nama'    => $kepalaDesa->nama,
            'email'   => $kepalaDesa->email,
            'nik'     => $kepalaDesa->nik,
            'nip'     => $kepalaDesa->nip,
            'role'    => $kepalaDesa->role,
            'jabatan' => $kepalaDesa->jabatan,
        ]
    ], 201);
}

public function getDetailKepalaDesa(Request $request, $id)
{
    // ============================
    // 🔐 AUTH CHECK
    // ============================
    $authUser = auth()->user();

    if (!$authUser) {
        return response()->json([
            'success' => false,
            'message' => 'Unauthorized'
        ], 401);
    }

    // ============================
    // 🔒 ROLE VALIDATION
    // ============================
    if (!in_array($authUser->role, ['super_admin', 'perangkat_desa'])) {
        return response()->json([
            'success' => false,
            'message' => 'Akses ditolak'
        ], 403);
    }

    // ============================
    // 🔎 GET DETAIL KEPALA DESA
    // ============================
    $kepalaDesa = User::where('role', 'kepala_desa')
        ->where('id', $id)
        ->first();

    if (!$kepalaDesa) {
        return response()->json([
            'success' => false,
            'message' => 'Data Kepala Desa tidak ditemukan'
        ], 404);
    }

    // ============================
    // 📤 RESPONSE DETAIL LENGKAP
    // ============================
    return response()->json([
        'success' => true,
        'data' => [
            'id'                => $kepalaDesa->id,
            'nama'              => $kepalaDesa->nama,
            'email'             => $kepalaDesa->email,
            'nik'               => $kepalaDesa->nik,
            'role'              => $kepalaDesa->role,
            'jabatan'           => $kepalaDesa->jabatan,
            'no_hp'             => $kepalaDesa->no_hp,

            'ttl'               => $kepalaDesa->ttl,
            'jenis_kelamin'     => $kepalaDesa->jenis_kelamin,
            'agama'             => $kepalaDesa->agama,
            'kewarganegaraan'   => $kepalaDesa->kewarganegaraan,
            'pendidikan'        => $kepalaDesa->pendidikan,
            'status_perkawinan' => $kepalaDesa->status_perkawinan,
            'alamat'            => $kepalaDesa->alamat,
            'nip'               => $kepalaDesa->nip,

            // 📎 DOKUMEN
            'foto_profil' => $kepalaDesa->foto_profil,
            'file_ktp'    => $kepalaDesa->file_ktp,
            'file_kk'     => $kepalaDesa->file_kk,
            'ttd'     => $kepalaDesa->ttd,

            'created_at' => $kepalaDesa->created_at,
            'updated_at' => $kepalaDesa->updated_at,
        ]
    ], 200);
}


public function updateKepalaDesaByAdmin(Request $request, $id)
{
    // ===============================
    // 🔐 AUTH CHECK
    // ===============================
    $authUser = auth()->user();

    if (!$authUser) {
        return response()->json([
            'success' => false,
            'message' => 'Token tidak valid'
        ], 401);
    }

    // ===============================
    // 🔒 ROLE VALIDATION (SUPER ADMIN ONLY)
    // ===============================
    if ($authUser->role !== 'super_admin') {
        return response()->json([
            'success' => false,
            'message' => 'Akses ditolak'
        ], 403);
    }

    // ===============================
    // 🔎 AMBIL DATA KEPALA DESA
    // ===============================
    $user = User::where('role', 'kepala_desa')
        ->where('id', $id)
        ->first();

    if (!$user) {
        return response()->json([
            'success' => false,
            'message' => 'Data Kepala Desa tidak ditemukan'
        ], 404);
    }

    // ===============================
    // ✅ VALIDASI
    // ===============================
    $request->validate([
        'nama' => 'required|string|max:255',
        'jabatan' => 'nullable|string|max:255',
        'no_hp' => 'nullable|string|max:20',

        'ttl' => 'required|string',
        'jenis_kelamin' => 'nullable|in:Laki-laki,Perempuan',
        'agama' => 'required|string',
        'kewarganegaraan' => 'required|string',
        'pendidikan' => 'required|string',
        'status_perkawinan' => 'nullable|in:Belum Menikah,Sudah Menikah,Cerai',
        'alamat' => 'required|string',
        'nip' => 'nullable|string|max:18',

        // dokumen
        'foto_profil' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        'file_ktp' => 'nullable|mimes:jpg,jpeg,png,pdf|max:4096',
        'file_kk' => 'nullable|mimes:jpg,jpeg,png,pdf|max:4096',
        'ttd' => 'nullable|image|mimes:png,jpg,jpeg|max:2048',
    ]);

    // ===============================
// 🔐 VALIDASI EMAIL (SUPER ADMIN SAJA + GMAIL)
// ===============================
if ($request->has('email')) {

    // selain super_admin dilarang ubah email
    if ($authUser->role !== 'super_admin') {
        return response()->json([
            'success' => false,
            'message' => 'Anda tidak memiliki izin mengubah email'
        ], 403);
    }

    // validasi email khusus gmail
    $request->validate([
        'email' => [
            'required',
            'email',
            'regex:/^[a-zA-Z0-9._%+-]+@gmail\.com$/',
            'unique:users,email,' . $user->id
        ]
    ], [
        'email.regex' => 'Email harus menggunakan domain @gmail.com'
    ]);

    $user->email = $request->email;
}

  // ===============================
    // 🔐 VALIDASI & UPDATE PASSWORD (SUPER ADMIN SAJA)
    // ===============================
    if ($request->filled('password')) {
        
        // 1. Cek izin
        if ($authUser->role !== 'super_admin') {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki izin mengubah password'
            ], 403);
        }

        // 2. Validasi (Perhatikan letak kurung siku array untuk custom message)
        $request->validate([
            'password' => [
                'required',
                'string',
                'min:6',
                'regex:/^[A-Za-z0-9@._-]+$/',
                'confirmed' // Membutuhkan input password_confirmation dari frontend
            ]
        ], [
            // Parameter kedua untuk custom messages
            'password.regex' => 'Password hanya boleh huruf, angka, dan simbol @ . _ -',
            'password.min' => 'Password minimal harus 6 karakter',
            'password.confirmed' => 'Konfirmasi password tidak cocok'
        ]);

        // 3. Update password jika lolos validasi
        $user->password = Hash::make($request->password);
    }

// ===============================
// 🔐 UPDATE PASSWORD (SUPER ADMIN SAJA)
// ===============================
if ($request->filled('password') && $authUser->role === 'super_admin') {
    $user->password = Hash::make($request->password);
}


    // ===============================
    // 📝 UPDATE FIELD TEXT
    // ===============================
    $user->nama = $request->nama;
    $user->jabatan = $request->jabatan;
    $user->no_hp = $request->no_hp;

    $user->ttl = $request->ttl;
    $user->jenis_kelamin = $request->jenis_kelamin;
    $user->agama = $request->agama;
    $user->kewarganegaraan = $request->kewarganegaraan;
    $user->pendidikan = $request->pendidikan;
    $user->status_perkawinan = $request->status_perkawinan;
    $user->alamat = $request->alamat;

    // ===============================
    // 📁 FOLDER UPLOAD
    // ===============================
    $basePath = public_path('uploads');
    if (!file_exists($basePath)) {
        mkdir($basePath, 0777, true);
    }

    // ===============================
    // 📸 FOTO PROFIL
    // ===============================
    if ($request->hasFile('foto_profil')) {
        $folder = $basePath.'/foto_profil_user';
        if (!file_exists($folder)) mkdir($folder, 0777, true);

        if ($user->foto_profil) {
            $oldFile = str_replace(url('/'), public_path(), $user->foto_profil);
            if (file_exists($oldFile)) unlink($oldFile);
        }

        $filename = time().'_'.$request->file('foto_profil')->getClientOriginalName();
        $request->file('foto_profil')->move($folder, $filename);
        $user->foto_profil = url('uploads/foto_profil_user/'.$filename);
    }

    // ===============================
    // 🪪 FILE KTP
    // ===============================
    if ($request->hasFile('file_ktp')) {
        $folder = $basePath.'/file_ktp_user';
        if (!file_exists($folder)) mkdir($folder, 0777, true);

        if ($user->file_ktp) {
            $oldFile = str_replace(url('/'), public_path(), $user->file_ktp);
            if (file_exists($oldFile)) unlink($oldFile);
        }

        $filename = time().'_'.$request->file('file_ktp')->getClientOriginalName();
        $request->file('file_ktp')->move($folder, $filename);
        $user->file_ktp = url('uploads/file_ktp_user/'.$filename);
    }

    // ===============================
    // 👪 FILE KK
    // ===============================
    if ($request->hasFile('file_kk')) {
        $folder = $basePath.'/file_kk_user';
        if (!file_exists($folder)) mkdir($folder, 0777, true);

        if ($user->file_kk) {
            $oldFile = str_replace(url('/'), public_path(), $user->file_kk);
            if (file_exists($oldFile)) unlink($oldFile);
        }

        $filename = time().'_'.$request->file('file_kk')->getClientOriginalName();
        $request->file('file_kk')->move($folder, $filename);
        $user->file_kk = url('uploads/file_kk_user/'.$filename);
    }

    // ===============================
    // 👪 FILE KK
    // ===============================
    if ($request->hasFile('ttd')) {
        $folder = $basePath.'/ttd_user';
        if (!file_exists($folder)) mkdir($folder, 0777, true);

        if ($user->ttd) {
            $oldFile = str_replace(url('/'), public_path(), $user->ttd);
            if (file_exists($oldFile)) unlink($oldFile);
        }

        $filename = time().'_'.$request->file('ttd')->getClientOriginalName();
        $request->file('ttd')->move($folder, $filename);
        $user->ttd = url('uploads/ttd_user/'.$filename);
    }

    $user->save();

    return response()->json([
        'success' => true,
        'message' => 'Data Kepala Desa berhasil diperbarui',
        'data' => $user
    ], 200);
}


public function deleteKepalaDesa(Request $request, $id)
{
    // ============================
    // 🔐 AUTH CHECK
    // ============================
    $authUser = auth()->user();

    if (!$authUser) {
        return response()->json([
            'success' => false,
            'message' => 'Unauthorized'
        ], 401);
    }

    // ============================
    // 🔒 ROLE VALIDATION (SUPER ADMIN ONLY)
    // ============================
    if ($authUser->role !== 'super_admin') {
        return response()->json([
            'success' => false,
            'message' => 'Akses ditolak'
        ], 403);
    }

    // ============================
    // 🔎 GET DATA KEPALA DESA
    // ============================
    $user = User::where('role', 'kepala_desa')
        ->where('id', $id)
        ->first();

    if (!$user) {
        return response()->json([
            'success' => false,
            'message' => 'Data Kepala Desa tidak ditemukan'
        ], 404);
    }

    // ============================
    // 🧹 DELETE FILE (AMAN)
    // ============================
    $files = ['foto_profil', 'file_ktp', 'file_kk', 'ttd'];

    foreach ($files as $field) {
        if ($user->$field) {
            $oldFile = str_replace(url('/'), public_path(), $user->$field);
            if (file_exists($oldFile)) {
                unlink($oldFile);
            }
        }
    }

    // ============================
    // ❌ DELETE USER
    // ============================
    $user->delete();

    // ============================
    // 📤 RESPONSE
    // ============================
    return response()->json([
        'success' => true,
        'message' => 'Data Kepala Desa berhasil dihapus'
    ], 200);
}


//==================== MANAGEMENT SUPER ADMIN DATAS ============================

public function getSuperAdmin(Request $request)
{
    // ============================
    // 🔐 AUTH CHECK
    // ============================
    $authUser = auth()->user();

    if (!$authUser) {
        return response()->json([
            'success' => false,
            'message' => 'Unauthorized'
        ], 401);
    }

    // ============================
    // 🔒 ROLE VALIDATION (SUPER ADMIN ONLY)
    // ============================
    if ($authUser->role !== 'super_admin') {
        return response()->json([
            'success' => false,
            'message' => 'Akses ditolak'
        ], 403);
    }

    // ============================
    // 📥 GET DATA SUPER ADMIN
    // ============================
    $data = User::where('role', 'super_admin')
        ->orderBy('created_at', 'asc')
        ->get()
        ->map(function ($superAdmin) {
            return [
                'id'            => $superAdmin->id,
                'nama'          => $superAdmin->nama,
                'email'         => $superAdmin->email,
                'nik'           => $superAdmin->nik,
                'jabatan'       => $superAdmin->jabatan,
                'role'          => $superAdmin->role,
                'no_hp'         => $superAdmin->no_hp,
                'foto_profil'   => $superAdmin->foto_profil,
                'created_at'    => $superAdmin->created_at,
                'updated_at'    => $superAdmin->updated_at,
            ];
        });

    // ============================
    // 📤 RESPONSE
    // ============================
    return response()->json([
        'success' => true,
        'count'   => $data->count(),
        'data'    => $data
    ], 200);
}


public function addSuperAdmin(Request $request)
{
    // ============================
    // 🔐 AUTH CHECK
    // ============================
    $authUser = auth()->user();

    if (!$authUser) {
        return response()->json([
            'success' => false,
            'message' => 'Unauthorized'
        ], 401);
    }

    // ============================
    // 🔒 ROLE VALIDATION (SUPER ADMIN ONLY)
    // ============================
    if ($authUser->role !== 'super_admin') {
        return response()->json([
            'success' => false,
            'message' => 'Akses ditolak. Hanya Super Admin yang dapat menambahkan Super Admin.'
        ], 403);
    }

    // ============================
    // ✅ VALIDASI INPUT
    // ============================
    $validated = $request->validate([
        'nama'     => 'required|string|max:255',
        'email'    => [
            'required',
            'email',
            'max:255',
            'unique:users,email',
            'regex:/^[a-zA-Z0-9._%+-]+@gmail\.com$/'
        ],
         'password' => [
                'required',
                'string',
                'min:6',
                'regex:/^[A-Za-z0-9@._-]+$/'
            ],
        'nik'      => 'required|string|min:16|max:16|unique:users,nik',
        'jabatan'  => 'required|string|max:255',
    ], [
        'email.regex' => 'Email harus menggunakan domain @gmail.com',
        'password.regex' => 'Password hanya boleh huruf, angka, dan simbol @ . _ -',
    ]);


    // ============================
    // ➕ CREATE SUPER ADMIN
    // ============================
    $superAdmin = User::create([
        'nama'     => $validated['nama'],
        'email'    => $validated['email'],
        'password' => Hash::make($validated['password']),
        'nik'      => $validated['nik'],
        'jabatan'  => $validated['jabatan'],
        'role'     => 'super_admin',
    ]);

    // ============================
    // 📤 RESPONSE
    // ============================
    return response()->json([
        'success' => true,
        'message' => 'Data Super Admin berhasil ditambahkan',
        'data' => [
            'id'      => $superAdmin->id,
            'nama'    => $superAdmin->nama,
            'email'   => $superAdmin->email,
            'nik'     => $superAdmin->nik,
            'role'    => $superAdmin->role,
            'jabatan' => $superAdmin->jabatan,
        ]
    ], 201);
}

public function getDetailSuperAdmin(Request $request, $id)
{
    // ============================
    // 🔐 AUTH CHECK
    // ============================
    $authUser = auth()->user();

    if (!$authUser) {
        return response()->json([
            'success' => false,
            'message' => 'Unauthorized'
        ], 401);
    }

    // ============================
    // 🔒 ROLE VALIDATION (SUPER ADMIN ONLY)
    // ============================
    if ($authUser->role !== 'super_admin') {
        return response()->json([
            'success' => false,
            'message' => 'Akses ditolak'
        ], 403);
    }

    // ============================
    // 🔎 GET DETAIL SUPER ADMIN
    // ============================
    $superAdmin = User::where('role', 'super_admin')
        ->where('id', $id)
        ->first();

    if (!$superAdmin) {
        return response()->json([
            'success' => false,
            'message' => 'Data Super Admin tidak ditemukan'
        ], 404);
    }

    // ============================
    // 📤 RESPONSE DETAIL LENGKAP
    // ============================
    return response()->json([
        'success' => true,
        'data' => [
            'id'                => $superAdmin->id,
            'nama'              => $superAdmin->nama,
            'email'             => $superAdmin->email,
            'nik'               => $superAdmin->nik,
            'role'              => $superAdmin->role,
            'jabatan'           => $superAdmin->jabatan,
            'no_hp'             => $superAdmin->no_hp,

            'ttl'               => $superAdmin->ttl,
            'jenis_kelamin'     => $superAdmin->jenis_kelamin,
            'agama'             => $superAdmin->agama,
            'kewarganegaraan'   => $superAdmin->kewarganegaraan,
            'pendidikan'        => $superAdmin->pendidikan,
            'status_perkawinan' => $superAdmin->status_perkawinan,
            'alamat'            => $superAdmin->alamat,
            'nip'               => $superAdmin->nip,

            // 📎 DOKUMEN
            'foto_profil' => $superAdmin->foto_profil,
            'file_ktp'    => $superAdmin->file_ktp,
            'file_kk'     => $superAdmin->file_kk,

            'created_at' => $superAdmin->created_at,
            'updated_at' => $superAdmin->updated_at,
        ]
    ], 200);
}



public function updateSuperAdminByAdmin(Request $request, $id)
{
    // ===============================
    // 🔐 AUTH CHECK
    // ===============================
    $authUser = auth()->user();

    if (!$authUser) {
        return response()->json([
            'success' => false,
            'message' => 'Token tidak valid'
        ], 401);
    }

    // ===============================
    // 🔒 ROLE VALIDATION (SUPER ADMIN ONLY)
    // ===============================
    if ($authUser->role !== 'super_admin') {
        return response()->json([
            'success' => false,
            'message' => 'Akses ditolak'
        ], 403);
    }

    // ===============================
    // 🔎 AMBIL DATA KEPALA DESA
    // ===============================
    $user = User::where('role', 'super_admin')
        ->where('id', $id)
        ->first();

    if (!$user) {
        return response()->json([
            'success' => false,
            'message' => 'Data Super Admin tidak ditemukan'
        ], 404);
    }

    // ===============================
    // ✅ VALIDASI
    // ===============================
    $request->validate([
        'nama' => 'required|string|max:255',
        'jabatan' => 'nullable|string|max:255',
        'no_hp' => 'nullable|string|max:20',

        'ttl' => 'required|string',
        'jenis_kelamin' => 'nullable|in:Laki-laki,Perempuan',
        'agama' => 'required|string',
        'kewarganegaraan' => 'required|string',
        'pendidikan' => 'required|string',
        'status_perkawinan' => 'nullable|in:Belum Menikah,Sudah Menikah,Cerai',
        'alamat' => 'required|string',
        'nip' => 'nullable|string|max:18',

        // dokumen
        'foto_profil' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        'file_ktp' => 'nullable|mimes:jpg,jpeg,png,pdf|max:4096',
        'file_kk' => 'nullable|mimes:jpg,jpeg,png,pdf|max:4096',
    ]);

    // ===============================
// 🔐 VALIDASI EMAIL (SUPER ADMIN SAJA + GMAIL)
// ===============================
if ($request->has('email')) {

    // selain super_admin dilarang ubah email
    if ($authUser->role !== 'super_admin') {
        return response()->json([
            'success' => false,
            'message' => 'Anda tidak memiliki izin mengubah email'
        ], 403);
    }

    // validasi email khusus gmail
    $request->validate([
        'email' => [
            'required',
            'email',
            'regex:/^[a-zA-Z0-9._%+-]+@gmail\.com$/',
            'unique:users,email,' . $user->id
        ]
    ], [
        'email.regex' => 'Email harus menggunakan domain @gmail.com'
    ]);

    $user->email = $request->email;
}

    // ===============================
    // 🔐 VALIDASI & UPDATE PASSWORD (SUPER ADMIN SAJA)
    // ===============================
    if ($request->filled('password')) {
        
        // 1. Cek izin
        if ($authUser->role !== 'super_admin') {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki izin mengubah password'
            ], 403);
        }

        // 2. Validasi (Perhatikan letak kurung siku array untuk custom message)
        $request->validate([
            'password' => [
                'required',
                'string',
                'min:6',
                'regex:/^[A-Za-z0-9@._-]+$/',
                'confirmed' // Membutuhkan input password_confirmation dari frontend
            ]
        ], [
            // Parameter kedua untuk custom messages
            'password.regex' => 'Password hanya boleh huruf, angka, dan simbol @ . _ -',
            'password.min' => 'Password minimal harus 6 karakter',
            'password.confirmed' => 'Konfirmasi password tidak cocok'
        ]);

        // 3. Update password jika lolos validasi
        $user->password = Hash::make($request->password);
    }

// ===============================
// 🔐 UPDATE PASSWORD (SUPER ADMIN SAJA)
// ===============================
if ($request->filled('password') && $authUser->role === 'super_admin') {
    $user->password = Hash::make($request->password);
}


    // ===============================
    // 📝 UPDATE FIELD TEXT
    // ===============================
    $user->nama = $request->nama;
    $user->jabatan = $request->jabatan;
    $user->no_hp = $request->no_hp;

    $user->ttl = $request->ttl;
    $user->jenis_kelamin = $request->jenis_kelamin;
    $user->agama = $request->agama;
    $user->kewarganegaraan = $request->kewarganegaraan;
    $user->pendidikan = $request->pendidikan;
    $user->status_perkawinan = $request->status_perkawinan;
    $user->alamat = $request->alamat;

    // ===============================
    // 📁 FOLDER UPLOAD
    // ===============================
    $basePath = public_path('uploads');
    if (!file_exists($basePath)) {
        mkdir($basePath, 0777, true);
    }

    // ===============================
    // 📸 FOTO PROFIL
    // ===============================
    if ($request->hasFile('foto_profil')) {
        $folder = $basePath.'/foto_profil_user';
        if (!file_exists($folder)) mkdir($folder, 0777, true);

        if ($user->foto_profil) {
            $oldFile = str_replace(url('/'), public_path(), $user->foto_profil);
            if (file_exists($oldFile)) unlink($oldFile);
        }

        $filename = time().'_'.$request->file('foto_profil')->getClientOriginalName();
        $request->file('foto_profil')->move($folder, $filename);
        $user->foto_profil = url('uploads/foto_profil_user/'.$filename);
    }

    // ===============================
    // 🪪 FILE KTP
    // ===============================
    if ($request->hasFile('file_ktp')) {
        $folder = $basePath.'/file_ktp_user';
        if (!file_exists($folder)) mkdir($folder, 0777, true);

        if ($user->file_ktp) {
            $oldFile = str_replace(url('/'), public_path(), $user->file_ktp);
            if (file_exists($oldFile)) unlink($oldFile);
        }

        $filename = time().'_'.$request->file('file_ktp')->getClientOriginalName();
        $request->file('file_ktp')->move($folder, $filename);
        $user->file_ktp = url('uploads/file_ktp_user/'.$filename);
    }

    // ===============================
    // 👪 FILE KK
    // ===============================
    if ($request->hasFile('file_kk')) {
        $folder = $basePath.'/file_kk_user';
        if (!file_exists($folder)) mkdir($folder, 0777, true);

        if ($user->file_kk) {
            $oldFile = str_replace(url('/'), public_path(), $user->file_kk);
            if (file_exists($oldFile)) unlink($oldFile);
        }

        $filename = time().'_'.$request->file('file_kk')->getClientOriginalName();
        $request->file('file_kk')->move($folder, $filename);
        $user->file_kk = url('uploads/file_kk_user/'.$filename);
    }
    
    // ===============================
    // 👪 FILE TTD
    // ===============================
    if ($request->hasFile('ttd')) {
        $folder = $basePath.'/ttd_user';
        if (!file_exists($folder)) mkdir($folder, 0777, true);

        if ($user->ttd) {
            $oldFile = str_replace(url('/'), public_path(), $user->ttd);
            if (file_exists($oldFile)) unlink($oldFile);
        }

        $filename = time().'_'.$request->file('ttd')->getClientOriginalName();
        $request->file('ttd')->move($folder, $filename);
        $user->ttd = url('uploads/ttd_user/'.$filename);
    }

    $user->save();

    return response()->json([
        'success' => true,
        'message' => 'Data Super Admin berhasil diperbarui',
        'data' => $user
    ], 200);
}



public function deleteSuperAdmin(Request $request, $id)
{
    // ============================
    // 🔐 AUTH CHECK
    // ============================
    $authUser = auth()->user();

    if (!$authUser) {
        return response()->json([
            'success' => false,
            'message' => 'Unauthorized'
        ], 401);
    }

    // ============================
    // 🔒 ROLE VALIDATION
    // ============================
    if ($authUser->role !== 'super_admin') {
        return response()->json([
            'success' => false,
            'message' => 'Akses ditolak'
        ], 403);
    }

    // ============================
    // ⛔ PROTEKSI DELETE DIRI SENDIRI
    // ============================
    if ((int)$authUser->id === (int)$id) {
        return response()->json([
            'success' => false,
            'message' => 'Tidak dapat menghapus akun sendiri'
        ], 400);
    }

    // ============================
    // 🔎 GET DATA KEPALA DESA
    // ============================
    $user = User::where('role', 'super_admin')->find($id);

    if (!$user) {
        return response()->json([
            'success' => false,
            'message' => 'Data Super Admin tidak ditemukan'
        ], 404);
    }

    DB::beginTransaction();

    try {
        // ============================
        // 🧹 DELETE FILE (SAFE)
        // ============================
        $fileFields = [
            'foto_profil' => 'uploads/foto_profil_user/',
            'file_ktp'    => 'uploads/file_ktp_user/',
            'file_kk'     => 'uploads/file_kk_user/',
            'ttd'     => 'uploads/ttd_user/',
        ];

        foreach ($fileFields as $field => $path) {
            if ($user->$field) {
                $filePath = public_path(
                    str_replace(url('/'), '', $user->$field)
                );

                if (file_exists($filePath)) {
                    unlink($filePath);
                }
            }
        }

        // ============================
        // ❌ DELETE USER
        // ============================
        $user->delete();

        DB::commit();

        return response()->json([
            'success' => true,
            'message' => 'Data Super Admin berhasil dihapus',
            'deleted_id' => $id
        ], 200);

    } catch (\Exception $e) {
        DB::rollBack();

        Log::error('Delete Super Admin Error', [
            'id' => $id,
            'error' => $e->getMessage()
        ]);

        return response()->json([
            'success' => false,
            'message' => 'Gagal menghapus data',
        ], 500);
    }
}

}
