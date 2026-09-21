<?php
// Proses form login: cek username/password, buat session, dan refresh VIEW data 'terbaru' di database.
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

    // 1. status_sia_mahasiswa DIHAPUS - status_sia (aktif/cuti/do/-)
    //    sekarang satu-satunya sumber kebenaran status mahasiswa.

    // 2. Tambah kolom status_sia di tabel user jika belum ada
    $cekKolomUser = mysqli_query($conn, "SHOW COLUMNS FROM user LIKE 'status_sia'");
    if ($cekKolomUser && mysqli_num_rows($cekKolomUser) === 0) {
        mysqli_query($conn, "
            ALTER TABLE user
            ADD COLUMN status_sia ENUM('aktif','nonaktif') NOT NULL DEFAULT 'aktif'
        ");
    }

    // 3. Refresh VIEW data_akademik_terbaru agar kolom baru ikut masuk
    //    PERBAIKAN BUG: sebelumnya "terbaru" ditentukan dari
    //    MAX(id_data) (baris terakhir yang di-INSERT). Ini salah
    //    kalau admin meng-upload data semester LAMA/sebelumnya
    //    setelah data semester yang lebih baru sudah ada di
    //    database - baris lama tsb baru saja di-INSERT sehingga
    //    id_data-nya justru lebih besar, dan VIEW ini keliru
    //    menganggapnya sebagai data "terbaru". Sekarang "terbaru"
    //    ditentukan dari nilai semester TERBESAR milik NIM
    //    tersebut (id_data hanya dipakai sebagai penentu kalau
    //    ada duplikat semester yang sama).
    mysqli_query($conn, "
        CREATE OR REPLACE VIEW data_akademik_terbaru AS
        SELECT da.*
        FROM data_akademik da
        INNER JOIN (
            SELECT nim, MAX(semester) AS semester_terbaru
            FROM data_akademik
            GROUP BY nim
        ) semTerbaru
        ON da.nim = semTerbaru.nim
        AND da.semester = semTerbaru.semester_terbaru
        INNER JOIN (
            SELECT nim, semester, MAX(id_data) AS id_data_terbaru
            FROM data_akademik
            GROUP BY nim, semester
        ) idTerbaru
        ON da.nim = idTerbaru.nim
        AND da.semester = idTerbaru.semester
        AND da.id_data = idTerbaru.id_data_terbaru
    ");

    // 4. Buat VIEW lain jika belum ada (aman: CREATE OR REPLACE tidak merusak data)
    //    PERBAIKAN BUG yang sama: "terbaru" ditentukan dari nomor
    //    semester yang tertanam di periode_evaluasi ("Semester NN"),
    //    bukan dari MAX(id_ranking)/MAX(id_hasil), supaya upload
    //    data semester lama tidak keliru dianggap periode terbaru.
    mysqli_query($conn, "
        CREATE OR REPLACE VIEW ranking_topsis_terbaru AS
        SELECT rt.*
        FROM ranking_topsis rt
        INNER JOIN (
            SELECT nim, MAX(CAST(SUBSTRING(periode_evaluasi, 10) AS UNSIGNED)) AS semester_terbaru
            FROM ranking_topsis
            GROUP BY nim
        ) terbaru
        ON rt.nim = terbaru.nim
        AND CAST(SUBSTRING(rt.periode_evaluasi, 10) AS UNSIGNED) = terbaru.semester_terbaru
    ");

    mysqli_query($conn, "
        CREATE OR REPLACE VIEW hasil_evaluasi_terbaru AS
        SELECT he.*
        FROM hasil_evaluasi he
        INNER JOIN (
            SELECT nim, MAX(CAST(SUBSTRING(periode_evaluasi, 10) AS UNSIGNED)) AS semester_terbaru
            FROM hasil_evaluasi
            GROUP BY nim
        ) terbaru
        ON he.nim = terbaru.nim
        AND CAST(SUBSTRING(he.periode_evaluasi, 10) AS UNSIGNED) = terbaru.semester_terbaru
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