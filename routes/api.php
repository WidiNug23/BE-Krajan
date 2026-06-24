<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\UsersController;
use App\Http\Controllers\Api\MasyarakatAuthController;
use App\Http\Controllers\Api\SuketTidakMampuController;
use App\Http\Controllers\Api\SuketBelumMenikahController;
use App\Http\Controllers\Api\SuketPendudukController;
use App\Http\Controllers\Api\SuketUsahaController;
use App\Http\Controllers\Api\SuketImunisasiTTController;
use App\Http\Controllers\Api\SuketJandaController;
use App\Http\Controllers\Api\PermohonanSKCKController;
use App\Http\Controllers\Api\MasyarakatController;
use App\Http\Controllers\Api\BeritaController;
use App\Http\Controllers\Api\LaporanController;
use App\Http\Controllers\Api\DataStatistikJSON;
use App\Http\Controllers\Api\DataStatistikController;
use App\Http\Controllers\Api\PerangkatDesaController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\KetuaRTController;
use App\Http\Controllers\Api\KepalaDesaController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Semua route untuk API didefinisikan di sini.
| Route ini akan otomatis memiliki prefix "/api"
|
*/

// === AUTH SISTEM (Super Admin, Kepala Desa, RT, Perangkat Desa) ===
Route::post('/login', [AuthController::class, 'login'])
    ->middleware('throttle:5,1');


Route::post('/add-perangkat-desa', [UsersController::class, 'addPerangkatDesa']);
Route::post('/add-kepala-desa', [UsersController::class, 'addKepalaDesa']);
Route::post('/add-super-admin', [UsersController::class, 'addSuperAdmin']);

// === ROUTE YANG PERLU TOKEN SISTEM ===
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
});


// ==================================================================
// === AUTH KHUSUS MASYARAKAT (Pakai tabel dan controller terpisah) ===
// ==================================================================
Route::prefix('masyarakat')->group(function () {
    // Register dan login masyarakat
    Route::post('/register', [MasyarakatAuthController::class, 'register']);
    Route::post('/verify-otp', [MasyarakatAuthController::class, 'verifyOtp']);
    Route::post('/resend-otp', [MasyarakatAuthController::class, 'resendOtp']);
    Route::post('/forgot-password/send-otp', [MasyarakatAuthController::class, 'sendForgotPasswordOtp']);
    Route::post('/forgot-password/reset', [MasyarakatAuthController::class, 'resetPassword']);
    Route::post('/forgot-password/resend-otp', [MasyarakatAuthController::class, 'resendForgotPasswordOtp']);
    Route::post('/login', [MasyarakatAuthController::class, 'login'])
    ->middleware('throttle:5,1');

    // Protected routes masyarakat
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me', [MasyarakatAuthController::class, 'me']);
        Route::post('/logout', [MasyarakatAuthController::class, 'logout']);
        Route::get('/profile/{id}', [MasyarakatController::class, 'getDetailMasyarakat']);
        Route::post('/update-profile/{id}', [MasyarakatController::class, 'updateProfile']);

    });
});

// =========================== ROUTE USERS ==========================
Route::middleware('auth:sanctum')->prefix('users')->group(function () {
    Route::get('/detail/{id}', [UsersController::class, 'getDetailUsers']);
    Route::post('/update-profile/{id}', [UsersController::class, 'updateProfileUsers']);
});


// ===============================================================================================================
// ================================================ ROUTE DOWNLOAD SURAT =========================================
// ===============================================================================================================

Route::get('/download-file', function (Request $request) {
    $path = $request->query('path');

    if (!$path) {
        return response()->json(['message' => 'Path file tidak ditemukan'], 400);
    }

    // Jika yang dikirim adalah URL lengkap, kita ambil bagian belakangnya saja
    $relativePath = parse_url($path, PHP_URL_PATH);
    $relativePath = ltrim($relativePath, '/'); // Hilangkan garis miring di depan

    // Gabungkan dengan folder public
    $fullPath = public_path($relativePath);

    // Cek apakah file benar-benar ada
    if (!File::exists($fullPath) || File::isDirectory($fullPath)) {
        return response()->json([
            'success' => false,
            'message' => 'File tidak ditemukan di server.',
            'debug_info' => [
                'received_path' => $path,
                'resolved_path' => $fullPath
            ]
        ], 404);
    }

    // Paksa browser untuk mengunduh file
    return response()->download($fullPath);
});


// ===============================================================================================================================================
// ================================================ ROUTE NOTIFIKASI =========================================
// ===============================================================================================================================================

Route::middleware('auth:sanctum')->get(
    '/notifications',
    [NotificationController::class, 'index']
);

Route::middleware('auth:sanctum')->post(
    '/notifications/{id}/read',
    [NotificationController::class, 'markAsRead']
);

Route::middleware('auth:sanctum')->get(
    '/notifications/count',
    [NotificationController::class, 'countUnread']
);

Route::middleware('auth:sanctum')->delete(
    '/notifications/{id}',
    [NotificationController::class, 'destroy']
);

Route::middleware('auth:sanctum')->delete(
    '/notifications',
    [NotificationController::class, 'deleteAll']
);

// ===============================================================================================================================================
// ================================================ ROUTE GET ALL DATA SISTEM UNTUK DASHBOARD =========================================
// ===============================================================================================================================================

Route::middleware('auth:sanctum')->prefix('dashboard')->group(function () {

    // =========================
    // SURAT
    // =========================
    Route::prefix('surat')->group(function () {
        Route::get('/sktm/count', [SuketTidakMampuController::class, 'countSKTM']);
        Route::get('/sku/count', [SuketUsahaController::class, 'countSKU']);
        Route::get('/skbm/count', [SuketBelumMenikahController::class, 'countSKBM']);
        Route::get('/skitt/count', [SuketImunisasiTTController::class, 'countSKITT']);
        Route::get('/skj/count', [SuketJandaController::class, 'countSKJ']);
        Route::get('/skp/count', [SuketPendudukController::class, 'countSKP']);
        Route::get('/skck/count', [PermohonanSKCKController::class, 'countSKCK']);
    });

    // =========================
    // DATA LAINNYA
    // =========================
    Route::get('/statistik/count', [DataStatistikController::class, 'countDataStatistik']);
    Route::get('/berita/count', [BeritaController::class, 'countBerita']);
    Route::get('/laporan/count', [LaporanController::class, 'countLaporan']);
    Route::get('/masyarakat/count', [MasyarakatController::class, 'countMasyarakat']);
    Route::get('/users/count', [UsersController::class, 'countUsers']);

});



// ===============================================================================================================================================
// ================================================ ROUTE GET TTD RT & KEPALA DESA =========================================
// ===============================================================================================================================================

Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/rt/profile/ttd', [KetuaRTController::class, 'getTtdRt']);
});

Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/kepala-desa/profile/ttd', [KepalaDesaController::class, 'getTtdKades']);
});




// ===============================================================================================================================================
// ================================================ ROUTE SUPER ADMIN & PERANGKAT DESA KELOLA DATA USERS =========================================
// ===============================================================================================================================================

// === ADD DATA Role (dibuat oleh admin, tidak ada register) ===
Route::middleware('auth:sanctum')
    ->prefix('users')
    ->group(function () {

        // ============================
        // RT MANAGEMENT
        // ============================

        Route::post('/add-rt', [UsersController::class, 'addRT']);

        Route::get('/rt', [UsersController::class, 'getRT']);

        Route::get('/rt/{id}', [UsersController::class, 'getDetailRT']);

        Route::post('/rt/{id}', [UsersController::class, 'updateRTByAdmin']);

        Route::delete('/rt/{id}', [UsersController::class, 'deleteRT']);

        // ============================
        // PERANGKAT DESA MANAGEMENT
        // ============================

        Route::post('/add-perangkat-desa', [UsersController::class, 'addPerangkatDesa']);

        Route::get('/perangkat-desa', [UsersController::class, 'getPerangkatDesa']);

        Route::get('/perangkat-desa/{id}', [UsersController::class, 'getDetailPerangkatDesa']);

        Route::post('/perangkat-desa/{id}', [UsersController::class, 'updatePerangkatDesaByAdmin']);

        Route::delete('/perangkat-desa/{id}', [UsersController::class, 'deletePerangkatDesa']);

        // ============================
        // KEPALA DESA MANAGEMENT
        // ============================

        Route::post('/add-kepala-desa', [UsersController::class, 'addKepalaDesa']);

        Route::get('/kepala-desa', [UsersController::class, 'getKepalaDesa']);

        Route::get('/kepala-desa/{id}', [UsersController::class, 'getDetailKepalaDesa']);

        Route::post('/kepala-desa/{id}', [UsersController::class, 'updateKepalaDesaByAdmin']);

        Route::delete('/kepala-desa/{id}', [UsersController::class, 'deleteKepalaDesa']);

        // ============================
        // SUPER ADMIN MANAGEMENT
        // ============================

        Route::post('/add-super-admin', [UsersController::class, 'addSuperAdmin']);

        Route::get('/super-admin', [UsersController::class, 'getSuperAdmin']);

        Route::get('/super-admin/{id}', [UsersController::class, 'getDetailSuperAdmin']);

        Route::post('/super-admin/{id}', [UsersController::class, 'updateSuperAdminByAdmin']);

        Route::delete('/super-admin/{id}', [UsersController::class, 'deleteSuperAdmin']);
    });



// ===============================================================================================================================================
// ======================================================== ROUTE DATA MASYARAKAT ===============================================================
// ===============================================================================================================================================

// === ROUTE KHUSUS PERANGKAT DESA ===
Route::middleware('auth:sanctum')->prefix('perangkat_desa')->group(function () {
    Route::get('/masyarakat', [PerangkatDesaController::class, 'getAllMasyarakat']);
    Route::get('/masyarakat/{id}', [PerangkatDesaController::class, 'getDetailMasyarakatById']);
    Route::put('/verifikasi/{id}', [PerangkatDesaController::class, 'verifikasiMasyarakat']);
    Route::post('/masyarakat-export', [PerangkatDesaController::class, 'exportDataMasyarakat']);
});


// ===============================================================================================================================================
// ========================================================== ROUTE DATA STATISTIK ===============================================================
// ===============================================================================================================================================

//PUBLIC
Route::get('/data-statistik/public', [DataStatistikController::class, 'indexPublic']);
Route::get('/data-statistik/public/{id}', [DataStatistikController::class, 'showPublic'])->whereNumber('id');

//ADMIN
Route::middleware('auth:sanctum')->prefix('data-statistik')->group(function () {
    Route::get('/admin', [DataStatistikController::class, 'index']);
    Route::get('/admin/{id}', [DataStatistikController::class, 'show'])->whereNumber('id');
    Route::post('/', [DataStatistikController::class, 'store']);
    Route::post('/{id}', [DataStatistikController::class, 'update']);
    Route::delete('/delete/{id}', [DataStatistikController::class, 'destroy']);

});

// DATA STATISTIK UNTUK DITAMPILKAN DI FE
Route::get('/statistik/json', [DataStatistikJSON::class, 'index']);

// ===============================================================================================================================================
// ======================================================== ROUTE DATA BERITA ===============================================================
// ===============================================================================================================================================

Route::prefix('berita')->group(function () {

    // ================== ADMIN ==================
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/', [BeritaController::class, 'store']);
        Route::get('/get-admin', [BeritaController::class, 'indexAdmin']);
        Route::get('/get-admin/{id}', [BeritaController::class, 'showAdmin']);
        Route::post('/{id}', [BeritaController::class, 'update']);
        Route::delete('/{id}', [BeritaController::class, 'deleteBerita']);
    });

    // ================== PUBLIC ==================
    Route::get('/', [BeritaController::class, 'indexPublic']);
    Route::get('/detail/{id}', [BeritaController::class, 'showPublic']);

});


// ===============================================================================================================================================
// ============================================================= ROUTE DATA LAPORAN ===============================================================
// ===============================================================================================================================================
Route::prefix('laporan')->group(function () {

    //ADMIN
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/get-admin', [LaporanController::class, 'indexAdmin']);
        Route::get('/get-admin/{id}', [LaporanController::class, 'showAdmin']);
        Route::put('/{id}', [LaporanController::class, 'update']);
        Route::delete('/{id}', [LaporanController::class, 'destroy']);
        });

    //PUBLIC
    Route::get('/', [LaporanController::class, 'index']);
    Route::post('/', [LaporanController::class, 'store'])->middleware('throttle:5,1');
    Route::get('/{id}', [LaporanController::class, 'show']);
 

});


// ===============================================================================================================================================
// ============================================ KUMPULAN ROUTE LAYANAN AJUAN SURAT ===============================================================
// ===============================================================================================================================================


// ================================================= ROUTE LAYANAN SKTM (FIX) ================================================


Route::middleware(['auth:sanctum,masyarakat'])->group(function () {
    Route::get('/sktm/filter', [SuketTidakMampuController::class, 'searchfilter']);
});

Route::prefix('sktm')->group(function () {
    // Route::get('/filter', [SuketTidakMampuController::class, 'searchfilter']);
    Route::get('/', [SuketTidakMampuController::class, 'index']); // semua RT
    Route::get('/{id}', [SuketTidakMampuController::class, 'show']);
    Route::put('/{id}', [SuketTidakMampuController::class, 'update']);
    Route::delete('/{id}', [SuketTidakMampuController::class, 'destroy']);
});

// === ROUTE KHUSUS MASYARAKAT ===
Route::middleware('auth:sanctum')->prefix('masyarakat')->group(function () {
    Route::post('/sktm', [SuketTidakMampuController::class, 'store']);
    Route::post('/sktm/rt', [SuketTidakMampuController::class, 'storeByRT']);
    Route::post('/sktm/perangkat', [SuketTidakMampuController::class, 'storeByPerangkat']);
    Route::post('/sktm/{id}', [SuketTidakMampuController::class, 'ajukanKembali']);
    Route::get('/getRT/my', [MasyarakatController::class, 'getAllRT']);
    Route::get('/sktm/my', [SuketTidakMampuController::class, 'getByMasyarakat']);
});

Route::get('sktm/file/{id_sktm}/{type}', [SuketTidakMampuController::class, 'viewSecureFile']);

// === ROUTE KHUSUS RT ===
Route::middleware('auth:sanctum')->prefix('rt')->group(function () {
    Route::get('/sktm', [SuketTidakMampuController::class, 'getByKetuaRT']);
});

// === ROUTE KHUSUS PERANGKAT DESA ===
Route::middleware('auth:sanctum')->prefix('perangkat_desa')->group(function () {
    Route::get('/sktm', [SuketTidakMampuController::class, 'getByPerangkatDesa']);
    Route::post('/sktm/export', [SuketTidakMampuController::class, 'exportSKTM']);
    Route::post('/sktm/export/zip', [SuketTidakMampuController::class, 'exportZipSKTM']);
    Route::delete('/sktm/delete-all', [SuketTidakMampuController::class, 'deleteAllSKTM']);
});

// === ROUTE KHUSUS KEPALA DESA ===
Route::middleware('auth:sanctum')->prefix('kepala_desa')->group(function () {
    Route::get('/sktm', [SuketTidakMampuController::class, 'getByKepalaDesa']);
    Route::post('/{kd_id}/sktm/{id_sktm}/validasi', [SuketTidakMampuController::class, 'validasiKepalaDesa']);
});

// === ROUTE KHUSUS SUPER ADMIN ===
Route::middleware('auth:sanctum')->prefix('super_admin')->group(function () {
    Route::get('/sktm', [SuketTidakMampuController::class, 'getAll']);
});

// === ROUTE KHUSUS RT ===
Route::middleware('auth:sanctum')->prefix('rt')->group(function () {
    // Route::get('/{rt_id}/sktm', [SuketTidakMampuController::class, 'getByRT']);
    Route::post('/{rt_id}/sktm/{id_sktm}/proses', [SuketTidakMampuController::class, 'validasiKetuaRT']
    );
});

// === ROUTE KHUSUS PERANGKAT DESA ===
Route::middleware('auth:sanctum')->prefix('perangkat-desa')->group(function () {
    Route::put('/{pd_id}/sktm/{id_sktm}/proses', [SuketTidakMampuController::class, 'validasiPerangkatDesa']);
});


// === ROUTE KHUSUS KEPALA DESA ===
// Route::middleware('auth:sanctum')->prefix('kepala-desa')->group(function () {
//     Route::post('/{kd_id}/sktm/{id_sktm}/validasi', [SuketTidakMampuController::class, 'validasiKepalaDesa']);
// });

// === ROUTE GENERATE PDF ===
Route::get('/sktm/{id}/pdf', [SuketTidakMampuController::class, 'generatePdf']);

//============================================================================================================================



// ================================================= ROUTE LAYANAN SKU (FIX) =================================================

Route::middleware(['auth:sanctum,masyarakat'])->group(function () {
    Route::get('/sku/filter', [SuketUsahaController::class, 'searchfilter']);
});

Route::prefix('sku')->group(function () {
    // Route::get('/filter', [SuketUsahaController::class, 'searchfilter']);
    Route::get('/', [SuketUsahaController::class, 'index']); // semua RT
    Route::get('/{id}', [SuketUsahaController::class, 'show']);
    Route::put('/{id}', [SuketUsahaController::class, 'update']);
    Route::delete('/{id}', [SuketUsahaController::class, 'destroy']);
});

// === ROUTE KHUSUS MASYARAKAT ===
Route::middleware('auth:sanctum')->prefix('masyarakat')->group(function () {
    Route::post('/sku', [SuketUsahaController::class, 'store']);
    Route::post('/sku/rt', [SuketUsahaController::class, 'storeByRT']);
    Route::post('/sku/perangkat', [SuketUsahaController::class, 'storeByPerangkat']);
    Route::post('/sku/{id}', [SuketUsahaController::class, 'ajukanKembali']);
    Route::get('/getRT/my', [MasyarakatController::class, 'getAllRT']);
    Route::get('/sku/my', [SuketUsahaController::class, 'getByMasyarakat']);
});

Route::get('sku/file/{id_sktm}/{type}', [SuketUsahaController::class, 'viewSecureFile']);

// === ROUTE KHUSUS RT ===
Route::middleware('auth:sanctum')->prefix('rt')->group(function () {
    Route::get('/sku', [SuketUsahaController::class, 'getByKetuaRT']);
});

// === ROUTE KHUSUS PERANGKAT DESA ===
Route::middleware('auth:sanctum')->prefix('perangkat_desa')->group(function () {
    Route::get('/sku', [SuketUsahaController::class, 'getByPerangkatDesa']);
    Route::post('/sku/export', [SuketUsahaController::class, 'exportSKU']);
    Route::post('/sku/export/zip', [SuketUsahaController::class, 'exportZipSKU']);
    Route::delete('/sku/delete-all', [SuketUsahaController::class, 'deleteAllSKU']);
});

// === ROUTE KHUSUS KEPALA DESA ===
Route::middleware('auth:sanctum')->prefix('kepala_desa')->group(function () {
    Route::get('/sku', [SuketUsahaController::class, 'getByKepalaDesa']);
    Route::post('/{kd_id}/sku/{id_sku}/validasi', [SuketUsahaController::class, 'validasiKepalaDesa']);
});

// === ROUTE KHUSUS SUPER ADMIN ===
Route::middleware('auth:sanctum')->prefix('super_admin')->group(function () {
    Route::get('/sku', [SuketUsahaController::class, 'getAll']);
});

// === ROUTE KHUSUS RT ===
Route::middleware('auth:sanctum')->prefix('rt')->group(function () {
    // Route::get('/{rt_id}/sku', [SuketUsahaController::class, 'getByRT']);
    Route::post('/{rt_id}/sku/{id_sku}/proses', [SuketUsahaController::class, 'validasiKetuaRT']
    );
});

// === ROUTE KHUSUS PERANGKAT DESA ===
Route::middleware('auth:sanctum')->prefix('perangkat-desa')->group(function () {
    Route::put('/{pd_id}/sku/{id_sku}/proses', [SuketUsahaController::class, 'validasiPerangkatDesa']);
});


// === ROUTE KHUSUS KEPALA DESA ===
// Route::middleware('auth:sanctum')->prefix('kepala-desa')->group(function () {
//     Route::post('/{kd_id}/sku/{id_sku}/validasi', [SuketUsahaController::class, 'validasiKepalaDesa']);
// });

// === ROUTE GENERATE PDF ===
Route::get('/sku/{id}/pdf', [SuketUsahaController::class, 'generatePdf']);




// ================================================= ROUTE LAYANAN SKITT (Surat Keterangan Imunisasi TT) (FIX) =================================================

Route::middleware(['auth:sanctum,masyarakat'])->group(function () {
    Route::get('/skitt/filter', [SuketImunisasiTTController::class, 'searchfilter']);
});

Route::prefix('skitt')->group(function () {
    // Route::get('/filter', [SuketImunisasiTTController::class, 'searchfilter']);
    Route::get('/', [SuketImunisasiTTController::class, 'index']); // semua RT
    Route::get('/{id}', [SuketImunisasiTTController::class, 'show']);
    Route::put('/{id}', [SuketImunisasiTTController::class, 'update']);
    Route::delete('/{id}', [SuketImunisasiTTController::class, 'destroy']);
});

// === ROUTE KHUSUS MASYARAKAT ===
Route::middleware('auth:sanctum')->prefix('masyarakat')->group(function () {
    Route::post('/skitt', [SuketImunisasiTTController::class, 'store']);
    Route::post('/skitt/rt', [SuketImunisasiTTController::class, 'storeByRT']);
    Route::post('/skitt/perangkat', [SuketImunisasiTTController::class, 'storeByPerangkat']);
    Route::post('/skitt/{id}', [SuketImunisasiTTController::class, 'ajukanKembali']);
    Route::get('/getRT/my', [MasyarakatController::class, 'getAllRT']);
    Route::get('/skitt/my', [SuketImunisasiTTController::class, 'getByMasyarakat']);
});

// === ROUTE KHUSUS RT ===
Route::middleware('auth:sanctum')->prefix('rt')->group(function () {
    Route::get('/skitt', [SuketImunisasiTTController::class, 'getByKetuaRT']);
});

// === ROUTE KHUSUS PERANGKAT DESA ===
Route::middleware('auth:sanctum')->prefix('perangkat_desa')->group(function () {
    Route::get('/skitt', [SuketImunisasiTTController::class, 'getByPerangkatDesa']);
    Route::post('/skitt/export', [SuketImunisasiTTController::class, 'exportSKITT']);
    Route::post('/skitt/export/zip', [SuketImunisasiTTController::class, 'exportZipSKITT']);
    Route::delete('/skitt/delete-all', [SuketImunisasiTTController::class, 'deleteAllSKITT']);
});

// === ROUTE KHUSUS KEPALA DESA ===
Route::middleware('auth:sanctum')->prefix('kepala_desa')->group(function () {
    Route::get('/skitt', [SuketImunisasiTTController::class, 'getByKepalaDesa']);
    Route::post('/{kd_id}/skitt/{id_skitt}/validasi', [SuketImunisasiTTController::class, 'validasiKepalaDesa']);
});

// === ROUTE KHUSUS SUPER ADMIN ===
Route::middleware('auth:sanctum')->prefix('super_admin')->group(function () {
    Route::get('/skitt', [SuketImunisasiTTController::class, 'getAll']);
});

// === ROUTE KHUSUS RT ===
Route::middleware('auth:sanctum')->prefix('rt')->group(function () {
    // Route::get('/{rt_id}/skitt', [SuketImunisasiTTController::class, 'getByRT']);
    Route::post('/{rt_id}/skitt/{id_skitt}/proses', [SuketImunisasiTTController::class, 'validasiKetuaRT']
    );
});

// === ROUTE KHUSUS PERANGKAT DESA ===
Route::middleware('auth:sanctum')->prefix('perangkat-desa')->group(function () {
    Route::put('/{pd_id}/skitt/{id_skitt}/proses', [SuketImunisasiTTController::class, 'validasiPerangkatDesa']);
});


// === ROUTE KHUSUS KEPALA DESA ===
// Route::middleware('auth:sanctum')->prefix('kepala-desa')->group(function () {
//     Route::post('/{kd_id}/skitt/{id_skitt}/validasi', [SuketImunisasiTTController::class, 'validasiKepalaDesa']);
// });

// === ROUTE GENERATE PDF ===
Route::get('/skitt/{id}/pdf', [SuketImunisasiTTController::class, 'generatePdf']);








// ================================================= ROUTE LAYANAN SKJ (Surat Keterangan Janda) (FIX) =================================================

Route::middleware(['auth:sanctum,masyarakat'])->group(function () {
    Route::get('/skj/filter', [SuketJandaController::class, 'searchfilter']);
});

Route::prefix('skj')->group(function () {
    // Route::get('/filter', [SuketJandaController::class, 'searchfilter']);
    Route::get('/', [SuketJandaController::class, 'index']); // semua RT
    Route::get('/{id}', [SuketJandaController::class, 'show']);
    Route::put('/{id}', [SuketJandaController::class, 'update']);
    Route::delete('/{id}', [SuketJandaController::class, 'destroy']);
});

// === ROUTE KHUSUS MASYARAKAT ===
Route::middleware('auth:sanctum')->prefix('masyarakat')->group(function () {
    Route::post('/skj', [SuketJandaController::class, 'store']);
    Route::post('/skj/rt', [SuketJandaController::class, 'storeByRT']);
    Route::post('/skj/perangkat', [SuketJandaController::class, 'storeByPerangkat']);
    Route::post('/skj/{id}', [SuketJandaController::class, 'ajukanKembali']);
    Route::get('/getRT/my', [MasyarakatController::class, 'getAllRT']);
    Route::get('/skj/my', [SuketJandaController::class, 'getByMasyarakat']);
});

// === ROUTE KHUSUS RT ===
Route::middleware('auth:sanctum')->prefix('rt')->group(function () {
    Route::get('/skj', [SuketJandaController::class, 'getByKetuaRT']);
});

// === ROUTE KHUSUS PERANGKAT DESA ===
Route::middleware('auth:sanctum')->prefix('perangkat_desa')->group(function () {
    Route::get('/skj', [SuketJandaController::class, 'getByPerangkatDesa']);
    Route::post('/skj/export', [SuketJandaController::class, 'exportSKJ']);
    Route::post('/skj/export/zip', [SuketJandaController::class, 'exportZipSKJ']);
    Route::delete('/skj/delete-all', [SuketJandaController::class, 'deleteAllSKJ']);
});

// === ROUTE KHUSUS KEPALA DESA ===
Route::middleware('auth:sanctum')->prefix('kepala_desa')->group(function () {
    Route::get('/skj', [SuketJandaController::class, 'getByKepalaDesa']);
    Route::post('/{kd_id}/skj/{id_skj}/validasi', [SuketJandaController::class, 'validasiKepalaDesa']);
});

// === ROUTE KHUSUS SUPER ADMIN ===
Route::middleware('auth:sanctum')->prefix('super_admin')->group(function () {
    Route::get('/skj', [SuketJandaController::class, 'getAll']);
});

// === ROUTE KHUSUS RT ===
Route::middleware('auth:sanctum')->prefix('rt')->group(function () {
    // Route::get('/{rt_id}/skj', [SuketJandaController::class, 'getByRT']);
    Route::post('/{rt_id}/skj/{id_skj}/proses', [SuketJandaController::class, 'validasiKetuaRT']
    );
});

// === ROUTE KHUSUS PERANGKAT DESA ===
Route::middleware('auth:sanctum')->prefix('perangkat-desa')->group(function () {
    Route::put('/{pd_id}/skj/{id_skj}/proses', [SuketJandaController::class, 'validasiPerangkatDesa']);
});


// === ROUTE KHUSUS KEPALA DESA ===
// Route::middleware('auth:sanctum')->prefix('kepala-desa')->group(function () {
//     Route::post('/{kd_id}/skj/{id_skj}/validasi', [SuketJandaController::class, 'validasiKepalaDesa']);
// });

// === ROUTE GENERATE PDF ===
Route::get('/skj/{id}/pdf', [SuketJandaController::class, 'generatePdf']);







// ================================================= ROUTE LAYANAN SKCK (FIX) =================================================

Route::middleware(['auth:sanctum,masyarakat'])->group(function () {
    Route::get('/skck/filter', [PermohonanSKCKController::class, 'searchfilter']);
});

Route::prefix('skck')->group(function () {
    // Route::get('/filter', [PermohonanSKCKController::class, 'searchfilter']);
    Route::get('/', [PermohonanSKCKController::class, 'index']); // semua RT
    Route::get('/{id}', [PermohonanSKCKController::class, 'show']);
    Route::put('/{id}', [PermohonanSKCKController::class, 'update']);
    Route::delete('/{id}', [PermohonanSKCKController::class, 'destroy']);
});

// === ROUTE KHUSUS MASYARAKAT ===
Route::middleware('auth:sanctum')->prefix('masyarakat')->group(function () {
    Route::post('/skck', [PermohonanSKCKController::class, 'store']);
    Route::post('/skck/rt', [PermohonanSKCKController::class, 'storeByRT']);
    Route::post('/skck/perangkat', [PermohonanSKCKController::class, 'storeByPerangkat']);
    Route::post('/skck/{id}', [PermohonanSKCKController::class, 'ajukanKembali']);
    Route::get('/getRT/my', [MasyarakatController::class, 'getAllRT']);
    Route::get('/skck/my', [PermohonanSKCKController::class, 'getByMasyarakat']);
});

// === ROUTE KHUSUS RT ===
Route::middleware('auth:sanctum')->prefix('rt')->group(function () {
    Route::get('/skck', [PermohonanSKCKController::class, 'getByKetuaRT']);
});

// === ROUTE KHUSUS PERANGKAT DESA ===
Route::middleware('auth:sanctum')->prefix('perangkat_desa')->group(function () {
    Route::get('/skck', [PermohonanSKCKController::class, 'getByPerangkatDesa']);
    Route::post('/skck/export', [PermohonanSKCKController::class, 'exportSKCK']);
    Route::post('/skck/export/zip', [PermohonanSKCKController::class, 'exportZipSKCK']);
    Route::delete('/skck/delete-all', [PermohonanSKCKController::class, 'deleteAllSKCK']);
});

// === ROUTE KHUSUS KEPALA DESA ===
Route::middleware('auth:sanctum')->prefix('kepala_desa')->group(function () {
    Route::get('/skck', [PermohonanSKCKController::class, 'getByKepalaDesa']);
    Route::post('/{kd_id}/skck/{id_skck}/validasi', [PermohonanSKCKController::class, 'validasiKepalaDesa']);
});

// === ROUTE KHUSUS SUPER ADMIN ===
Route::middleware('auth:sanctum')->prefix('super_admin')->group(function () {
    Route::get('/skck', [PermohonanSKCKController::class, 'getAll']);
});

// === ROUTE KHUSUS RT ===
Route::middleware('auth:sanctum')->prefix('rt')->group(function () {
    // Route::get('/{rt_id}/skck', [PermohonanSKCKController::class, 'getByRT']);
    Route::post('/{rt_id}/skck/{id_skck}/proses', [PermohonanSKCKController::class, 'validasiKetuaRT']
    );
});

// === ROUTE KHUSUS PERANGKAT DESA ===
Route::middleware('auth:sanctum')->prefix('perangkat-desa')->group(function () {
    Route::put('/{pd_id}/skck/{id_skck}/proses', [PermohonanSKCKController::class, 'validasiPerangkatDesa']);
});


// === ROUTE KHUSUS KEPALA DESA ===
// Route::middleware('auth:sanctum')->prefix('kepala-desa')->group(function () {
//     Route::post('/{kd_id}/skck/{id_skck}/validasi', [PermohonanSKCKController::class, 'validasiKepalaDesa']);
// });

// === ROUTE GENERATE PDF ===
Route::get('/skck/{id}/pdf', [PermohonanSKCKController::class, 'generatePdf']);







// ================================================= ROUTE LAYANAN SKBM (Surat Keterangan Belum Menikah) (FIX) =================================================

Route::middleware(['auth:sanctum,masyarakat'])->group(function () {
    Route::get('/skbm/filter', [SuketBelumMenikahController::class, 'searchfilter']);
});

Route::prefix('skbm')->group(function () {
    // Route::get('/filter', [SuketBelumMenikahController::class, 'searchfilter']);
    Route::get('/', [SuketBelumMenikahController::class, 'index']); // semua RT
    Route::get('/{id}', [SuketBelumMenikahController::class, 'show']);
    Route::put('/{id}', [SuketBelumMenikahController::class, 'update']);
    Route::delete('/{id}', [SuketBelumMenikahController::class, 'destroy']);
});

// === ROUTE KHUSUS MASYARAKAT ===
Route::middleware('auth:sanctum')->prefix('masyarakat')->group(function () {
    Route::post('/skbm', [SuketBelumMenikahController::class, 'store']);
    Route::post('/skbm/rt', [SuketBelumMenikahController::class, 'storeByRT']);
    Route::post('/skbm/perangkat', [SuketBelumMenikahController::class, 'storeByPerangkat']);
    Route::post('/skbm/{id}', [SuketBelumMenikahController::class, 'ajukanKembali']);
    Route::get('/getRT/my', [MasyarakatController::class, 'getAllRT']);
    Route::get('/skbm/my', [SuketBelumMenikahController::class, 'getByMasyarakat']);
});

Route::get('skbm/file/{id_sktm}/{type}', [SuketBelumMenikahController::class, 'viewSecureFile']);

// === ROUTE KHUSUS RT ===
Route::middleware('auth:sanctum')->prefix('rt')->group(function () {
    Route::get('/skbm', [SuketBelumMenikahController::class, 'getByKetuaRT']);
});

// === ROUTE KHUSUS PERANGKAT DESA ===
Route::middleware('auth:sanctum')->prefix('perangkat_desa')->group(function () {
    Route::get('/skbm', [SuketBelumMenikahController::class, 'getByPerangkatDesa']);
    Route::post('/skbm/export', [SuketBelumMenikahController::class, 'exportSKBM']);
    Route::post('/skbm/export/zip', [SuketBelumMenikahController::class, 'exportZipSKBM']);
    Route::delete('/skbm/delete-all', [SuketBelumMenikahController::class, 'deleteAllSKBM']);
});

// === ROUTE KHUSUS KEPALA DESA ===
Route::middleware('auth:sanctum')->prefix('kepala_desa')->group(function () {
    Route::get('/skbm', [SuketBelumMenikahController::class, 'getByKepalaDesa']);
    Route::post('/{kd_id}/skbm/{id_skbm}/validasi', [SuketBelumMenikahController::class, 'validasiKepalaDesa']);
});

// === ROUTE KHUSUS SUPER ADMIN ===
Route::middleware('auth:sanctum')->prefix('super_admin')->group(function () {
    Route::get('/skbm', [SuketBelumMenikahController::class, 'getAll']);
});

// === ROUTE KHUSUS RT ===
Route::middleware('auth:sanctum')->prefix('rt')->group(function () {
    // Route::get('/{rt_id}/skbm', [SuketBelumMenikahController::class, 'getByRT']);
    Route::post('/{rt_id}/skbm/{id_skbm}/proses', [SuketBelumMenikahController::class, 'validasiKetuaRT']
    );
});

// === ROUTE KHUSUS PERANGKAT DESA ===
Route::middleware('auth:sanctum')->prefix('perangkat-desa')->group(function () {
    Route::put('/{pd_id}/skbm/{id_skbm}/proses', [SuketBelumMenikahController::class, 'validasiPerangkatDesa']);
});


// === ROUTE KHUSUS KEPALA DESA ===
// Route::middleware('auth:sanctum')->prefix('kepala-desa')->group(function () {
//     Route::post('/{kd_id}/skbm/{id_skbm}/validasi', [SuketBelumMenikahController::class, 'validasiKepalaDesa']);
// });

// === ROUTE GENERATE PDF ===
Route::get('/skbm/{id}/pdf', [SuketBelumMenikahController::class, 'generatePdf']);








// ================================================= ROUTE LAYANAN SKP (Surat Keterangan Penduduk) (FIX) =================================================

Route::middleware(['auth:sanctum,masyarakat'])->group(function () {
    Route::get('/skp/filter', [SuketPendudukController::class, 'searchfilter']);
});

Route::prefix('skp')->group(function () {
    // Route::get('/filter', [SuketPendudukController::class, 'searchfilter']);
    Route::get('/', [SuketPendudukController::class, 'index']); // semua RT
    Route::get('/{id}', [SuketPendudukController::class, 'show']);
    Route::put('/{id}', [SuketPendudukController::class, 'update']);
    Route::delete('/{id}', [SuketPendudukController::class, 'destroy']);
});

// === ROUTE KHUSUS MASYARAKAT ===
Route::middleware('auth:sanctum')->prefix('masyarakat')->group(function () {
    Route::post('/skp', [SuketPendudukController::class, 'store']);
    Route::post('/skp/rt', [SuketPendudukController::class, 'storeByRT']);
    Route::post('/skp/perangkat', [SuketPendudukController::class, 'storeByPerangkat']);
    Route::post('/skp/{id}', [SuketPendudukController::class, 'ajukanKembali']);
    Route::get('/getRT/my', [MasyarakatController::class, 'getAllRT']);
    Route::get('/skp/my', [SuketPendudukController::class, 'getByMasyarakat']);
});

// === ROUTE KHUSUS RT ===
Route::middleware('auth:sanctum')->prefix('rt')->group(function () {
    Route::get('/skp', [SuketPendudukController::class, 'getByKetuaRT']);
});

// === ROUTE KHUSUS PERANGKAT DESA ===
Route::middleware('auth:sanctum')->prefix('perangkat_desa')->group(function () {
    Route::get('/skp', [SuketPendudukController::class, 'getByPerangkatDesa']);
    Route::post('/skp/export', [SuketPendudukController::class, 'exportSKP']);
    Route::post('/skp/export/zip', [SuketPendudukController::class, 'exportZipSKP']);
    Route::delete('/skp/delete-all', [SuketPendudukController::class, 'deleteAllSKP']);
});

// === ROUTE KHUSUS KEPALA DESA ===
Route::middleware('auth:sanctum')->prefix('kepala_desa')->group(function () {
    Route::get('/skp', [SuketPendudukController::class, 'getByKepalaDesa']);
    Route::post('/{kd_id}/skp/{id_skp}/validasi', [SuketPendudukController::class, 'validasiKepalaDesa']);
});

// === ROUTE KHUSUS SUPER ADMIN ===
Route::middleware('auth:sanctum')->prefix('super_admin')->group(function () {
    Route::get('/skp', [SuketPendudukController::class, 'getAll']);
});

// === ROUTE KHUSUS RT ===
Route::middleware('auth:sanctum')->prefix('rt')->group(function () {
    // Route::get('/{rt_id}/skp', [SuketPendudukController::class, 'getByRT']);
    Route::post('/{rt_id}/skp/{id_skp}/proses', [SuketPendudukController::class, 'validasiKetuaRT']
    );
});

// === ROUTE KHUSUS PERANGKAT DESA ===
Route::middleware('auth:sanctum')->prefix('perangkat-desa')->group(function () {
    Route::put('/{pd_id}/skp/{id_skp}/proses', [SuketPendudukController::class, 'validasiPerangkatDesa']);
});


// === ROUTE KHUSUS KEPALA DESA ===
// Route::middleware('auth:sanctum')->prefix('kepala-desa')->group(function () {
//     Route::post('/{kd_id}/skp/{id_skp}/validasi', [SuketPendudukController::class, 'validasiKepalaDesa']);
// });

// === ROUTE GENERATE PDF ===
Route::get('/skp/{id}/pdf', [SuketPendudukController::class, 'generatePdf']);
