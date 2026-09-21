<?php
// Halaman awal aplikasi: redirect ke dashboard sesuai role kalau sudah login, atau ke login.php kalau belum.
session_start();

if (isset($_SESSION['role'])) {
    if ($_SESSION['role'] == 'admin') {
        header("Location: pages/dashboard_admin.php");
    } elseif ($_SESSION['role'] == 'kaprodi') {
        header("Location: pages/dashboard_kaprodi.php");
    } elseif ($_SESSION['role'] == 'dpa') {
        header("Location: pages/dashboard_dpa.php");
    }
} else {
    header("Location: login.php");
}
exit;
?>