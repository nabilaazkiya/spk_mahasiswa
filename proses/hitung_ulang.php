<?php
// Jalankan ulang seluruh proses TOPSIS, SAW, dan uji Spearman dari data akademik terkini.
session_start();
include "../config/database.php";

/* Hanya admin yang boleh memicu hitung ulang manual */
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

define('SPK_CHAIN', true);
require '../proses/topsis_proses.php';
require '../proses/saw_proses.php';
require '../proses/spearman_proses.php';

$idAdmin = $_SESSION['id_user'] ?? null;
$stmtLog = mysqli_prepare($conn, "INSERT INTO log_aktivitas (aksi, tanggal, id_user) VALUES ('Hitung ulang TOPSIS/SAW/Spearman manual', NOW(), ?)");
mysqli_stmt_bind_param($stmtLog, "i", $idAdmin);
mysqli_stmt_execute($stmtLog);
mysqli_stmt_close($stmtLog);

echo "
<script>
    alert('TOPSIS, SAW, dan Spearman berhasil dihitung ulang dari data akademik terkini.');
    window.location='../pages/manajemen_data.php';
</script>
";
