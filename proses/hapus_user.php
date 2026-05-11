<?php
session_start();
include "../config/database.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit;
}

if (!isset($_GET['id'])) {
    header("Location: ../pages/manajemen_data.php");
    exit;
}

$id_user = mysqli_real_escape_string($conn, $_GET['id']);

$query = mysqli_query($conn, "
    DELETE FROM user 
    WHERE id_user = '$id_user'
");

if ($query) {
    header("Location: ../pages/manajemen_data.php");
    exit;
} else {
    echo "Gagal menghapus pengguna: " . mysqli_error($conn);
}
?>