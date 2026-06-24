<?php

namespace App\Exports;

use App\Models\SuketBelumMenikah;
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

class SKBMExport implements
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
        return SuketBelumMenikah::with([
            'masyarakat',
            'rt',
            'perangkat',
            'kepala_desa',
            'submitterUser',
            'submitterMasyarakat',
            'perangkatValidator'
        ])->orderBy('created_at', 'desc')->get();
    }

    public function headings(): array
{
    return [
        'ID', 'Nama', 'NIK', 'Jenis Kelamin', 'TTL', 'Agama', 
        'Status Kawin', 'Pekerjaan', 'Alamat', 'Kewarganegaraan', 'Pendidikan',
        'RT', 'Perangkat Desa', 'Kepala Desa',
        'Keperluan', 'Alasan', 'Keterangan', 'Status',
        'Nomor Surat', 'No Surat Pengantar', 'Poin II',
        'Validasi RT', 'Validasi Perangkat', 'Validasi Kepala Desa',
        'Ditolak Oleh', 'Diajukan Oleh', 'Nama yang mengajukan',
        'Tipe surat pengantar RT', 'Nama RT', 'Perangkat yang memvalidasi',
        'Dibuat', 'Diupdate'
    ];
}

   public function map($row): array
{
     $namaPengaju = '-';

    if ($row->submitted_by === 'masyarakat') {
        $namaPengaju = optional($row->submitterMasyarakat)->nama ?? $row->submitted_by_id;
    } else {
        $namaPengaju = optional($row->submitterUser)->nama ?? $row->submitted_by_id;
    }
    return [
        $row->id_skbm,
        $row->nama,
        $row->nik . " ",
        $row->jenis_kelamin,
        $row->ttl,
        $row->agama,
        $row->status_perkawinan,
        $row->pekerjaan,
        $row->alamat,
        $row->kewarganegaraan,
        $row->pendidikan,
        optional($row->rt)->nama,
        optional($row->perangkat)->nama,
        optional($row->kepala_desa)->nama,
        $row->keperluan,
        $row->alasan,
        $row->keterangan,
        $row->status,
        $row->nomor_surat,
        $row->no_surat_pengantar,
        $row->poin_ii,

        $row->rt_validated_at,
        $row->perangkat_validated_at,
        $row->kepala_desa_validated_at,

        $row->rejected_by,
        $row->submitted_by,

         $namaPengaju,

        $row->pengantar_rt_type,
        $row->nama_rt,

        optional($row->perangkatValidator)->nama ?? $row->perangkat_validated_by,

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

    /** ATUR LEBAR KOLOM SECARA PROPORSIONAL */
   public function columnWidths(): array
{
    return [
        'A' => 8,
        'B' => 30,
        'C' => 25,
        'D' => 15,
        'E' => 35,
        'F' => 12,
        'G' => 15,
        'H' => 20,
        'I' => 45,
        'J' => 18,
        'K' => 15,
        'L' => 25,
        'M' => 25,
        'N' => 25,
        'O' => 35,
        'P' => 35,
        'Q' => 35,
        'R' => 40,
        'S' => 40,
        'T' => 20,
        'U' => 25,
        'V' => 30,
        'W' => 30,
        'X' => 30,
        'Y' => 22,
        'Z' => 22,
        'AA' => 25,
        'AB' => 35,
        'AC' => 30,
        'AD' => 25,
        'AE' => 35,
        'AF' => 22,
        'AG' => 22,
    ];
}

   public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
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

                $sheet->getStyle("A1:{$highestCol}1")->applyFromArray([
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => '1E40AF']
                    ],
                ]);

                $sheet->getStyle("A1:{$highestCol}{$highestRow}")->getAlignment()->setWrapText(true);
                $sheet->getStyle("A1:{$highestCol}{$highestRow}")->getAlignment()->setVertical(Alignment::VERTICAL_TOP);

                $sheet->getStyle("A1:{$highestCol}{$highestRow}")->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['rgb' => 'D1D5DB'],
                        ],
                    ],
                ]);

                $sheet->freezePane('A2');

                for ($i = 2; $i <= $highestRow; $i++) {
                    if ($i % 2 == 0) {
                        $sheet->getStyle("A{$i}:{$highestCol}{$i}")->getFill()
                            ->setFillType(Fill::FILL_SOLID)
                            ->getStartColor()->setRGB('F9FAFB');
                    }
                }
            },
        ];
    }
}