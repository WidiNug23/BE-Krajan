<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LaporanModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;


class LaporanController extends Controller
{

 public function countLaporan(Request $request)
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
            // 2. HITUNG JUMLAH DATA
            // ===============================
            $totalLaporan = LaporanModel::count();

            // ===============================
            // 3. RESPONSE
            // ===============================
            return response()->json([
                'success' => true,
                'data' => [
                    'total_laporan' => $totalLaporan
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil jumlah Data Laporan',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    
    // 🔹 TAMPIL SEMUA DATA
    public function index()
    {
        return response()->json(LaporanModel::all(), 200);
    }

        // 🔹 DETAIL DATA
    public function show($id)
    {
        $laporan = LaporanModel::find($id);

        if (!$laporan) {
            return response()->json([
                'message' => 'Laporan tidak ditemukan'
            ], 404);
        }

        return response()->json($laporan, 200);
    }

    public function indexAdmin(Request $request)
{
    // ================= AMBIL USER LOGIN =================
    $user = Auth::user();

    if (!$user) {
        return response()->json([
            'message' => 'Unauthorized'
        ], 401);
    }

    // ================= CEK ROLE =================
    if (!in_array($user->role, ['perangkat_desa', 'super_admin'])) {
        return response()->json([
            'message' => 'Anda tidak memiliki akses melihat data laporan'
        ], 403);
    }

    // ================= AMBIL DATA =================
    $laporan = LaporanModel::with([
        'petugas:id,nama,role,jabatan,foto_profil'
    ])
    ->orderBy('created_at', 'desc')
    ->get();

    return response()->json([
        'message' => 'Data laporan berhasil diambil',
        'total'   => $laporan->count(),
        'data'    => $laporan
    ], 200);
}

public function showAdmin(Request $request, $id)
{
    // ================= AMBIL USER LOGIN =================
    $user = Auth::user();

    if (!$user) {
        return response()->json([
            'message' => 'Unauthorized'
        ], 401);
    }

    // ================= CEK ROLE =================
    if (!in_array($user->role, ['perangkat_desa', 'super_admin'])) {
        return response()->json([
            'message' => 'Anda tidak memiliki akses melihat detail laporan'
        ], 403);
    }

    // ================= CEK DATA =================
    $laporan = LaporanModel::with([
        'petugas:id,nama,role,jabatan,foto_profil'
    ])->find($id);

    if (!$laporan) {
        return response()->json([
            'message' => 'Laporan tidak ditemukan'
        ], 404);
    }

    return response()->json([
        'message' => 'Detail laporan berhasil diambil',
        'data'    => $laporan
    ], 200);
}


public function store(Request $request)
{
    $request->validate([
        'nama' => 'required|string',
        'isi_laporan' => 'required|string',
    ]);

    $ip = $request->ip();

    // Ambil laporan terakhir dari IP ini
    $laporanTerakhir = LaporanModel::where('ip_address', $ip)
        ->latest('created_at')
        ->first();

    // Cek apakah masih dalam cooldown 24 jam
    if ($laporanTerakhir) {
        $waktuBerikutnya = Carbon::parse($laporanTerakhir->created_at)->addHours(24);

        if (Carbon::now()->lessThan($waktuBerikutnya)) {
            return response()->json([
                'message' => 'Anda hanya bisa mengirim 1 laporan setiap 24 jam'
            ], 429);
        }
    }

    $laporan = LaporanModel::create([
        'nama' => $request->nama,
        'isi_laporan' => $request->isi_laporan,
        'status_laporan' => 'proses',
        'ip_address' => $ip
    ]);

    return response()->json([
        'message' => 'Laporan berhasil dikirim',
        'data' => $laporan
    ], 201);
}




    // 🔹 UPDATE DATA
public function update(Request $request, $id)
{
    $user = Auth::user();

    if (!$user) {
        return response()->json([
            'message' => 'Unauthorized'
        ], 401);
    }

    if (!in_array($user->role, ['perangkat_desa', 'super_admin'])) {
        return response()->json([
            'message' => 'Anda tidak memiliki akses'
        ], 403);
    }

    $laporan = LaporanModel::find($id);

    if (!$laporan) {
        return response()->json([
            'message' => 'Laporan tidak ditemukan'
        ], 404);
    }

    $validated = $request->validate([
        'status_laporan'  => 'required|in:proses,ditinjau,sedang dikerjakan,ditolak,selesai',
        'jawaban_laporan' => 'required|string|min:5'
    ]);

    $laporan->update([
        'status_laporan'  => $validated['status_laporan'],
        'jawaban_laporan' => $validated['jawaban_laporan'],
        'users_id'        => $user->id
    ]);

    // load relasi dengan NAMA BARU
    $laporan->load('petugas');

    return response()->json([
        'message' => 'Laporan berhasil diperbarui',
        'data' => [
            'id_laporan'      => $laporan->id_laporan,
            'nama'            => $laporan->nama,
            'isi_laporan'     => $laporan->isi_laporan,
            'status_laporan'  => $laporan->status_laporan,
            'jawaban_laporan' => $laporan->jawaban_laporan,
            'updated_at'      => $laporan->updated_at,
            'petugas' => [
                'nama'    => $laporan->petugas->nama ?? null,
                'role'    => $laporan->petugas->role ?? null,
                'jabatan' => $laporan->petugas->jabatan ?? null,
            ]
        ]
    ], 200);
}


    // 🔹 HAPUS DATA
   public function destroy(Request $request, $id)
{
    try {
        // ================= AMBIL USER LOGIN =================
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.',
            ], 401);
        }

        // ================= VALIDASI ROLE =================
        if (
            !$user->tokenCan('perangkat_desa') &&
            !$user->tokenCan('super_admin')
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki akses untuk menghapus laporan.',
            ], 403);
        }

        // ================= VALIDASI PASSWORD =================
        $request->validate([
            'password' => 'required|string',
        ]);

        if (!Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Password yang Anda masukkan salah.',
            ], 401);
        }

        // ================= CEK DATA LAPORAN =================
        $laporan = LaporanModel::find($id);

        if (!$laporan) {
            return response()->json([
                'success' => false,
                'message' => 'Laporan tidak ditemukan.',
            ], 404);
        }

        // ================= SIMPAN INFO PENGHAPUS (OPSIONAL) =================
        // Jika ada kolom users_id / deleted_by bisa diisi dulu
        if (Schema::hasColumn('laporan_masyarakat', 'users_id')) {
            $laporan->users_id = $user->id;
            $laporan->save();
        }

        // ================= HAPUS DATA =================
        $laporan->delete();

        return response()->json([
            'success' => true,
            'message' => 'Laporan berhasil dihapus.',
            'deleted_by' => [
                'nama'    => $user->nama,
                'role'    => $user->role,
                'jabatan' => $user->jabatan,
            ],
        ], 200);

    } catch (\Throwable $e) {
        return response()->json([
            'success' => false,
            'message' => 'Terjadi kesalahan saat menghapus laporan.',
            'error' => $e->getMessage(),
        ], 500);
    }
}

}
