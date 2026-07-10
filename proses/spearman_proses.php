<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include "../config/database.php";

mysqli_query($conn, "DELETE FROM uji_spearman");

$query = mysqli_query($conn, "
    SELECT 
        t.nim,
        t.ranking AS ranking_topsis,
        s.ranking AS ranking_saw
    FROM ranking_topsis_terbaru t
    JOIN ranking_saw s ON t.nim = s.nim
");

$n = mysqli_num_rows($query);
$sumD2 = 0;

while ($row = mysqli_fetch_assoc($query)) {
    $d = $row['ranking_topsis'] - $row['ranking_saw'];
    $d2 = pow($d, 2);
    $sumD2 += $d2;
}

$rs = 0;

if ($n > 1) {
    $rs = 1 - ((6 * $sumD2) / ($n * (pow($n, 2) - 1)));
}

$keterangan = 'Tidak Ada Korelasi';

if ($rs >= 0.80) {
    $keterangan = 'Sangat Kuat';
} elseif ($rs >= 0.60) {
    $keterangan = 'Kuat';
} elseif ($rs >= 0.40) {
    $keterangan = 'Cukup Kuat';
} elseif ($rs >= 0.20) {
    $keterangan = 'Lemah';
}

$preferensiModel = 'TOPSIS dan SAW belum konsisten';

if ($rs >= 0.80) {
    $preferensiModel = 'TOPSIS dan SAW sangat konsisten';
} elseif ($rs >= 0.60) {
    $preferensiModel = 'TOPSIS dan SAW cukup konsisten';
} elseif ($rs >= 0.40) {
    $preferensiModel = 'TOPSIS dan SAW memiliki kesamaan sedang';
}

mysqli_query($conn, "
    INSERT INTO uji_spearman (
        rs,
        keterangan,
        preferensi_model,
        tanggal_uji
    ) VALUES (
        '$rs',
        '$keterangan',
        '$preferensiModel',
        NOW()
    )
");

if (isset($_SESSION['id_user'])) {
    mysqli_query($conn, "
        INSERT INTO log_aktivitas (aksi, tanggal, id_user)
        VALUES ('Melakukan uji korelasi Spearman TOPSIS dan SAW', NOW(), '{$_SESSION['id_user']}')
    ");
}

/* Jika dirantai dari input_data.php (proses import oleh admin),
   JANGAN redirect ke monitoring.php karena halaman itu
   khusus untuk role 'kaprodi'. Biarkan input_data.php
   yang menentukan halaman tujuan akhir. */
if (!defined('SPK_CHAIN')) {
    header("Location: ../pages/monitoring.php");
    exit;
}
?>