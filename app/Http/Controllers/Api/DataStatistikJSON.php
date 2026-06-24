<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ModelDataStatistik;
use PhpOffice\PhpSpreadsheet\IOFactory;

class DataStatistikJSON extends Controller
{
    /**
     * Ambil data statistik dari file Excel secara dinamis
     */
    public function index(Request $request)
    {
        // ===================== VALIDASI PARAMETER =====================
        $request->validate([
            'id' => 'required|integer|exists:data_statistik,id_data_statistik',
        ]);

        // ===================== AMBIL DATA DARI DB =====================
        $data = ModelDataStatistik::find($request->id);
        if (!$data) {
            return response()->json([
                'message' => 'Data statistik tidak ditemukan'
            ], 404);
        }

        $filePath = public_path($data->file_data);
        if (!file_exists($filePath)) {
            return response()->json([
                'message' => 'File statistik tidak ditemukan'
            ], 404);
        }

        // ===================== BACA FILE EXCEL =====================
        try {
            $spreadsheet = IOFactory::load($filePath);
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray();

            if (count($rows) < 2) {
                return response()->json([
                    'message' => 'Data statistik kosong'
                ], 400);
            }

            // ===================== AMBIL KOLom PERTAMA & KEDUA =====================
$labelIndex = 1; // kolom B
$valueIndex = 2; // kolom C

$result = [];
foreach (array_slice($rows, 1) as $row) {
    // Skip baris kosong
    if (!empty($row[$labelIndex])) {
        $result[] = [
            'label' => $row[$labelIndex],
            'value' => is_numeric($row[$valueIndex]) ? (int)$row[$valueIndex] : 0,
        ];
    }
}

            return response()->json([
                'meta' => [
                    'nama_file' => $data->nama_file,
                    'total' => count($result)
                ],
                'data' => $result
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal membaca file Excel',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
