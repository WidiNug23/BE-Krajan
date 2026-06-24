<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Surat Pengantar RT</title>

    <style>
        /* Mengatur margin kertas agar muat 1 halaman */
        @page {
            margin: 1cm 1.5cm;
        }

        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 10pt; /* Ukuran standar MS Word */
            line-height: 1.4;
            color: #000;
            margin: 0;
            padding: 0;
        }

        .center {
            text-align: center;
        }

        .bold {
            font-weight: bold;
        }

        /* KOP SURAT - Teknik 3 Kolom agar teks benar-benar di tengah */
        .kop-table {
            width: 100%;
            margin-bottom: 5px;
            border-collapse: collapse;
        }

        .logo-cell {
            width: 20%;
            vertical-align: middle;
            padding-left: 25px; 
        }

        .logo-cell img {
            width: 95px; /* Logo diperbesar sesuai permintaan sebelumnya */
            height: auto;
        }

        .title-cell {
            width: 60%;
            vertical-align: middle;
        }

        .spacer-cell {
            width: 20%; 
        }

        .kop-title {
            font-size: 18pt;
            font-weight: bold;
            margin: 0;
            line-height: 1.2;
        }

        .kop-subtitle {
            font-size: 15pt;
            font-weight: bold;
            margin: 2px 0;
        }

        .kop-address {
            font-size: 10.5pt;
            margin: 0;
        }

        /* GARIS TEBAL KOP */
        .kop-divider {
            border-top: 2px solid #000;
            border-bottom: 1px solid #000;
            height: 2px;
            margin: 8px 0 12px 0; /* Jarak dirapatkan sedikit agar muat 1 hal */
        }

        /* JUDUL SURAT */
        .judul-surat {
            text-decoration: underline;
            font-size: 13pt;
            margin-bottom: 0;
            text-transform: uppercase;
        }

        .nomor-surat {
            margin-top: 2px;
            margin-bottom: 12px; /* Dirapatkan sedikit */
        }

        /* DATA DIRI */
        .data-table {
            width: 100%;
            margin-left: auto;
            margin-right: auto;
            border-collapse: collapse;
        }

        .data-table td {
            padding: 2px 0;
            vertical-align: top;
        }

        .indent {
            text-indent: 45px;
            text-align: justify;
            margin-bottom: 8px;
        }

        /* KEPERLUAN */
        .keperluan-section {
            margin: 10px 0;
        }

        .keperluan-box {
            border-bottom: 1px dotted #000;
            display: inline-block;
            width: 100%;
            padding-bottom: 2px;
            font-weight: bold;
        }

        /* TANDA TANGAN */
        .ttd-table {
            width: 100%;
            margin-top: 10px; /* Dirapatkan agar ttd besar tetap muat 1 hal */
        }

        .ttd-container {
            width: 45%;
            text-align: center;
        }

        .ttd-image {
            height: 100px; /* Diperbesar sesuai permintaan */
            margin: 5px auto;
        }

        .ttd-image img {
            height: 100px; /* Diperbesar sesuai permintaan */
        }

        .ttd-name {
            font-weight: bold;
            text-decoration: underline;
            display: inline-block;
            width: 220px;
            text-transform: uppercase;
        }

    </style>
</head>

<body>

    <table class="kop-table">
        <tr>
            <td class="logo-cell">
                <img src="{{ public_path('uploads/Logo_kabupaten_madiun.png') }}" alt="Logo">
            </td>
            <td class="title-cell center">
                <div class="kop-title">PEMERINTAH DESA Krajan</div>
                <div class="kop-subtitle">
                    RUKUN TETANGGA {{ str_pad($skitt->rt->no_rt ?? '', 2, '0', STR_PAD_LEFT) ?: '....' }}
                </div>
                <div class="kop-address">Kecamatan Krajan, Kabupaten Madiun, Jawa Timur</div>
            </td>
            <td class="spacer-cell"></td>
        </tr>
    </table>

    <div class="kop-divider"></div>

    <div class="center">
        <h3 class="judul-surat bold">SURAT PENGANTAR RT</h3>
        <p class="nomor-surat">Nomor: {{ $skitt->no_surat_pengantar ?? '......../......../........' }}</p>
    </div>

    <p class="indent">
        Yang bertanda tangan di bawah ini, kami Ketua RT {{ str_pad($skitt->rt->no_rt ?? '', 2, '0', STR_PAD_LEFT) ?: '....' }} 
        Desa Krajan menerangkan bahwa:
    </p>

    <table class="data-table">
        <tr>
            <td width="30%">Nama Lengkap</td>
            <td width="3%">:</td>
            <td>{{ $skitt->nama }}</td>
        </tr>
        <tr>
            <td>Tempat, Tgl. Lahir</td>
            <td>:</td>
            <td>{{ $skitt->ttl }}</td>
        </tr>
        <tr>
            <td>NIK</td>
            <td>:</td>
            <td>{{ $skitt->nik }}</td>
        </tr>
        <tr>
            <td>Agama</td>
            <td>:</td>
            <td>{{ $skitt->agama }}</td>
        </tr>
        <tr>
            <td>Kewarganegaraan</td>
            <td>:</td>
            <td>{{ $skitt->kewarganegaraan }}</td>
        </tr>
        <tr>
            <td>Pendidikan</td>
            <td>:</td>
            <td>{{ $skitt->pendidikan }}</td>
        </tr>
        <tr>
            <td>Status Perkawinan</td>
            <td>:</td>
            <td>{{ $skitt->status_perkawinan }}</td>
        </tr>
        <tr>
            <td>Pekerjaan</td>
            <td>:</td>
            <td>{{ $skitt->pekerjaan }}</td>
        </tr>
        <tr>
            <td>Alamat</td>
            <td>:</td>
            <td>{{ $skitt->alamat }}</td>
        </tr>
        {{-- <tr>
            <td>Keterangan</td>
            <td>:</td>
            <td>{{ $skitt->alasan }}</td>
        </tr> --}}
    </table>

    <div class="keperluan-section">
        <p style="margin-bottom: 5px;">Orang tersebut di atas adalah benar warga kami, surat ini diberikan untuk:</p>
        <div class="keperluan-box">
            {{ $skitt->keperluan }}
        </div>
    </div>

    <p class="indent">
        Demikian surat keterangan ini kami buat dengan sebenarnya untuk dapat dipergunakan sebagaimana mestinya.
    </p>

    <table class="ttd-table">
        <tr>
            <td width="55%"></td>
            <td class="ttd-container">
                @php \Carbon\Carbon::setLocale('id'); @endphp
                Krajan, {{ \Carbon\Carbon::now()->translatedFormat('d F Y') }}
                
                <div style="margin-top: 5px;">
                    <div class="bold">Ketua RT {{ str_pad($skitt->rt->no_rt ?? '', 2, '0', STR_PAD_LEFT) }}</div>
                    
                    <div class="ttd-image">
                        @php
                            $ttdRtPath = null;
                            if (!empty($skitt->ttd_rt)) {
                                // kalau berupa URL -> ambil path setelah domain
                                if (Str::startsWith($skitt->ttd_rt, ['http://', 'https://'])) {
                                    $ttdRtPath = public_path(parse_url($skitt->ttd_rt, PHP_URL_PATH));
                                } else {
                                    // kalau sudah relative path
                                    $ttdRtPath = public_path($skitt->ttd_rt);
                                }
                            }
                        @endphp

                        @if ($ttdRtPath && file_exists($ttdRtPath))
                            <img src="{{ $ttdRtPath }}">
                        @else
                            <div style="height:90px;"></div>
                        @endif
                    </div>

                    <div class="ttd-name">
                        {{ $skitt->rt->nama }}
                    </div>
                </div>
            </td>
        </tr>
    </table>

</body>
</html>