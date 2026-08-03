<?php
session_start();
include "../config/database.php";
require "../includes/dpa_sync.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit;
}

/**
 * =============================================
 * SINKRONISASI MANUAL DPA <-> MAHASISWA
 * =============================================
 * Sinkronisasi otomatis (includes/dpa_sync.php) hanya berjalan
 * saat akun DPA BARU dibuat atau saat nama_lengkap-nya DIEDIT.
 * Akun DPA yang sudah ada dari sebelumnya (dan tidak pernah
 * diedit) tidak pernah "disegarkan" ulang, sehingga bisa saja
 * tetap 0 mahasiswa terhubung walau datanya sudah ada di
 * database. Halaman ini memicu sinkronisasi itu secara manual,
 * kapan saja, untuk satu akun DPA (?id=..) atau SEMUA akun DPA
 * sekaligus (tanpa parameter id).
 */

$idHanya = isset($_GET['id']) ? (int) $_GET['id'] : null;

if ($idHanya) {
    $whereDpa = "AND id_user = $idHanya";
} else {
    $whereDpa = "";
}

$daftarDpa = mysqli_query($conn, "
    SELECT id_user, nama_lengkap
    FROM user
    WHERE role = 'dpa' $whereDpa
");

if (!$daftarDpa || mysqli_num_rows($daftarDpa) === 0) {
    echo "
        <script>
            alert('Tidak ada akun DPA yang ditemukan untuk disinkronkan.');
            window.location='../pages/manajemen_data.php';
        </script>
    ";
    exit;
}

$totalTerhubung = 0;
$rincian = [];

while ($dpa = mysqli_fetch_assoc($daftarDpa)) {
    $jumlah = sinkronkanMahasiswaDpa($conn, $dpa['id_user'], $dpa['nama_lengkap']);
    $totalTerhubung += $jumlah;
    $rincian[] = $dpa['nama_lengkap'] . ' (' . $jumlah . ' mahasiswa)';
}

/* CATAT LOG */
$idAdmin = $_SESSION['id_user'];
$aksiLog = mysqli_real_escape_string($conn, "Menjalankan sinkronisasi manual DPA <-> mahasiswa (" . $totalTerhubung . " tersambung)");

mysqli_query($conn, "
    INSERT INTO log_aktivitas (aksi, tanggal, id_user)
    VALUES ('$aksiLog', NOW(), '$idAdmin')
");

$pesan = "Sinkronisasi selesai. Total $totalTerhubung mahasiswa terhubung.\\n\\n" . implode('\\n', $rincian);
$pesanJs = json_encode($pesan);

echo "
    <script>
        alert($pesanJs);
        window.location='../pages/manajemen_data.php';
    </script>
";
