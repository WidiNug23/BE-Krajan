<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Surat Keterangan Penduduk</title>
    <style>
        /* Mengatur margin profesional agar muat 1 halaman */
        @page {
            margin: 1cm 1.5cm;
        }

        body { 
            font-family: 'DejaVu Sans', sans-serif; 
            font-size: 10pt;
            line-height: 1.3;
            color: #000;
            margin: 0;
            padding: 0;
        }

        .center { text-align: center; }
        .bold { font-weight: bold; }
        .uppercase { text-transform: uppercase; }
        .nowrap { white-space: nowrap; }

            .indent { 
            text-indent: 40px; 
        }

        /* KOP SURAT */
        .kop-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 2px;
            margin-left: -40px
        }

        .logo-cell {
            width: 10%;
            vertical-align: middle;
            text-align: left;
            padding-left: 25px;
            position: fixed; 
        }

        .logo-cell img {
            width: 100px;
            height: auto;
        }

        .title-cell {
            width: 80%;
            text-align: center;
            vertical-align: middle;
        }

        .spacer-cell {
            width: 10%;
        }

        .kop-1 { font-size: 13pt; margin: 0; line-height: 1.1; }
        .kop-2 { font-size: 18pt; margin: 0; line-height: 1.1; }
        .kop-address { font-size: 8.5pt; margin: 0; font-weight: normal; }

        .kop-divider {
            border-top: 2px solid #000;
            border-bottom: 1px solid #000;
            height: 2px;
            margin: 5px 0 15px 0;
        }

        /* JUDUL SURAT */
        .judul-surat {
            text-decoration: underline;
            font-size: 12pt;
            margin-bottom: 0;
        }

        .nomor-surat {
            margin-top: 0px;
            margin-bottom: 15px;
        }

        /* ISI SURAT */
        .content {
            text-align: justify;
        }

        .table-data { 
            width: 100%; 
            margin: 5px 0;
            border-collapse: collapse;
        }

        .table-data td { 
            padding: 1.5px 0; 
            vertical-align: top; 
        }

        /* TANDA TANGAN */
        .ttd-table { 
            width: 100%; 
            margin-top: 40px; 
            border-collapse: collapse;
            position: relative;
        }

        .ttd-cell { 
            width: 50%; 
            text-align: center; 
            vertical-align: top; 
        }

        .ttd-container {
            position: relative;
            display: inline-block;
            margin: 5px auto;
            width: 200px; /* Lebar container agar stempel dan TTD sejajar */
        }

        .ttd-image-img {
            height: 100px;
            position: relative;
            z-index: 2; /* TTD di atas stempel */
        }
        
        /* CSS Stempel Kades Diperbaiki */
        .stempel-kades {
            position: absolute;
            top: -30px;     /* Naikkan sedikit agar menimpa teks 'Kepala Desa' */
            left: -40px;    /* Geser ke kiri agar menimpa awal TTD */
            width: 160px;   /* Ukuran diperbesar agar lebih proporsional */
            height: auto;
            z-index: 1;     /* Stempel di bawah TTD */
            opacity: 0.85;  /* Sedikit transparan agar terlihat menimpa */
        }

        .ttd-name {
            font-weight: bold;
            text-decoration: underline;
            display: inline-block;
            min-width: 180px;
            text-transform: uppercase;
            margin-top: 5px;
            position: relative;
            z-index: 3; /* Nama jelas di paling atas */
        }
    </style>
</head>
<body>

    <table class="kop-table">
        <tr>
            <td class="logo-cell">
                <img src="{{ public_path('uploads/Logo_kabupaten_madiun.png') }}" alt="Logo">
            </td>
             <td class="title-cell">
                <div class="kop-1 bold">PEMERINTAH KABUPATEN MADIUN</div>
                <div class="kop-1 bold">KECAMATAN MEJAYAN</div>
                <div class="kop-1 bold">KELURAHAN KRAJAN</div>
                <div class="kop-address">Jl. Bali No. 01 Telp. 0351-383322 Email: kelurahankrajan@gmail.com</div>
                <div class="kop-1 bold">KRAJAN</div>
            </td>
            <td class="spacer-cell"></td>
        </tr>
    </table>

    <div class="kop-divider"></div>

     <div class="center">
        <h3 class="judul-surat bold uppercase">SURAT KETERANGAN</h3>
        <p class="nomor-surat">Nomor : 400.12.4.3 / {{ $data->nomor_surat ?? '____' }} / 402.410.02 / {{ date('Y') }}</p>
    </div>

    <div class="content">
        <p style="margin-bottom: 2px;">I. Yang bertanda tangan di bawah ini :</p>
        <table class="table-data" style="margin-left: 20px;">
            <tr>
                <td width="28%">Nama</td>
                <td width="3%">:</td>
                <td>{{ $data->kepala_desa->nama ?? 'Tri Santoso , S.STP' }}</td>
            </tr>
            <tr>
                <td>Jabatan</td>
                <td>:</td>
                <td>{{ $data->kepala_desa->jabatan ?? 'Lurah Krajan' }}</td>
            </tr>
            @if(!empty($data->kepala_desa->nip))
            <tr>
                <td>NIP</td>
                <td>:</td>
                <td>{{ $data->kepala_desa->nip }}</td>
            </tr>
            @endif
        </table>

        <p style="margin-top: 10px; margin-bottom: 2px;">Dengan ini menerangkan bahwa :</p>
        <table class="table-data" style="margin-left: 20px;">
            <tr><td width="28%">Nama Lengkap</td><td width="3%">:</td><td>{{ $data->nama }}</td></tr>
            <tr><td>Tempat, Tgl. Lahir</td><td>:</td><td>{{ $data->ttl }}</td></tr>
            <tr><td>Jenis Kelamin</td><td>:</td><td>{{ $data->jenis_kelamin }}</td></tr>
            <tr><td>Pekerjaan</td><td>:</td><td>{{ $data->pekerjaan }}</td></tr>
            <tr><td>Agama</td><td>:</td><td>{{ $data->agama }}</td></tr>
            <tr><td>Status Perkawinan</td><td>:</td><td>{{ $data->status_perkawinan }}</td></tr>
            <tr><td>NIK (No. KTP)</td><td>:</td><td>{{ $data->nik }}</td></tr>
            <tr><td>Alamat</td><td>:</td><td>{{ $data->alamat }}</td></tr>
        </table>

        <p style="margin-top: 10px; margin-bottom: 5px;">
            <span>II.</span> {{ $data->poin_ii ?? 'Orang tersebut di atas benar-benar penduduk Kelurahan Krajan Kecamatan Mejayan Kabupaten Madiun' }}
        </p>

        <p style="margin-bottom: 5px;">
            <span>III.</span> Surat keterangan ini dipergunakan untuk : <span>{{ $data->alasan }}</span>
        </p>

        <p style="margin-top: 10px;">Demikian surat ini dibuat untuk dapat dipergunakan sebagaimana mestinya.</p>
    </div>

     <table class="ttd-table">
        <tr>
            <td class="ttd-cell">
                Pemegang surat
            </td>
            <td class="ttd-cell">
                @php 
                    \Carbon\Carbon::setLocale('id'); 
                    $jabatan = strtolower($data->kepala_desa->jabatan ?? '');
                    $isPejabatUtama = (strpos($jabatan, 'lurah') !== false || strpos($jabatan, 'kepala desa') !== false);
                @endphp
                Krajan, {{ \Carbon\Carbon::now()->translatedFormat('d F Y') }} <br>
                
                {{-- Logika An. Lurah Krajan --}}
                @if(!$isPejabatUtama)
                    An. Lurah Krajan <br>
                @endif
                
                {{ $data->kepala_desa->jabatan ?? 'Lurah Krajan' }}
            </td>
        </tr>
        <tr>
            <td class="ttd-cell">
                <div class="ttd-container">
                    @php $ttdMasyarakatPath = !empty($data->ttd_masyarakat) ? public_path($data->ttd_masyarakat) : null; @endphp
                    @if($ttdMasyarakatPath && file_exists($ttdMasyarakatPath))
                        <img src="{{ $ttdMasyarakatPath }}" class="ttd-image-img">
                    @else
                        <br><br><br>
                    @endif
                </div>
                <div class="ttd-name nowrap">{{ $data->nama }}</div>
            </td>
            <td class="ttd-cell">
                <div class="ttd-container">
                    @php
                        $ttdKadesPath = null;
                        if (!empty($data->ttd_kades)) {
                            if (strpos($data->ttd_kades, 'http://') === 0 || strpos($data->ttd_kades, 'https://') === 0) {
                                $ttdKadesPath = public_path(parse_url($data->ttd_kades, PHP_URL_PATH));
                            } else {
                                $ttdKadesPath = public_path($data->ttd_kades);
                            }
                        }
                        $stempelPath = public_path('uploads/stempel_lurah_hd.png');
                    @endphp

                    {{-- Menampilkan Stempel --}}
                    @if($stempelPath && file_exists($stempelPath))
                        <img src="{{ $stempelPath }}" class="stempel-kades" alt="Stempel">
                    @endif

                    {{-- Menampilkan TTD --}}
                    @if($ttdKadesPath && file_exists($ttdKadesPath))
                        <img src="{{ $ttdKadesPath }}" class="ttd-image-img">
                    @else
                        <br><br><br>
                    @endif
                </div>
                
                {{-- Nama dan NIP Pejabat --}}
                <div class="ttd-name nowrap">{{ $data->kepala_desa->nama ?? 'Tri Santoso , S.STP' }}</div>
                @if(!empty($data->kepala_desa->nip))
                    <div style="margin-top: 1px;">NIP. {{ $data->kepala_desa->nip }}</div>
                @endif
            </td>
        </tr>
    </table>

</body>
</html>