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

    $cekKolomUser = mysqli_query($conn, "SHOW COLUMNS FROM user LIKE 'status_sia'");
    if ($cekKolomUser && mysqli_num_rows($cekKolomUser) === 0) {
        mysqli_query($conn, "
            ALTER TABLE user
            ADD COLUMN status_sia ENUM('aktif','nonaktif') NOT NULL DEFAULT 'aktif'
        ");
    }


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
