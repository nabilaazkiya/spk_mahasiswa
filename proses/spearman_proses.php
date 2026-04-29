<?php
include "../config/database.php";

mysqli_query($conn, "DELETE FROM uji_spearman");

$query = mysqli_query($conn, "
    SELECT t.nim, t.ranking AS ranking_topsis, s.ranking AS ranking_saw
    FROM ranking_topsis t
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

mysqli_query($conn, "
    INSERT INTO uji_spearman (rs, keterangan)
    VALUES ('$rs', '$keterangan')
");

header("Location: ../pages/monitoring.php");
exit;
?>