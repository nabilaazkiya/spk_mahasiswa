<?php
// Koneksi ke database MySQL (mysqli) dipakai di semua file lain.
$host = "localhost";
$user = "root";
$pass = "";
$db   = "spk_mahasiswa";

$conn = mysqli_connect($host, $user, $pass, $db);

if (!$conn) {
    die("Koneksi database gagal: " . mysqli_connect_error());
}
?>