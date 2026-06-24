<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SuketImunisasiTT;
use App\Models\User;
use App\Models\Masyarakat;
use Illuminate\Support\Facades\Log;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;
use ZipArchive;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use App\Exports\SKITTExport;
use Illuminate\Support\Facades\Hash;
use Dompdf\Options;
use Illuminate\Validation\Rule;
use App\Models\RtPengantarNumber;
use App\Models\Notification;
use Illuminate\Support\Facades\DB;


class SuketImunisasiTTController extends Controller
{
    
 public function countSKITT(Request $request)
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
            $totalSKITT = SuketImunisasiTT::count();

            // ===============================
            // 3. RESPONSE
            // ===============================
            return response()->json([
                'success' => true,
                'data' => [
                    'total_skitt' => $totalSKITT
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil jumlah Surat Keterangan Imunisasi TT',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


    // ✅ GET: /api/skitt
    public function index()
    {
        try {
            $data = SuketImunisasiTT::with([
                'rt' => function ($q) {
                    $q->select('id', 'nama', 'role');
                },
                'masyarakat' => function ($q) {
                    $q->select('id', 'nama', 'email', 'nik');
                }
            ])
            ->orderByDesc('id_skitt')
            ->get();

            return response()->json([
                'success' => true,
                'count' => $data->count(),
                'data' => $data
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data Surat Keterangan Imunisasi TT',
                'error' => $e->getMessage()
            ], 500);
        }
    }

// GET DATA Surat Keterangan Imunisasi TT BY MASYARAKAT (TOKEN + VALIDASI ID) baru diperbaiki
public function getByMasyarakat(Request $request)
{
    try {
        // ===============================
        // 1. VALIDASI TOKEN
        // ===============================
      $masyarakat = $request->user();

        // 🔴 DIUBAH: cukup pastikan token valid & role masyarakat
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
        $paginator = SuketImunisasiTT::where('masyarakat_id', $tokenMasyarakatId)
            ->with([
                'rt:id,nama,jabatan,role',
                    'perangkat:id,nama,jabatan,role',
                    'perangkatValidator:id,nama,jabatan,role',
                    'kepala_desa:id,nama,jabatan,role',
                    'masyarakat:id,nama,email,nik',

                    // 🔥 TAMBAH INI
                    'submitterUser:id,nama,role',
                    'submitterMasyarakat:id,nama'
            ])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
// 🔥 TAMBAHKAN LOG DI SINI
Log::info('Surat Keterangan Imunisasi TT PAGINATION DEBUG', [
    'current_page' => $paginator->currentPage(),
    'per_page'     => $paginator->perPage(),
    'total'        => $paginator->total(),
    'last_page'    => $paginator->lastPage(),
]);
        // ===============================
            // 4. MANIPULASI DATA (AMAN)
            // ===============================
            $collection = $paginator->getCollection()->map(function ($item) {

                // Sembunyikan file_pdf jika belum selesai
                if ($item->status !== 'selesai') {
                    $item->file_pdf = null;
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
            'message' => 'Daftar pengajuan Surat Keterangan Imunisasi TT masyarakat',
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



    // GET DATA Surat Keterangan Imunisasi TT BY KETUA RT TOKEN LOGIN
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
        $data = SuketImunisasiTT::where('rt_id', $rt->id)
            ->with([
                'rt:id,nama,jabatan,role',
                    'perangkat:id,nama,jabatan,role',
                    'perangkatValidator:id,nama,jabatan,role',
                    'kepala_desa:id,nama,jabatan,role',
                    'masyarakat:id,nama,email,nik',

                    // 🔥 TAMBAH INI
                    'submitterUser:id,nama,role',
                    'submitterMasyarakat:id,nama'
            ])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Daftar Surat Keterangan Imunisasi TT untuk RT yang sedang login',
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


    // GET DATA Surat Keterangan Imunisasi TT BY PERANGKAT DESA TOKEN LOGIN
public function getByPerangkatDesa(Request $request)
{
    try {
        $user = $request->user();

        // Validasi token: wajib punya ability perangkat_desa
        if (!$user || !$user->tokenCan('perangkat_desa')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: perangkat desa belum login',
            ], 401);
        }

        // jumlah data per halaman (default 10)
        $perPage = $request->get('per_page', 10);

        // Perangkat desa bisa melihat semua Surat Keterangan Imunisasi TT
        $data = SuketImunisasiTT::with([
                'rt:id,nama,jabatan,role',
                    'perangkat:id,nama,jabatan,role',
                    'perangkatValidator:id,nama,jabatan,role',
                    'kepala_desa:id,nama,jabatan,role',
                    'masyarakat:id,nama,email,nik',

                    // 🔥 TAMBAH INI
                    'submitterUser:id,nama,role',
                    'submitterMasyarakat:id,nama'
            ])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Daftar Surat Keterangan Imunisasi TT untuk perangkat desa',
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


    // GET DATA Surat Keterangan Imunisasi TT BY KEPALA DESA TOKEN LOGIN
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

        // Kepala desa bisa melihat semua Surat Keterangan Imunisasi TT
        $data = SuketImunisasiTT::with([
               'rt:id,nama,jabatan,role',
                    'perangkat:id,nama,jabatan,role',
                    'perangkatValidator:id,nama,jabatan,role',
                    'kepala_desa:id,nama,jabatan,role',
                    'masyarakat:id,nama,email,nik',

                    // 🔥 TAMBAH INI
                    'submitterUser:id,nama,role',
                    'submitterMasyarakat:id,nama'
            ])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Daftar Surat Keterangan Imunisasi TT untuk kepala desa',
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


    // GET DATA Surat Keterangan Imunisasi TT BY SUPER ADMIN TOKEN LOGIN
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

            // Super admin bisa melihat semua Surat Keterangan Imunisasi TT
           $data = SuketImunisasiTT::with([
                'rt:id,nama,jabatan,role',
                    'perangkat:id,nama,jabatan,role',
                    'perangkatValidator:id,nama,jabatan,role',
                    'kepala_desa:id,nama,jabatan,role',
                    'masyarakat:id,nama,email,nik',

                    // 🔥 TAMBAH INI
                    'submitterUser:id,nama,role',
                    'submitterMasyarakat:id,nama'
            ])
            ->orderBy('created_at', 'desc')
            ->get();


            return response()->json([
                'success' => true,
                'message' => 'Daftar Surat Keterangan Imunisasi TT (super admin)',
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

    // POST DATA AJUAN Surat Keterangan Imunisasi TT BY MASYARAKAT
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
        'fasilitas_kesehatan' => 'required|string',
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
            'message' => 'Anda tidak berhak mengajukan Surat Keterangan Imunisasi TT untuk masyarakat lain'
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
        $folder = public_path('uploads/skitt');
        if (!file_exists($folder)) mkdir($folder, 0777, true);

        $file = $request->file('file');
        $filename = time() . '_' . $file->getClientOriginalName();
        $file->move($folder, $filename);

        $validated['file'] = url('uploads/skitt/' . $filename);
    }

    // ===============================
    // UPLOAD FILE KTP
    // ===============================
    if ($request->hasFile('file_ktp')) {
        $folder = public_path('uploads/skitt/ktp');
        if (!file_exists($folder)) mkdir($folder, 0777, true);

        $file = $request->file('file_ktp');
        $filename = 'ktp_' . time() . '.' . $file->getClientOriginalExtension();
        $file->move($folder, $filename);

        $validated['file_ktp'] = 'uploads/skitt/ktp/' . $filename;
    }

    // ===============================
    // UPLOAD FILE KK
    // ===============================
    if ($request->hasFile('file_kk')) {
        $folder = public_path('uploads/skitt/kk');
        if (!file_exists($folder)) mkdir($folder, 0777, true);

        $file = $request->file('file_kk');
        $filename = 'kk_' . time() . '.' . $file->getClientOriginalExtension();
        $file->move($folder, $filename);

        $validated['file_kk'] = 'uploads/skitt/kk/' . $filename;
    }

     // ===============================
    // UPLOAD TTD MASYARAKAT
    // ===============================
    if ($request->hasFile('ttd_masyarakat')) {
        $folder = public_path('uploads/skitt/ttd/masyarakat');
        if (!file_exists($folder)) mkdir($folder, 0777, true);

        $file = $request->file('ttd_masyarakat');
        $filename = 'ttd_masyarakat_' . time() . '.' . $file->getClientOriginalExtension();
        $file->move($folder, $filename);

        $validated['ttd_masyarakat'] = 'uploads/skitt/ttd/masyarakat/' . $filename;
    }

    // ===============================
    // UPLOAD TTD RT ✅ (BARU)
    // ===============================
    if ($request->hasFile('ttd_rt')) {
        $folder = public_path('uploads/skitt/ttd/rt');
        if (!file_exists($folder)) mkdir($folder, 0777, true);

        $file = $request->file('ttd_rt');
        $filename = 'ttd_rt_' . time() . '.' . $file->getClientOriginalExtension();
        $file->move($folder, $filename);

        $validated['ttd_rt'] = 'uploads/skitt/ttd/rt/' . $filename;
    }

    // ===============================
    // UPLOAD TTD KADES
    // ===============================
    if ($request->hasFile('ttd_kades')) {
        $folder = public_path('uploads/skitt/ttd/kades');
        if (!file_exists($folder)) mkdir($folder, 0777, true);

        $file = $request->file('ttd_kades');
        $filename = 'ttd_kades_' . time() . '.' . $file->getClientOriginalExtension();
        $file->move($folder, $filename);

        $validated['ttd_kades'] = 'uploads/skitt/ttd/kades/' . $filename;
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

        $skitt = SuketImunisasiTT::create($validated);

         // 🔔 NOTIFIKASI KE RT TUJUAN
    Notification::create([
        'user_id'    => $skitt->rt_id,
        'surat_type' => 'SKITT',
        'surat_id'   => $skitt->id_skitt,
        'title'      => 'Pengajuan Surat Keterangan Imunisasi TT Baru',
        'message'    => 'Ada pengajuan Surat Keterangan Imunisasi TT baru dari masyarakat.',
    ]);

        return response()->json([
            'success' => true,
            'message' => 'Pengajuan Surat Keterangan Imunisasi TT berhasil dibuat',
            'data' => $skitt->load(['rt:id,nama,role,no_rt', 'masyarakat:id,nama,email,nik']),
        ], 201);
    }

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
        'fasilitas_kesehatan' => 'required|string',
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

    // =====================================================
    // ✅ UPLOAD FILE (SAMA PERSIS)
    // =====================================================

    if ($request->hasFile('file_ktp')) {
        $folder = public_path('uploads/skitt/ktp');
        if (!file_exists($folder)) mkdir($folder, 0777, true);

        $file = $request->file('file_ktp');
        $filename = 'ktp_' . time() . '.' . $file->getClientOriginalExtension();
        $file->move($folder, $filename);

        $validated['file_ktp'] = 'uploads/skitt/ktp/' . $filename;
    }

    if ($request->hasFile('file_kk')) {
        $folder = public_path('uploads/skitt/kk');
        if (!file_exists($folder)) mkdir($folder, 0777, true);

        $file = $request->file('file_kk');
        $filename = 'kk_' . time() . '.' . $file->getClientOriginalExtension();
        $file->move($folder, $filename);

        $validated['file_kk'] = 'uploads/skitt/kk/' . $filename;
    }

    if ($request->hasFile('ttd_masyarakat')) {
        $folder = public_path('uploads/skitt/ttd/masyarakat');
        if (!file_exists($folder)) mkdir($folder, 0777, true);

        $file = $request->file('ttd_masyarakat');
        $filename = 'ttd_masyarakat_' . time() . '.' . $file->getClientOriginalExtension();
        $file->move($folder, $filename);

        $validated['ttd_masyarakat'] = 'uploads/skitt/ttd/masyarakat/' . $filename;
    }

    if ($request->hasFile('ttd_rt')) {
        $folder = public_path('uploads/skitt/ttd/rt');
        if (!file_exists($folder)) mkdir($folder, 0777, true);

        $file = $request->file('ttd_rt');
        $filename = 'ttd_rt_' . time() . '.' . $file->getClientOriginalExtension();
        $file->move($folder, $filename);

        $validated['ttd_rt'] = 'uploads/skitt/ttd/rt/' . $filename;
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
    $skitt = SuketImunisasiTT::create($validated);

    // =====================================================
    // 🔔 NOTIF KE PERANGKAT DESA
    // =====================================================
    Notification::create([
        'user_id'    => User::where('role', 'perangkat')->first()->id ?? null,
        'surat_type' => 'SKITT',
        'surat_id'   => $skitt->id_skitt,
        'title'      => 'Pengajuan Surat Keterangan Imunisasi TT dari RT',
        'message'    => 'RT telah mengajukan dan memverifikasi Surat Keterangan Imunisasi TT.',
    ]);

    return response()->json([
        'success' => true,
        'message' => 'Pengajuan Surat Keterangan Imunisasi TT oleh RT berhasil',
        'data' => $skitt->load(['rt:id,nama,no_rt']),
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
        'fasilitas_kesehatan' => 'required|string',
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

        // =====================================================
        // UPLOAD FILE
        // =====================================================

        // KTP
        if ($request->hasFile('file_ktp')) {
            $folder = public_path('uploads/skitt/ktp');
            if (!file_exists($folder)) mkdir($folder, 0777, true);

            $file = $request->file('file_ktp');
            $filename = 'ktp_' . time() . '.' . $file->getClientOriginalExtension();
            $file->move($folder, $filename);

            $validated['file_ktp'] = 'uploads/skitt/ktp/' . $filename;
        }

        // KK
        if ($request->hasFile('file_kk')) {
            $folder = public_path('uploads/skitt/kk');
            if (!file_exists($folder)) mkdir($folder, 0777, true);

            $file = $request->file('file_kk');
            $filename = 'kk_' . time() . '.' . $file->getClientOriginalExtension();
            $file->move($folder, $filename);

            $validated['file_kk'] = 'uploads/skitt/kk/' . $filename;
        }

        // TTD masyarakat
        if ($request->hasFile('ttd_masyarakat')) {
            $folder = public_path('uploads/skitt/ttd/masyarakat');
            if (!file_exists($folder)) mkdir($folder, 0777, true);

            $file = $request->file('ttd_masyarakat');
            $filename = 'ttd_masyarakat_' . time() . '.' . $file->getClientOriginalExtension();
            $file->move($folder, $filename);

            $validated['ttd_masyarakat'] = 'uploads/skitt/ttd/masyarakat/' . $filename;
        }

        // 🔥 PENGANTAR RT OFFLINE
        if ($request->hasFile('file_pengantar_rt')) {
            $folder = public_path('uploads/pengantar-rt-skitt');
            if (!file_exists($folder)) mkdir($folder, 0777, true);

            $file = $request->file('file_pengantar_rt');
            $filename = 'pengantar_' . time() . '.' . $file->getClientOriginalExtension();
            $file->move($folder, $filename);

            $validated['file_pengantar_rt'] = 'uploads/pengantar-rt-skitt/' . $filename;

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
        $skitt = SuketImunisasiTT::create($validated);

        // =====================================================
        // 🔔 NOTIF KE KEPALA DESA
        // =====================================================
        Notification::create([
            'role'       => 'kepala_desa',
            'surat_type' => 'SKITT',
            'surat_id'   => $skitt->id_skitt,
            'title' => 'Surat Keterangan Imunisasi TT Menunggu verifikasi Perangkat Desa',
            'message' => 'Pengajuan Surat Keterangan Imunisasi TT dari perangkat desa (dengan pengantar RT offline) menunggu verifikasi.',
        ]);

        RtPengantarNumber::create([
            'no_surat_pengantar' => $validated['no_surat_pengantar'],
            'surat_type'         => 'SKITT',
            'surat_id'           => $skitt->id_skitt,
            'rt_id'              => $validated['rt_id'], // dari pilihan perangkat
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Pengajuan Surat Keterangan Imunisasi TT oleh perangkat desa berhasil',
            'data' => $skitt
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

         // === Ambil SKITT ===
        $skitt = SuketImunisasiTT::where('id_skitt', $id)->first();

        // ✅ FIX: cek dulu sebelum dipakai
        if (!$skitt) {
            return response()->json([
                'success' => false,
                'message' => 'Data Surat Keterangan Imunisasi TT tidak ditemukan'
            ], 404);
        }

            // === CEK HAK AKSES BERDASARKAN PENGAJU ===
        if ($skitt->submitted_by === 'masyarakat') {

            if (!$user->tokenCan('masyarakat') || $skitt->masyarakat_id != $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anda tidak berhak mengajukan ulang data ini'
                ], 403);
            }

        } else if ($skitt->submitted_by === 'rt') {

            if (!$user->tokenCan('rt') || $skitt->rt_id != $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Hanya RT yang mengajukan yang bisa ajukan ulang'
                ], 403);
            }

        } else if ($skitt->submitted_by === 'perangkat_desa') {

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
    if (!$request->hasFile('file_pengantar_rt') && !$skitt->file_pengantar_rt) {
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

        if (!$skitt) {
            return response()->json([
                'success' => false,
                'message' => 'Data Surat Keterangan Imunisasi TT tidak ditemukan atau bukan milik anda'
            ], 404);
        }

        if ($skitt->status !== 'ditolak') {
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
            'alasan' => 'required|string',
            'fasilitas_kesehatan' => 'required|string',
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
                    function ($attribute, $value, $fail) use ($skitt) {

                        $existing = RtPengantarNumber::where('no_surat_pengantar', $value)
    ->where('id', '!=', optional(
        RtPengantarNumber::where('surat_id', $skitt->id_skitt)
            ->where('surat_type', 'SKITT')
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
        $tujuan = $skitt->rejected_by;

                $tujuanLabel = match ($tujuan) {
                        'rt' => 'Ketua RT',
                        'perangkat_desa' => 'Perangkat Desa',
                        'kepala_desa' => 'Kepala Desa',
                        default => ucfirst(str_replace('_', ' ', $tujuan)),
                    };

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
            $validated['no_rt'] = $skitt->no_rt; // tetap jika tidak diubah
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
        // 🔥 JIKA PENGAJUAN ULANG VIA PERANGKAT (OFFLINE RT)
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
            $validated['perangkat_id'] = $user->id; // opsional tracking

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
if (!$request->hasFile('file_pengantar_rt') && !$skitt->file_pengantar_rt) {
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
            // $validated['rt_validated_at'] = $skitt->rt_validated_at;
            $validated['rt_validated_by'] = $skitt->rt_validated_by;

            // perangkat desa reset
            $validated['perangkat_validated_at'] = null;
            $validated['perangkat_validated_by'] = null;

            // kepala desa reset
            $validated['kepala_desa_validated_at'] = null;
            $validated['kepala_desa_validated_by'] = null;
        }

        else if ($tujuan === 'kepala_desa') {

            $validated['status'] = 'diproses kepala desa';
            $validated['keterangan'] = $request->keterangan ?? "Diajukan kembali ke Kepala Desa";

            $validated['rt_id'] = $skitt->rt_id;
            $validated['perangkat_id'] = $skitt->perangkat_id;

            // RT tetap
            $validated['rt_validated_at'] = $skitt->rt_validated_at;
            $validated['rt_validated_by'] = $skitt->rt_validated_by;

            // perangkat tetap
            $validated['perangkat_validated_at'] = $skitt->perangkat_validated_at;
            $validated['perangkat_validated_by'] = $skitt->perangkat_validated_by;

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
            $folder = public_path('uploads/skitt');
            if (!file_exists($folder)) mkdir($folder, 0777, true);

            $file = $request->file('file');
            $filename = time().'_'.$file->getClientOriginalName();
            $file->move($folder, $filename);

            $validated['file'] = 'uploads/skitt/'.$filename;
        }
        // ============================
        // Upload FILE KTP (opsional)
        // ============================
        if ($request->hasFile('file_ktp')) {

        // 🔥 HAPUS FILE LAMA
        if ($skitt->file_ktp && file_exists(public_path($skitt->file_ktp))) {
            unlink(public_path($skitt->file_ktp));
        }

        $folder = public_path('uploads/skitt/ktp');
                

                $fileKtp = $request->file('file_ktp');
                $filenameKtp = 'ktp_' . time() . '.' . $fileKtp->getClientOriginalExtension();
                $fileKtp->move($folder, $filenameKtp);

                $validated['file_ktp'] = 'uploads/skitt/ktp/' . $filenameKtp;
            }


            // ============================
            // Upload FILE KK (opsional)
            // ============================
        if ($request->hasFile('file_kk')) {

        // 🔥 HAPUS FILE LAMA
        if ($skitt->file_kk && file_exists(public_path($skitt->file_kk))) {
            unlink(public_path($skitt->file_kk));
        }

        $folder = public_path('uploads/skitt/kk');

                $fileKk = $request->file('file_kk');
                $filenameKk = 'kk_' . time() . '.' . $fileKk->getClientOriginalExtension();
                $fileKk->move($folder, $filenameKk);

                $validated['file_kk'] = 'uploads/skitt/kk/' . $filenameKk;
            }

        if ($request->hasFile('file_pengantar_rt')) {

        // 🔥 NORMALISASI PATH
        $oldPath = $skitt->file_pengantar_rt;

        if ($oldPath) {
        $fullPath = public_path($oldPath);

        // fallback jika path lama salah format
        if (!file_exists($fullPath)) {
            $fullPath = public_path(str_replace('uploads/pengantar-rt-skitt', 'uploads/pengantar-rt-skitt/', $oldPath));
        }

        if (file_exists($fullPath)) {
            unlink($fullPath);
        }
    }

        $folder = public_path('uploads/pengantar-rt-skitt');

        if (!file_exists($folder)) {
            mkdir($folder, 0777, true);
        }

        $filePengantar = $request->file('file_pengantar_rt');
        $filenamePengantar = 'pengantar_' . time() . '.' . $filePengantar->getClientOriginalExtension();
        $filePengantar->move($folder, $filenamePengantar);

        $validated['file_pengantar_rt'] = 'uploads/pengantar-rt-skitt/' . $filenamePengantar;

        $validated['pengantar_rt_type'] = 'offline';
    }

            // ============================
            // Upload TTD Masyarakat (opsional)
            // ============================
        if ($request->hasFile('ttd_masyarakat')) {

        // 🔥 HAPUS FILE LAMA
        if ($skitt->ttd_masyarakat && file_exists(public_path($skitt->ttd_masyarakat))) {
            unlink(public_path($skitt->ttd_masyarakat));
        }

        $folder = public_path('uploads/skitt/ttd/masyarakat');

                $fileTtd = $request->file('ttd_masyarakat');
                $filenameTtd = 'ttd_masyarakat_' . time() . '.' . $fileTtd->getClientOriginalExtension();
                $fileTtd->move($folder, $filenameTtd);

                $validated['ttd_masyarakat'] = 'uploads/skitt/ttd/masyarakat/' . $filenameTtd;
            }


        // file_pdf harus direset
        $validated['file_pdf'] = null;

        // Reset rejected_by
        $validated['rejected_by'] = null;

        // Update
        $skitt->update($validated);
  // =====================================================
        // 🔥 RESET FIELD PENTING
        // =====================================================
        $validated['file_pdf'] = null;
        $validated['rejected_by'] = null;

        // =====================================================
        // 🔥 UPDATE
        // =====================================================
        $skitt->update($validated);

         //NOTIFIKASI
        if ($tujuan === 'rt') {
    Notification::create([
        'user_id'    => $skitt->rt_id,
        'role'       => 'rt',
        'surat_type' => 'SKITT',
        'surat_id'   => $skitt->id_skitt,
        'title'      => 'Ajuan Ulang Surat Keterangan Imunisasi TT',
        'message'    => 'Terdapat ajuan ulang Surat Keterangan Imunisasi TT dari masyarakat untuk diproses kembali.',
    ]);
}

else if ($tujuan === 'perangkat_desa') {
    Notification::create([
        'role'       => 'perangkat_desa',
        'surat_type' => 'SKITT',
        'surat_id'   => $skitt->id_skitt,
        'title'      => 'Ajuan Ulang Surat Keterangan Imunisasi TT',
        'message'    => 'Terdapat ajuan ulang Surat Keterangan Imunisasi TT dari masyarakat untuk diproses kembali.',
    ]);
}

else if ($tujuan === 'kepala_desa') {
    Notification::create([
        'role'       => 'kepala_desa',
        'surat_type' => 'SKITT',
        'surat_id'   => $skitt->id_skitt,
        'title'      => 'Ajuan Ulang Surat Keterangan Imunisasi TT',
        'message'    => 'Surat Keterangan Imunisasi TT yang sebelumnya ditolak telah diajukan ulang oleh masyarakat.',
    ]);
}

     // =====================================================
        // 🔥 SIMPAN NOMOR PENGANTAR RT (JIKA ADA)
        // =====================================================
if (!empty($validated['no_surat_pengantar'])) {

    $existingPengantar = RtPengantarNumber::where('surat_id', $skitt->id_skitt)
        ->where('surat_type', 'SKITT')
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
            'surat_id' => $skitt->id_skitt,
            'surat_type' => 'SKITT',
            'no_surat_pengantar' => $validated['no_surat_pengantar'],
            'rt_id' => $validated['rt_id'] ?? null,
            'nama_rt' => $validated['nama_rt'] ?? null,
        ]);
    }
}


        return response()->json([
            'success' => true,
            'message' => "Pengajuan berhasil diajukan kembali ke {$tujuanLabel}",
            'data' => $skitt->fresh()
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Terjadi kesalahan',
            'error' => $e->getMessage()
        ], 500);
    }
    }


    // ✅ PUT: KETUA RT UBAH STATUS & KETERANGAN Surat Keterangan Imunisasi TT
        public function validasiKetuaRT(Request $request, $rt_id, $id_skitt)
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
    // 3. Ambil data skitt pemohon
    // ============================
    $skitt = SuketImunisasiTT::where('id_skitt', $id_skitt)
        ->where('rt_id', $rt->id)
        ->first();

    if (!$skitt) {
        return response()->json([
            'success' => false,
            'message' => 'Data Surat Keterangan Imunisasi TT tidak ditemukan.'
        ], 404);
    }

// ==========================================
// 3.5 Validasi status SKITT (RT tidak boleh proses ulang)
// ==========================================
if ($skitt->status === 'ditolak') {
    return response()->json([
        'success' => false,
        'message' => 'Pengajuan sudah ditolak. Silakan tunggu masyarakat mengajukan kembali.'
    ], 422);
}

if ($skitt->status === 'diproses perangkat desa') {
    return response()->json([
        'success' => false,
        'message' => 'Pengajuan sudah diproses oleh Perangkat Desa dan tidak dapat diubah oleh RT.'
    ], 422);
}

if ($skitt->status === 'diproses kepala desa') {
    return response()->json([
        'success' => false,
        'message' => 'Pengajuan sudah diteruskan ke Kepala Desa dan tidak dapat diubah oleh RT.'
    ], 422);
}

if ($skitt->status === 'selesai') {
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
    'keterangan'      => $validated['keterangan'] ?? $skitt->keterangan,
    'rt_validated_at' => now(),
];

if ($validated['status'] === 'ditolak') {
    $updateData['no_surat_pengantar'] = null;
        $updateData['file_pengantar_rt'] = null; // ⬅️ tambah
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
        $folder = public_path('uploads/skitt/ttd/rt');
        if (!file_exists($folder)) {
            mkdir($folder, 0777, true);
        }

        $file = $request->file('ttd_rt');
        $filename = 'ttd_rt_' . time() . '.' . $file->getClientOriginalExtension();
        $file->move($folder, $filename);

        $updateData['ttd_rt'] = 'uploads/skitt/ttd/rt/' . $filename;
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
    // 7. Update SKITT ke database
    // ============================
    $skitt->update($updateData);

    // ======================================
// 7.5 SIMPAN NO PENGANTAR KE TABEL PUSAT
// ======================================
if ($validated['status'] === 'diproses perangkat desa') {
    RtPengantarNumber::create([
        'no_surat_pengantar' => $validated['no_surat_pengantar'],
        'surat_type'         => 'SKITT',
        'surat_id'           => $skitt->id_skitt,
        'rt_id'              => $rt->id,
    ]);
}


   // ======================================
// 8. Generate PDF Pengantar RT (KONDISIONAL)
// ======================================
if ($validated['status'] === 'diproses perangkat desa') {
    try {
        $pdfUrl = $this->generatePengantarRT($skitt->fresh());

        $skitt->update([
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
        'surat_type' => 'SKITT',
        'surat_id'   => $skitt->id_skitt,
        'title'      => 'Surat Keterangan Imunisasi TT Menunggu Verifikasi',
        'message'    => 'Pengajuan Surat Keterangan Imunisasi TT telah divalidasi RT.',
    ]);
}

// 🔔 NOTIFIKASI KE MASYARAKAT
if ($validated['status'] === 'diproses perangkat desa') {
    Notification::create([
        'masyarakat_id' => $skitt->masyarakat_id,
        'role'          => 'masyarakat',
        'surat_type'    => 'SKITT',
        'surat_id'      => $skitt->id_skitt,
        'title'         => 'Pengajuan Surat Keterangan Imunisasi TT diverifikasi RT',
        'message'       => 'Pengajuan Surat Keterangan Imunisasi TT Anda telah diverifikasi RT dan diteruskan ke Perangkat Desa.',
    ]);
}

if ($validated['status'] === 'ditolak') {
    Notification::create([
         'masyarakat_id' => $skitt->masyarakat_id,
        'role'          => 'masyarakat',
        'surat_type'    => 'SKITT',
        'surat_id'      => $skitt->id_skitt,
        'title'         => 'Pengajuan Surat Keterangan Imunisasi TT Ditolak RT',
        'message'       => 'Pengajuan Surat Keterangan Imunisasi TT Anda ditolak oleh RT. Silakan periksa keterangan.',
    ]);
}

    // ============================
    // 9. Response ke client
    // ============================
    $skittFresh = $skitt->fresh();

    if (!empty($skittFresh->ttd_rt)) {
        $skittFresh->ttd_rt = url($skittFresh->ttd_rt);
    }

    return response()->json([
    'success' => true,
    'message' => ($validated['status'] === 'diproses perangkat desa')
        ? "Status diproses perangkat desa. Surat pengantar RT tersedia."
        : "Pengajuan ditolak oleh RT. Surat pengantar RT dibatalkan.",
    'data' => $skittFresh,
]);

    }


    // ✅ PUT: PERANGKAT DESA UBAH NOMOR SURAT, STATUS, & KETERANGAN Surat Keterangan Imunisasi TT
   public function validasiPerangkatDesa(Request $request, $pd_id, $id_skitt)
{
    try {

        // ================================
        // 1. Ambil user login (harus perangkat desa)
        // ================================
        $pd = auth()->user();

        if (!$pd || $pd->role !== 'perangkat_desa') {
            return response()->json([
                'success' => false,
                'message' => 'Akses ditolak. Hanya perangkat desa yang dapat memproses.'
            ], 403);
        }

        // ================================
        // 2. Cocokkan ID perangkat desa di token
        // ================================
        if ($pd->id != $pd_id) {
            return response()->json([
                'success' => false,
                'message' => 'Perangkat desa tidak sesuai dengan token login.'
            ], 403);
        }

        // ================================
        // 3. Ambil data Surat Keterangan Imunisasi TT
        // ================================
        $skitt = SuketImunisasiTT::where('id_skitt', $id_skitt)->first();

        if (!$skitt) {
            return response()->json([
                'success' => false,
                'message' => 'Data Surat Keterangan Imunisasi TT tidak ditemukan.'
            ], 404);
        }

       // ================================
// 4. Validasi status yang boleh diproses perangkat desa
// ================================
if ($skitt->status === 'ditolak') {
    return response()->json([
        'success' => false,
        'message' => 'Pengajuan sudah ditolak. Silakan tunggu masyarakat mengajukan kembali.'
    ], 400);
}

if ($skitt->status === 'diproses kepala desa') {
    return response()->json([
        'success' => false,
        'message' => 'Status tidak dapat diubah karena sudah diproses oleh Kepala Desa.'
    ], 400);
}

if ($skitt->status === 'diproses rt') {
    return response()->json([
        'success' => false,
        'message' => 'Status tidak dapat diubah karena masih diproses oleh Ketua RT.'
    ], 400);
}

if ($skitt->status === 'selesai') {
    return response()->json([
        'success' => false,
        'message' => 'Pengajuan sudah selesai dan tidak dapat diproses ulang oleh Perangkat Desa.'
    ], 400);
}

if ($skitt->status !== 'diproses perangkat desa') {
    return response()->json([
        'success' => false,
        'message' => 'Status pengajuan tidak valid untuk diproses oleh Perangkat Desa.'
    ], 400);
}

       $validated = $request->validate([
        'status'      => 'required|in:diproses kepala desa,ditolak',
        'keterangan'  => 'nullable|string|max:255',
        'nomor_surat' => 'nullable|string|max:100',

        // poin ii wajib KECUALI jika ditolak
        'poin_ii'     => 'required_unless:status,ditolak|string',
    ]);


        // ================================
        // 5.1 Validasi nomor_surat harus unik
        // ================================
        if (!empty($validated['nomor_surat'])) {
            $existing = SuketImunisasiTT::where('nomor_surat', $validated['nomor_surat'])
                ->where('id_skitt', '!=', $id_skitt)
                ->first();

            if ($existing) {
                return response()->json([
                    'success' => false,
                    'message' => 'Nomor surat sudah digunakan pada pengajuan lain.'
                ], 422);
            }
        }

        // ================================
        // 6. Update data dasar
        // ================================
        $updateData = [
            'status'     => $validated['status'],
            'keterangan' => $validated['keterangan'] ?? $skitt->keterangan,
              'perangkat_validated_by' => $pd->id, //TAMBAH
            'perangkat_validated_at' => now(),
            'poin_ii' => $validated['poin_ii'] ?? $skitt->poin_ii,
            'file_pdf' => null,
        ];

        // Jika ditolak → kosongkan nomor + pdf
        if ($validated['status'] === 'ditolak') {
    $updateData['rejected_by'] = 'perangkat_desa';
    $updateData['nomor_surat'] = null;
    $updateData['file_pdf'] = null;
    $updateData['poin_ii'] = null;
}


        // Jika diteruskan ke kepala desa dan nomor_surat ada
        if ($validated['status'] === 'diproses kepala desa') {
            if (!empty($validated['nomor_surat'])) {
                $updateData['nomor_surat'] = $validated['nomor_surat'];
            }
        }

        // Simpan update
        $skitt->update($updateData);

        // 🔔 NOTIFIKASI KE KEPALA DESA (HANYA JIKA DITERUSKAN)
if ($validated['status'] === 'diproses kepala desa') {
    Notification::create([
        'role'       => 'kepala_desa',
        'surat_type' => 'SKITT',
        'surat_id'   => $skitt->id_skitt,
        'title'      => 'Surat Keterangan Imunisasi TT Menunggu Persetujuan',
        'message'    => 'Pengajuan Surat Keterangan Imunisasi TT menunggu persetujuan Kepala Desa.',
    ]);
}
// 🔔 NOTIFIKASI KE MASYARAKAT
if ($validated['status'] === 'diproses kepala desa') {
    Notification::create([
        'masyarakat_id' => $skitt->masyarakat_id,
        'role'          => 'masyarakat',
        'surat_type'    => 'SKITT',
        'surat_id'      => $skitt->id_skitt,
        'title'         => 'Surat Keterangan Imunisasi TT Diteruskan ke Kepala Desa',
        'message'       => 'Pengajuan Surat Keterangan Imunisasi TT Anda telah diverifikasi oleh Perangkat Desa dan diteruskan ke Kepala Desa.',
    ]);
}

if ($validated['status'] === 'ditolak') {
    Notification::create([
        'masyarakat_id' => $skitt->masyarakat_id,
        'role'          => 'masyarakat',
        'surat_type'    => 'SKITT',
        'surat_id'      => $skitt->id_skitt,
        'title'         => 'Pengajuan Surat Keterangan Imunisasi TT Ditolak',
        'message'       => 'Pengajuan Surat Keterangan Imunisasi TT Anda ditolak oleh Perangkat Desa. Silakan periksa keterangan.',
    ]);
}

        // ================================
        // 8. Response
        // ================================
       $message = match ($validated['status']) {
    'diproses kepala desa' => 'Data berhasil diverifikasi dan diteruskan ke Kepala Desa.',
    'ditolak' => 'Pengajuan ditolak oleh Perangkat Desa.',
};


        return response()->json([
            'success' => true,
            'message' => $message,
            'data'    => $skitt->fresh()
        ]);

    } catch (\Exception $e) {

        return response()->json([
            'success' => false,
            'message' => 'Terjadi kesalahan saat memproses',
            'error'   => $e->getMessage()
        ], 500);
    }
}


    // ✅ PUT: KEPALA DESA UBAH STATUS SELESAI & KETERANGAN Surat Keterangan Imunisasi TT
    // ❌ Surat Keterangan Imunisasi TT yang sudah selesai seharusnya TIDAK boleh diproses ulang (Keucali Super Admin)
    public function validasiKepalaDesa(Request $request, $kd_id, $id_skitt)
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
            if ($kd->id != $kd_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'ID Kepala Desa tidak sesuai dengan token login.'
                ], 403);
            }

            // 3. Ambil data skitt
            $skitt = SuketImunisasiTT::where('id_skitt', $id_skitt)->first();
            if (!$skitt) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data Surat Keterangan Imunisasi TT tidak ditemukan.'
                ], 404);
            }

            // 4. Validasi input
           $rules = [
    'status'     => 'required|in:selesai,ditolak',
    'keterangan' => 'nullable|string|max:255',
];

// 🔥 TTD KADES: FILE ATAU STRING (dari profil)
if ($request->hasFile('ttd_kades')) {
    $rules['ttd_kades'] = 'image|mimes:jpg,jpeg,png|max:2048';
} else {
    $rules['ttd_kades'] = 'nullable|string';
}

$validated = $request->validate($rules);


            $currentStatus = $skitt->status;
            $newStatus     = $validated['status'];

            /*
            |--------------------------------------------------------------------------
            | RULE TRANSISI STATUS
            |--------------------------------------------------------------------------
            | diproses kepala desa -> selesai / ditolak
            | ditolak -> selesai
            | selesai -> TIDAK BOLEH DIUBAH
            */

             // ==========================================
// VALIDASI STATUS KHUSUS KEPALA DESA
// ==========================================
if ($currentStatus === 'ditolak') {
    return response()->json([
        'success' => false,
        'message' => 'Surat Keterangan Imunisasi TTtelah ditolak. Silakan tunggu hingga diajukan ulang oleh pemohon.'
    ], 422);
}

if ($currentStatus === 'diproses perangkat desa') {
    return response()->json([
        'success' => false,
        'message' => 'Surat Keterangan Imunisasi TT masih diproses oleh Perangkat Desa.'
    ], 422);
}

if ($currentStatus === 'diproses rt') {
    return response()->json([
        'success' => false,
        'message' => 'Surat Keterangan Imunisasi TT masih diproses oleh Ketua RT.'
    ], 422);
}

// ❌ jika sudah selesai, tidak boleh diubah
if ($currentStatus === 'selesai') {
    return response()->json([
        'success' => false,
        'message' => 'Pengajuan sudah selesai dan tidak dapat diproses ulang oleh Kepala Desa'
    ], 422);
}

// ❌ Kepala desa hanya boleh memproses jika status = diproses kepala desa
if ($currentStatus !== 'diproses kepala desa') {
    return response()->json([
        'success' => false,
        'message' => 'Status Surat Keterangan Imunisasi TT tidak valid untuk diproses oleh Kepala Desa.'
    ], 422);
}

            // 5. Data dasar update
            $updateData = [
                'status' => $newStatus,
                'keterangan' => $validated['keterangan'] ?? $skitt->keterangan,
                'kepala_desa_id' => $kd->id,
                'kepala_desa_validated_at' => now(),
            ];

            // 6. Jika DITOLAK
            if ($newStatus === 'ditolak') {
                $updateData['rejected_by'] = 'kepala_desa';
                $updateData['file_pdf']   = null;
                $updateData['ttd_kades']  = null;
            }

            // 7. UPLOAD TTD (HANYA JIKA SELESAI)
            if ($newStatus === 'selesai' && $request->hasFile('ttd_kades')) {
                $folder = public_path('uploads/skitt/ttd/kades');
                if (!file_exists($folder)) {
                    mkdir($folder, 0777, true);
                }

                $file = $request->file('ttd_kades');
                $filename = 'ttd_kades_' . time() . '.' . $file->getClientOriginalExtension();
                $file->move($folder, $filename);

                $updateData['ttd_kades'] = 'uploads/skitt/ttd/kades/' . $filename;
            }

            // ============================
// TENTUKAN TTD KADES (UPLOAD > PROFIL)
// ============================
if ($newStatus === 'selesai') {

    // ❌ Jika tidak upload, tidak kirim string, dan profil kosong
    if (
        !$request->hasFile('ttd_kades') &&
        empty($validated['ttd_kades']) &&
        empty($kd->ttd)
    ) {
        return response()->json([
            'success' => false,
            'message' => 'Tanda tangan Kepala Desa belum tersedia. Upload tanda tangan atau lengkapi di profil.'
        ], 422);
    }

    // ✅ Jika tidak upload file → pakai dari profil
    if (!$request->hasFile('ttd_kades')) {
        $updateData['ttd_kades'] = $kd->ttd;
    }

    // ⚠️ Jika upload file → jangan timpa
}


            // 8. SIMPAN DATA
            $skitt->update($updateData);
            $skittFresh = $skitt->fresh();

            // 9. GENERATE / HAPUS PDF
            if ($newStatus === 'selesai') {
                try {
                    $pdfUrl = $this->generatePdf($skittFresh);
                    $skittFresh->update([
                        'file_pdf' => $pdfUrl
                    ]);
                } catch (\Exception $e) {
                    Log::error('Gagal generate PDF Surat Keterangan Imunisasi TT oleh Kepala Desa', [
                        'error' => $e->getMessage()
                    ]);
                }
            }

            // 10. Format URL TTD
            if (!empty($skittFresh->ttd_kades)) {
                $skittFresh->ttd_kades = url($skittFresh->ttd_kades);
            }

            return response()->json([
                'success' => true,
                'message' => $newStatus === 'selesai'
                    ? 'Surat Keterangan Imunisasi TT selesai dan PDF berhasil dibuat.'
                    : 'Surat Keterangan Imunisasi TT berhasil ditolak.',
                'data' => $skittFresh
            ]);

            // 🔔 NOTIFIKASI KE MASYARAKAT (KEPUTUSAN AKHIR)
if ($newStatus === 'selesai') {
    Notification::create([
        'masyarakat_id' => $skitt->masyarakat_id,
        'role'          => 'masyarakat',
        'surat_type'    => 'SKITT',
        'surat_id'      => $skittFresh->id_skitt,
        'title'         => 'Pengajuan Surat Keterangan Imunisasi TT Selesai',
        'message'       => 'Pengajuan Surat Keterangan Imunisasi TT Anda telah disetujui oleh Kepala Desa dan surat sudah dapat diunduh.',
    ]);
}

if ($newStatus === 'ditolak') {
    Notification::create([
        'masyarakat_id' => $skitt->masyarakat_id,
        'role'          => 'masyarakat',
        'surat_type'    => 'SKITT',
        'surat_id'      => $skittFresh->id_skitt,
        'title'         => 'Pengajuan Surat Keterangan Imunisasi TT Ditolak',
        'message'       => 'Pengajuan Surat Keterangan Imunisasi TT Anda ditolak oleh Kepala Desa. Silakan periksa keterangan.',
    ]);
}


        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan.',
                'error' => $e->getMessage()
            ], 500);
        }
    }


public function exportSKITT(Request $request)
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
            'message' => 'Anda tidak memiliki akses untuk export data Surat Keterangan Imunisasi TT.',
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

    $finalFilename = 'Surat Keterangan Imunisasi TT_' .
        $safeFilename . '_' .
        now()->format('Y-m-d_H-i-s') . '.xlsx';

    // 📤 EXPORT EXCEL
    return Excel::download(
        new SKITTExport,
        $finalFilename
    );
}

public function exportZipSKITT(Request $request)
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
            'message' => 'Anda tidak memiliki akses untuk export data SKITT.',
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

    $zipFileName = 'SKITT_' . $safeFilename . '_' . now()->format('Y-m-d_H-i-s') . '.zip';
    $zipPath = public_path('exports/' . $zipFileName);

    // 📁 BUAT FOLDER EXPORT
    if (!File::exists(public_path('exports'))) {
        File::makeDirectory(public_path('exports'), 0777, true);
    }

    $zip = new ZipArchive;

    if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {

        // ===========================
        // 📄 FILE SKITT (DARI DB)
        // ===========================
        $data = SuketImunisasiTT::whereNotNull('file_pdf')->get();

        foreach ($data as $item) {

            if ($item->file_pdf) {
                $pdfPath = parse_url($item->file_pdf, PHP_URL_PATH);
                $filePath = public_path($pdfPath);

                if (file_exists($filePath)) {
                    $zip->addFile(
                        $filePath,
                        'skitt/' . basename($filePath)
                    );
                }
            }
        }

        // ===========================
        // 📎 FILE PENGANTAR RT (SEMUA FILE DALAM FOLDER)
        // ===========================
        $folderPengantar = public_path('uploads/pengantar-rt-skitt');

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

public function deleteAllSKITT(Request $request)
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

        $data = SuketImunisasiTT::all();

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
        SuketImunisasiTT::query()->update([
            'deleted_by' => $deletedByRole,
        ]);

        // delete database
        SuketImunisasiTT::query()->delete();

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

    $query = SuketImunisasiTT::query();

    /**
     * ===============================
     * 2. KUNCI DATA BERDASARKAN ROLE
     * ===============================
     */

    // 🔒 MASYARAKAT → hanya miliknya
    if ($masyarakat) {
        $query->where('masyarakat_id', $masyarakat->id);
    }

    // 🔒 RT → hanya Surat Keterangan Imunisasi TT RT-nya
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


    // ✅ GET: /api/skitt/{id}
    public function show($id)
    {
        $data = SuketImunisasiTT::with(['rt:id,name,role', 'masyarakat:id,nama,email,nik'])->find($id);
        if (!$data) {
            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan'], 404);
        }
        return response()->json(['success' => true, 'data' => $data]);
    }


    // ✅ PUT: /api/skitt/{id}
    public function update(Request $request, $id)
    {
        $skitt = SuketImunisasiTT::find($id);
        if (!$skitt) {
            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan'], 404);
        }

        $validated = $request->validate([
            'nomor_surat' => 'required|string|max:50', 
            'masyarakat_id' => 'sometimes|exists:masyarakats,id',
            'nama' => 'sometimes|string|max:255',
            'jenis_kelamin' => 'sometimes|string|in:Laki-laki,Perempuan',
            'ttl' => 'sometimes|string|max:255',
            'agama' => 'sometimes|string|max:100',
            'pekerjaan' => 'sometimes|string|max:255',
            'nik' => 'sometimes|string|max:20',
            'alamat' => 'sometimes|string|max:255',
            'rt_id' => 'sometimes|exists:users,id',
            'file' => 'nullable|file|mimes:jpg,jpeg,png,pdf,doc,docx|max:5120',
            'status' => 'nullable|string|in:diproses rt,diproses perangkat desa,selesai,ditolak',
            'keterangan' => 'sometimes|string',
            'status_perkawinan' => 'sometimes|string|max:50',
            'alasan' => 'sometimes|string',
            'fasilitas_kesehatan' => 'sometimes|string',
        ]);

        // Upload file baru
        if ($request->hasFile('file')) {
            if ($skitt->file) {
                $oldPath = str_replace(url('/') . '/', '', $skitt->file);
                if (file_exists(public_path($oldPath))) unlink(public_path($oldPath));
            }

            $file = $request->file('file');
            $filename = time() . '_' . $file->getClientOriginalName();
            $file->move(public_path('uploads/skitt'), $filename);

            $validated['file'] = url('uploads/skitt/' . $filename);
        }

        // Update ke DB
        $skitt->update($validated);

        // === Generate ulang PDF ketika nomor_surat berubah ===
        try {
            $pdfUrl = $this->generatePdf($skitt);
            $skitt->update(['file_pdf' => $pdfUrl]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui PDF setelah edit',
                'error' => $e->getMessage()
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Data Surat Keterangan Imunisasi TT berhasil diperbarui',
            'data' => $skitt->load(['rt:id,nama,role', 'masyarakat:id,nama,email,nik'])
        ]);
    }


    // ✅ DELETE: /api/skitt/{id}
    public function destroy($id)
    {
        $skitt = SuketImunisasiTT::find($id);
        if (!$skitt) {
            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan'], 404);
        }

        if ($skitt->file) {
            $oldPath = str_replace(url('/') . '/', '', $skitt->file);
            if (file_exists(public_path($oldPath))) unlink(public_path($oldPath));
        }

        $skitt->delete();

        return response()->json(['success' => true, 'message' => 'Data Surat Keterangan Imunisasi TT berhasil dihapus']);
    }


    // ✅ GET: /api/rt/{rt_id}/skitt
    public function getByRT($rt_id)
    {
        $rt = User::where('id', $rt_id)->where('role', 'rt')->first();
        if (!$rt) {
            return response()->json(['success' => false, 'message' => 'RT tidak ditemukan'], 404);
        }

        // Hanya ambil data Surat Keterangan Imunisasi TT milik RT tersebut, TANPA mengubah status apa pun
        $data = SuketImunisasiTT::where('rt_id', $rt_id)
            ->with(['masyarakat:id,nama,email,nik'])
            ->orderByDesc('id_skitt')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Data Surat Keterangan Imunisasi TT milik RT berhasil diambil',
            'data' => $data
        ]);
    }

    // ✅ PUT: /api/skitt/{id}/tolak
    public function tolak($id)
    {
        $skitt = SuketImunisasiTT::find($id);
        if (!$skitt) {
            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan'], 404);
        }

        $skitt->status = 'ditolak';
        $skitt->save();

        return response()->json([
            'success' => true,
            'message' => 'Status berhasil diubah menjadi ditolak',
            'data' => $skitt
        ]);
    }


    public function generatePdf($skitt)
    {
        try {
            $pdfFolder = public_path('uploads/skitt/pdf');

            if (!file_exists($pdfFolder)) {
                mkdir($pdfFolder, 0777, true);
            }

            // Muat PDF
            $pdf = Pdf::loadView('pdf.skitt', ['data' => $skitt])
                ->setPaper('A4', 'portrait');

            $filename = 'skitt_' . $skitt->id_skitt . '.pdf';
            $fullPath = $pdfFolder . '/' . $filename;

            // Simpan ke server
            $pdf->save($fullPath);

            return url('uploads/skitt/pdf/' . $filename);

        } catch (\Exception $e) {
            Log::error("PDF ERROR: " . $e->getMessage());
            throw new \Exception($e->getMessage());
        }
    }

       public function generatePengantarRT($skitt)
{
    try {
        $pdfFolder = public_path('uploads/pengantar-rt-skitt');

        // Buat folder jika belum ada
        if (!file_exists($pdfFolder)) {
            mkdir($pdfFolder, 0777, true);
        }

        // Muat PDF dari Blade
        $pdf = PDF::loadView('pdf.pengantar_skitt', [
                'skitt' => $skitt
            ])
            ->setPaper('A4', 'portrait');

        // Nama file
        $filename = 'pengantar_rt_skitt_' . $skitt->id_skitt . '.pdf';
        $fullPath = $pdfFolder . '/' . $filename;

        // Simpan ke server
        $pdf->save($fullPath);

        // URL akses file
        return url('uploads/pengantar-rt-skitt/' . $filename);

    } catch (\Exception $e) {

        Log::error("ERROR GENERATE PENGANTAR RT: " . $e->getMessage());

        return null;
    }
}
    }