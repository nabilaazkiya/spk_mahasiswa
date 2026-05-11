<?php
session_start();
include "../config/database.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit;
}

$id_user = mysqli_real_escape_string($conn, $_POST['id_user']);
$username = mysqli_real_escape_string($conn, $_POST['username']);
$password = $_POST['password'];
$nama_lengkap = mysqli_real_escape_string($conn, $_POST['nama_lengkap']);
$status_sia = mysqli_real_escape_string($conn, $_POST['status_sia']);
$role = mysqli_real_escape_string($conn, $_POST['role']);

$cekUsername = mysqli_query($conn, "
    SELECT * FROM user 
    WHERE username = '$username' 
    AND id_user != '$id_user'
");

if (mysqli_num_rows($cekUsername) > 0) {
    echo "
        <script>
            alert('Username sudah digunakan pengguna lain!');
            window.location='../pages/edit_user.php?id=$id_user';
        </script>
    ";
    exit;
}

if ($password != '') {
    $password_hash = password_hash($password, PASSWORD_DEFAULT);

    $query = mysqli_query($conn, "
        UPDATE user SET
            username = '$username',
            password = '$password_hash',
            nama_lengkap = '$nama_lengkap',
            status_sia = '$status_sia',
            role = '$role'
        WHERE id_user = '$id_user'
    ");
} else {
    $query = mysqli_query($conn, "
        UPDATE user SET
            username = '$username',
            nama_lengkap = '$nama_lengkap',
            status_sia = '$status_sia',
            role = '$role'
        WHERE id_user = '$id_user'
    ");
}

if ($query) {
    echo "
        <script>
            alert('Data pengguna berhasil diperbarui!');
            window.location='../pages/manajemen_data.php';
        </script>
    ";
} else {
    echo "
        <script>
            alert('Gagal memperbarui data pengguna!');
            window.location='../pages/edit_user.php?id=$id_user';
        </script>
    ";
}
?>