<?php
// Hapus satu batch data akademik yang pernah diupload admin, sekaligus catat ke log aktivitas.
session_start();
include "../config/database.php";

/* =============================================
   PROTEKSI AKSES - hanya admin yang boleh
   menghapus batch upload (aksi destruktif: ikut
   menghapus data akademik terkait).
   ============================================= */
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['waktu_upload']) || trim($_POST['waktu_upload']) === '') {
    header("Location: ../pages/manajemen_data.php");
    exit;
}

$waktuUpload = $_POST['waktu_upload'];

/* Validasi format supaya tidak ada input aneh yang lolos
   (harus persis format datetime MySQL: YYYY-MM-DD HH:MM:SS) */
if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $waktuUpload)) {
    header("Location: ../pages/manajemen_data.php");
    exit;
}

/* =============================================
   Grup "batch upload" ditentukan dari kesamaan
   tanggal_upload persis di riwayat_akademik (satu
   waktu yang sama dipakai untuk semua baris dalam
   satu kali proses import - lihat $waktuImpor di
   proses/input_data.php). Menghapus batch berarti
   menghapus SEMUA baris data_akademik yang muncul
   di riwayat_akademik dengan tanggal_upload ini,
   sesuai keputusan user (dokumen dihapus = data
   akademik yang berasal darinya ikut terhapus).
   ============================================= */
mysqli_begin_transaction($conn);

try {
    $stmtHitung = mysqli_prepare($conn, "SELECT COUNT(*) AS jumlah FROM riwayat_akademik WHERE tanggal_upload = ?");
    mysqli_stmt_bind_param($stmtHitung, "s", $waktuUpload);
    mysqli_stmt_execute($stmtHitung);
    $jumlahDataTerhapus = (int) (mysqli_fetch_assoc(mysqli_stmt_get_result($stmtHitung))['jumlah'] ?? 0);
    mysqli_stmt_close($stmtHitung);

    if ($jumlahDataTerhapus === 0) {
        mysqli_rollback($conn);
        echo "
        <script>
            alert('Batch upload tidak ditemukan (mungkin sudah dihapus sebelumnya).');
            window.location='../pages/manajemen_data.php';
        </script>
        ";
        exit;
    }

    $stmtHapusAkademik = mysqli_prepare($conn, "
        DELETE da FROM data_akademik da
        INNER JOIN riwayat_akademik ra ON ra.id_data = da.id_data
        WHERE ra.tanggal_upload = ?
    ");
    mysqli_stmt_bind_param($stmtHapusAkademik, "s", $waktuUpload);
    mysqli_stmt_execute($stmtHapusAkademik);
    mysqli_stmt_close($stmtHapusAkademik);

    $stmtHapusRiwayat = mysqli_prepare($conn, "DELETE FROM riwayat_akademik WHERE tanggal_upload = ?");
    mysqli_stmt_bind_param($stmtHapusRiwayat, "s", $waktuUpload);
    mysqli_stmt_execute($stmtHapusRiwayat);
    mysqli_stmt_close($stmtHapusRiwayat);

    $idAdmin = $_SESSION['id_user'] ?? null;
    $aksiLog = "Hapus batch upload tanggal $waktuUpload ($jumlahDataTerhapus data akademik ikut terhapus)";
    $stmtLog = mysqli_prepare($conn, "INSERT INTO log_aktivitas (aksi, tanggal, id_user) VALUES (?, NOW(), ?)");
    mysqli_stmt_bind_param($stmtLog, "si", $aksiLog, $idAdmin);
    mysqli_stmt_execute($stmtLog);
    mysqli_stmt_close($stmtLog);

    mysqli_commit($conn);
} catch (Exception $e) {
    mysqli_rollback($conn);
    echo "
    <script>
        alert('Gagal menghapus batch upload: data tidak diubah. Silakan coba lagi.');
        window.location='../pages/manajemen_data.php';
    </script>
    ";
    exit;
}

/* rerun TOPSIS/SAW/Spearman setelah data akademik
   berubah (dihapus), supaya ranking di dashboard tidak lagi
   menyertakan mahasiswa yang datanya baru saja dihapus. */
define('SPK_CHAIN', true);
require '../proses/topsis_proses.php';
require '../proses/saw_proses.php';
require '../proses/spearman_proses.php';

echo "
<script>
    alert('Batch upload tanggal " . addslashes(date('d/m/Y H:i:s', strtotime($waktuUpload))) . " berhasil dihapus, beserta $jumlahDataTerhapus baris data akademik. Ranking TOPSIS/SAW telah dihitung ulang.');
    window.location='../pages/manajemen_data.php';
</script>
";
