<?php
session_start();
include "../config/database.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit;
}

if (isset($_GET['id'])) {
    $id_user = (int) $_GET['id'];

    // Ambil nama user sebelum dihapus
    $dataUser = mysqli_fetch_assoc(mysqli_query($conn, "
        SELECT nama_lengkap 
        FROM user 
        WHERE id_user='$id_user'
    "));

    $namaUser = $dataUser ? $dataUser['nama_lengkap'] : 'Pengguna';

    // Hapus user
    $hapus = mysqli_query($conn, "
        DELETE FROM user 
        WHERE id_user='$id_user'
    ");

    if ($hapus) {
        // Simpan log aktivitas
        $idAdmin = $_SESSION['id_user'];

        mysqli_query($conn, "
            INSERT INTO log_aktivitas (aksi, tanggal, id_user)
            VALUES (
                'Menghapus pengguna: $namaUser',
                NOW(),
                '$idAdmin'
            )
        ");

        echo "
        <script>
            alert('Pengguna berhasil dihapus.');
            window.location='../pages/manajemen_data.php';
        </script>
        ";
    } else {
        echo "
        <script>
            alert('Pengguna gagal dihapus.');
            window.location='../pages/manajemen_data.php';
        </script>
        ";
    }
}
?>