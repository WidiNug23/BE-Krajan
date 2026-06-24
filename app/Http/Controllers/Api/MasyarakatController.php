<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Masyarakat;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class MasyarakatController extends Controller
{

 public function countMasyarakat(Request $request)
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
            $totalMasyarakat = Masyarakat::count();

            // ===============================
            // 3. RESPONSE
            // ===============================
            return response()->json([
                'success' => true,
                'data' => [
                    'total_masyarakat' => $totalMasyarakat
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil jumlah Data Masyarakat',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    // GET daftar semua RT (butuh token)
public function getAllRT(Request $request)
    {
        // User yang sudah login
        $user = auth()->user();

        // Ambil semua user dengan role RT
        $rtUsers = User::where('role', 'rt')->get();

        return response()->json([
            'status' => 'success',
            'message' => 'List semua RT berhasil diambil',
            'data' => $rtUsers
        ]);
}

public function updateProfile(Request $request, $id)
{
    $authUser = auth()->user();

    if (!$authUser) {
        return response()->json([
            'success' => false,
            'message' => 'Token tidak valid'
        ], 401);
    }

    if ($authUser->id != $id) {
        return response()->json([
            'success' => false,
            'message' => 'Tidak boleh mengedit profil orang lain'
        ], 403);
    }

    $masyarakat = Masyarakat::find($id);

    if (!$masyarakat) {
        return response()->json([
            'success' => false,
            'message' => 'Data tidak ditemukan'
        ], 404);
    }

    // VALIDASI
   $request->validate([
        'nama' => 'required|string|max:255',
        // 'email' => 'required|email',
        // 'nik' => 'required|string|size:16',
        'no_hp' => 'required|string|max:20',

        'ttl' => 'required|string',
        'jenis_kelamin' => 'nullable|in:Laki-laki,Perempuan',
        'agama' => 'required|string',
        'kewarganegaraan' => 'required|string',
        'pendidikan' => 'required|string',
        'status_perkawinan' => 'required|string',
        'alamat' => 'required|string',

        // file boleh kosong (tidak wajib diupload ulang)
        'foto_profil' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        'file_ktp' => 'nullable|mimes:jpg,jpeg,png,pdf|max:4096',
        'file_kk' => 'nullable|mimes:jpg,jpeg,png,pdf|max:4096',
    ]);

    // =============================
    // UPDATE FIELD TEXT
    // =============================
    $masyarakat->nama = $request->nama ?? $masyarakat->nama;
    $masyarakat->email = $request->email ?? $masyarakat->email;
    $masyarakat->nik = $request->nik ?? $masyarakat->nik;

    $masyarakat->ttl = $request->ttl ?? $masyarakat->ttl;
   $masyarakat->jenis_kelamin = $request->jenis_kelamin ?? $masyarakat->jenis_kelamin;
    $masyarakat->no_hp = $request->no_hp ?? $masyarakat->no_hp;
    $masyarakat->agama = $request->agama ?? $masyarakat->agama;
    $masyarakat->kewarganegaraan = $request->kewarganegaraan ?? $masyarakat->kewarganegaraan;
    $masyarakat->pendidikan = $request->pendidikan ?? $masyarakat->pendidikan;
    $masyarakat->status_perkawinan = $request->status_perkawinan ?? $masyarakat->status_perkawinan;
    $masyarakat->alamat = $request->alamat ?? $masyarakat->alamat;

    // =============================
    // FOLDER UTAMA
    // =============================
    $basePath = public_path('uploads');
    if (!file_exists($basePath)) {
        mkdir($basePath, 0777, true);
    }


    // =======================================================
    // 1. FOTO PROFIL
    // =======================================================
    if ($request->hasFile('foto_profil')) {

        $folder = $basePath . '/foto_profil';
        if (!file_exists($folder)) mkdir($folder, 0777, true);

        // HAPUS FILE LAMA (Jika ada)
        if ($masyarakat->foto_profil) {
            $oldFile = str_replace(url('/'), public_path(), $masyarakat->foto_profil);
            if (file_exists($oldFile)) unlink($oldFile);
        }

        $filename = time() . '_' . $request->file('foto_profil')->getClientOriginalName();
        $request->file('foto_profil')->move($folder, $filename);

        $masyarakat->foto_profil = url('uploads/foto_profil/' . $filename);
    }


    // =======================================================
    // 2. FILE KTP
    // =======================================================
    if ($request->hasFile('file_ktp')) {

        $folder = $basePath . '/file_ktp';
        if (!file_exists($folder)) mkdir($folder, 0777, true);

        if ($masyarakat->file_ktp) {
            $oldFile = str_replace(url('/'), public_path(), $masyarakat->file_ktp);
            if (file_exists($oldFile)) unlink($oldFile);
        }

        $filename = time() . '_' . $request->file('file_ktp')->getClientOriginalName();
        $request->file('file_ktp')->move($folder, $filename);

        $masyarakat->file_ktp = url('uploads/file_ktp/' . $filename);
    }


    // =======================================================
    // 3. FILE KK
    // =======================================================
    if ($request->hasFile('file_kk')) {

        $folder = $basePath . '/file_kk';
        if (!file_exists($folder)) mkdir($folder, 0777, true);

        if ($masyarakat->file_kk) {
            $oldFile = str_replace(url('/'), public_path(), $masyarakat->file_kk);
            if (file_exists($oldFile)) unlink($oldFile);
        }

        $filename = time() . '_' . $request->file('file_kk')->getClientOriginalName();
        $request->file('file_kk')->move($folder, $filename);

        $masyarakat->file_kk = url('uploads/file_kk/' . $filename);
    }


    $masyarakat->save();

    return response()->json([
        'success' => true,
        'message' => 'Profil berhasil diperbarui',
        'data' => $masyarakat
    ]);
}

public function getDetailMasyarakat(Request $request, $id)
{
    // 🔹 Cek token
    $user = $request->user(); // user login melalui sanctum masyarakat

    if (!$user) {
        return response()->json([
            'success' => false,
            'message' => 'Token tidak valid'
        ], 401);
    }

    // 🔹 Validasi agar user hanya bisa melihat datanya sendiri
    if ($user->id != $id) {
        return response()->json([
            'success' => false,
            'message' => 'Anda tidak memiliki izin untuk melihat data ini.'
        ], 403);
    }

    // 🔹 Ambil data masyarakat
    $data = Masyarakat::with('validator')->find($id);

    if (!$data) {
        return response()->json([
            'success' => false,
            'message' => 'Data masyarakat tidak ditemukan'
        ], 404);
    }

    // 🔹 Ubah path ke URL penuh
    $data->foto_profil = $data->foto_profil ? url($data->foto_profil) : null;
    $data->file_ktp = $data->file_ktp ? url($data->file_ktp) : null;
    $data->file_kk = $data->file_kk ? url($data->file_kk) : null;

    return response()->json([
        'success' => true,
        'message' => 'Data profil berhasil diambil',
        'data' => $data
    ]);
}


// public function verifikasiMasyarakat(Request $request, $id)
// {
//     // Validasi input
//     $request->validate([
//         'status_verifikasi' => 'required|in:pending,disetujui,ditolak',
//         'keterangan_verifikasi' => 'nullable|string'
//     ]);

//     // Ambil data masyarakat
//     $masyarakat = Masyarakat::find($id);

//     if (!$masyarakat) {
//         return response()->json([
//             'success' => false,
//             'message' => 'Data masyarakat tidak ditemukan'
//         ], 404);
//     }

//     // Ambil user perangkat desa yang login
//     $perangkat = auth()->user();

//     if (!$perangkat || $perangkat->role !== 'perangkat_desa') {
//         return response()->json([
//             'success' => false,
//             'message' => 'Hanya perangkat desa yang dapat melakukan verifikasi'
//         ], 403);
//     }

//     // Update status verifikasi
//     $masyarakat->status_verifikasi = $request->status_verifikasi;
//     $masyarakat->keterangan_verifikasi = $request->keterangan_verifikasi;
//     $masyarakat->perangkat_id = $perangkat->id;
//     $masyarakat->perangkat_validated_at = now();

//     $masyarakat->save();

//     return response()->json([
//         'success' => true,
//         'message' => 'Status verifikasi berhasil diperbarui',
//         'data' => $masyarakat
//     ]);
// }

}
