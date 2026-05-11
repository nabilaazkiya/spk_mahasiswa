<?php
session_start();

include "../config/database.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit;
}

/* AMBIL DATA FORM */
$username      = mysqli_real_escape_string($conn, $_POST['username']);
$password      = $_POST['password'];
$nama_lengkap  = mysqli_real_escape_string($conn, $_POST['nama_lengkap']);
$status_sia    = mysqli_real_escape_string($conn, $_POST['status_sia']);
$role          = mysqli_real_escape_string($conn, $_POST['role']);

/* HASH PASSWORD */
$password_hash = password_hash($password, PASSWORD_DEFAULT);

/* CEK USERNAME */
$cek = mysqli_query($conn, "
    SELECT * FROM user
    WHERE username = '$username'
");

if (mysqli_num_rows($cek) > 0) {

    echo "
        <script>
            alert('Username sudah digunakan!');
            window.location='../pages/tambah_user.php';
        </script>
    ";

    exit;
}

/* INSERT USER */
$query = mysqli_query($conn, "
    INSERT INTO user
    (
        username,
        password,
        nama_lengkap,
        status_sia,
        role
    )
    VALUES
    (
        '$username',
        '$password_hash',
        '$nama_lengkap',
        '$status_sia',
        '$role'
    )
");

/* HASIL */
if ($query) {

    echo "
        <script>
            alert('Pengguna berhasil ditambahkan!');
            window.location='../pages/manajemen_data.php';
        </script>
    ";

} else {

    echo "
        <script>
            alert('Gagal menambahkan pengguna!');
            window.location='../pages/tambah_user.php';
        </script>
    ";

}
?>