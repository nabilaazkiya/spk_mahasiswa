<?php
session_start();
include "../config/database.php";

$username = mysqli_real_escape_string($conn, $_POST['username']);
$password = $_POST['password'];

$query = mysqli_query($conn, "SELECT * FROM user WHERE username='$username'");
$user = mysqli_fetch_assoc($query);

if ($user && password_verify($password, $user['password'])) {
    $_SESSION['id_user'] = $user['id_user'];
    $_SESSION['nama_lengkap'] = $user['nama_lengkap'];
    $_SESSION['role'] = $user['role'];

    /* =============================================
       AUTO-FIX KOMPATIBILITAS DATABASE LAMA:
       Dijalankan sekali saat login berhasil agar
       laptop lain yang import database versi lama
       langsung kompatibel tanpa langkah manual.
       Semua operasi ini aman dijalankan berkali-kali.
       ============================================= */

    // 1. Tambah kolom status_sia_mahasiswa jika belum ada
    $cekKolom = mysqli_query($conn, "SHOW COLUMNS FROM data_akademik LIKE 'status_sia_mahasiswa'");
    if ($cekKolom && mysqli_num_rows($cekKolom) === 0) {
        mysqli_query($conn, "
            ALTER TABLE data_akademik
            ADD COLUMN status_sia_mahasiswa ENUM('aktif','tidak_aktif') NOT NULL DEFAULT 'aktif'
            AFTER sks_nilai_kurang_b
        ");
    }

    // 2. Tambah kolom status_sia di tabel user jika belum ada
    $cekKolomUser = mysqli_query($conn, "SHOW COLUMNS FROM user LIKE 'status_sia'");
    if ($cekKolomUser && mysqli_num_rows($cekKolomUser) === 0) {
        mysqli_query($conn, "
            ALTER TABLE user
            ADD COLUMN status_sia ENUM('aktif','nonaktif') NOT NULL DEFAULT 'aktif'
        ");
    }

    // 3. Refresh VIEW data_akademik_terbaru agar kolom baru ikut masuk
    mysqli_query($conn, "
        CREATE OR REPLACE VIEW data_akademik_terbaru AS
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

    // 4. Buat VIEW lain jika belum ada (aman: CREATE OR REPLACE tidak merusak data)
    mysqli_query($conn, "
        CREATE OR REPLACE VIEW ranking_topsis_terbaru AS
        SELECT rt.*
        FROM ranking_topsis rt
        INNER JOIN (
            SELECT nim, MAX(id_ranking) AS id_ranking_terbaru
            FROM ranking_topsis
            GROUP BY nim
        ) terbaru
        ON rt.nim = terbaru.nim
        AND rt.id_ranking = terbaru.id_ranking_terbaru
    ");

    mysqli_query($conn, "
        CREATE OR REPLACE VIEW hasil_evaluasi_terbaru AS
        SELECT he.*
        FROM hasil_evaluasi he
        INNER JOIN (
            SELECT nim, MAX(id_hasil) AS id_hasil_terbaru
            FROM hasil_evaluasi
            GROUP BY nim
        ) terbaru
        ON he.nim = terbaru.nim
        AND he.id_hasil = terbaru.id_hasil_terbaru
    ");

    if ($user['role'] == 'admin') {
        header("Location: ../pages/dashboard_admin.php");
    } elseif ($user['role'] == 'kaprodi') {
        header("Location: ../pages/dashboard_kaprodi.php");
    } elseif ($user['role'] == 'dpa') {
        header("Location: ../pages/dashboard_dpa.php");
    }
} else {
    echo "Username atau password salah.";
}
?>