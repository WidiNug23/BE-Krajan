<?php

namespace App\Exports;

use App\Models\Masyarakat;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class DataMasyarakatExport implements
    FromCollection,
    WithHeadings,
    WithMapping,
    WithStyles,
    WithColumnWidths,
    WithEvents,
    WithColumnFormatting
{
    public function collection()
    {
        return Masyarakat::with('validator')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function headings(): array
    {
        return [
            'ID',
            'Nama',
            'NIK',
            'Email',
            'No HP',
            'Jenis Kelamin',
            'TTL',
            'Agama',
            'Status Perkawinan',
            'Pendidikan',
            'Kewarganegaraan',
            'Alamat',
            'Foto Profil',
            'File KTP',
            'File KK',
            'Status Verifikasi',
            'Keterangan Verifikasi',
            'Diverifikasi Oleh',
            'Waktu Verifikasi',
            'Dibuat',
            'Diupdate',
        ];
    }

    public function map($row): array
    {
        return [
            $row->id,
            $row->nama,
            $row->nik . ' ', // Paksa text (hindari scientific notation)
            $row->email,
            $row->no_hp,
            $row->jenis_kelamin,
            $row->ttl,
            $row->agama,
            $row->status_perkawinan,
            $row->pendidikan,
            $row->kewarganegaraan,
            $row->alamat,
            $row->foto_profil,
            $row->file_ktp,
            $row->file_kk,
            $row->status_verifikasi,
            $row->keterangan_verifikasi,
            optional($row->validator)->nama ?? '-',
            $row->users_validated_at,
            $row->created_at,
            $row->updated_at,
        ];
    }

    /** FORMAT NIK SEBAGAI TEXT */
    public function columnFormats(): array
    {
        return [
            'C' => NumberFormat::FORMAT_TEXT,
        ];
    }

    /** LEBAR KOLOM */
    public function columnWidths(): array
    {
        return [
            'A' => 8,    // ID
            'B' => 30,   // Nama
            'C' => 25,   // NIK
            'D' => 30,   // Email
            'E' => 18,   // No HP
            'F' => 15,   // JK
            'G' => 30,   // TTL
            'H' => 15,   // Agama
            'I' => 20,   // Status Kawin
            'J' => 18,   // Pendidikan
            'K' => 22,   // Kewarganegaraan
            'L' => 45,   // Alamat
            'M' => 40,   // Foto Profil
            'N' => 40,   // File KTP
            'O' => 40,   // File KK
            'P' => 20,   // Status
            'Q' => 35,   // Keterangan
            'R' => 30,   // Validator
            'S' => 25,   // Waktu Validasi
            'T' => 22,   // Created
            'U' => 22,   // Updated
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => [
                    'bold' => true,
                    'color' => ['rgb' => 'FFFFFF'],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
            ],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $highestCol = $sheet->getHighestColumn();
                $highestRow = $sheet->getHighestRow();

                // Header biru
                $sheet->getStyle("A1:{$highestCol}1")->applyFromArray([
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => '1E40AF'],
                    ],
                ]);

                // Wrap & vertical align
                $sheet->getStyle("A1:{$highestCol}{$highestRow}")
                    ->getAlignment()
                    ->setWrapText(true)
                    ->setVertical(Alignment::VERTICAL_TOP);

                // Border tabel
                $sheet->getStyle("A1:{$highestCol}{$highestRow}")->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['rgb' => 'D1D5DB'],
                        ],
                    ],
                ]);

                // Freeze header
                $sheet->freezePane('A2');

                // Zebra striping
                for ($i = 2; $i <= $highestRow; $i++) {
                    if ($i % 2 === 0) {
                        $sheet->getStyle("A{$i}:{$highestCol}{$i}")
                            ->getFill()
                            ->setFillType(Fill::FILL_SOLID)
                            ->getStartColor()
                            ->setRGB('F9FAFB');
                    }
                }
            },
        ];
    }
}
