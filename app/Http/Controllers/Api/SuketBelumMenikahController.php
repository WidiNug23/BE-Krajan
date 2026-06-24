<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SuketBelumMenikah;
use App\Models\User;
use App\Models\Masyarakat;
use Illuminate\Support\Facades\Log;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;
use ZipArchive;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use App\Exports\SKBMExport;
use Illuminate\Support\Facades\Hash;
use Dompdf\Options;
use Illuminate\Validation\Rule;
use App\Models\RtPengantarNumber;
use App\Models\Notification;
use Illuminate\Support\Facades\DB;



class SuketBelumMenikahController extends Controller
{

 public function countSKBM(Request $request)
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
            $totalSKBM = SuketBelumMenikah::count();

            // ===============================
            // 3. RESPONSE
            // ===============================
            return response()->json([
                'success' => true,
                'data' => [
                    'total_skbm' => $totalSKBM
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil jumlah SKBM',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    
    // ✅ GET: /api/skbm
    public function index()
    {
        try {
            $data = SuketBelumMenikah::with([
                'rt' => function ($q) {
                    $q->select('id', 'nama', 'role');
                },
                'masyarakat' => function ($q) {
                    $q->select('id', 'nama', 'email', 'nik');
                }
            ])
            ->orderByDesc('id_skbm')
            ->get();

            return response()->json([
                'success' => true,
                'count' => $data->count(),
                'data' => $data
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data Surat Keterangan Belum Menikah',
                'error' => $e->getMessage()
            ], 500);
        }
    }

 // GET DATA SKBM BY MASYARAKAT (TOKEN + VALIDASI ID) baru diperbaiki //TAMBAH
public function getByMasyarakat(Request $request)
{
    try {
        // ===============================
        // 1. VALIDASI TOKEN
        // ===============================
        $masyarakat = $request->user();

        // Cukup pastikan token valid & role masyarakat
        if (!$masyarakat || !$masyarakat->tokenCan('masyarakat')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: masyarakat belum login',
            ], 401);
        }

        // ===============================
        // 2. AMBIL masyarakat_id
        // ===============================
        $tokenMasyarakatId = $masyarakat->id;

        // ===============================
        // 3. PAGINATION
        // ===============================
        $perPage = (int) $request->get('per_page', 10);

        /** @var \Illuminate\Pagination\LengthAwarePaginator $paginator */
        $paginator = SuketBelumMenikah::where('masyarakat_id', $tokenMasyarakatId)
            ->with([
                'rt:id,nama,jabatan,role',
                'perangkat:id,nama,jabatan,role',
                'perangkatValidator:id,nama,jabatan,role',
                'kepala_desa:id,nama,jabatan,role',
                'masyarakat:id,nama,email,nik',
                'submitterUser:id,nama,role',
                'submitterMasyarakat:id,nama'
            ])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        // LOG DI SINI
        Log::info('SKBM PAGINATION DEBUG', [
            'current_page' => $paginator->currentPage(),
            'per_page'     => $paginator->perPage(),
            'total'        => $paginator->total(),
            'last_page'    => $paginator->lastPage(),
        ]);

        $collection = $paginator->getCollection()->map(function ($item) {

            // Sembunyikan file_pdf jika belum selesai
            if ($item->status !== 'selesai') {
                $item->file_pdf = null;
            }

            // 🔥 UBAH LINK KTP, KK & PENGANTAR RT MENJADI LINK ROUTE SECURE API
            $item->file_ktp = url("api/skbm/file/{$item->id_skbm}/ktp");
            $item->file_kk = url("api/skbm/file/{$item->id_skbm}/kk");
            
            // Perbaikan untuk Surat Pengantar RT jika file-nya tersedia
            if (!empty($item->file_pengantar_rt)) {
                $item->file_pengantar_rt = url("api/skbm/file/{$item->id_skbm}/pengantar");
            }

            // Tambahkan ini di dalam blok map() fungsi-fungsi GET Anda:
            if (!empty($item->file_pdf)) {
                $item->file_pdf = url("api/skbm/file/{$item->id_skbm}/pdf");
            }

            // ===============================
            // 🔥 FORMAT NAMA PENGAJU
            // ===============================
            if ($item->submitted_by === 'masyarakat') {
                $item->submitted_by_nama = $item->submitterMasyarakat->nama ?? null;
            }
            else {
                $item->submitted_by_nama = $item->submitterUser->nama ?? null;
            }

            // Format tanggal
            $item->rt_validated_at_formatted = $item->rt_validated_at
                ? $item->rt_validated_at->format('d-m-Y H:i')
                : null;

            $item->perangkat_validated_at_formatted = $item->perangkat_validated_at
                ? $item->perangkat_validated_at->format('d-m-Y H:i')
                : null;

            $item->kepala_desa_validated_at_formatted = $item->kepala_desa_validated_at
                ? $item->kepala_desa_validated_at->format('d-m-Y H:i')
                : null;

            return $item;
        });

        $paginator->setCollection($collection);

        // ===============================
        // 5. RESPONSE
        // ===============================
        return response()->json([
            'success' => true,
            'message' => 'Daftar pengajuan SKBM masyarakat',
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
                'last_page'    => $paginator->lastPage(),
            ]
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Gagal mengambil data',
            'error' => $e->getMessage(),
        ], 500);
    }
}

    // GET DATA SKBM BY KETUA RT TOKEN LOGIN
public function getByKetuaRT(Request $request)
{
    try {
        // Ambil user RT dari token Sanctum
        $rt = $request->user();

        // Validasi role
        if (!$rt || $rt->tokenCan('rt') === false) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: hanya RT yang bisa mengakses data ini',
            ], 401);
        }

        // jumlah data per halaman (default 10)
        $perPage = $request->get('per_page', 10);

        // Query + pagination
        $data = SuketBelumMenikah::where('rt_id', $rt->id)
            ->with([
                'rt:id,nama,jabatan,role',
                'perangkat:id,nama,jabatan,role',
                'perangkatValidator:id,nama,jabatan,role',
                'kepala_desa:id,nama,jabatan,role',
                'masyarakat:id,nama,email,nik',
                'submitterUser:id,nama,role',
                'submitterMasyarakat:id,nama'
            ])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        // =========================================================================
        // 🔥 TAMBAHAN BARU: MANIPULASI DATA DATA KTP/KK UNTUK RT (SAMA SEPERTI MASYARAKAT)
        // =========================================================================
        $collection = $data->getCollection()->map(function ($item) {

            // Sembunyikan file_pdf jika belum selesai
            if ($item->status !== 'selesai') {
                $item->file_pdf = null;
            }

            // Ubah link KTP & KK menjadi secure route stream API
            $item->file_ktp = url("api/skbm/file/{$item->id_skbm}/ktp");
            $item->file_kk = url("api/skbm/file/{$item->id_skbm}/kk");

            // 🛠️ PERBAIKAN: Ubah link Surat Pengantar RT menjadi secure API stream
            if (!empty($item->file_pengantar_rt)) {
                $item->file_pengantar_rt = url("api/skbm/file/{$item->id_skbm}/pengantar");
            }

            // Tambahkan ini di dalam blok map() fungsi-fungsi GET Anda:
            if (!empty($item->file_pdf)) {
                $item->file_pdf = url("api/skbm/file/{$item->id_skbm}/pdf");
            }

            // Format nama pengaju sesuai role-nya
            if ($item->submitted_by === 'masyarakat') {
                $item->submitted_by_nama = $item->submitterMasyarakat->nama ?? null;
            } else {
                $item->submitted_by_nama = $item->submitterUser->nama ?? null;
            }

            // Format penanggalan untuk log verifikasi
            $item->rt_validated_at_formatted = $item->rt_validated_at
                ? $item->rt_validated_at->format('d-m-Y H:i')
                : null;

            $item->perangkat_validated_at_formatted = $item->perangkat_validated_at
                ? $item->perangkat_validated_at->format('d-m-Y H:i')
                : null;

            $item->kepala_desa_validated_at_formatted = $item->kepala_desa_validated_at
                ? $item->kepala_desa_validated_at->format('d-m-Y H:i')
                : null;

            return $item;
        });

        // Set kembali collection yang sudah dimodifikasi ke dalam objek paginator
        $data->setCollection($collection);

        return response()->json([
            'success' => true,
            'message' => 'Daftar SKBM untuk RT yang sedang login',
            'pagination' => [
                'current_page' => $data->currentPage(),
                'per_page'     => $data->perPage(),
                'total'        => $data->total(),
                'last_page'    => $data->lastPage(),
            ],
            'data' => $data->items(),
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Gagal mengambil data',
            'error' => $e->getMessage(),
        ], 500);
    }
}

    // GET DATA SKBM BY PERANGKAT DESA TOKEN LOGIN
public function getByPerangkatDesa(Request $request)
{
    try {
        // Ambil user Perangkat Desa dari token Sanctum
        $perangkat = $request->user();

        // Validasi role (menyesuaikan tokenCan berdasarkan sistem Anda)
        if (!$perangkat || ($perangkat->tokenCan('perangkat_desa') === false && $perangkat->tokenCan('perangkat') === false)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: hanya Perangkat Desa yang bisa mengakses data ini',
            ], 401);
        }

        // Jumlah data per halaman (default 10)
        $perPage = $request->get('per_page', 10);

        // Query + pagination data SKBM
        $data = SuketBelumMenikah::with([
                'rt:id,nama,jabatan,role',
                'perangkat:id,nama,jabatan,role',
                'perangkatValidator:id,nama,jabatan,role',
                'kepala_desa:id,nama,jabatan,role',
                'masyarakat:id,nama,email,nik',
                'submitterUser:id,nama,role',
                'submitterMasyarakat:id,nama'
            ])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        // =========================================================================
        // 🔥 TAMBAHAN BARU: MANIPULASI DATA BERKAS KTP/KK UNTUK PERANGKAT DESA
        // =========================================================================
        $collection = $data->getCollection()->map(function ($item) {

            // Sembunyikan file_pdf jika belum selesai
            if ($item->status !== 'selesai') {
                $item->file_pdf = null;
            }

            // Ubah link KTP & KK menjadi secure route stream API
            $item->file_ktp = url("api/skbm/file/{$item->id_skbm}/ktp");
            $item->file_kk = url("api/skbm/file/{$item->id_skbm}/kk");

            // 🛠️ PERBAIKAN: Ubah link Surat Pengantar RT menjadi secure API stream
            if (!empty($item->file_pengantar_rt)) {
                $item->file_pengantar_rt = url("api/skbm/file/{$item->id_skbm}/pengantar");
            }

            // Tambahkan ini di dalam blok map() fungsi-fungsi GET Anda:
            if (!empty($item->file_pdf)) {
                $item->file_pdf = url("api/skbm/file/{$item->id_skbm}/pdf");
            }

            // Format nama pengaju sesuai role
            if ($item->submitted_by === 'masyarakat') {
                $item->submitted_by_nama = $item->submitterMasyarakat->nama ?? null;
            } else {
                $item->submitted_by_nama = $item->submitterUser->nama ?? null;
            }

            // Format tanggal log verifikasi
            $item->rt_validated_at_formatted = $item->rt_validated_at
                ? $item->rt_validated_at->format('d-m-Y H:i')
                : null;

            $item->perangkat_validated_at_formatted = $item->perangkat_validated_at
                ? $item->perangkat_validated_at->format('d-m-Y H:i')
                : null;

            $item->kepala_desa_validated_at_formatted = $item->kepala_desa_validated_at
                ? $item->kepala_desa_validated_at->format('d-m-Y H:i')
                : null;

            return $item;
        });

        // Set kembali collection yang sudah dimodifikasi ke dalam objek paginator
        $data->setCollection($collection);

        // =========================================================================
        // RESPONSE UTAMA (SESUAI BAWAAN SCRIPT ANDA)
        // =========================================================================
        return response()->json([
            'success' => true,
            'message' => 'Daftar SKBM untuk perangkat desa',
            'pagination' => [
                'current_page' => $data->currentPage(),
                'per_page'     => $data->perPage(),
                'total'        => $data->total(),
                'last_page'    => $data->lastPage(),
            ],
            'data' => $data->items(),
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Gagal mengambil data',
            'error' => $e->getMessage(),
        ], 500);
    }
}


   // GET DATA SKBM BY KEPALA DESA TOKEN LOGIN
    public function getByKepalaDesa(Request $request)
    {
        try {
            $user = $request->user();

            // Validasi token: wajib punya ability kepala_desa
            if (!$user || !$user->tokenCan('kepala_desa')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: kepala desa belum login',
                ], 401);
            }

            // jumlah data per halaman (default 10)
            $perPage = $request->get('per_page', 10);

            // Kepala desa bisa melihat semua SKBM
            $data = SuketBelumMenikah::with([
                    'rt:id,nama,jabatan,role',
                    'perangkat:id,nama,jabatan,role',
                    'perangkatValidator:id,nama,jabatan,role',
                    'kepala_desa:id,nama,jabatan,role',
                    'masyarakat:id,nama,email,nik',
                    'submitterUser:id,nama,role',
                    'submitterMasyarakat:id,nama'
                ])
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);

            // =========================================================================
            // 🔥 TAMBAHAN BARU: MANIPULASI DATA BERKAS KTP/KK UNTUK KEPALA DESA
            // =========================================================================
            $collection = $data->getCollection()->map(function ($item) {

                // Sembunyikan file_pdf jika belum selesai
                if ($item->status !== 'selesai') {
                    $item->file_pdf = null;
                }

                // Ubah link KTP & KK menjadi secure route stream API
                $item->file_ktp = url("api/skbm/file/{$item->id_skbm}/ktp");
                $item->file_kk = url("api/skbm/file/{$item->id_skbm}/kk");

                // 🛠️ PERBAIKAN: Ubah link Surat Pengantar RT menjadi secure API stream
                if (!empty($item->file_pengantar_rt)) {
                    $item->file_pengantar_rt = url("api/skbm/file/{$item->id_skbm}/pengantar");
                }

                // Tambahkan ini di dalam blok map() fungsi-fungsi GET Anda:
                if (!empty($item->file_pdf)) {
                    $item->file_pdf = url("api/skbm/file/{$item->id_skbm}/pdf");
                }

                // Format nama pengaju sesuai role
                if ($item->submitted_by === 'masyarakat') {
                    $item->submitted_by_nama = $item->submitterMasyarakat->nama ?? null;
                } else {
                    $item->submitted_by_nama = $item->submitterUser->nama ?? null;
                }

                // Format tanggal log verifikasi
                $item->rt_validated_at_formatted = $item->rt_validated_at
                    ? $item->rt_validated_at->format('d-m-Y H:i')
                    : null;

                $item->perangkat_validated_at_formatted = $item->perangkat_validated_at
                    ? $item->perangkat_validated_at->format('d-m-Y H:i')
                    : null;

                $item->kepala_desa_validated_at_formatted = $item->kepala_desa_validated_at
                    ? $item->kepala_desa_validated_at->format('d-m-Y H:i')
                    : null;

                return $item;
            });

            // Set kembali collection yang sudah dimodifikasi ke dalam objek paginator
            $data->setCollection($collection);

            return response()->json([
                'success' => true,
                'message' => 'Daftar SKBM untuk kepala desa',
                'pagination' => [
                    'current_page' => $data->currentPage(),
                    'per_page'     => $data->perPage(),
                    'total'        => $data->total(),
                    'last_page'    => $data->lastPage(),
                ],
                'data' => $data->items(),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

// GET DATA SKBM BY SUPER ADMIN TOKEN LOGIN
    public function getAll(Request $request)
    {
        try {
            $user = $request->user();

            // Validasi token: wajib punya ability super_admin
            if (!$user || !$user->tokenCan('super_admin')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: super admin belum login',
                ], 401);
            }

            // Super admin bisa melihat semua SKBM
            $data = SuketBelumMenikah::with([
                    'rt:id,nama,jabatan,role',
                    'perangkat:id,nama,jabatan,role',
                    'perangkatValidator:id,nama,jabatan,role',
                    'kepala_desa:id,nama,jabatan,role',
                    'masyarakat:id,nama,email,nik',
                    'submitterUser:id,nama,role',
                    'submitterMasyarakat:id,nama'
                ])
                ->orderBy('created_at', 'desc')
                ->get();

            // =========================================================================
            // 🔥 TAMBAHAN BARU: MANIPULASI DATA BERKAS KTP/KK UNTUK SUPER ADMIN
            // =========================================================================
            $data = $data->map(function ($item) {

                // Sembunyikan file_pdf jika belum selesai
                if ($item->status !== 'selesai') {
                    $item->file_pdf = null;
                }

                // Ubah link KTP & KK menjadi secure route stream API
                $item->file_ktp = url("api/skbm/file/{$item->id_skbm}/ktp");
                $item->file_kk = url("api/skbm/file/{$item->id_skbm}/kk");

                // 🛠️ PERBAIKAN: Ubah link Surat Pengantar RT menjadi secure API stream
                if (!empty($item->file_pengantar_rt)) {
                    $item->file_pengantar_rt = url("api/skbm/file/{$item->id_skbm}/pengantar");
                }
                // Tambahkan ini di dalam blok map() fungsi-fungsi GET Anda:
                if (!empty($item->file_pdf)) {
                    $item->file_pdf = url("api/skbm/file/{$item->id_skbm}/pdf");
                }

                // Format nama pengaju sesuai role
                if ($item->submitted_by === 'masyarakat') {
                    $item->submitted_by_nama = $item->submitterMasyarakat->nama ?? null;
                } else {
                    $item->submitted_by_nama = $item->submitterUser->nama ?? null;
                }

                // Format tanggal log verifikasi
                $item->rt_validated_at_formatted = $item->rt_validated_at
                    ? $item->rt_validated_at->format('d-m-Y H:i')
                    : null;

                $item->perangkat_validated_at_formatted = $item->perangkat_validated_at
                    ? $item->perangkat_validated_at->format('d-m-Y H:i')
                    : null;

                $item->kepala_desa_validated_at_formatted = $item->kepala_desa_validated_at
                    ? $item->kepala_desa_validated_at->format('d-m-Y H:i')
                    : null;

                return $item;
            });

            return response()->json([
                'success' => true,
                'message' => 'Daftar SKBM (super admin)',
                'count' => $data->count(),
                'data' => $data
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    //TAMBAH
public function viewSecureFile(Request $request, $id_skbm, $type)
{
    try {
        // 1. Ambil data SKBM
        $skbm = SuketBelumMenikah::find($id_skbm);
        if (!$skbm) {
            return response()->json(['message' => 'Data tidak ditemukan'], 404);
        }

        // 2. Ambil token string dari Header ATAU Query String (?token=...)
        $tokenString = $request->bearerToken() ?? $request->query('token');
        $user = null;

        if ($tokenString) {
            // Jika token mengandung karakter pipe '|' (contoh: 231|Ur6G0O...), pecah stringnya
            if (strpos($tokenString, '|') !== false) {
                [$id, $tokenString] = explode('|', $tokenString, 2);
            }

            // Cari token di database kueri Sanctum
            $accessToken = \Laravel\Sanctum\PersonalAccessToken::findToken($tokenString);

            if ($accessToken) {
                $user = $accessToken->tokenable; // Mendapatkan objek User / Masyarakat
                
                // Pasang token ke instance user agar fungsi tokenCan() bisa bekerja
                if ($user) {
                    $user->withAccessToken($accessToken);
                }
            }
        }

        // 3. Logika Validasi Otoritas Akses
        $isOwner = ($user && $user->tokenCan('masyarakat') && (int)$user->id === (int)$skbm->masyarakat_id);
        $isAparatValid = ($user && in_array($user->role, ['rt', 'perangkat_desa', 'kepala_desa', 'super_admin']));

        // Tentukan path file asli
        // Di dalam fungsi viewSecureFile Anda, sesuaikan penentuan path-nya:
        $dbPath = '';
        if ($type === 'ktp') {
            $dbPath = $skbm->file_ktp;
        } elseif ($type === 'kk') {
            $dbPath = $skbm->file_kk;
        } elseif ($type === 'pengantar') {
            $dbPath = $skbm->file_pengantar_rt;
        } elseif ($type === 'pdf') {
            $dbPath = $skbm->file_pdf; // 🔥 Mengarah ke kolom file_pdf terenkripsi
        }

        $realFilePath = storage_path('app/' . $dbPath);

        // Jika file asli tidak ditemukan di server
        if (empty($dbPath) || !file_exists($realFilePath)) {
            return response()->json(['message' => 'Berkas fisik tidak ditemukan di server'], 404);
        }

        // =========================================================================
        // 4. JIKA LOLOS VALIDASI -> Dekripsi File Dinamis & Tampilkan Jernih
        // =========================================================================
        if ($isOwner || $isAparatValid) {
            try {
                // Ambil konten biner terenkripsi dari berkas fisik
                $encryptedContent = file_get_contents($realFilePath);
                
                // Proses Dekripsi menggunakan Facade Crypt Laravel
                $decryptedContent = \Illuminate\Support\Facades\Crypt::decrypt($encryptedContent);

                // Deteksi tipe konten berdasarkan ekstensi file asli agar browser/front-end tidak salah render
                $extension = strtolower(pathinfo($realFilePath, PATHINFO_EXTENSION));
                $mimeTypes = [
                    'pdf'  => 'application/pdf',
                    'png'  => 'image/png',
                    'jpg'  => 'image/jpeg',
                    'jpeg' => 'image/jpeg'
                ];
                $contentType = $mimeTypes[$extension] ?? 'application/octet-stream';

                // Kembalikan response berupa data mentah file jernih hasil dekripsi dengan Header Mime-Type yang sesuai
                return response($decryptedContent)->header('Content-Type', $contentType);

            } catch (\Exception $e) {
                return response()->json(['message' => 'Gagal membuka dekripsi berkas. Berkas rusak atau kunci enkripsi salah.'], 500);
            }
        }

        // 5. JIKA TIDAK LOLOS VALIDASI -> Berikan Gambar Sensor/Blur Placeholder
        $blurredPlaceholder = public_path('uploads/placeholder_blur.png');
        
        if (file_exists($blurredPlaceholder)) {
            return response()->file($blurredPlaceholder);
        }

        return response()->json(['message' => 'Unauthorized Access. Berkas disensor.'], 403);

    } catch (\Exception $e) {
        return response()->json([
            'message' => 'Terjadi kesalahan sistem',
            'error' => $e->getMessage()
        ], 500);
    }
}

    // POST DATA AJUAN SKBM BY MASYARAKAT
    public function store(Request $request)
{
     // =====================================================
    // ✅ (1) VALIDASI TOKEN MASYARAKAT (WAJIB ADA)
    // =====================================================
    $masyarakat = $request->user();

    if (!$masyarakat || !$masyarakat->tokenCan('masyarakat')) {
        return response()->json([
            'success' => false,
            'message' => 'Unauthorized: anda bukan masyarakat'
        ], 401);
    }
    
    $validated = $request->validate([
        'nomor_surat' => 'nullable|string|max:50',
        'masyarakat_id' => 'required|exists:masyarakats,id',
        'nama' => 'required|string|max:255',
        'jenis_kelamin' => 'required|string|in:Laki-laki,Perempuan',
        'ttl' => 'required|string|max:255',
        'agama' => 'required|string|max:100',
        'pekerjaan' => 'required|string|max:255',
        'nik' => 'required|string|max:20',
        'alamat' => 'required|string|max:255',
        'kewarganegaraan' => 'required|string|max:100',
        'pendidikan' => 'required|string|max:100',
        'rt_id' => 'required|exists:users,id',
        'file' => 'nullable|file|mimes:jpg,jpeg,png,pdf,doc,docx|max:5120',
        'status_perkawinan' => 'required|string|max:50',
        'alasan' => 'required|string',
        'keterangan' => 'nullable|string',
        'keperluan' => 'required|string|max:255',
        'poin_ii' => 'nullable|string',
        'file_ktp' => 'required|file|mimes:jpg,jpeg,png,pdf|max:5120',
        'file_kk'  => 'required|file|mimes:jpg,jpeg,png,pdf|max:5120',
        'ttd_masyarakat' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        'ttd_rt' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        'ttd_kades' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
    ]);

     if ((int) $validated['masyarakat_id'] !== (int) $masyarakat->id) {
        return response()->json([
            'success' => false,
            'message' => 'Anda tidak berhak mengajukan Surat Keterangan Belum Menikah untuk masyarakat lain'
        ], 403);
    }

    // Ambil RT dari users
    $rt = User::where('id', $validated['rt_id'])->where('role', 'rt')->first();
    if (!$rt) {
        return response()->json([
            'success' => false,
            'message' => 'ID RT tidak valid'
        ], 422);
    }

    // Set no_rt dari tabel users
    $validated['no_rt'] = $rt->no_rt;

    // Upload file jika ada
    if ($request->hasFile('file')) {
        $folder = public_path('uploads/skbm');
        if (!file_exists($folder)) mkdir($folder, 0777, true);

        $file = $request->file('file');
        $filename = time() . '_' . $file->getClientOriginalName();
        $file->move($folder, $filename);

        $validated['file'] = url('uploads/skbm/' . $filename);
    }

   /// ===============================
    // UPLOAD & ENKRIPSI FILE KTP (NAMA SANGAT ACAK)
    // ===============================
    if ($request->hasFile('file_ktp')) {
        $folder = storage_path('app/private/skbm/ktp');
        if (!file_exists($folder)) mkdir($folder, 0777, true);

        $file = $request->file('file_ktp');
        
        // 🔥 MENGGUNAKAN STR::RANDOM(40) UNTUK STRING ACAK YANG KUAT
        $randomName = \Illuminate\Support\Str::random(40);
        $filename = 'ktp_' . $randomName . '.' . $file->getClientOriginalExtension();
        $targetPath = $folder . '/' . $filename;

        // Ambil konten file lalu enkripsi sebelum disimpan
        $fileContent = file_get_contents($file->getRealPath());
        $encryptedContent = \Illuminate\Support\Facades\Crypt::encrypt($fileContent);
        
        file_put_contents($targetPath, $encryptedContent);

        $validated['file_ktp'] = 'private/skbm/ktp/' . $filename;
    }

    // ===============================
    // UPLOAD & ENKRIPSI FILE KK (NAMA SANGAT ACAK)
    // ===============================
    if ($request->hasFile('file_kk')) {
        $folder = storage_path('app/private/skbm/kk');
        if (!file_exists($folder)) mkdir($folder, 0777, true);

        $file = $request->file('file_kk');
        
        // 🔥 MENGGUNAKAN STR::RANDOM(40) UNTUK STRING ACAK YANG KUAT
        $randomName = \Illuminate\Support\Str::random(40);
        $filename = 'kk_' . $randomName . '.' . $file->getClientOriginalExtension();
        $targetPath = $folder . '/' . $filename;

        // Ambil konten file lalu enkripsi sebelum disimpan
        $fileContent = file_get_contents($file->getRealPath());
        $encryptedContent = \Illuminate\Support\Facades\Crypt::encrypt($fileContent);
        
        file_put_contents($targetPath, $encryptedContent);

        $validated['file_kk'] = 'private/skbm/kk/' . $filename;
    }

     // ===============================
    // UPLOAD TTD MASYARAKAT
    // ===============================
    if ($request->hasFile('ttd_masyarakat')) {
        $folder = public_path('uploads/skbm/ttd/masyarakat');
        if (!file_exists($folder)) mkdir($folder, 0777, true);

        $file = $request->file('ttd_masyarakat');
        $filename = 'ttd_masyarakat_' . time() . '.' . $file->getClientOriginalExtension();
        $file->move($folder, $filename);

        $validated['ttd_masyarakat'] = 'uploads/skbm/ttd/masyarakat/' . $filename;
    }

    // ===============================
    // UPLOAD TTD RT ✅ (BARU)
    // ===============================
    if ($request->hasFile('ttd_rt')) {
        $folder = public_path('uploads/skbm/ttd/rt');
        if (!file_exists($folder)) mkdir($folder, 0777, true);

        $file = $request->file('ttd_rt');
        $filename = 'ttd_rt_' . time() . '.' . $file->getClientOriginalExtension();
        $file->move($folder, $filename);

        $validated['ttd_rt'] = 'uploads/skbm/ttd/rt/' . $filename;
    }

    // ===============================
    // UPLOAD TTD KADES
    // ===============================
    if ($request->hasFile('ttd_kades')) {
        $folder = public_path('uploads/skbm/ttd/kades');
        if (!file_exists($folder)) mkdir($folder, 0777, true);

        $file = $request->file('ttd_kades');
        $filename = 'ttd_kades_' . time() . '.' . $file->getClientOriginalExtension();
        $file->move($folder, $filename);

        $validated['ttd_kades'] = 'uploads/skbm/ttd/kades/' . $filename;
    }

        // Set default
        $validated['status'] = 'diproses rt';
        $validated['perangkat_id'] = null;
        $validated['file_pdf'] = null;
        $validated['file_pengantar_rt'] = null;
        $validated['rt_validated_at'] = null;
        $validated['perangkat_validated_at'] = null;
        $validated['kepala_desa_validated_at'] = null;
        $validated['file_ktp'] = $validated['file_ktp'] ?? null;
        $validated['file_kk']  = $validated['file_kk'] ?? null;
        $validated['ttd_rt'] = $validated['ttd_rt'] ?? null;
        $validated['ttd_kades'] = $validated['ttd_kades'] ?? null;

        $skbm = SuketBelumMenikah::create($validated);

         // 🔔 NOTIFIKASI KE RT TUJUAN
    Notification::create([
        'user_id'    => $skbm->rt_id,
        'surat_type' => 'SKBM',
        'surat_id'   => $skbm->id_skbm,
        'title'      => 'Pengajuan Surat Keterangan Belum Menikah Baru',
        'message'    => 'Ada pengajuan Surat Keterangan Belum Menikah baru dari masyarakat.',
    ]);

        return response()->json([
            'success' => true,
            'message' => 'Pengajuan Surat Keterangan Belum Menikah berhasil dibuat',
            'data' => $skbm->load(['rt:id,nama,role,no_rt', 'masyarakat:id,nama,email,nik']),
        ], 201);
    }

     // POST DATA AJUAN SKBM BY KETUA RT
    public function storeByRT(Request $request)
{
    // =====================================================
    // ✅ VALIDASI TOKEN RT
    // =====================================================
    $rtUser = $request->user();

    if (!$rtUser || !$rtUser->tokenCan('rt')) {
        return response()->json([
            'success' => false,
            'message' => 'Unauthorized: anda bukan RT'
        ], 401);
    }

    $validated = $request->validate([
        'nomor_surat' => 'nullable|string|max:50',
        'masyarakat_id' => 'nullable|exists:masyarakats,id', // optional
        'nama' => 'required|string|max:255',
        'jenis_kelamin' => 'required|string|in:Laki-laki,Perempuan',
        'ttl' => 'required|string|max:255',
        'agama' => 'required|string|max:100',
        'pekerjaan' => 'required|string|max:255',
        'nik' => 'required|string|max:20',
        'alamat' => 'required|string|max:255',
        'kewarganegaraan' => 'required|string|max:100',
        'pendidikan' => 'required|string|max:100',
        'status_perkawinan' => 'required|string|max:50',
        'alasan' => 'required|string',
        'keterangan' => 'nullable|string',
        'keperluan' => 'required|string|max:255',
        'poin_ii' => 'nullable|string',

        'file_ktp' => 'required|file|mimes:jpg,jpeg,png,pdf|max:5120',
        'file_kk'  => 'required|file|mimes:jpg,jpeg,png,pdf|max:5120',

        'ttd_masyarakat' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        'ttd_rt' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
    ]);

    // =====================================================
    // ✅ AUTO SET RT DARI LOGIN
    // =====================================================
    $validated['rt_id'] = $rtUser->id;
    $validated['no_rt'] = $rtUser->no_rt;

   /// ===============================
    // UPLOAD & ENKRIPSI FILE KTP (NAMA SANGAT ACAK)
    // ===============================
    if ($request->hasFile('file_ktp')) {
        $folder = storage_path('app/private/skbm/ktp');
        if (!file_exists($folder)) mkdir($folder, 0777, true);

        $file = $request->file('file_ktp');
        
        // 🔥 MENGGUNAKAN STR::RANDOM(40) UNTUK STRING ACAK YANG KUAT
        $randomName = \Illuminate\Support\Str::random(40);
        $filename = 'ktp_' . $randomName . '.' . $file->getClientOriginalExtension();
        $targetPath = $folder . '/' . $filename;

        // Ambil konten file lalu enkripsi sebelum disimpan
        $fileContent = file_get_contents($file->getRealPath());
        $encryptedContent = \Illuminate\Support\Facades\Crypt::encrypt($fileContent);
        
        file_put_contents($targetPath, $encryptedContent);

        $validated['file_ktp'] = 'private/skbm/ktp/' . $filename;
    }

    // ===============================
    // UPLOAD & ENKRIPSI FILE KK (NAMA SANGAT ACAK)
    // ===============================
    if ($request->hasFile('file_kk')) {
        $folder = storage_path('app/private/skbm/kk');
        if (!file_exists($folder)) mkdir($folder, 0777, true);

        $file = $request->file('file_kk');
        
        // 🔥 MENGGUNAKAN STR::RANDOM(40) UNTUK STRING ACAK YANG KUAT
        $randomName = \Illuminate\Support\Str::random(40);
        $filename = 'kk_' . $randomName . '.' . $file->getClientOriginalExtension();
        $targetPath = $folder . '/' . $filename;

        // Ambil konten file lalu enkripsi sebelum disimpan
        $fileContent = file_get_contents($file->getRealPath());
        $encryptedContent = \Illuminate\Support\Facades\Crypt::encrypt($fileContent);
        
        file_put_contents($targetPath, $encryptedContent);

        $validated['file_kk'] = 'private/skbm/kk/' . $filename;
    }

    if ($request->hasFile('ttd_masyarakat')) {
        $folder = public_path('uploads/skbm/ttd/masyarakat');
        if (!file_exists($folder)) mkdir($folder, 0777, true);

        $file = $request->file('ttd_masyarakat');
        $filename = 'ttd_masyarakat_' . time() . '.' . $file->getClientOriginalExtension();
        $file->move($folder, $filename);

        $validated['ttd_masyarakat'] = 'uploads/skbm/ttd/masyarakat/' . $filename;
    }

    if ($request->hasFile('ttd_rt')) {
        $folder = public_path('uploads/skbm/ttd/rt');
        if (!file_exists($folder)) mkdir($folder, 0777, true);

        $file = $request->file('ttd_rt');
        $filename = 'ttd_rt_' . time() . '.' . $file->getClientOriginalExtension();
        $file->move($folder, $filename);

        $validated['ttd_rt'] = 'uploads/skbm/ttd/rt/' . $filename;
    }

    // =====================================================
    // ✅ DEFAULT STATUS
    // =====================================================
    $validated['status'] = 'diproses rt';
    $validated['perangkat_id'] = null;
    $validated['file_pdf'] = null;
    $validated['rt_validated_at'] = null;
    $validated['file_pengantar_rt'] = null;
    $validated['perangkat_validated_at'] = null;
    $validated['kepala_desa_validated_at'] = null;
    $validated['submitted_by'] = 'rt';
    $validated['submitted_by_id'] = $rtUser->id;
    

    // =====================================================
    // ✅ SIMPAN
    // =====================================================
    $skbm = SuketBelumMenikah::create($validated);

    // =====================================================
    // 🔔 NOTIF KE PERANGKAT DESA
    // =====================================================
    Notification::create([
        'user_id'    => User::where('role', 'perangkat')->first()->id ?? null,
        'surat_type' => 'SKBM',
        'surat_id'   => $skbm->id_skbm,
        'title'      => 'Pengajuan surat keterangan belum menikah dari RT',
        'message'    => 'RT telah mengajukan dan memverifikasi SKBM.',
    ]);

    return response()->json([
        'success' => true,
        'message' => 'Pengajuan surat keterangan belum menikah oleh RT berhasil',
        'data' => $skbm->load(['rt:id,nama,no_rt']),
    ], 201);
}

    public function storeByPerangkat(Request $request)
    {
        // =====================================================
        // ✅ VALIDASI TOKEN PERANGKAT
        // =====================================================
        $user = $request->user();

        if (!$user || !$user->tokenCan('perangkat_desa')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: anda bukan perangkat desa'
            ], 401);
        }

        // =====================================================
        // ✅ VALIDASI INPUT
        // =====================================================
       $validated = $request->validate([
        'nomor_surat' => 'nullable|string|max:50',
        'masyarakat_id' => 'nullable|exists:masyarakats,id',

        'rt_id' => 'nullable|exists:users,id',
        'nama_rt_manual' => 'nullable|string|max:255',

        'no_surat_pengantar' => [
            'required',
            'regex:/^\d+\/\d+\/\d+$/',
            Rule::unique('rt_pengantar_numbers', 'no_surat_pengantar'),
        ],

        'nama' => 'required|string|max:255',
        'jenis_kelamin' => 'required|in:Laki-laki,Perempuan',
        'ttl' => 'required|string|max:255',
        'agama' => 'required|string|max:100',
        'pekerjaan' => 'required|string|max:255',
        'nik' => 'required|string|max:20',
        'alamat' => 'required|string|max:255',
        'kewarganegaraan' => 'required|string|max:100',
        'pendidikan' => 'required|string|max:100',
        'status_perkawinan' => 'required|string|max:50',
        'alasan' => 'required|string',
        'keperluan' => 'required|string|max:255',

        'file_pengantar_rt' => 'required|file|mimes:jpg,jpeg,png,pdf|max:5120',
        'file_ktp' => 'required|file|mimes:jpg,jpeg,png,pdf|max:5120',
        'file_kk'  => 'required|file|mimes:jpg,jpeg,png,pdf|max:5120',

        'ttd_masyarakat' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
    ], [
        'no_surat_pengantar.required' => 'Nomor surat pengantar wajib diisi.',
        'no_surat_pengantar.regex' => 'Format nomor harus 123/456/789.',
        'no_surat_pengantar.unique' => 'Nomor surat pengantar RT sudah digunakan.',
    ]);

      // =====================================================
// ✅ HANDLE RT (PILIH ATAU MANUAL)
// =====================================================
if (!empty($validated['rt_id'])) {

    $rt = User::where('id', $validated['rt_id'])
        ->where('role', 'rt')
        ->first();

    if (!$rt) {
        return response()->json([
            'success' => false,
            'message' => 'RT tidak valid'
        ], 422);
    }

    $validated['rt_id'] = $rt->id;
    $validated['no_rt'] = $rt->no_rt;
    $validated['nama_rt'] = $rt->nama;

} else if (!empty($request->nama_rt_manual)) {

    // 🔥 RT manual (tanpa akun)
    $validated['rt_id'] = null;
    $validated['no_rt'] = $request->nama_rt_manual;
    $validated['nama_rt'] = $request->nama_rt_manual;

} else {
    return response()->json([
        'success' => false,
        'message' => 'RT wajib diisi (pilih atau input manual)'
    ], 422);
}

        // =====================================================
        // ✅ SET USER
        // =====================================================
        $validated['perangkat_id'] = $user->id;

        $validated['status'] = 'diproses perangkat desa';
        $validated['submitted_by'] = 'perangkat_desa';
        $validated['submitted_by_id'] = $user->id;

        $validated['rt_validated_at'] = now(); // karena offline

        /// ===============================
    // UPLOAD & ENKRIPSI FILE KTP (NAMA SANGAT ACAK)
    // ===============================
    if ($request->hasFile('file_ktp')) {
        $folder = storage_path('app/private/skbm/ktp');
        if (!file_exists($folder)) mkdir($folder, 0777, true);

        $file = $request->file('file_ktp');
        
        // 🔥 MENGGUNAKAN STR::RANDOM(40) UNTUK STRING ACAK YANG KUAT
        $randomName = \Illuminate\Support\Str::random(40);
        $filename = 'ktp_' . $randomName . '.' . $file->getClientOriginalExtension();
        $targetPath = $folder . '/' . $filename;

        // Ambil konten file lalu enkripsi sebelum disimpan
        $fileContent = file_get_contents($file->getRealPath());
        $encryptedContent = \Illuminate\Support\Facades\Crypt::encrypt($fileContent);
        
        file_put_contents($targetPath, $encryptedContent);

        $validated['file_ktp'] = 'private/skbm/ktp/' . $filename;
    }

    // ===============================
    // UPLOAD & ENKRIPSI FILE KK (NAMA SANGAT ACAK)
    // ===============================
    if ($request->hasFile('file_kk')) {
        $folder = storage_path('app/private/skbm/kk');
        if (!file_exists($folder)) mkdir($folder, 0777, true);

        $file = $request->file('file_kk');
        
        // 🔥 MENGGUNAKAN STR::RANDOM(40) UNTUK STRING ACAK YANG KUAT
        $randomName = \Illuminate\Support\Str::random(40);
        $filename = 'kk_' . $randomName . '.' . $file->getClientOriginalExtension();
        $targetPath = $folder . '/' . $filename;

        // Ambil konten file lalu enkripsi sebelum disimpan
        $fileContent = file_get_contents($file->getRealPath());
        $encryptedContent = \Illuminate\Support\Facades\Crypt::encrypt($fileContent);
        
        file_put_contents($targetPath, $encryptedContent);

        $validated['file_kk'] = 'private/skbm/kk/' . $filename;
    }

        // TTD masyarakat
        if ($request->hasFile('ttd_masyarakat')) {
            $folder = public_path('uploads/skbm/ttd/masyarakat');
            if (!file_exists($folder)) mkdir($folder, 0777, true);

            $file = $request->file('ttd_masyarakat');
            $filename = 'ttd_masyarakat_' . time() . '.' . $file->getClientOriginalExtension();
            $file->move($folder, $filename);

            $validated['ttd_masyarakat'] = 'uploads/skbm/ttd/masyarakat/' . $filename;
        }

         // =====================================================================
        // 🔥 PERBAIKAN: UPLOAD & ENKRIPSI FILE PENGANTAR RT OFFLINE (KE PRIVATE FOLDER)
        // =====================================================================
        if ($request->hasFile('file_pengantar_rt')) {
            $folder = storage_path('app/private/pengantar-rt-skbm');
            if (!file_exists($folder)) mkdir($folder, 0777, true);

            $file = $request->file('file_pengantar_rt');
            
            // Menggunakan Str::random(40) agar nama file sangat acak
            $randomName = \Illuminate\Support\Str::random(40);
            $filename = 'pengantar_' . $randomName . '.' . $file->getClientOriginalExtension();
            $targetPath = $folder . '/' . $filename;

            // Membaca binary file lalu dienkripsi sebelum ditulis ke harddisk
            $fileContent = file_get_contents($file->getRealPath());
            $encryptedContent = \Illuminate\Support\Facades\Crypt::encrypt($fileContent);
            file_put_contents($targetPath, $encryptedContent);

            // Set path relatif untuk database
            $validated['file_pengantar_rt'] = 'private/pengantar-rt-skbm/' . $filename;

            // ✅ TAMBAHAN PENTING
            $validated['pengantar_rt_type'] = 'offline';
        }
        
        if (!$request->hasFile('file_pengantar_rt')) {
            return response()->json([
                'error' => 'File tidak terbaca',
                'all' => $request->all()
            ]);
        }

        // =====================================================
        // DEFAULT
        // =====================================================
        $validated['file_pdf'] = null;
        $validated['kepala_desa_validated_at'] = null;
        $validated['perangkat_validated_at'] = null;

        // =====================================================
        // SIMPAN
        // =====================================================
        $skbm = SuketBelumMenikah::create($validated);

        // =====================================================
        // 🔔 NOTIF KE KEPALA DESA
        // =====================================================
        Notification::create([
            'role'       => 'kepala_desa',
            'surat_type' => 'SKBM',
            'surat_id'   => $skbm->id_skbm,
            'title' => 'SKBM Menunggu Verifikasi Perangkat Desa',
            'message' => 'Pengajuan SKBM dari perangkat desa (dengan pengantar RT offline) menunggu verifikasi.',
        ]);

        RtPengantarNumber::create([
            'no_surat_pengantar' => $validated['no_surat_pengantar'],
            'surat_type'         => 'SKBM',
            'surat_id'           => $skbm->id_skbm,
            'rt_id'              => $validated['rt_id'], // dari pilihan perangkat
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Pengajuan surat keterangan belum menikah oleh perangkat desa berhasil',
            'data' => $skbm
        ], 201);
    }

    //POST DATA AJUKAN KEMBALI SURAT DITOLAK
    public function ajukanKembali(Request $request, $id)
{
    try {
        // === Ambil user masyarakat ===
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 401);
        }

         // === Ambil SKBM ===
        $skbm = SuketBelumMenikah::where('id_skbm', $id)->first();

        // ✅ FIX: cek dulu sebelum dipakai
        if (!$skbm) {
            return response()->json([
                'success' => false,
                'message' => 'Data surat keterangan belum menikah tidak ditemukan'
            ], 404);
        }

            // === CEK HAK AKSES BERDASARKAN PENGAJU ===
        if ($skbm->submitted_by === 'masyarakat') {

            if (!$user->tokenCan('masyarakat') || $skbm->masyarakat_id != $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anda tidak berhak mengajukan ulang data ini'
                ], 403);
            }

        } else if ($skbm->submitted_by === 'rt') {

            if (!$user->tokenCan('rt') || $skbm->rt_id != $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Hanya RT yang mengajukan yang bisa ajukan ulang'
                ], 403);
            }

        } else if ($skbm->submitted_by === 'perangkat_desa') {

           if ($user->tokenCan('perangkat_desa')) {

    if ($request->filled('rt_id')) {
        $rt = User::where('id', $request->rt_id)
            ->where('role', 'rt')
            ->first();

        if (!$rt) {
            return response()->json([
                'success' => false,
                'message' => 'RT tidak valid'
            ], 422);
        }

    } else {

        if (!$request->filled('nama_rt_manual')) {
            return response()->json([
                'success' => false,
                'message' => 'Nama RT manual wajib diisi jika tidak memilih RT'
            ], 422);
        }

        $validated['rt_id'] = null;
        $validated['nama_rt'] = $request->nama_rt_manual;
    }

    // 🔥 FIX DI SINI
    if (!$request->hasFile('file_pengantar_rt') && !$skbm->file_pengantar_rt) {
        return response()->json([
            'success' => false,
            'message' => 'File pengantar RT wajib diupload'
        ], 422);
    }

    $validated['rt_validated_at'] = now();
}

        } else {
            return response()->json([
                'success' => false,
                'message' => 'submitted_by tidak valid'
            ], 400);
        }

        if (!$skbm) {
            return response()->json([
                'success' => false,
                'message' => 'Data SKBM tidak ditemukan atau bukan milik anda'
            ], 404);
        }

        if ($skbm->status !== 'ditolak') {
            return response()->json([
                'success' => false,
                'message' => 'Pengajuan hanya dapat diedit jika statusnya ditolak'
            ], 400);
        }

        // === Validasi input FormData ===
        $validated = $request->validate([
            'nama' => 'required|string|max:255',
            'jenis_kelamin' => 'required|string|in:Laki-laki,Perempuan',
            'ttl' => 'required|string|max:255',
            'agama' => 'required|string|max:100',
            'pekerjaan' => 'required|string|max:255',
            'nik' => 'required|string|max:20',
            'alamat' => 'required|string|max:255',
            'kewarganegaraan' => 'required|string|max:100',
            'pendidikan' => 'required|string|max:100', 
            'status_perkawinan' => 'required|string|max:50',
            'rt_id' => 'nullable|exists:users,id',
             'nama_rt_manual' => 'nullable|string|max:255',
            'alasan' => 'required|string',
            'keterangan' => 'nullable|string',
            'file' => 'nullable|file|mimes:jpg,jpeg,png,pdf,doc,docx|max:5120',
            'keperluan' => 'required|string|max:255',
            'poin_ii' => 'nullable|string',
            'file_ktp' => 'nullable|mimes:jpg,jpeg,png,pdf|max:4096',
            'file_kk' => 'nullable|mimes:jpg,jpeg,png,pdf|max:4096',
            'ttd_masyarakat' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
                       'no_surat_pengantar' => [
                    'nullable',
                    'regex:/^\d+\/\d+\/\d+$/',
                    function ($attribute, $value, $fail) use ($skbm) {

                        $existing = RtPengantarNumber::where('no_surat_pengantar', $value)
    ->where('id', '!=', optional(
        RtPengantarNumber::where('surat_id', $skbm->id_skbm)
            ->where('surat_type', 'SKBM')
            ->first()//perbaikan baru
    )->id)
    ->first();

if ($existing) {
    $fail('Nomor surat pengantar sudah digunakan oleh surat lain.');
}
                    }
                ],
            'file_pengantar_rt' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
        ]);

       // Tentukan tujuan sesuai siapa yg menolak sebelumnya
    $tujuan = $skbm->rejected_by;
        $keteranganTolak = $skbm->keterangan ? strtolower($skbm->keterangan) : '';

        // Jika ditolak oleh role kepala_desa, cek apakah penolaknya Sekretaris Lurah via text keterangan
        if ($tujuan === 'kepala_desa' && str_contains($keteranganTolak, 'sekretaris lurah')) {
            $tujuan = 'sekretaris_lurah';
        }

        $tujuanLabel = match ($tujuan) {
            'rt' => 'Ketua RT',
            'perangkat_desa' => 'Perangkat Desa',
            'sekretaris_lurah' => 'Sekretaris Lurah',
            'kepala_desa' => 'Lurah',
            default => ucfirst(str_replace('_', ' ', $tujuan)),
        };


            // ===============================
// 🔥 FIX: HANDLE NAMA RT DI SINI (SETELAH VALIDASI)
// ===============================
if ($user->tokenCan('perangkat_desa')) {

    if ($request->filled('rt_id')) {

        $rt = User::where('id', $request->rt_id)
            ->where('role', 'rt')
            ->first();

        if (!$rt) {
            return response()->json([
                'success' => false,
                'message' => 'RT tidak valid'
            ], 422);
        }

        $validated['rt_id'] = $rt->id;
        $validated['nama_rt'] = $rt->nama;

    } else if ($request->filled('nama_rt_manual')) {

        $validated['rt_id'] = null;
        $validated['nama_rt'] = $request->nama_rt_manual;
    }
}

        if ($tujuan === 'rt') {

       // Ambil RT baru jika diubah
        if ($request->filled('rt_id')) {
            $rt = User::where('id', $validated['rt_id'])->where('role', 'rt')->first();
            if (!$rt) {
                return response()->json([
                    'success' => false,
                    'message' => 'ID RT tidak valid'
                ], 422);
            }
            $validated['no_rt'] = $rt->no_rt;
        } else {
            $validated['no_rt'] = $skbm->no_rt; // tetap jika tidak diubah
        }


            $validated['status'] = 'diproses rt';
            $validated['keterangan'] = $request->keterangan ?? "Diajukan kembali ke Ketua RT";

            // RESET semua validasi jika ditolak RT
            $validated['rt_validated_at'] = null;
            $validated['rt_validated_by'] = null;

            $validated['perangkat_validated_at'] = null;
            $validated['perangkat_validated_by'] = null;

            $validated['kepala_desa_validated_at'] = null;
            $validated['kepala_desa_validated_by'] = null;
        }

  else if ($tujuan === 'perangkat_desa') {

                // =====================================================
                // JIKA PENGAJUAN ULANG VIA PERANGKAT (OFFLINE RT)
                // =====================================================
                if ($user->tokenCan('perangkat_desa')) {

                if ($request->filled('rt_id')) {

                    $rt = User::where('id', $request->rt_id)
                        ->where('role', 'rt')
                        ->first();

                    if (!$rt) {
                        return response()->json([
                            'success' => false,
                            'message' => 'RT tidak valid'
                        ], 422);
                    }

                    $validated['rt_id'] = $rt->id;
                    $validated['nama_rt'] = $rt->nama;
                    $validated['perangkat_id'] = $user->id;

                } else {

                    if (!$request->filled('nama_rt_manual')) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Nama RT manual wajib diisi jika tidak memilih RT'
                        ], 422);
                    }

                    $validated['rt_id'] = null;
                    $validated['nama_rt'] = $request->nama_rt_manual;
                }

                    // wajib file pengantar
                    if (!$request->hasFile('file_pengantar_rt') && !$skbm->file_pengantar_rt) {
                        return response()->json([
                            'success' => false,
                            'message' => 'File pengantar RT wajib diupload'
                        ], 422);
                    }

                    $validated['rt_validated_at'] = now();

            }

            $validated['status'] = 'diproses perangkat desa';
            $validated['keterangan'] = $request->keterangan ?? "Diajukan kembali ke Perangkat Desa";


            // RT validation tetap
            // $validated['rt_validated_at'] = $skbm->rt_validated_at;
            $validated['rt_validated_by'] = $skbm->rt_validated_by;

            // perangkat desa reset
            $validated['perangkat_validated_at'] = null;
            $validated['perangkat_validated_by'] = null;

            // kepala desa reset
            $validated['kepala_desa_validated_at'] = null;
            $validated['kepala_desa_validated_by'] = null;
        }

        // TAMBAHAN BARU: JIKA DITOLAK OLEH SEKRETARIS LURAH
      else if ($tujuan === 'sekretaris_lurah') {
            $validated['status'] = 'diproses sekretaris lurah';
            $validated['keterangan'] = $request->keterangan ?? "Diajukan kembali ke Sekretaris Lurah";

            $validated['rt_id'] = $skbm->rt_id;
            $validated['perangkat_id'] = $skbm->perangkat_id;

            $validated['rt_validated_by'] = $skbm->rt_validated_by;
            $validated['perangkat_validated_at'] = $skbm->perangkat_validated_at;
            $validated['perangkat_validated_by'] = $skbm->perangkat_validated_by;
            
            $validated['kepala_desa_validated_at'] = null;
            $validated['kepala_desa_validated_by'] = null;
        }

        else if ($tujuan === 'kepala_desa') {

            $validated['status'] = 'diproses kepala desa';
            $validated['keterangan'] = $request->keterangan ?? "Diajukan kembali ke Kepala Desa";

            $validated['rt_id'] = $skbm->rt_id;
            $validated['perangkat_id'] = $skbm->perangkat_id;

            // RT tetap
            // $validated['rt_validated_at'] = $skbm->rt_validated_at;
            $validated['rt_validated_by'] = $skbm->rt_validated_by;

            // perangkat tetap
            $validated['perangkat_validated_at'] = $skbm->perangkat_validated_at;
            $validated['perangkat_validated_by'] = $skbm->perangkat_validated_by;

            // kepala desa saja reset
            $validated['kepala_desa_validated_at'] = null;
            $validated['kepala_desa_validated_by'] = null;
        }

        else {
            return response()->json([
                'success' => false,
                'message' => 'rejected_by tidak valid'
            ], 400);
        }

               // Upload file (opsional)
        if ($request->hasFile('file')) {
            $folder = public_path('uploads/skbm');
            if (!file_exists($folder)) mkdir($folder, 0777, true);

            $file = $request->file('file');
            $filename = time().'_'.$file->getClientOriginalName();
            $file->move($folder, $filename);

            $validated['file'] = 'uploads/skbm/'.$filename;
        }
         // =====================================================================
        // 🔥 PERBAIKAN: Upload & Enkripsi FILE KTP (Ke Folder Private)
        // =====================================================================
        if ($request->hasFile('file_ktp')) {
            // Hapus file terenkripsi lama di storage private jika ada
            if ($skbm->file_ktp && file_exists(storage_path('app/' . $skbm->file_ktp))) {
                unlink(storage_path('app/' . $skbm->file_ktp));
            }

            $folder = storage_path('app/private/skbm/ktp');
            if (!file_exists($folder)) mkdir($folder, 0777, true);

            $fileKtp = $request->file('file_ktp');
            $randomName = \Illuminate\Support\Str::random(40);
            $filenameKtp = 'ktp_' . $randomName . '.' . $fileKtp->getClientOriginalExtension();
            $targetPath = $folder . '/' . $filenameKtp;

            // Enkripsi konten
            $fileContent = file_get_contents($fileKtp->getRealPath());
            $encryptedContent = \Illuminate\Support\Facades\Crypt::encrypt($fileContent);
            file_put_contents($targetPath, $encryptedContent);

            $validated['file_ktp'] = 'private/skbm/ktp/' . $filenameKtp;
        }

        // =====================================================================
        // 🔥 PERBAIKAN: Upload & Enkripsi FILE KK (Ke Folder Private)
        // =====================================================================
        if ($request->hasFile('file_kk')) {
            // Hapus file terenkripsi lama di storage private jika ada
            if ($skbm->file_kk && file_exists(storage_path('app/' . $skbm->file_kk))) {
                unlink(storage_path('app/' . $skbm->file_kk));
            }

            $folder = storage_path('app/private/skbm/kk');
            if (!file_exists($folder)) mkdir($folder, 0777, true);

            $fileKk = $request->file('file_kk');
            $randomName = \Illuminate\Support\Str::random(40);
            $filenameKk = 'kk_' . $randomName . '.' . $fileKk->getClientOriginalExtension();
            $targetPath = $folder . '/' . $filenameKk;

            // Enkripsi konten
            $fileContent = file_get_contents($fileKk->getRealPath());
            $encryptedContent = \Illuminate\Support\Facades\Crypt::encrypt($fileContent);
            file_put_contents($targetPath, $encryptedContent);

            $validated['file_kk'] = 'private/skbm/kk/' . $filenameKk;
        }

       // =====================================================================
        // 🔥 PERBAIKAN: Upload & Enkripsi FILE PENGANTAR RT (Ke Folder Private)
        // =====================================================================
        if ($request->hasFile('file_pengantar_rt')) {
            // Hapus file pengantar lama di storage private jika ada eksistensinya
            if ($skbm->file_pengantar_rt && file_exists(storage_path('app/' . $skbm->file_pengantar_rt))) {
                unlink(storage_path('app/' . $skbm->file_pengantar_rt));
            }

            $folder = storage_path('app/private/pengantar-rt-skbm');

            if (!file_exists($folder)) {
                mkdir($folder, 0777, true);
            }

            $filePengantar = $request->file('file_pengantar_rt');
            // Penamaan super acak menggunakan helper Str::random(40)
            $randomName = \Illuminate\Support\Str::random(40);
            $filenamePengantar = 'pengantar_' . $randomName . '.' . $filePengantar->getClientOriginalExtension();
            $targetPath = $folder . '/' . $filenamePengantar;

            // Membaca file biner lalu dienkripsi sebelum ditulis
            $fileContent = file_get_contents($filePengantar->getRealPath());
            $encryptedContent = \Illuminate\Support\Facades\Crypt::encrypt($fileContent);
            file_put_contents($targetPath, $encryptedContent);

            $validated['file_pengantar_rt'] = 'private/pengantar-rt-skbm/' . $filenamePengantar;
            $validated['pengantar_rt_type'] = 'offline';
        }

            // ============================
            // Upload TTD Masyarakat (opsional)
            // ============================
        if ($request->hasFile('ttd_masyarakat')) {

        // 🔥 HAPUS FILE LAMA
        if ($skbm->ttd_masyarakat && file_exists(public_path($skbm->ttd_masyarakat))) {
            unlink(public_path($skbm->ttd_masyarakat));
        }

        $folder = public_path('uploads/skbm/ttd/masyarakat');

                $fileTtd = $request->file('ttd_masyarakat');
                $filenameTtd = 'ttd_masyarakat_' . time() . '.' . $fileTtd->getClientOriginalExtension();
                $fileTtd->move($folder, $filenameTtd);

                $validated['ttd_masyarakat'] = 'uploads/skbm/ttd/masyarakat/' . $filenameTtd;
            }


        // file_pdf harus direset
        $validated['file_pdf'] = null;

        // Reset rejected_by
        $validated['rejected_by'] = null;

        // Update
        $skbm->update($validated);

        // =====================================================
        // 🔥 RESET FIELD PENTING
        // =====================================================
        $validated['file_pdf'] = null;
        $validated['rejected_by'] = null;

        // =====================================================
        // 🔥 UPDATE
        // =====================================================
        $skbm->update($validated);

        // =====================================================
        // 🔥 NOTIFIKASI DINAMIS
        // =====================================================
        $pengaju = $skbm->submitted_by === 'rt' ? 'RT' : 'masyarakat';

        if ($tujuan === 'rt') {
            Notification::create([
                'user_id'    => $skbm->rt_id,
                'role'       => 'rt',
                'surat_type' => 'SKBM',
                'surat_id'   => $skbm->id_skbm,
                'title'      => 'Ajuan Ulang surat keterangan belum menikah',
                'message'    => "Terdapat ajuan ulang surat keterangan belum menikah dari {$pengaju}.",
            ]);
        }

        else if ($tujuan === 'perangkat_desa') {
            Notification::create([
                'role'       => 'perangkat_desa',
                'surat_type' => 'SKBM',
                'surat_id'   => $skbm->id_skbm,
                'title'      => 'Ajuan Ulang surat keterangan belum menikah',
                'message'    => "Terdapat ajuan ulang surat keterangan belum menikah dari {$pengaju}.",
            ]);
        }

        else if ($tujuan === 'kepala_desa') {
            Notification::create([
                'role'       => 'kepala_desa',
                'surat_type' => 'SKBM',
                'surat_id'   => $skbm->id_skbm,
                'title'      => 'Ajuan Ulang surat keterangan belum menikah',
                'message'    => "surat keterangan belum menikah telah diajukan ulang oleh {$pengaju}.",
            ]);
        }

        // =====================================================
        // 🔥 SIMPAN NOMOR PENGANTAR RT (JIKA ADA)
        // =====================================================
if (!empty($validated['no_surat_pengantar'])) {

    $existingPengantar = RtPengantarNumber::where('surat_id', $skbm->id_skbm)
        ->where('surat_type', 'SKBM')
        ->first();

    // =====================================
    // ✅ CEK DUPLIKAT (KECUALI MILIK SENDIRI)
    // =====================================
   $duplicate = RtPengantarNumber::where('no_surat_pengantar', $validated['no_surat_pengantar'])
    ->where('id', '!=', optional($existingPengantar)->id)
    ->exists();//perbaikan baru

    if ($duplicate) {
        return response()->json([
            'success' => false,
            'message' => 'Nomor surat pengantar sudah digunakan oleh surat lain'
        ], 422);
    }

    // =====================================
    // ✅ SIMPAN / UPDATE
    // =====================================
    if ($existingPengantar) {

    // DEBUG
$check = RtPengantarNumber::where('no_surat_pengantar', $validated['no_surat_pengantar'])->get();

if ($check->count() > 1) {
    return response()->json([
        'error_debug' => $check
    ]);
}

 // update hanya kalau berubah
       if ($existingPengantar) {

    $isSameNumber = $existingPengantar->no_surat_pengantar === $validated['no_surat_pengantar'];
    $isSameRt     = ($existingPengantar->rt_id ?? null) === ($validated['rt_id'] ?? null);
    $isSameNamaRt = ($existingPengantar->nama_rt ?? null) === ($validated['nama_rt'] ?? null);

    // ✅ JIKA SEMUA SAMA → JANGAN UPDATE
    if ($isSameNumber && $isSameRt && $isSameNamaRt) {
        // skip update (INI YANG FIX BUG KAMU)
    } else {
        $existingPengantar->update([
            'no_surat_pengantar' => $validated['no_surat_pengantar'],
            'rt_id' => $validated['rt_id'] ?? null,
            'nama_rt' => $validated['nama_rt'] ?? null,
        ]);
    }

}

    } else {
        RtPengantarNumber::create([
            'surat_id' => $skbm->id_skbm,
            'surat_type' => 'SKBM',
            'no_surat_pengantar' => $validated['no_surat_pengantar'],
            'rt_id' => $validated['rt_id'] ?? null,
            'nama_rt' => $validated['nama_rt'] ?? null,
        ]);
    }
}


        return response()->json([
            'success' => true,
            'message' => "Pengajuan berhasil diajukan kembali ke {$tujuanLabel}",
            'data' => $skbm->fresh()
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Terjadi kesalahan',
            'error' => $e->getMessage()
        ], 500);
    }
    }


    // ✅ PUT: KETUA RT UBAH STATUS & KETERANGAN SKBM
        public function validasiKetuaRT(Request $request, $rt_id, $id_skbm)
{
    // ============================
    // 1. Cek user login harus RT
    // ============================
    $rt = auth()->user();

    if (!$rt || $rt->role !== 'rt') {
        return response()->json([
            'success' => false,
            'message' => 'Akses ditolak. Hanya RT yang bisa memproses.'
        ], 403);
    }

    // ==========================================
    // 2. Validasi kecocokan RT dari token & URL
    // ==========================================
    if ($rt->id != $rt_id) {
        return response()->json([
            'success' => false,
            'message' => 'RT tidak sesuai dengan token login.'
        ], 403);
    }

    // ============================
    // 3. Ambil data SKBM pemohon
    // ============================
    $skbm = SuketBelumMenikah::where('id_skbm', $id_skbm)
        ->where('rt_id', $rt->id)
        ->first();

    if (!$skbm) {
        return response()->json([
            'success' => false,
            'message' => 'Data surat keterangan belum menikah tidak ditemukan.'
        ], 404);
    }

// ==========================================
// 3.5 Validasi status SKBM (RT tidak boleh proses ulang)
// ==========================================
if ($skbm->status === 'ditolak') {
    return response()->json([
        'success' => false,
        'message' => 'Pengajuan sudah ditolak. Silakan tunggu masyarakat mengajukan kembali.'
    ], 422);
}

if ($skbm->status === 'diproses perangkat desa') {
    return response()->json([
        'success' => false,
        'message' => 'Pengajuan sudah diproses oleh Perangkat Desa dan tidak dapat diubah oleh RT.'
    ], 422);
}

if ($skbm->status === 'diproses kepala desa') {
    return response()->json([
        'success' => false,
        'message' => 'Pengajuan sudah diteruskan ke Kepala Desa dan tidak dapat diubah oleh RT.'
    ], 422);
}

if ($skbm->status === 'selesai') {
    return response()->json([
        'success' => false,
        'message' => 'Pengajuan sudah selesai dan tidak dapat diproses ulang oleh RT.'
    ], 422);
}


// ============================
// 4. Validasi input dari RT
// ============================

// 🔹 Base rules
$rules = [
    'status' => 'required|in:diproses perangkat desa,ditolak',
    'keterangan' => 'nullable|string|max:255',

    'no_surat_pengantar' => [
        Rule::requiredIf($request->status === 'diproses perangkat desa'),
        'regex:/^\d+\/\d+\/\d+$/',
        // ✅ VALIDASI KE TABEL PUSAT
        Rule::unique('rt_pengantar_numbers', 'no_surat_pengantar'),
    ],
];

// 🔥 VALIDASI TTD RT (FLEKSIBEL: FILE ATAU STRING)
if ($request->hasFile('ttd_rt')) {
    // Jika dikirim sebagai FILE
    $rules['ttd_rt'] = 'image|mimes:jpg,jpeg,png|max:2048';
} else {
    // Jika dikirim sebagai STRING (hasil getTtdRt)
    $rules['ttd_rt'] = 'nullable|string';
}

$validated = $request->validate(
    $rules,
    [
        'no_surat_pengantar.required' =>
            'Nomor surat pengantar wajib diisi.',
        'no_surat_pengantar.regex' =>
            'Format nomor surat harus 123/456/789.',
        'no_surat_pengantar.unique' =>
            'Nomor surat pengantar sudah digunakan.',
        'ttd_rt.image' =>
            'Tanda tangan RT harus berupa gambar.',
    ]
);



    // ============================
    // 5. Siapkan data update
    // ============================
 $updateData = [
    'status'          => $validated['status'],
    'keterangan'      => $validated['keterangan'] ?? $skbm->keterangan,
    'rt_validated_at' => now(),
];

if ($validated['status'] === 'ditolak') {
    $updateData['no_surat_pengantar'] = null;
    $updateData['file_pengantar_rt'] = null; // ⬅️ PENTING
    $updateData['pengantar_rt_type'] = null; // tambah
    $updateData['rejected_by'] = 'rt';
} else {
    $updateData['no_surat_pengantar'] = $validated['no_surat_pengantar'];
    $updateData['rejected_by'] = null;
}



// ============================
    // 6. UPLOAD TTD RT (TAMBAHAN)
    // ============================
    if ($request->hasFile('ttd_rt')) {
        $folder = public_path('uploads/skbm/ttd/rt');
        if (!file_exists($folder)) {
            mkdir($folder, 0777, true);
        }

        $file = $request->file('ttd_rt');
        $filename = 'ttd_rt_' . time() . '.' . $file->getClientOriginalExtension();
        $file->move($folder, $filename);

        $updateData['ttd_rt'] = 'uploads/skbm/ttd/rt/' . $filename;
    }

// ============================
// TENTUKAN TTD RT (UPLOAD > PROFIL)
// ============================
if ($validated['status'] === 'diproses perangkat desa') {

    // ✅ JIKA TIDAK ADA FILE DAN TIDAK ADA STRING → ERROR
    if (
        !$request->hasFile('ttd_rt') &&
        empty($validated['ttd_rt']) &&
        empty($rt->ttd)
    ) {
        return response()->json([
            'success' => false,
            'message' => 'TTD RT belum tersedia. Upload TTD atau lengkapi di profil RT.'
        ], 422);
    }

    // ✅ JIKA TIDAK UPLOAD FILE → PAKAI PROFIL
    if (!$request->hasFile('ttd_rt')) {
        $updateData['ttd_rt'] = $rt->ttd;
    }

    // ⚠️ JIKA UPLOAD FILE → JANGAN TIMPA
    // (karena sudah di-set di step upload)
}






   // ============================
    // 7. Update skbm ke database
    // ============================
    $skbm->update($updateData);

    // ======================================
// 7.5 SIMPAN NO PENGANTAR KE TABEL PUSAT
// ======================================
if ($validated['status'] === 'diproses perangkat desa') {
    RtPengantarNumber::create([
        'no_surat_pengantar' => $validated['no_surat_pengantar'],
        'surat_type'         => 'SKBM',
        'surat_id'           => $skbm->id_skbm,
        'rt_id'              => $rt->id,
    ]);
}


   // ======================================
// 8. Generate PDF Pengantar RT (KONDISIONAL)
// ======================================
if ($validated['status'] === 'diproses perangkat desa') {
    try {
        $pdfUrl = $this->generatePengantarRT($skbm->fresh());

        $skbm->update([
            'file_pengantar_rt' => $pdfUrl,
            'pengantar_rt_type' => 'system' // ✅ TAMBAHAN
        ]);
    } catch (\Exception $e) {
        Log::error("Gagal generate PDF pengantar RT", [
            'error' => $e->getMessage()
        ]);
    }
}

// 🔔 NOTIFIKASI KE PERANGKAT DESA (HANYA JIKA DITERUSKAN)
if ($validated['status'] === 'diproses perangkat desa') {
    Notification::create([
        'role'       => 'perangkat_desa',
        'surat_type' => 'SKBM',
        'surat_id'   => $skbm->id_skbm,
        'title'      => 'Surat keterangan belum menikah Menunggu Verifikasi',
        'message'    => 'Pengajuan surat keterangan belum menikah telah diverifikasi RT.',
    ]);
}

// 🔔 NOTIFIKASI KE MASYARAKAT
if ($validated['status'] === 'diproses perangkat desa') {
    Notification::create([
        'masyarakat_id' => $skbm->masyarakat_id,
        'role'          => 'masyarakat',
        'surat_type'    => 'SKBM',
        'surat_id'      => $skbm->id_skbm,
        'title'         => 'Pengajuan surat keterangan belum menikah Diproses RT',
        'message'       => 'Pengajuan surat keterangan belum menikah Anda telah diverifikasi RT dan diteruskan ke Perangkat Desa.',
    ]);
}

if ($validated['status'] === 'ditolak') {
    Notification::create([
         'masyarakat_id' => $skbm->masyarakat_id,
        'role'          => 'masyarakat',
        'surat_type'    => 'SKBM',
        'surat_id'      => $skbm->id_skbm,
        'title'         => 'Pengajuan surat keterangan belum menikah Ditolak RT',
        'message'       => 'Pengajuan surat keterangan belum menikah Anda ditolak oleh RT. Silakan periksa keterangan.',
    ]);
}

    // ============================
    // 9. Response ke client
    // ============================
    $skbmFresh = $skbm->fresh();

    if (!empty($skbmFresh->ttd_rt)) {
        $skbmFresh->ttd_rt = url($skbmFresh->ttd_rt);
    }

    return response()->json([
    'success' => true,
    'message' => ($validated['status'] === 'diproses perangkat desa')
        ? "Status diproses perangkat desa. Surat pengantar RT tersedia."
        : "Pengajuan ditolak oleh RT. Surat pengantar RT dibatalkan.",
    'data' => $skbmFresh,
]);

    }


public function validasiPerangkatDesa(Request $request, $pd_id, $id_skbm)
{
    try {
        $pd = auth()->user();

        if (!$pd || $pd->role !== 'perangkat_desa') {
            return response()->json([
                'success' => false,
                'message' => 'Akses ditolak. Hanya perangkat desa yang dapat memproses.'
            ], 403);
        }

        if ($pd->id != $pd_id) {
            return response()->json([
                'success' => false,
                'message' => 'Perangkat desa tidak sesuai dengan token login.'
            ], 403);
        }

        $skbm = SuketBelumMenikah::where('id_skbm', $id_skbm)->first();

        if (!$skbm) {
            return response()->json([
                'success' => false,
                'message' => 'Data surat keterangan tidak mampu tidak ditemukan.'
            ], 404);
        }

        // =======================================================
        // VALIDASI STATUS YANG BOLEH DIPROSES PERANGKAT DESA
        // =======================================================
        if ($skbm->status === 'ditolak') {
            return response()->json([
                'success' => false,
                'message' => 'Pengajuan sudah ditolak. Silakan tunggu masyarakat mengajukan kembali.'
            ], 400);
        }

        // PERBAIKAN: Menyesuaikan dengan status alur baru
        if (in_array($skbm->status, ['diproses sekretaris lurah', 'diproses kepala desa'])) {
            return response()->json([
                'success' => false,
                'message' => 'Status tidak dapat diubah karena sudah diteruskan ke tingkat pimpinan (Lurah/Sekkel).'
            ], 400);
        }

        if ($skbm->status === 'diproses rt') {
            return response()->json([
                'success' => false,
                'message' => 'Status tidak dapat diubah karena masih diproses oleh Ketua RT.'
            ], 400);
        }

        if ($skbm->status === 'selesai') {
            return response()->json([
                'success' => false,
                'message' => 'Pengajuan sudah selesai dan tidak dapat diproses ulang oleh Perangkat Desa.'
            ], 400);
        }

        if ($skbm->status !== 'diproses perangkat desa') {
            return response()->json([
                'success' => false,
                'message' => 'Status pengajuan tidak valid untuk diproses oleh Perangkat Desa.'
            ], 400);
        }

        // PERBAIKAN: Diubah dari 'diproses kepala desa' menjadi 'diproses sekretaris lurah'
        $validated = $request->validate([
            'status'      => 'required|in:diproses sekretaris lurah,ditolak',
            'keterangan'  => 'nullable|string|max:255',
            'nomor_surat' => 'nullable|string|max:100',
            'poin_ii'     => 'required_unless:status,ditolak|string',
        ]);

        if (!empty($validated['nomor_surat'])) {
            $existing = SuketBelumMenikah::where('nomor_surat', $validated['nomor_surat'])
                ->where('id_skbm', '!=', $id_skbm)
                ->first();

            if ($existing) {
                return response()->json([
                    'success' => false,
                    'message' => 'Nomor surat sudah digunakan pada pengajuan lain.'
                ], 422);
            }
        }

        $updateData = [
            'status'                 => $validated['status'],
            'keterangan'             => $validated['keterangan'] ?? $skbm->keterangan,
            'perangkat_validated_by' => $pd->id,
            'perangkat_validated_at' => now(),
            'poin_ii'                => $validated['poin_ii'] ?? $skbm->poin_ii,
            'file_pdf'               => null,
        ];

        if ($validated['status'] === 'ditolak') {
            $updateData['rejected_by'] = 'perangkat_desa';
            $updateData['nomor_surat'] = null;
            $updateData['file_pdf']    = null;
            $updateData['poin_ii']     = null;
        }

        // PERBAIKAN: Menyesuaikan kondisi penulisan nomor surat ke status baru
        if ($validated['status'] === 'diproses sekretaris lurah') {
            if (!empty($validated['nomor_surat'])) {
                $updateData['nomor_surat'] = $validated['nomor_surat'];
            }
        }

        $skbm->update($updateData);

        // 🔔 NOTIFIKASI KE SEKRETARIS LURAH
        if ($validated['status'] === 'diproses sekretaris lurah') {
            Notification::create([
                'role'       => 'kepala_desa', // Tetap dikirim ke role kepala_desa agar muncul di dashboard mereka
                'surat_type' => 'SKBM',
                'surat_id'   => $skbm->id_skbm,
                'title'      => 'SKBM Menunggu Persetujuan Sekretaris Lurah',
                'message'    => 'Pengajuan SKBM menunggu verifikasi dari Sekretaris Lurah.',
            ]);

            // 🔔 NOTIFIKASI KE MASYARAKAT
            Notification::create([
                'masyarakat_id' => $skbm->masyarakat_id,
                'role'          => 'masyarakat',
                'surat_type'    => 'SKBM',
                'surat_id'      => $skbm->id_skbm,
                'title'         => 'SKBM Diteruskan ke Sekretaris Lurah',
                'message'       => 'Pengajuan SKBM Anda telah disetujui oleh Perangkat Desa dan diteruskan ke Sekretaris Lurah.',
            ]);
        }

        if ($validated['status'] === 'ditolak') {
            Notification::create([
                'masyarakat_id' => $skbm->masyarakat_id,
                'role'          => 'masyarakat',
                'surat_type'    => 'SKBM',
                'surat_id'      => $skbm->id_skbm,
                'title'         => 'Pengajuan SKBM Ditolak',
                'message'       => 'Pengajuan SKBM Anda ditolak oleh Perangkat Desa. Silakan periksa keterangan untuk melihat perbaikan.',
            ]);
        }

        $message = match ($validated['status']) {
            'diproses sekretaris lurah' => 'Data berhasil diverifikasi dan diteruskan ke Sekretaris Lurah.',
            'ditolak'                   => 'Pengajuan ditolak oleh Perangkat Desa.',
        };

        return response()->json([
            'success' => true,
            'message' => $message,
            'data'    => $skbm->fresh()
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Terjadi kesalahan saat memproses',
            'error'   => $e->getMessage()
        ], 500);
    }
}


    // ✅ PUT: KEPALA DESA UBAH STATUS SELESAI & KETERANGAN SKBM
    // ❌ SKBM yang sudah selesai seharusnya TIDAK boleh diproses ulang (Keucali Super Admin)
public function validasiKepalaDesa(Request $request, $kd_id, $id_skbm)
{
    try {
        // 1. Validasi user login (role harus kepala desa)
        $kd = auth()->user();
        if (!$kd || $kd->role !== 'kepala_desa') {
            return response()->json([
                'success' => false,
                'message' => 'Akses ditolak. Hanya kepala desa yang dapat memproses.'
            ], 403);
        }

        // 2. Validasi ID kepala desa
        $targetKd = \App\Models\User::where('id', $kd_id)
                        ->where('role', 'kepala_desa')
                        ->first();

        if (!$targetKd) {
            return response()->json([
                'success' => false,
                'message' => 'ID yang dikirimkan tidak terdaftar sebagai otoritas Kepala Desa yang valid.'
            ], 403);
        }

        if ($kd->id != $kd_id) {
            Log::info("User {$kd->nama} (ID: {$kd->id}) melakukan validasi atas nama ID: {$kd_id}");
        }

        // 3. Ambil data SKBM
        $skbm = SuketBelumMenikah::where('id_skbm', $id_skbm)->first();
        if (!$skbm) {
            return response()->json([
                'success' => false,
                'message' => 'Data SKBM tidak ditemukan.'
            ], 404);
        }

        $currentStatus = trim($skbm->status);
        $jabatanUser = strtolower(trim($kd->jabatan)); 

        // 4. Pembagian Validasi Input Berdasarkan Fase Jabatan
        if ($currentStatus === 'diproses sekretaris lurah') {
            if ($jabatanUser !== 'sekretaris lurah') {
                return response()->json([
                    'success' => false,
                    'message' => 'Akses ditolak. Tahap ini hanya bisa divalidasi oleh Sekretaris Lurah.'
                ], 403);
            }

            $validated = $request->validate([
                'status'     => 'required|in:diproses kepala desa,ditolak',
                'keterangan' => 'nullable|string|max:255',
            ]);

        } elseif ($currentStatus === 'diproses kepala desa') {
            if ($jabatanUser !== 'lurah') {
                return response()->json([
                    'success' => false,
                    'message' => 'Akses ditolak. Tahap akhir ini hanya bisa divalidasi oleh Lurah.'
                ], 403);
            }

            $rules = [
                'status'     => 'required|in:selesai,ditolak',
                'keterangan' => 'nullable|string|max:255',
            ];

            if ($request->hasFile('ttd_kades')) {
                $rules['ttd_kades'] = 'image|mimes:jpg,jpeg,png|max:2048';
            } else {
                $rules['ttd_kades'] = 'nullable|string';
            }

            $validated = $request->validate($rules);

        } else {
            return response()->json([
                'success' => false,
                'message' => 'Status SKBM saat ini tidak valid untuk diproses pada tahap Anda (' . $currentStatus . ').'
            ], 422);
        }

        $newStatus = $validated['status'];

        // 5. Siapkan Array Data Update Dasar
        $updateData = [
            'status'     => $newStatus,
            'keterangan' => $validated['keterangan'] ?? $skbm->keterangan,
        ];

      // 6. Jika DITOLAK (Baik oleh Sekretaris Lurah maupun Lurah)
        if ($newStatus === 'ditolak') {
            // Sesuai koreksi Anda: Kembalikan ke role aslinya 'kepala_desa' agar lolos ENUM DB
            $updateData['rejected_by'] = 'kepala_desa'; 
            $updateData['file_pdf']    = null;
            $updateData['ttd_kades']   = null;

            // Masukkan informasi jabatan penolak ke dalam keterangan secara otomatis jika kosong
            if (empty($validated['keterangan'])) {
                $updateData['keterangan'] = ($jabatanUser === 'sekretaris lurah') 
                    ? 'Permohonan ditolak oleh Sekretaris Lurah.' 
                    : 'Permohonan ditolak oleh Lurah.';
            }
        }

        // 7. Pengondisian TTD Jika Disetujui SELESAI Oleh Lurah
        if ($jabatanUser === 'lurah' && $newStatus === 'selesai') {
            $updateData['kepala_desa_id'] = $kd->id;
            $updateData['kepala_desa_validated_at'] = now();

            if ($request->hasFile('ttd_kades')) {
                $folder = public_path('uploads/skbm/ttd/kades');
                if (!file_exists($folder)) {
                    mkdir($folder, 0777, true);
                }

                $file = $request->file('ttd_kades');
                $filename = 'ttd_kades_' . time() . '.' . $file->getClientOriginalExtension();
                $file->move($folder, $filename);

                $updateData['ttd_kades'] = 'uploads/skbm/ttd/kades/' . $filename;
            } else {
                // Gunakan dari profil jika tidak ada file upload baru
                if (empty($kd->ttd)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Tanda tangan Anda belum diatur di profil. Silakan upload file TTD.'
                    ], 422);
                }
                $updateData['ttd_kades'] = $kd->ttd;
            }
        }

        // 8. SIMPAN DATA
        $skbm->update($updateData);
        $skbmFresh = $skbm->fresh();

        // 9. GENERATE PDF (HANYA JIKA DISETUJUI OLEH LURAH SAMPAI SELESAI)
        if ($jabatanUser === 'lurah' && $newStatus === 'selesai') {
            try {
                $pdfUrl = $this->generatePdf($skbmFresh);
                $skbmFresh->update([
                    'file_pdf' => $pdfUrl
                ]);
            } catch (\Exception $e) {
                Log::error('Gagal generate PDF SKBM oleh Lurah', [
                    'error' => $e->getMessage()
                ]);
            }
        }

        // 10. Format URL Response File
        if (!empty($skbmFresh->ttd_kades)) {
            $skbmFresh->ttd_kades = url($skbmFresh->ttd_kades);
        }
        if (!empty($skbmFresh->file_ktp)) {
            $skbmFresh->file_ktp = url("api/skbm/file/{$skbmFresh->id_skbm}/ktp");
        }
        if (!empty($skbmFresh->file_kk)) {
            $skbmFresh->file_kk = url("api/skbm/file/{$skbmFresh->id_skbm}/kk");
        }
        if (!empty($skbmFresh->file_pengantar_rt)) {
            $skbmFresh->file_pengantar_rt = url("api/skbm/file/{$skbmFresh->id_skbm}/pengantar");
        }
        if (!empty($skbmFresh->file_pdf)) {
            $skbmFresh->file_pdf = url("api/skbm/file/{$skbmFresh->id_skbm}/pdf");
        }

        // ==========================================================================
        // PERBAIKAN NOTIFIKASI: Disesuaikan dengan tahapan jabatan yang memproses
        // ==========================================================================
        if ($jabatanUser === 'sekretaris lurah') {
            if ($newStatus === 'diproses kepala desa') {
                Notification::create([
                    'role'       => 'kepala_desa', // Dikirim kembali ke dashboard pimpinan agar bisa dibaca Lurah
                    'surat_type' => 'SKBM',
                    'surat_id'   => $skbmFresh->id_skbm,
                    'title'      => 'SKBM Menunggu Persetujuan Lurah',
                    'message'    => 'Pengajuan SKBM telah disetujui oleh Sekretaris Lurah dan kini menunggu persetujuan akhir dari Lurah.',
                ]);

                Notification::create([
                    'masyarakat_id' => $skbm->masyarakat_id,
                    'role'          => 'masyarakat',
                    'surat_type'    => 'SKBM',
                    'surat_id'      => $skbmFresh->id_skbm,
                    'title'         => 'SKBM Diteruskan ke Lurah',
                    'message'       => 'Pengajuan SKBM Anda telah disetujui oleh Sekretaris Lurah dan diteruskan ke Lurah.',
                ]);
            } elseif ($newStatus === 'ditolak') {
                Notification::create([
                    'masyarakat_id' => $skbmFresh->masyarakat_id, // ✅ FIX: diubah dari $skbm ke $skbmFresh
                    'role'          => 'masyarakat',
                    'surat_type'    => 'SKBM',
                    'surat_id'      => $skbmFresh->id_skbm, // ✅ FIX: diubah dari $skbm ke $skbmFresh
                    'title'         => 'SKBM Ditolak Sekretaris Lurah',
                    'message'       => 'Pengajuan SKBM Anda ditolak oleh Sekretaris Lurah. Silakan periksa keterangan.',
                ]);
            }
        } 
        
      if ($jabatanUser === 'lurah') {
            if ($newStatus === 'selesai') {
                Notification::create([
                    'masyarakat_id' => $skbmFresh->masyarakat_id, // ✅ FIX: diubah dari $skbm ke $skbmFresh
                    'role'          => 'masyarakat',
                    'surat_type'    => 'SKBM',
                    'surat_id'      => $skbmFresh->id_skbm,
                    'title'         => 'SKBM Selesai',
                    'message'       => 'Pengajuan SKBM Anda telah disetujui oleh Lurah dan surat sudah dapat diunduh.',
                ]);
            } elseif ($newStatus === 'ditolak') {
                Notification::create([
                    'masyarakat_id' => $skbmFresh->masyarakat_id, // ✅ FIX: diubah dari $skbm ke $skbmFresh
                    'role'          => 'masyarakat',
                    'surat_type'    => 'SKBM',
                    'surat_id'      => $skbmFresh->id_skbm,
                    'title'         => 'SKBM Ditolak Lurah',
                    'message'       => 'Pengajuan SKBM Anda ditolak oleh Lurah. Silakan periksa keterangan untuk melihat perbaikan.',
                ]);
            }
        }

        // Tentukan pesan response json balikannya
        $returnMessage = 'Surat berhasil diproses.';
        if ($jabatanUser === 'sekretaris lurah') {
            $returnMessage = $newStatus === 'diproses kepala desa' ? 'SKBM berhasil diverifikasi dan diteruskan ke Lurah.' : 'SKBM ditolak oleh Sekretaris Lurah.';
        } elseif ($jabatanUser === 'lurah') {
            $returnMessage = $newStatus === 'selesai' ? 'SKBM selesai dan PDF berhasil dibuat.' : 'SKBM ditolak oleh Lurah.';
        }

        return response()->json([
            'success' => true,
            'message' => $returnMessage,
            'data'    => $skbmFresh
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Terjadi kesalahan.',
            'error'   => $e->getMessage()
        ], 500);
    }
}

    public function exportSKBM(Request $request)
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
                'message' => 'Anda tidak memiliki akses untuk export data SKBM.',
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

        $finalFilename = 'SKBM_' .
            $safeFilename . '_' .
            now()->format('Y-m-d_H-i-s') . '.xlsx';

        // 📤 EXPORT EXCEL
        return Excel::download(
            new SKBMExport,
            $finalFilename
        );
    }

    public function exportZipSKBM(Request $request)
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
            'message' => 'Anda tidak memiliki akses untuk export data SKBM.',
        ], 403);
    }

    // 🧾 VALIDASI INPUT
    $request->validate([
        'filename' => 'required|string|min:3|max:100',
        'password' => 'required|string|min:6',
    ]);

    // 🔑 VALIDASI PASSWORD
    if (!Hash::check($request->password, $user->password)) {
        return response()->json([
            'success' => false,
            'message' => 'Password yang Anda masukkan salah.',
        ], 401);
    }

    // 🧼 SANITASI NAMA FILE
    $safeFilename = Str::slug($request->filename, '_');

    $zipFileName = 'SKBM_' . $safeFilename . '_' . now()->format('Y-m-d_H-i-s') . '.zip';
    $zipPath = public_path('exports/' . $zipFileName);

    // 📁 BUAT FOLDER EXPORT
    if (!File::exists(public_path('exports'))) {
        File::makeDirectory(public_path('exports'), 0777, true);
    }

    $zip = new ZipArchive;

    if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {

        // ===========================
        // 📄 FILE SKBM (DARI DB)
        // ===========================
        $data = SuketBelumMenikah::whereNotNull('file_pdf')->get();

        foreach ($data as $item) {

            if ($item->file_pdf) {
                $pdfPath = parse_url($item->file_pdf, PHP_URL_PATH);
                $filePath = public_path($pdfPath);

                if (file_exists($filePath)) {
                    $zip->addFile(
                        $filePath,
                        'skbm/' . basename($filePath)
                    );
                }
            }
        }

        // ===========================
        // 📎 FILE PENGANTAR RT (SEMUA FILE DALAM FOLDER)
        // ===========================
        $folderPengantar = public_path('uploads/pengantar-rt-skbm');

        if (File::exists($folderPengantar)) {

            $files = File::files($folderPengantar);

            foreach ($files as $file) {

                $ext = strtolower($file->getExtension());
                $allowed = ['pdf', 'jpg', 'jpeg', 'png'];

                if (in_array($ext, $allowed)) {
                    $zip->addFile(
                        $file->getRealPath(),
                        'pengantar_rt/' . $file->getFilename()
                    );
                }
            }
        }

        // ✅ TUTUP ZIP
        $zip->close();

    } else {
        return response()->json([
            'success' => false,
            'message' => 'Gagal membuat file ZIP',
        ], 500);
    }

    // 📥 DOWNLOAD
    return response()->download($zipPath)->deleteFileAfterSend(true);
}

public function deleteAllSKBM(Request $request)
{
    try {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.',
            ], 401);
        }

        if (
            !$user->tokenCan('perangkat_desa') &&
            !$user->tokenCan('super_admin')
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki akses.',
            ], 403);
        }

        $request->validate([
            'password' => 'required|string',
        ]);

        if (!Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Password yang anda masukkan salah!',
            ], 401);
        }

        $data = SuketBelumMenikah::all();

        if ($data->count() === 0) {
            return response()->json([
                'success' => false,
                'message' => 'Data surat kosong',
            ], 400);
        }

        $deletedByRole = $user->tokenCan('super_admin')
            ? 'super_admin'
            : 'perangkat_desa';

        DB::beginTransaction();

        // 🔥 Helper function (di luar loop biar tidak dibuat ulang terus)
        $deleteFile = function ($filePath) {

            if (!$filePath) return;

            // hilangkan domain (http://...)
            $cleanPath = preg_replace('/^https?:\/\/[^\/]+\//', '', $filePath);

            // hilangkan slash depan
            $cleanPath = ltrim($cleanPath, '/');

            $fullPath = public_path($cleanPath);

            if (File::exists($fullPath)) {
                File::delete($fullPath);
            }
        };

        foreach ($data as $item) {

            $deleteFile($item->file_pdf);
            $deleteFile($item->file_pengantar_rt);
            $deleteFile($item->file_ktp);
            $deleteFile($item->file_kk);
            $deleteFile($item->ttd_masyarakat);
            $deleteFile($item->ttd_rt);
            $deleteFile($item->ttd_kades);
        }

        // update deleted_by
        SuketBelumMenikah::query()->update([
            'deleted_by' => $deletedByRole,
        ]);

        // delete database
        SuketBelumMenikah::query()->delete();

        DB::commit();

        return response()->json([
            'success' => true,
            'message' => 'Semua data & file berhasil dihapus',
        ]);

    } catch (\Throwable $e) {

        DB::rollBack();

        return response()->json([
            'success' => false,
            'message' => 'Gagal menghapus data coba sesaat lagi',
            'error' => $e->getMessage(),
        ], 500);
    }
}

public function searchfilter(Request $request)
{
    /**
     * ===============================
     * 1. DETEKSI LOGIN
     * ===============================
     */
    $masyarakat = auth('masyarakat')->user(); // tabel masyarakats
    $user       = auth('sanctum')->user();    // tabel users

    if (!$masyarakat && !$user) {
        return response()->json([
            'success' => false,
            'message' => 'Unauthorized'
        ], 401);
    }

    $query = SuketBelumMenikah::query();

    /**
     * ===============================
     * 2. KUNCI DATA BERDASARKAN ROLE
     * ===============================
     */

    // 🔒 MASYARAKAT → hanya miliknya
    if ($masyarakat) {
        $query->where('masyarakat_id', $masyarakat->id);
    }

    // 🔒 RT → hanya SKBM RT-nya
    if ($user && $user->role === 'rt') {
        $query->where('rt_id', $user->id);
    }

    // 🔓 perangkat_desa / kepala_desa / super_admin
      if ($user) {
        switch ($user->role) {
            case 'perangkat_desa':
                // boleh semua atau wilayah tertentu
                break;

            case 'kepala_desa':
                // full akses
                break;

            case 'super_admin':
                // full akses
                break;
        }
      }

    /**
     * ===============================
     * 3. SEARCH
     * ===============================
     */
    if ($request->filled('search')) {
        $keyword = trim($request->search);

        $query->where(function ($q) use ($keyword) {
            $q->where('nama', 'like', "%{$keyword}%")
              ->orWhere('nik', 'like', "%{$keyword}%");
        });
    }

    /**
     * ===============================
     * 4. FILTER
     * ===============================
     */
    if ($request->filled('status')) {
        $query->where('status', $request->status);
    }

    if ($request->filled('tanggal')) {
        $query->whereDay('created_at', (int) $request->tanggal);
    }

    if ($request->filled('bulan')) {
        $query->whereMonth('created_at', (int) $request->bulan);
    }

    if ($request->filled('tahun')) {
        $query->whereYear('created_at', (int) $request->tahun);
    }

    /**
     * ===============================
     * 5. RESPONSE
     * ===============================
     */
    return response()->json([
        'success' => true,
        'data' => $query
            ->with('masyarakat:id,nama,nik')
            ->orderByDesc('created_at')
            ->paginate(10)
            
    ]);
    
}


     public function generatePdf($skbm)
    {
        try {
            // 🔥 1. Pindahkan folder tujuan ke private storage_path
            $pdfFolder = storage_path('app/private/skbm/pdf');

            if (!file_exists($pdfFolder)) {
                mkdir($pdfFolder, 0777, true);
            }

            // Muat PDF dari view Blade seperti biasa
            $pdf = Pdf::loadView('pdf.skbm', ['data' => $skbm])
                ->setPaper('A4', 'portrait');

            // 🔥 2. Buat nama file sangat acak menggunakan Str::random(40)
            $randomName = \Illuminate\Support\Str::random(40);
            $filename = 'skbm_' . $randomName . '.pdf';
            $fullPath = $pdfFolder . '/' . $filename;

            // 🔥 3. Ambil biner output PDF mentah lalu ENKRIPSI
            $pdfContent = $pdf->output();
            $encryptedContent = \Illuminate\Support\Facades\Crypt::encrypt($pdfContent);

            // Simpan konten terenkripsi ke file fisik
            file_put_contents($fullPath, $encryptedContent);

            // 🔥 4. Kembalikan relative path untuk disimpan di database
            return 'private/skbm/pdf/' . $filename;

        } catch (\Exception $e) {
            Log::error("PDF ERROR: " . $e->getMessage());
            throw new \Exception($e->getMessage());
        }
    }

    public function generatePengantarRT($skbm)
{
    try {
        // 🔥 1. Pindahkan folder tujuan ke private storage_path
        $pdfFolder = storage_path('app/private/pengantar-rt-skbm');

        // Buat folder jika belum ada
        if (!file_exists($pdfFolder)) {
            mkdir($pdfFolder, 0777, true);
        }

        // Muat PDF dari Blade seperti biasa
        $pdf = PDF::loadView('pdf.pengantar_skbm', [
                'skbm' => $skbm
            ])
            ->setPaper('A4', 'portrait');

        // 🔥 2. Buat nama file sangat acak menggunakan Str::random(40)
        $randomName = \Illuminate\Support\Str::random(40);
        $filename = 'pengantar_' . $randomName . '.pdf';
        $fullPath = $pdfFolder . '/' . $filename;

        // 🔥 3. Ambil biner output PDF mentah lalu ENKRIPSI
        $pdfContent = $pdf->output();
        $encryptedContent = \Illuminate\Support\Facades\Crypt::encrypt($pdfContent);

        // Simpan konten terenkripsi ke file fisik
        file_put_contents($fullPath, $encryptedContent);

        // 🔥 4. Kembalikan relative path untuk disimpan di database
        return 'private/pengantar-rt-skbm/' . $filename;

    } catch (\Exception $e) {
        Log::error("ERROR GENERATE PENGANTAR RT: " . $e->getMessage());
        return null;
    }
}
    }
