<?php
session_start();
include "../config/database.php";

if (!isset($_SESSION['role'])) {
    header("Location: ../login.php");
    exit;
}

$periode = date('Y-m-d');

/* HAPUS HASIL SAW LAMA */
mysqli_query($conn, "DELETE FROM ranking_saw");

/* AMBIL DATA AKADEMIK TERBARU PER MAHASISWA */
$dataMahasiswa = [];

$qMahasiswa = mysqli_query($conn, "
    SELECT da.*
    FROM data_akademik da
    INNER JOIN (
        SELECT nim, MAX(id_data) AS id_data_terbaru
        FROM data_akademik
        GROUP BY nim
    ) terbaru
    ON da.nim = terbaru.nim
    AND da.id_data = terbaru.id_data_terbaru
");

while ($row = mysqli_fetch_assoc($qMahasiswa)) {
    $dataMahasiswa[] = $row;
}

/* AMBIL KRITERIA BERDASARKAN BOBOT DELPHI */
$dataKriteria = [];

$qKriteria = mysqli_query($conn, "
    SELECT *
    FROM kriteria
    WHERE kolom_data IS NOT NULL
    AND kolom_data != ''
    AND bobot_delphi > 0
    ORDER BY bobot_delphi DESC
");

while ($row = mysqli_fetch_assoc($qKriteria)) {
    $dataKriteria[] = $row;
}

/* VALIDASI DATA */
if (count($dataMahasiswa) == 0) {
    echo "
    <script>
        alert('Data akademik mahasiswa masih kosong.');
        window.location='../pages/monitoring.php';
    </script>";
    exit;
}

if (count($dataKriteria) == 0) {
    echo "
    <script>
        alert('Data kriteria atau bobot Delphi belum tersedia.');
        window.location='../pages/konfigurasi_kriteria.php';
    </script>";
    exit;
}

/* CEK DAN NORMALISASI TOTAL BOBOT */
$totalBobot = 0;

foreach ($dataKriteria as $krit) {
    $totalBobot += floatval($krit['bobot_delphi']);
}

if ($totalBobot <= 0) {
    echo "
    <script>
        alert('Total bobot Delphi masih 0. Hitung bobot Delphi terlebih dahulu.');
        window.location='../pages/konfigurasi_kriteria.php';
    </script>";
    exit;
}

foreach ($dataKriteria as $i => $krit) {
    $dataKriteria[$i]['bobot_normal'] =
        floatval($krit['bobot_delphi']) / $totalBobot;
}

/* FUNGSI AMBIL NILAI SAW */
function ambilNilaiSaw($mhs, $kolomData)
{
    if (!isset($mhs[$kolomData])) {
        return 0;
    }

    $nilai = $mhs[$kolomData];

    if ($nilai === null || $nilai === '') {
        return 0;
    }

    /*
        Jika jalur_masuk berupa teks,
        ubah menjadi angka.
        Sesuaikan mapping ini jika data kamu berbeda.
    */
    if ($kolomData == 'jalur_masuk') {
        $nilaiLower = strtolower(trim($nilai));

        if ($nilaiLower == 'snbp' || $nilaiLower == 'snmptn') {
            return 4;
        } elseif ($nilaiLower == 'snbt' || $nilaiLower == 'sbmptn') {
            return 3;
        } elseif ($nilaiLower == 'mandiri') {
            return 2;
        } else {
            return 1;
        }
    }

    return floatval($nilai);
}

/* 1. MEMBENTUK MATRIKS KEPUTUSAN */
$matrix = [];

foreach ($dataMahasiswa as $mhs) {
    $nim = $mhs['nim'];

    foreach ($dataKriteria as $krit) {
        $idKriteria = $krit['id_kriteria'];
        $kolomData = $krit['kolom_data'];

        $matrix[$nim][$idKriteria] = ambilNilaiSaw($mhs, $kolomData);
    }
}

/* 2. MENENTUKAN NILAI MAX DAN MIN SETIAP KRITERIA */
$maxMin = [];

foreach ($dataKriteria as $krit) {
    $idKriteria = $krit['id_kriteria'];
    $nilaiKolom = [];

    foreach ($dataMahasiswa as $mhs) {
        $nim = $mhs['nim'];
        $nilaiKolom[] = $matrix[$nim][$idKriteria];
    }

    $maxMin[$idKriteria] = [
        'max' => max($nilaiKolom),
        'min' => min($nilaiKolom)
    ];
}

/* 3. NORMALISASI SAW DAN HITUNG NILAI PREFERENSI */
$hasilSaw = [];

foreach ($dataMahasiswa as $mhs) {
    $nim = $mhs['nim'];
    $total = 0;

    foreach ($dataKriteria as $krit) {
        $idKriteria = $krit['id_kriteria'];
        $nilai = $matrix[$nim][$idKriteria];
        $bobot = floatval($krit['bobot_normal']);
        $jenis = strtolower(trim($krit['jenis']));

        $normal = 0;

        if ($jenis == 'benefit') {
            if ($maxMin[$idKriteria]['max'] != 0) {
                $normal = $nilai / $maxMin[$idKriteria]['max'];
            }
        } else {
            /*
                Cost:
                semakin kecil semakin baik.
                Rumus: min / nilai

                Jika nilai 0 dan kriteria cost,
                maka mahasiswa mendapat nilai normalisasi terbaik = 1.
                Contoh: jumlah mengulang = 0, absensi = 0.
            */
            if ($nilai == 0) {
                $normal = 1;
            } else {
                $normal = $maxMin[$idKriteria]['min'] / $nilai;
            }
        }

        $total += $normal * $bobot;
    }

    $hasilSaw[] = [
        'nim' => $nim,
        'nilai_preferensi' => $total
    ];
}

/* 4. URUTKAN RANKING SAW */
usort($hasilSaw, function ($a, $b) {
    if ($a['nilai_preferensi'] == $b['nilai_preferensi']) {
        return 0;
    }

    return ($a['nilai_preferensi'] < $b['nilai_preferensi']) ? 1 : -1;
});

/* 5. SIMPAN HASIL SAW */
$ranking = 1;

foreach ($hasilSaw as $hasil) {
    $nim = mysqli_real_escape_string($conn, $hasil['nim']);
    $nilaiPreferensi = $hasil['nilai_preferensi'];

    mysqli_query($conn, "
        INSERT INTO ranking_saw (
            nim,
            nilai_preferensi,
            ranking,
            periode_evaluasi
        ) VALUES (
            '$nim',
            '$nilaiPreferensi',
            '$ranking',
            '$periode'
        )
    ");

    $ranking++;
}

/* LOG AKTIVITAS */
if (isset($_SESSION['id_user'])) {
    $idUser = mysqli_real_escape_string($conn, $_SESSION['id_user']);

    mysqli_query($conn, "
        INSERT INTO log_aktivitas (
            id_user,
            aksi,
            tanggal
        ) VALUES (
            '$idUser',
            'Menjalankan proses perhitungan SAW',
            NOW()
        )
    ");
}

/* LANJUT KE SPEARMAN */
header("Location: spearman_proses.php");
exit;
?>