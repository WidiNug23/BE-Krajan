<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class KepalaDesaController extends Controller
{
    public function getTtdKades(Request $request)
    {
        $user = auth()->user();

        // 🔐 Proteksi role
        if (!$user || $user->role !== 'kepala_desa') {
            return response()->json([
                'success' => false,
                'message' => 'Akses ditolak'
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'kepala_desa_id' => $user->id,
                'nama'           => $user->nama,
                'ttd'            => $user->ttd ? url($user->ttd) : null
            ]
        ]);
    }
}
