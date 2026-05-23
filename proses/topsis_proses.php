<?php
session_start();
include "../config/database.php";

if (!isset($_SESSION['role'])) {
    header("Location: ../login.php");
    exit;
}

$periode = date('Y-m-d');

/* HAPUS HASIL LAMA */
mysqli_query($conn, "DELETE FROM ranking_topsis");
mysqli_query($conn, "DELETE FROM hasil_evaluasi");
mysqli_query($conn, "DELETE FROM solusi_ideal");

/* AMBIL DATA MAHASISWA */
$dataMahasiswa = [];
$qMahasiswa = mysqli_query($conn, "
    SELECT * FROM data_akademik
");

while ($row = mysqli_fetch_assoc($qMahasiswa)) {
    $dataMahasiswa[] = $row;
}

/* AMBIL DATA KRITERIA DARI KONFIGURASI ADMIN */
$dataKriteria = [];
$qKriteria = mysqli_query($conn, "
    SELECT * FROM kriteria
    WHERE kolom_data IS NOT NULL
    AND kolom_data != ''
    ORDER BY bobot DESC
");

while ($row = mysqli_fetch_assoc($qKriteria)) {
    $dataKriteria[] = $row;
}

/* VALIDASI DATA */
if (count($dataMahasiswa) == 0) {
    echo "
    <script>
        alert('Data mahasiswa masih kosong.');
        window.location='../pages/monitoring.php';
    </script>
    ";
    exit;
}

if (count($dataKriteria) == 0) {
    echo "
    <script>
        alert('Data kriteria masih kosong atau kolom data belum diatur.');
        window.location='../pages/konfigurasi_kriteria.php';
    </script>
    ";
    exit;
}

/* CEK TOTAL BOBOT */
$totalBobot = 0;
foreach ($dataKriteria as $krit) {
    $totalBobot += floatval($krit['bobot']);
}

if (abs($totalBobot - 1.00) > 0.001) {
    echo "
    <script>
        alert('Total bobot kriteria harus tepat 1.00 sebelum proses TOPSIS.');
        window.location='../pages/konfigurasi_kriteria.php';
    </script>
    ";
    exit;
}

/* FUNGSI AMBIL NILAI BERDASARKAN KOLOM DATA */
function ambilNilaiKriteria($mhs, $kolomData)
{
    if (isset($mhs[$kolomData])) {
        return floatval($mhs[$kolomData]);
    }

    return 0;
}

/* 1. MATRIKS KEPUTUSAN */
$matriks = [];

foreach ($dataMahasiswa as $mhs) {
    foreach ($dataKriteria as $krit) {
        $idKriteria = $krit['id_kriteria'];
        $kolomData = $krit['kolom_data'];

        $matriks[$mhs['nim']][$idKriteria] = ambilNilaiKriteria($mhs, $kolomData);
    }
}

/* 2. NORMALISASI */
$pembagi = [];
$normalisasi = [];

foreach ($dataKriteria as $krit) {
    $idKriteria = $krit['id_kriteria'];
    $totalKuadrat = 0;

    foreach ($dataMahasiswa as $mhs) {
        $totalKuadrat += pow($matriks[$mhs['nim']][$idKriteria], 2);
    }

    $pembagi[$idKriteria] = sqrt($totalKuadrat);

    foreach ($dataMahasiswa as $mhs) {
        if ($pembagi[$idKriteria] == 0) {
            $normalisasi[$mhs['nim']][$idKriteria] = 0;
        } else {
            $normalisasi[$mhs['nim']][$idKriteria] =
                $matriks[$mhs['nim']][$idKriteria] / $pembagi[$idKriteria];
        }
    }
}

/* 3. NORMALISASI TERBOBOT */
$normalisasiTerbobot = [];

foreach ($dataMahasiswa as $mhs) {
    foreach ($dataKriteria as $krit) {
        $idKriteria = $krit['id_kriteria'];
        $bobot = floatval($krit['bobot']);

        $normalisasiTerbobot[$mhs['nim']][$idKriteria] =
            $normalisasi[$mhs['nim']][$idKriteria] * $bobot;
    }
}

/* 4. SOLUSI IDEAL POSITIF DAN NEGATIF */
$solusiPositif = [];
$solusiNegatif = [];

foreach ($dataKriteria as $krit) {
    $idKriteria = $krit['id_kriteria'];
    $jenis = strtolower($krit['jenis']);

    $nilaiKriteria = [];

    foreach ($dataMahasiswa as $mhs) {
        $nilaiKriteria[] = $normalisasiTerbobot[$mhs['nim']][$idKriteria];
    }

    if ($jenis == 'benefit') {
        $solusiPositif[$idKriteria] = max($nilaiKriteria);
        $solusiNegatif[$idKriteria] = min($nilaiKriteria);
    } else {
        $solusiPositif[$idKriteria] = min($nilaiKriteria);
        $solusiNegatif[$idKriteria] = max($nilaiKriteria);
    }

    mysqli_query($conn, "
        INSERT INTO solusi_ideal (
            id_kriteria,
            nilai_positif,
            nilai_negatif
        ) VALUES (
            '$idKriteria',
            '{$solusiPositif[$idKriteria]}',
            '{$solusiNegatif[$idKriteria]}'
        )
    ");
}

/* 5. HITUNG JARAK DAN NILAI PREFERENSI */
$hasilTopsis = [];

foreach ($dataMahasiswa as $mhs) {
    $dPlus = 0;
    $dMinus = 0;

    foreach ($dataKriteria as $krit) {
        $idKriteria = $krit['id_kriteria'];
        $nilai = $normalisasiTerbobot[$mhs['nim']][$idKriteria];

        $dPlus += pow($nilai - $solusiPositif[$idKriteria], 2);
        $dMinus += pow($nilai - $solusiNegatif[$idKriteria], 2);
    }

    $dPlus = sqrt($dPlus);
    $dMinus = sqrt($dMinus);

    $preferensi = 0;

    if (($dPlus + $dMinus) != 0) {
        $preferensi = $dMinus / ($dPlus + $dMinus);
    }

    $hasilTopsis[] = [
        'nim' => $mhs['nim'],
        'nilai_preferensi' => $preferensi
    ];
}

/* 6. URUTKAN RANKING */
usort($hasilTopsis, function ($a, $b) {
    return $b['nilai_preferensi'] <=> $a['nilai_preferensi'];
});

/* 7. SIMPAN HASIL TOPSIS */
$ranking = 1;

foreach ($hasilTopsis as $hasil) {
    $status = 'Aman';

    if ($hasil['nilai_preferensi'] < 0.40) {
        $status = 'Kritis';
    } elseif ($hasil['nilai_preferensi'] < 0.60) {
        $status = 'Waspada';
    } elseif ($hasil['nilai_preferensi'] >= 0.80) {
        $status = 'Sangat Baik';
    }

    mysqli_query($conn, "
        INSERT INTO ranking_topsis (
            nim,
            nilai_preferensi,
            ranking,
            periode_evaluasi
        ) VALUES (
            '{$hasil['nim']}',
            '{$hasil['nilai_preferensi']}',
            '$ranking',
            '$periode'
        )
    ");

    mysqli_query($conn, "
        INSERT INTO hasil_evaluasi (
            nim,
            periode_evaluasi,
            nilai_preferensi,
            status_early_warning
        ) VALUES (
            '{$hasil['nim']}',
            '$periode',
            '{$hasil['nilai_preferensi']}',
            '$status'
        )
    ");

    $ranking++;
}

/* LOG AKTIVITAS */
if (isset($_SESSION['id_user'])) {
    mysqli_query($conn, "
        INSERT INTO log_aktivitas (
            aksi,
            tanggal,
            id_user
        ) VALUES (
            'Menjalankan proses perhitungan TOPSIS',
            NOW(),
            '{$_SESSION['id_user']}'
        )
    ");
}

echo "
<script>
    alert('Proses TOPSIS berhasil dijalankan.');
    window.location='../pages/monitoring.php';
</script>
";
exit;
?>