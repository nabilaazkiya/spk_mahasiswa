<?php
session_start();
include "../config/database.php";

if (!isset($_SESSION['role'])) {
    header("Location: ../login.php");
    exit;
}

$periode = date('Y-m-d');

mysqli_query($conn, "DELETE FROM ranking_saw");

$dataMahasiswa = [];
$qMahasiswa = mysqli_query($conn, "SELECT * FROM data_akademik");

while ($row = mysqli_fetch_assoc($qMahasiswa)) {
    $dataMahasiswa[] = $row;
}

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

if (count($dataMahasiswa) == 0 || count($dataKriteria) == 0) {
    echo "
    <script>
        alert('Data mahasiswa atau kriteria masih kosong.');
        window.location='../pages/monitoring.php';
    </script>";
    exit;
}

function ambilNilaiSaw($mhs, $kolomData)
{
    return isset($mhs[$kolomData]) ? floatval($mhs[$kolomData]) : 0;
}

$matrix = [];

foreach ($dataMahasiswa as $mhs) {
    foreach ($dataKriteria as $krit) {
        $idKriteria = $krit['id_kriteria'];
        $kolomData = $krit['kolom_data'];

        $matrix[$mhs['nim']][$idKriteria] = ambilNilaiSaw($mhs, $kolomData);
    }
}

$maxMin = [];

foreach ($dataKriteria as $krit) {
    $idKriteria = $krit['id_kriteria'];
    $nilaiKolom = [];

    foreach ($dataMahasiswa as $mhs) {
        $nilaiKolom[] = $matrix[$mhs['nim']][$idKriteria];
    }

    $maxMin[$idKriteria] = [
        'max' => max($nilaiKolom),
        'min' => min($nilaiKolom)
    ];
}

$hasilSaw = [];

foreach ($dataMahasiswa as $mhs) {
    $total = 0;

    foreach ($dataKriteria as $krit) {
        $idKriteria = $krit['id_kriteria'];
        $nilai = $matrix[$mhs['nim']][$idKriteria];
        $bobot = floatval($krit['bobot']);
        $jenis = strtolower($krit['jenis']);

        $normal = 0;

        if ($jenis == 'benefit') {
            if ($maxMin[$idKriteria]['max'] != 0) {
                $normal = $nilai / $maxMin[$idKriteria]['max'];
            }
        } else {
            if ($nilai != 0) {
                $normal = $maxMin[$idKriteria]['min'] / $nilai;
            }
        }

        $total += $normal * $bobot;
    }

    $hasilSaw[] = [
        'nim' => $mhs['nim'],
        'nilai_preferensi' => $total
    ];
}

usort($hasilSaw, function ($a, $b) {
    return $b['nilai_preferensi'] <=> $a['nilai_preferensi'];
});

$ranking = 1;

foreach ($hasilSaw as $hasil) {
    mysqli_query($conn, "
        INSERT INTO ranking_saw (
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

    $ranking++;
}

if (isset($_SESSION['id_user'])) {
    mysqli_query($conn, "
        INSERT INTO log_aktivitas (aksi, tanggal, id_user)
        VALUES ('Menjalankan proses perhitungan SAW', NOW(), '{$_SESSION['id_user']}')
    ");
}

header("Location: spearman_proses.php");
exit;
?>