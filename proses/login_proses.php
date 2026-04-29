<?php
session_start();
include "../config/database.php";

$username = $_POST['username'];
$password = $_POST['password'];

$query = mysqli_query($conn, "SELECT * FROM user WHERE username='$username'");
$user = mysqli_fetch_assoc($query);

if ($user && password_verify($password, $user['password'])) {
    $_SESSION['id_user'] = $user['id_user'];
    $_SESSION['nama_lengkap'] = $user['nama_lengkap'];
    $_SESSION['role'] = $user['role'];

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