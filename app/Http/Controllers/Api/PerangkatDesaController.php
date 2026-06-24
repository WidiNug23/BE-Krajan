<?php

namespace App\Http\Controllers\Api;

use App\Models\Masyarakat;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\DataMasyarakatExport;

class PerangkatDesaController extends Controller
{
    // =====================================================
    // GET ALL MASYARAKAT (KHUSUS PERANGKAT DESA)
    // =====================================================

     public function getAllMasyarakat(Request $request)
    {
        // 🔹 Validasi Token
        $user = $request->user(); // auth()->user() juga bisa

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Token tidak valid'
            ], 401);
        }

                // 🔹 Validasi Role (Perangkat Desa & Super Admin)
        if (!in_array($user->role, ['perangkat_desa', 'super_admin'])) {
            return response()->json([
                'success' => false,
                'message' => 'Akses ditolak. Hanya perangkat desa dan super admin yang boleh mengakses data ini.'
            ], 403);
        }


                // 🔹 Query data masyarakat + relasi
            // jumlah data per halaman (default 10)
        $perPage = $request->get('per_page', 10);

        $data = Masyarakat::with('perangkat')
            ->orderBy('id', 'desc')
            ->paginate($perPage);


                // 🔹 Convert file path → URL penuh
            foreach ($data->items() as $item) {
                    $item->foto_profil = $item->foto_profil ? url($item->foto_profil) : null;
                    $item->file_ktp = $item->file_ktp ? url($item->file_ktp) : null;
                    $item->file_kk = $item->file_kk ? url($item->file_kk) : null;
                }

            return response()->json([
            'success' => true,
            'message' => 'Data masyarakat berhasil diambil',
            'pagination' => [
                'current_page' => $data->currentPage(),
                'per_page'     => $data->perPage(),
                'total'        => $data->total(),
                'last_page'    => $data->lastPage(),
            ],
            'data' => $data->items()
        ]);

            }

    public function getDetailMasyarakatById(Request $request, $id)
{
    // 🔹 Validasi Token
    $user = $request->user();

    if (!$user) {
        return response()->json([
            'success' => false,
            'message' => 'Token tidak valid'
        ], 401);
    }

    // 🔹 Validasi Role Perangkat Desa & Super Admin
if (!in_array($user->role, ['perangkat_desa', 'super_admin'])) {
    return response()->json([
        'success' => false,
        'message' => 'Akses ditolak. Hanya perangkat desa dan super admin yang boleh mengakses data ini.'
    ], 403);
}


    // 🔹 Ambil data lengkap berdasarkan ID + relasi perangkat
    $data = Masyarakat::with('validator')->find($id);

    if (!$data) {
        return response()->json([
            'success' => false,
            'message' => 'Data masyarakat tidak ditemukan.'
        ], 404);
    }

    // 🔹 Convert file path → URL penuh
    $data->foto_profil = $data->foto_profil ? url($data->foto_profil) : null;
    $data->file_ktp = $data->file_ktp ? url($data->file_ktp) : null;
    $data->file_kk = $data->file_kk ? url($data->file_kk) : null;

    return response()->json([
        'success' => true,
        'message' => 'Detail masyarakat berhasil diambil',
        'data' => $data
    ]);
    }

   public function verifikasiMasyarakat(Request $request, $id)
{
    // Validasi input
    $request->validate([
        'status_verifikasi' => 'required|in:pending,disetujui,ditolak',
        'keterangan_verifikasi' => 'nullable|string'
    ]);

    // Ambil data masyarakat
    $masyarakat = Masyarakat::find($id);

    if (!$masyarakat) {
        return response()->json([
            'success' => false,
            'message' => 'Data masyarakat tidak ditemukan'
        ], 404);
    }

    // Ambil user login
    $user = auth()->user();

    // Validasi role
    if (
        !$user ||
        !in_array($user->role, ['perangkat_desa', 'super_admin'])
    ) {
        return response()->json([
            'success' => false,
            'message' => 'Anda tidak memiliki izin untuk melakukan verifikasi'
        ], 403);
    }

    // Update data verifikasi
    $masyarakat->status_verifikasi = $request->status_verifikasi;
    $masyarakat->keterangan_verifikasi = $request->keterangan_verifikasi;
    $masyarakat->users_id = $user->id;
    $masyarakat->users_validated_at = now();

    $masyarakat->save();

    return response()->json([
        'success' => true,
        'message' => 'Status verifikasi berhasil diperbarui',
        'data' => $masyarakat
    ]);
}

public function exportDataMasyarakat(Request $request)
{
    $user = $request->user();

    // 🔐 WAJIB LOGIN
    if (!$user) {
        return response()->json([
            'success' => false,
            'message' => 'Unauthorized',
        ], 401);
    }

    // 🔒 VALIDASI ROLE
    if (!in_array($user->role, ['perangkat_desa', 'super_admin'])) {
        return response()->json([
            'success' => false,
            'message' => 'Anda tidak memiliki akses untuk export data masyarakat.',
        ], 403);
    }

    // 🧾 VALIDASI INPUT
    $request->validate([
        'filename' => 'required|string|min:3|max:100',
        'password' => 'required|string|min:6',
    ]);

    // 🔑 VALIDASI PASSWORD AKUN
    if (!Hash::check($request->password, $user->password)) {
        return response()->json([
            'success' => false,
            'message' => 'Password yang Anda masukkan salah.',
        ], 401);
    }

    // 🧼 SANITASI NAMA FILE
    $safeFilename = Str::slug($request->filename, '_');

    $finalFilename = 'Masyarakat_' .
        $safeFilename . '_' .
        now()->format('Y-m-d_H-i-s') . '.xlsx';

    // 📤 EXPORT EXCEL
    return Excel::download(
        new DataMasyarakatExport,
        $finalFilename
    );
}
    }
