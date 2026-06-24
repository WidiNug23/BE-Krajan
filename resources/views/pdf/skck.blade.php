<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Surat Permohonan Mengajukan SKCK</title>
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

        .judul-surat {
            text-decoration: underline;
            font-size: 12pt;
            margin-bottom: 0;
        }

        .nomor-surat {
            margin-top: 0px;
            margin-bottom: 15px;
        }

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

        .label-col {
            width: 34%;
            white-space: nowrap;
        }

        .colon-col {
            width: 3%;
        }

        /* TANDA TANGAN */
        .ttd-table { 
            width: 100%; 
            margin-top: 40px; 
            border-collapse: collapse;
        }

        .ttd-cell { 
            width: 50%; 
            text-align: center; 
            vertical-align: top; 
        }

        /* Container untuk overlap Stempel & TTD */
        .ttd-container {
            position: relative;
            display: inline-block;
            margin: 5px auto;
            width: 200px;
        }

        .ttd-image-img {
            height: 100px;
            position: relative;
            z-index: 2; /* TTD di atas stempel */
        }
        
        /* CSS Stempel Kades */
        .stempel-kades {
            position: absolute;
            top: -30px;     /* Menimpa teks jabatan */
            left: -40px;    /* Menimpa bagian awal tanda tangan */
            width: 160px;   /* Ukuran HD agar tidak menciut */
            height: auto;
            z-index: 1;     /* Stempel di bawah TTD */
            opacity: 0.85;  /* Efek tinta stempel transparan */
        }

        .ttd-name {
            font-weight: bold;
            text-decoration: underline;
            display: inline-block;
            min-width: 180px;
            text-transform: uppercase;
            margin-top: 5px;
            position: relative;
            z-index: 3; /* Nama di layer paling atas */
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
                <div class="kop-1">PEMERINTAH KABUPATEN MADIUN</div>
                <div class="kop-1">KECAMATAN Krajan</div>
                <div class="kop-2 bold">DESA Krajan</div>
                <div class="kop-address">Jl. P. Sudirman No. 74 A Telp. 0351-383055 email: desaKrajan@gmail.com</div>
                <div class="bold" style="font-size: 10pt; text-decoration: underline;">Krajan 63153</div>
            </td>
            <td class="spacer-cell"></td>
        </tr>
    </table>

    <div class="kop-divider"></div>

    <div class="center">
        <h3 class="judul-surat bold uppercase">SURAT KETERANGAN</h3>
        <p class="nomor-surat">Nomor : 331 / {{ $data->nomor_surat ?? '____' }} / 402.410.11 / {{ date('Y') }}</p>
    </div>

    <div class="content">
        <p class="indent" style="margin-bottom: 2px;">Yang bertanda tangan di bawah ini kami Kepala Desa Krajan Kecamatan Krajan Kabupaten Madiun menerangkan dengan sesungguhnya bahwa : </p>
        
        <table class="table-data" style="margin-left: 20px;">
            <tr><td class="label-col">Nama Lengkap</td><td class="colon-col">:</td><td class="bold uppercase">{{ $data->nama }}</td></tr>
            <tr><td class="label-col">Tempat, Tgl. Lahir</td><td class="colon-col">:</td><td>{{ $data->ttl }}</td></tr>
            <tr><td class="label-col">Jenis Kelamin</td><td class="colon-col">:</td><td>{{ $data->jenis_kelamin }}</td></tr>
            <tr><td class="label-col">Kewarganegaraan</td><td class="colon-col">:</td><td>{{ $data->kewarganegaraan }}</td></tr>
            <tr><td class="label-col">Agama</td><td class="colon-col">:</td><td>{{ $data->agama }}</td></tr>
            <tr><td class="label-col">Status Perkawinan</td><td class="colon-col">:</td><td>{{ $data->status_perkawinan }}</td></tr>
            <tr><td class="label-col">Pendidikan</td><td class="colon-col">:</td><td>{{ $data->pendidikan }}</td></tr>
            <tr><td class="label-col">Pekerjaan</td><td class="colon-col">:</td><td>{{ $data->pekerjaan }}</td></tr>
            <tr><td class="label-col">NIK (No. KTP)</td><td class="colon-col">:</td><td>{{ $data->nik }}</td></tr>
            <tr><td class="label-col">Alamat</td><td class="colon-col">:</td><td>{{ $data->alamat }}</td></tr>
            <tr><td class="label-col">Keterangan</td><td class="colon-col">:</td><td>{{ $data->keterangan_surat }}</td></tr>
            
            <tr><td class="label-col">Surat ini dipergunakan untuk</td><td class="colon-col">:</td><td>Mengajukan permohonan SKCK ke Polsek Krajan untuk persyaratan {{ $data->alasan }}</td></tr>
            <tr><td class="label-col">Berlaku mulai tanggal</td><td class="colon-col">:</td><td>{{ $data->masa_surat }}</td></tr>
        </table>

        <p class="indent" style="margin-top: 10px;">Demikian yang berwajib harap menjadikan maklum dan surat ini untuk dapat dipergunakan seperlunya.</p>
    </div>

    <table class="ttd-table">
        <tr>
            <td class="ttd-cell">
                Pemegang surat
            </td>
            <td class="ttd-cell">
                @php \Carbon\Carbon::setLocale('id'); @endphp
                Krajan, {{ \Carbon\Carbon::now()->translatedFormat('d F Y') }} <br>
                Kepala Desa Krajan
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
        // kalau berupa URL (http / https)
        if (strpos($data->ttd_kades, 'http://') === 0 || strpos($data->ttd_kades, 'https://') === 0) {
            $ttdKadesPath = public_path(parse_url($data->ttd_kades, PHP_URL_PATH));
        } else {
            // kalau sudah relative path
            $ttdKadesPath = public_path($data->ttd_kades);
        }
    }
@endphp
                    @php $stempelPath = public_path('uploads/stempel_lurah_hd.png'); @endphp
                    
                    {{-- Render Stempel --}}
                    @if($stempelPath && file_exists($stempelPath))
                        <img src="{{ $stempelPath }}" class="stempel-kades" alt="Stempel">
                    @endif

                    {{-- Render TTD Kades --}}
                    @if($ttdKadesPath && file_exists($ttdKadesPath))
                        <img src="{{ $ttdKadesPath }}" class="ttd-image-img">
                    @else
                        <br><br><br>
                    @endif
                </div>
                <div class="ttd-name nowrap">GUNAWAN WIBISONO, S.T</div>
            </td>
        </tr>
    </table>

</body>
</html>