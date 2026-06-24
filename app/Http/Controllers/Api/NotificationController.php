<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Notification;

class NotificationController extends Controller
{
   public function index(Request $request)
{
    $user = auth()->user();

    if (!$user) {
        return response()->json([
            'success' => false,
            'message' => 'Unauthorized'
        ], 401);
    }

    // ============================
    // QUERY DASAR
    // ============================
    $query = Notification::query()
        ->orderBy('created_at', 'desc');

    // ============================
    // 🔹 JIKA LOGIN MASYARAKAT
    // ============================
    if ($request->user()->tokenCan('masyarakat')) {

        $query->where('masyarakat_id', $user->id);

        $notifications = $query->get();

        return response()->json([
            'success' => true,
            'count'   => $notifications->count(),
            'data'    => $notifications
        ]);
    }

    // ============================
    // 🔹 JIKA LOGIN USERS (RT, DLL)
    // ============================

    // RT → notifikasi khusus user_id
    if ($user->role === 'rt') {
        $query->where('user_id', $user->id);
    }

    // Perangkat Desa
    elseif ($user->role === 'perangkat_desa') {
        $query->where('role', 'perangkat_desa');
    }

    // Kepala Desa
    elseif ($user->role === 'kepala_desa') {
        $query->where('role', 'kepala_desa');
    }

    // Role lain
    else {
        return response()->json([
            'success' => true,
            'data' => [],
            'count' => 0
        ]);
    }

    $notifications = $query->get();

    return response()->json([
        'success' => true,
        'count'   => $notifications->count(),
        'data'    => $notifications
    ]);
}


public function markAsRead($id)
{
    $user = auth()->user();

    if (!$user) {
        return response()->json([
            'success' => false,
            'message' => 'Unauthorized'
        ], 401);
    }

    $notification = Notification::find($id);

    if (!$notification) {
        return response()->json([
            'success' => false,
            'message' => 'Notifikasi tidak ditemukan'
        ], 404);
    }

    // ============================
    // 🔹 MASYARAKAT
    // ============================
    if (request()->user()->tokenCan('masyarakat')) {

        if ($notification->masyarakat_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Akses ditolak'
            ], 403);
        }

        $notification->update(['is_read' => true]);

        return response()->json([
            'success' => true,
            'message' => 'Notifikasi ditandai sudah dibaca'
        ]);
    }

    // ============================
    // 🔹 USERS (RT / PERANGKAT / KEPALA)
    // ============================

    // RT
    if ($user->role === 'rt' && $notification->user_id !== $user->id) {
        return response()->json([
            'success' => false,
            'message' => 'Akses ditolak'
        ], 403);
    }

    // Perangkat Desa
    if ($user->role === 'perangkat_desa' && $notification->role !== 'perangkat_desa') {
        return response()->json([
            'success' => false,
            'message' => 'Akses ditolak'
        ], 403);
    }

    // Kepala Desa
    if ($user->role === 'kepala_desa' && $notification->role !== 'kepala_desa') {
        return response()->json([
            'success' => false,
            'message' => 'Akses ditolak'
        ], 403);
    }

    if ($notification->is_read) {
    return response()->json([
        'success' => true,
        'message' => 'Notifikasi sudah dibaca sebelumnya'
    ]);
}

    // ============================
    // UPDATE STATUS
    // ============================
    $notification->update([
        'is_read' => true
    ]);

    return response()->json([
        'success' => true,
        'message' => 'Notifikasi ditandai sudah dibaca'
    ]);
}


public function countUnread()
{
    $user = auth()->user();

    if (!$user) {
        return response()->json([
            'success' => false,
            'message' => 'Unauthorized'
        ], 401);
    }

    $query = Notification::where('is_read', false);

    // ============================
    // 🔹 MASYARAKAT
    // ============================
    if (request()->user()->tokenCan('masyarakat')) {

        $query->where('masyarakat_id', $user->id);

        return response()->json([
            'success' => true,
            'unread' => $query->count()
        ]);
    }

    // ============================
    // 🔹 USERS (RT / PERANGKAT / KEPALA)
    // ============================

    if ($user->role === 'rt') {
        $query->where('user_id', $user->id);
    }
    elseif ($user->role === 'perangkat_desa') {
        $query->where('role', 'perangkat_desa');
    }
    elseif ($user->role === 'kepala_desa') {
        $query->where('role', 'kepala_desa');
    }
    else {
        return response()->json([
            'success' => true,
            'unread' => 0
        ]);
    }

    return response()->json([
        'success' => true,
        'unread' => $query->count()
    ]);
}

public function destroy($id)
{
    $user = auth()->user();

    if (!$user) {
        return response()->json([
            'success' => false,
            'message' => 'Unauthorized'
        ], 401);
    }

    $notification = Notification::find($id);

    if (!$notification) {
        return response()->json([
            'success' => false,
            'message' => 'Notifikasi tidak ditemukan'
        ], 404);
    }

    // ============================
    // 🔹 MASYARAKAT
    // ============================
    if (request()->user()->tokenCan('masyarakat')) {

        if ($notification->masyarakat_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Akses ditolak'
            ], 403);
        }

        $notification->delete();

        return response()->json([
            'success' => true,
            'message' => 'Notifikasi berhasil dihapus'
        ]);
    }

    // ============================
    // 🔹 USERS (RT / PERANGKAT / KEPALA)
    // ============================

    if ($user->role === 'rt' && $notification->user_id !== $user->id) {
        return response()->json([
            'success' => false,
            'message' => 'Akses ditolak'
        ], 403);
    }

    if (
        in_array($user->role, ['perangkat_desa', 'kepala_desa']) &&
        $notification->role !== $user->role
    ) {
        return response()->json([
            'success' => false,
            'message' => 'Akses ditolak'
        ], 403);
    }

    $notification->delete();

    return response()->json([
        'success' => true,
        'message' => 'Notifikasi berhasil dihapus'
    ]);
}

public function deleteAll()
{
    $user = auth()->user();

    if (!$user) {
        return response()->json([
            'success' => false,
            'message' => 'Unauthorized'
        ], 401);
    }

    // ============================
    // 🔹 MASYARAKAT
    // ============================
    if (request()->user()->tokenCan('masyarakat')) {

        $deleted = Notification::where('masyarakat_id', $user->id)->delete();

        return response()->json([
            'success' => true,
            'message' => 'Semua notifikasi berhasil dihapus',
            'deleted' => $deleted
        ]);
    }

    // ============================
    // 🔹 USERS (RT / PERANGKAT / KEPALA)
    // ============================

    $query = Notification::query();

    // RT → hanya miliknya
    if ($user->role === 'rt') {
        $query->where('user_id', $user->id);
    }

    // Perangkat Desa → role perangkat
    elseif ($user->role === 'perangkat_desa') {
        $query->where('role', 'perangkat_desa');
    }

    // Kepala Desa → role kades
    elseif ($user->role === 'kepala_desa') {
        $query->where('role', 'kepala_desa');
    }

    else {
        return response()->json([
            'success' => false,
            'message' => 'Role tidak memiliki akses'
        ], 403);
    }

    $deleted = $query->delete();

    return response()->json([
        'success' => true,
        'message' => 'Semua notifikasi berhasil dihapus',
        'deleted' => $deleted
    ]);
}


}
