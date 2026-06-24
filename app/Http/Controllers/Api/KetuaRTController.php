<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class KetuaRTController extends Controller
{
    public function getTtdRt(Request $request)
    {
        $user = auth()->user();

        // 🔐 Proteksi role
        if (!$user || $user->role !== 'rt') {
            return response()->json([
                'success' => false,
                'message' => 'Akses ditolak'
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'rt_id' => $user->id,
                'nama'  => $user->nama,
                'ttd'   => $user->ttd ? url($user->ttd) : null
            ]
        ]);
    }
}

