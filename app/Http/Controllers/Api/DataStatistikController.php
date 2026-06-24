<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ModelDataStatistik;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use Illuminate\Support\Str;

class DataStatistikController extends Controller
{
    /**
     * Simpan data statistik
     */
 public function countDataStatistik(Request $request)
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
            $totalStatistik = ModelDataStatistik::count();

            // ===============================
            // 3. RESPONSE
            // ===============================
            return response()->json([
                'success' => true,
                'data' => [
                    'total_Statistik' => $totalStatistik
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil jumlah Data Statistik',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    
    public function index()
{
    // =====================
    // VALIDASI AUTH
    // =====================
    $user = Auth::user();
    if (!$user) {
        return response()->json([
            'message' => 'Unauthorized'
        ], 401);
    }

    // =====================
    // VALIDASI ROLE
    // =====================
    if (!in_array($user->role, ['super_admin', 'perangkat_desa'])) {
        return response()->json([
            'message' => 'Akses ditolak'
        ], 403);
    }

    // =====================
    // AMBIL DATA
    // =====================
    $data = ModelDataStatistik::with('uploader:id,nama,role')
        ->orderBy('tgl_buat', 'desc')
        ->get();

    return response()->json([
        'message' => 'Berhasil mengambil data statistik',
        'total'   => $data->count(),
        'data'    => $data
    ], 200);
}


public function show($id)
{
    // =====================
    // VALIDASI AUTH
    // =====================
    $user = Auth::user();
    if (!$user) {
        return response()->json([
            'message' => 'Unauthorized'
        ], 401);
    }

    // =====================
    // VALIDASI ROLE
    // =====================
    if (!in_array($user->role, ['super_admin', 'perangkat_desa'])) {
        return response()->json([
            'message' => 'Akses ditolak'
        ], 403);
    }

    // =====================
    // AMBIL DATA
    // =====================
    $data = ModelDataStatistik::with('uploader:id,nama,role')
        ->where('id_data_statistik', $id)
        ->first();

    if (!$data) {
        return response()->json([
            'message' => 'Data statistik tidak ditemukan'
        ], 404);
    }

    return response()->json([
        'message' => 'Berhasil mengambil detail data statistik',
        'data'    => $data
    ], 200);
}

public function indexPublic()
{
    $data = ModelDataStatistik::select(
            'id_data_statistik',
            'nama_file',
            'file_data',
            'tgl_buat'
        )
        ->orderBy('tgl_buat', 'desc')
        ->get();

    return response()->json([
        'message' => 'Berhasil mengambil data statistik',
        'total'   => $data->count(),
        'data'    => $data
    ], 200);
}

public function showPublic($id)
{
    $data = ModelDataStatistik::select(
            'id_data_statistik',
            'nama_file',
            'file_data',
            'tgl_buat'
        )
        ->where('id_data_statistik', $id)
        ->first();

    if (!$data) {
        return response()->json([
            'message' => 'Data statistik tidak ditemukan'
        ], 404);
    }

    return response()->json([
        'message' => 'Berhasil mengambil detail data statistik',
        'data'    => $data
    ], 200);
}


public function store(Request $request)
{
    // =====================
    // 🔐 AUTH CHECK
    // =====================
    $user = Auth::user();
    if (!$user) {
        return response()->json([
            'message' => 'Unauthorized'
        ], 401);
    }

    // =====================
    // 🔐 ROLE CHECK
    // =====================
    if (!in_array($user->role, ['super_admin', 'perangkat_desa'])) {
        return response()->json([
            'message' => 'Anda tidak memiliki akses'
        ], 403);
    }

    // =====================
    // VALIDASI
    // =====================
    $validator = Validator::make($request->all(), [
        'nama_file' => 'required|string|max:255',
        'file_data' => 'required|file|mimes:xls,xlsx,csv|max:5120',
    ]);

    if ($validator->fails()) {
        return response()->json([
            'message' => 'Validasi gagal',
            'errors'  => $validator->errors()
        ], 422);
    }

    // =====================
    // 📂 SIMPAN FILE KE public/uploads/data_statistik
    // =====================
    $file = $request->file('file_data');

    $fileName = time() . '_' . Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME))
        . '.' . $file->getClientOriginalExtension();

    $destinationPath = public_path('uploads/data_statistik');

    // pastikan folder ada
    if (!file_exists($destinationPath)) {
        mkdir($destinationPath, 0755, true);
    }

    $file->move($destinationPath, $fileName);

    // path yang disimpan ke DB
    $filePath = 'uploads/data_statistik/' . $fileName;

    // =====================
    // 💾 SIMPAN KE DATABASE
    // =====================
    $data = ModelDataStatistik::create([
        'nama_file' => $request->nama_file,
        'file_data' => $filePath,
        'tgl_buat'  => Carbon::now(),
        'users_id'  => $user->id,
    ]);

    return response()->json([
        'message' => 'Data statistik berhasil ditambahkan',
        'data'    => $data
    ], 201);
}

    
public function update(Request $request, $id)
{
    // =====================
    // VALIDASI AUTH
    // =====================
    $user = Auth::user();
    if (!$user) {
        return response()->json([
            'message' => 'Unauthorized'
        ], 401);
    }

    // =====================
    // VALIDASI ROLE
    // =====================
    if (!in_array($user->role, ['super_admin', 'perangkat_desa'])) {
        return response()->json([
            'message' => 'Akses ditolak'
        ], 403);
    }

    // =====================
    // AMBIL DATA
    // =====================
    $data = ModelDataStatistik::find($id);
    if (!$data) {
        return response()->json([
            'message' => 'Data statistik tidak ditemukan'
        ], 404);
    }

    // =====================
    // VALIDASI INPUT
    // =====================
    $validator = Validator::make($request->all(), [
        'nama_file' => 'required|string|max:255',
        'file_data' => 'nullable|file|mimes:xls,xlsx,csv|max:5120'
    ]);

    if ($validator->fails()) {
        return response()->json([
            'message' => 'Validasi gagal',
            'errors'  => $validator->errors()
        ], 422);
    }

    // =====================
    // UPDATE FILE (JIKA ADA)
    // =====================
    if ($request->hasFile('file_data')) {

        // 🔥 HAPUS FILE LAMA (PAKAI public_path)
        if ($data->file_data) {
            $oldFilePath = public_path($data->file_data);
            if (file_exists($oldFilePath)) {
                unlink($oldFilePath);
            }
        }

        // 📂 SIMPAN FILE BARU
        $file = $request->file('file_data');

        $fileName = time() . '_' . Str::slug(
            pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)
        ) . '.' . $file->getClientOriginalExtension();

        $destinationPath = public_path('uploads/data_statistik');

        if (!file_exists($destinationPath)) {
            mkdir($destinationPath, 0755, true);
        }

        $file->move($destinationPath, $fileName);

        // path yang disimpan ke DB
        $data->file_data = 'uploads/data_statistik/' . $fileName;
    }

    // =====================
    // UPDATE DATA
    // =====================
    $data->nama_file = $request->nama_file;
    $data->tgl_edit  = now();
    $data->users_id  = $user->id;
    $data->save();

    return response()->json([
        'message' => 'Data statistik berhasil diperbarui',
        'data'    => $data
    ], 200);
}

public function destroy($id)
{
    // =====================
    // VALIDASI AUTH
    // =====================
    $user = Auth::user();
    if (!$user) {
        return response()->json([
            'message' => 'Unauthorized'
        ], 401);
    }

    // =====================
    // VALIDASI ROLE
    // =====================
    if (!in_array($user->role, ['super_admin', 'perangkat_desa'])) {
        return response()->json([
            'message' => 'Akses ditolak'
        ], 403);
    }

    // =====================
    // AMBIL DATA
    // =====================
    $data = ModelDataStatistik::find($id);
    if (!$data) {
        return response()->json([
            'message' => 'Data statistik tidak ditemukan'
        ], 404);
    }

    // =====================
    // HAPUS FILE (JIKA ADA)
    // =====================
    if ($data->file_data) {
        $filePath = public_path($data->file_data);
        if (file_exists($filePath)) {
            unlink($filePath);
        }
    }

    // =====================
    // HAPUS DATA DB
    // =====================
    $data->delete();

    return response()->json([
        'message' => 'Data statistik berhasil dihapus'
    ], 200);
}
}


