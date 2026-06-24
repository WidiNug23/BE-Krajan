<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Berita;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;

class BeritaController extends Controller
{

 public function countBerita(Request $request)
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
            $totalBerita = Berita::count();

            // ===============================
            // 3. RESPONSE
            // ===============================
            return response()->json([
                'success' => true,
                'data' => [
                    'total_berita' => $totalBerita
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil jumlah Data Berita',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
public function indexPublic()
{
    $berita = Berita::orderBy('tanggal_publikasi', 'desc')->get();

    return response()->json([
        'success' => true,
        'data' => $berita
    ], 200, [], JSON_UNESCAPED_UNICODE);
}

public function showPublic($id)
{
    $berita = Berita::find($id);

    if (!$berita) {
        return response()->json([
            'success' => false,
            'message' => 'Berita tidak ditemukan'
        ], 404);
    }

    return response()->json([
        'success' => true,
        'data' => $berita
    ], 200, [], JSON_UNESCAPED_UNICODE);
}


public function indexAdmin(Request $request)
{
    $user = $request->user();

    if (!in_array($user->role, ['super_admin', 'perangkat_desa'])) {
        return response()->json([
            'success' => false,
            'message' => 'Anda tidak memiliki izin'
        ], 403);
    }

    $berita = Berita::select(
        'id_berita',
        'judul',
        'isi',
        'image',
        'author',
        'jenis_berita',
        'tanggal_publikasi',
        'tanggal_update',
        'created_at',
        'updated_at'
    )
    ->orderBy('tanggal_publikasi', 'desc')
    ->get();

    return response()->json([
        'success' => true,
        'data' => $berita
    ], 200, [], JSON_UNESCAPED_UNICODE);
}


public function showAdmin(Request $request, $id)
{
    // 🔐 VALIDASI TOKEN
    $user = $request->user();

    if (!$user) {
        return response()->json([
            'success' => false,
            'message' => 'Unauthorized'
        ], 401);
    }

    // 🔒 VALIDASI ROLE
    $role = strtolower(trim($user->role));

    if (!in_array($role, ['super_admin', 'perangkat_desa'])) {
        return response()->json([
            'success' => false,
            'message' => 'Anda tidak memiliki izin'
        ], 403);
    }

    // 🔍 CARI BERITA
    $berita = Berita::find($id);

    if (!$berita) {
        return response()->json([
            'success' => false,
            'message' => 'Berita tidak ditemukan'
        ], 404);
    }

    return response()->json([
        'success' => true,
        'data' => [
            'id_berita'                 => $berita->id_berita,
            'judul'              => $berita->judul,
            'isi'                => $berita->isi,
            'image'              => $berita->image,
            'author'             => $berita->author,
            'jenis_berita'       => $berita->jenis_berita,
            'tanggal_publikasi'  => $berita->tanggal_publikasi,
            'tanggal_update'     => $berita->tanggal_update,
            'created_at'         => $berita->created_at,
            'updated_at'         => $berita->updated_at,
        ]
    ], 200, [], JSON_UNESCAPED_UNICODE);
}


public function store(Request $request)
    {
        $user = $request->user();
        // Validasi Role
        if (!$user || !in_array($user->role, ['perangkat_desa', 'super_admin'])) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        // Validasi Input
        $request->validate([
            'judul' => 'required|string|max:255',
            'isi' => 'required|string',
            'jenis_berita' => 'required|string',
            'media_type' => 'required|in:image,youtube,instagram',
            'image' => 'nullable|image|mimes:jpg,jpeg,png|max:5120', // Digunakan untuk tipe image & instagram
            'video_link' => 'nullable|url', // Digunakan untuk tipe youtube & instagram
        ]);

        $finalMediaPath = "";

        // 1. Proses Upload File (Jika ada)
        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
            $file->move(public_path('uploads/berita'), $filename);
            $finalMediaPath = url('uploads/berita/' . $filename);
        }

        // 2. Logika Pengisian Kolom 'image' berdasarkan media_type
        $dataToSave = [
            'judul' => $request->judul,
            'isi' => $request->isi,
            'jenis_berita' => $request->jenis_berita,
            'author' => $user->nama,
            'tanggal_publikasi' => now(),
            'tanggal_update' => now(),
        ];

        if ($request->media_type === 'instagram') {
            // Gabungkan Path Gambar + Separator + Link Instagram
            $dataToSave['image'] = $finalMediaPath . '[SPLIT]' . $request->video_link;
        } elseif ($request->media_type === 'youtube') {
            // Simpan Link YouTube saja
            $dataToSave['image'] = $request->video_link;
        } else {
            // Simpan Path Gambar saja
            $dataToSave['image'] = $finalMediaPath;
        }

        $berita = Berita::create($dataToSave);

        return response()->json([
            'success' => true, 
            'message' => 'Berita berhasil diterbitkan',
            'data' => $berita
        ], 201);
    }


public function update(Request $request, $id)
{
    $user = $request->user();
    if (!$user || !in_array($user->role, ['perangkat_desa', 'super_admin'])) {
        return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
    }

    $berita = Berita::find($id);
    if (!$berita) {
        return response()->json(['success' => false, 'message' => 'Berita tidak ditemukan'], 404);
    }

    $validated = $request->validate([
        'judul'        => 'nullable|string|max:255',
        'isi'          => 'nullable|string',
        'image'        => 'nullable|image|mimes:jpg,jpeg,png|max:5120',
        'jenis_berita' => 'nullable|string',
        'media_type'   => 'nullable|string|in:image,youtube,instagram',
        'video_link'   => 'nullable|string',
    ]);

    // 1. Ambil Thumbnail yang sudah ada (bersihkan dari [SPLIT] jika ada)
    $currentThumbnail = $berita->image;
    if (str_contains($currentThumbnail, '[SPLIT]')) {
        $currentThumbnail = explode('[SPLIT]', $currentThumbnail)[0];
    }

    // 2. Handle Upload Image Baru
    if ($request->hasFile('image')) {
        // Hapus file fisik lama
        $oldPath = public_path(str_replace(url('/') . '/', '', $currentThumbnail));
        if (File::exists($oldPath) && !str_contains($currentThumbnail, 'http')) { 
            File::delete($oldPath); 
        }

        $file = $request->file('image');
        $filename = time() . '_' . $file->getClientOriginalName();
        $file->move(public_path('uploads/berita'), $filename);
        $currentThumbnail = url('uploads/berita/' . $filename);
    }

    // 3. Logika Penyimpanan Berdasarkan Tipe Media
    $dbValue = $currentThumbnail; 

    if ($request->media_type === 'instagram') {
        // Gabungkan thumbnail (baru/lama) dengan link video baru
        $dbValue = $currentThumbnail . '[SPLIT]' . $request->video_link;
    } elseif ($request->media_type === 'youtube') {
        // YouTube simpan linknya saja (frontend akan generate thumbnail otomatis)
        $dbValue = $request->video_link;
    }

    $berita->update([
        'judul'          => $request->judul ?? $berita->judul,
        'isi'            => $request->isi ?? $berita->isi,
        'jenis_berita'   => $request->jenis_berita ?? $berita->jenis_berita,
        'image'          => $dbValue,
        'author'         => $user->nama,
        'tanggal_update' => now(),
    ]);

    return response()->json([
        'success' => true,
        'message' => 'Berita berhasil diperbarui',
        'data' => $berita
    ], 200);
}


public function deleteBerita(Request $request, $id)
{
    // 🔐 VALIDASI TOKEN
    $user = $request->user();

    if (!$user) {
        return response()->json([
            'success' => false,
            'message' => 'Unauthorized'
        ], 401);
    }

    // 🔒 VALIDASI ROLE
    if (!in_array($user->role, ['super_admin', 'perangkat_desa'])) {
        return response()->json([
            'success' => false,
            'message' => 'Anda tidak memiliki izin untuk menghapus berita'
        ], 403);
    }

    // 🔍 CARI BERITA
    $berita = Berita::find($id);

    if (!$berita) {
        return response()->json([
            'success' => false,
            'message' => 'Berita tidak ditemukan'
        ], 404);
    }

 // 🖼️ HAPUS IMAGE JIKA ADA
    if ($berita->image) {

        // contoh image: http://domain/uploads/berita/nama.jpg
        $imagePath = public_path(
            str_replace(url('/') . '/', '', $berita->image)
        );

        if (File::exists($imagePath)) {
            File::delete($imagePath);
        }
    }

    // 🗑️ HAPUS DATA BERITA
    $berita->delete();

    return response()->json([
        'success' => true,
        'message' => 'Berita berhasil dihapus'
    ], 200);
}
}
