<?php
session_start();
include "../config/database.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit;
}

$fileCsv = "../assets/data_delphi.csv";

if (!file_exists($fileCsv)) {
    echo "<script>
        alert('File hasil_delphi_iterasi1.csv tidak ditemukan.');
        window.location='konfigurasi_kriteria.php';
    </script>";
    exit;
}

$dataDelphi = [];
$totalRataRata = 0;

$file = fopen($fileCsv, "r");
$header = fgetcsv($file);

while (($row = fgetcsv($file)) !== false) {

    $data = array_combine($header, $row);

    $kode = trim($data['kode_kriteria']);
    $nama = trim($data['nama_kriteria']);
    $kolom = trim($data['kolom_data']);
    $jenis = strtolower(trim($data['jenis']));

    $nilaiPakar = [];

    foreach ($data as $key => $value) {
        if (strpos($key, 'pakar_') === 0) {
            $nilaiPakar[] = floatval($value);
        }
    }

    if (count($nilaiPakar) == 0) {
        continue;
    }

    $rataRata = array_sum($nilaiPakar) / count($nilaiPakar);

    $dataDelphi[] = [
        'kode_kriteria' => $kode,
        'nama_kriteria' => $nama,
        'kolom_data' => $kolom,
        'jenis' => $jenis,
        'rata_rata' => $rataRata
    ];

    $totalRataRata += $rataRata;
}

fclose($file);

if ($totalRataRata <= 0) {
    echo "<script>
        alert('Data nilai pakar tidak valid.');
        window.location='konfigurasi_kriteria.php';
    </script>";
    exit;
}

foreach ($dataDelphi as $item) {
    $kode = mysqli_real_escape_string($conn, $item['kode_kriteria']);
    $nama = mysqli_real_escape_string($conn, $item['nama_kriteria']);
    $kolom = mysqli_real_escape_string($conn, $item['kolom_data']);
    $jenis = mysqli_real_escape_string($conn, $item['jenis']);

    $rataRata = $item['rata_rata'];
    $bobotDelphi = $rataRata / $totalRataRata;

    $cek = mysqli_query($conn, "
        SELECT id_kriteria 
        FROM kriteria 
        WHERE kode_kriteria = '$kode'
    ");

    if (mysqli_num_rows($cek) > 0) {
        mysqli_query($conn, "
            UPDATE kriteria SET
                nama_kriteria = '$nama',
                kolom_data = '$kolom',
                jenis = '$jenis',
                bobot_delphi = '$bobotDelphi'
            WHERE kode_kriteria = '$kode'
        ");
    } else {
        mysqli_query($conn, "
            INSERT INTO kriteria (
                kode_kriteria,
                nama_kriteria,
                kolom_data,
                jenis,
                bobot_delphi
            ) VALUES (
                '$kode',
                '$nama',
                '$kolom',
                '$jenis',
                '$bobotDelphi'
            )
        ");
    }
}

if (isset($_SESSION['id_user'])) {
    mysqli_query($conn, "
        INSERT INTO log_aktivitas (id_user, aksi, tanggal)
        VALUES (
            '{$_SESSION['id_user']}',
            'Menghitung bobot kriteria menggunakan Delphi Iterasi 1',
            NOW()
        )
    ");
}

echo "<script>
    alert('Bobot Delphi Iterasi 1 berhasil dihitung otomatis.');
    window.location='konfigurasi_kriteria.php';
</script>";
exit;
?>