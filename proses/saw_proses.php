<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
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

    /* DISAMAKAN DENGAN TOPSIS (topsis_proses.php::ambilNilaiTopsis):
       Jalur Masuk 5 tingkat sesuai urutan prioritas yang ditetapkan
       (dari tertinggi ke terendah): Beasiswa Mahasiswa Internasional >
       SNMPTN/SNBP > SBMPTN/SNBT > Mandiri > Mahasiswa Pindahan.
       Sebelumnya SAW hanya punya 4 tingkat dan tidak mengenali
       Beasiswa Internasional sama sekali (jatuh ke skor terendah),
       sehingga hasil SAW dan TOPSIS bisa berbeda untuk kriteria ini. */
    if ($kolomData == 'jalur_masuk') {
        $nilaiLower = strtolower(trim($nilai));

        if (strpos($nilaiLower, 'beasiswa') !== false && strpos($nilaiLower, 'internasional') !== false) {
            return 5;
        } elseif ($nilaiLower == 'snbp' || $nilaiLower == 'snmptn') {
            return 4;
        } elseif ($nilaiLower == 'snbt' || $nilaiLower == 'sbmptn') {
            return 3;
        } elseif ($nilaiLower == 'mandiri') {
            return 2;
        } elseif (strpos($nilaiLower, 'pindahan') !== false) {
            return 1;
        } else {
            return 1;
        }
    }

    /* DISAMAKAN DENGAN TOPSIS: SKS Lulus & SKS Diambil dinormalisasi
       jadi rasio terhadap SKS ideal (semester berjalan x 20
       SKS/semester), bukan angka mentah - supaya mahasiswa semester
       awal tidak otomatis dirugikan dibanding semester akhir. Kalau
       total SKS Lulus + SKS Diambil sudah mencapai syarat kelulusan
       (145 SKS), langsung diberi skor maksimal (1). Sebelumnya SAW
       memakai angka SKS mentah tanpa normalisasi ini. */
    if ($kolomData == 'sks_lulus' || $kolomData == 'sks_diambil') {
        $sksLulusVal   = (isset($mhs['sks_lulus']) && is_numeric($mhs['sks_lulus']))
            ? floatval($mhs['sks_lulus']) : 0;
        $sksDiambilVal = (isset($mhs['sks_diambil']) && is_numeric($mhs['sks_diambil']))
            ? floatval($mhs['sks_diambil']) : 0;
        $totalSksMenujuKelulusan = $sksLulusVal + $sksDiambilVal;

        if ($totalSksMenujuKelulusan >= 145) {
            return 1;
        }

        $semester = isset($mhs['semester']) ? floatval($mhs['semester']) : 0;
        if ($semester <= 0) {
            return 0;
        }
        $sksIdeal = $semester * 20;
        $sksAktual = is_numeric($nilai) ? floatval($nilai) : 0;
        return $sksIdeal == 0 ? 0 : ($sksAktual / $sksIdeal);
    }

    /* DISAMAKAN DENGAN TOPSIS: Skor TOEFL diubah jadi tingkat
       ordinal, bukan dipakai sebagai angka mentah - sesuai aturan
       yang ditetapkan: <400 tidak valid (0), 400-449 (1), >=450
       setara predikat cumlaude (2). Sebelumnya SAW memakai skor
       TOEFL mentah tanpa kategori ini. */
    if ($kolomData == 'skor_toefl') {
        $skor = is_numeric($nilai) ? floatval($nilai) : 0;
        if ($skor < 400) {
            return 0;
        } elseif ($skor < 450) {
            return 1;
        } else {
            return 2;
        }
    }

    if (!is_numeric($nilai)) {
        return 0;
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

/* LANJUT KE SPEARMAN
   (Jika dirantai dari input_data.php, biarkan
   caller yang melanjutkan ke spearman_proses.php) */
if (!defined('SPK_CHAIN')) {
    header("Location: spearman_proses.php");
    exit;
}
?>