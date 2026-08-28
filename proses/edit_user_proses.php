<?php
session_start();
include "../config/database.php";
require "../includes/dpa_sync.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit;
}

$id_user       = mysqli_real_escape_string($conn, $_POST['id_user']);
$username      = mysqli_real_escape_string($conn, $_POST['username']);
$password      = $_POST['password'];
$nama_lengkap  = mysqli_real_escape_string($conn, $_POST['nama_lengkap']);
$status_sia    = mysqli_real_escape_string($conn, $_POST['status_sia']);
$role          = mysqli_real_escape_string($conn, $_POST['role']);

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

/* PERBAIKAN: cek nama lengkap duplikat HANYA DI ROLE YANG
   SAMA (lihat penjelasan lengkap di tambah_user_proses.php) -
   satu orang boleh punya akun Kaprodi + DPA sekaligus dengan
   nama sama, itu bukan masalah. Mengecualikan akun yang
   sedang diedit sendiri via id_user != $id_user. */
$namaLengkapTrim = trim($_POST['nama_lengkap']);
$cekNama = mysqli_query($conn, "
    SELECT nama_lengkap, role FROM user
    WHERE TRIM(nama_lengkap) = '" . mysqli_real_escape_string($conn, $namaLengkapTrim) . "'
    AND role = '$role'
    AND id_user != '$id_user'
");

if ($cekNama && mysqli_num_rows($cekNama) > 0) {
    $pesanDuplikat = json_encode(
        "Nama \"$namaLengkapTrim\" sudah dipakai oleh akun lain dengan role \"$role\" yang sama. " .
        "Nama lengkap harus unik di dalam role yang sama untuk mencegah kesalahan pencocokan data mahasiswa."
    );

    echo "
        <script>
            alert($pesanDuplikat);
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

    $idAdmin = $_SESSION['id_user'];

    /* PERBAIKAN BUG: kalau nama_lengkap seorang DPA diperbaiki
       (misal ditambah gelar supaya cocok dengan teks "Dosen PA"
       di file yang diimpor), sinkronkan ulang link mahasiswa->DPA.
       Sebelumnya link ini tidak pernah disegarkan saat edit,
       sehingga koreksi nama tidak berdampak apapun ke dashboard DPA. */
    $jumlahTerhubung = 0;

    if ($role === 'dpa') {
        $jumlahTerhubung = sinkronkanMahasiswaDpa($conn, $id_user, trim($_POST['nama_lengkap']));
    }

    mysqli_query($conn, "
        INSERT INTO log_aktivitas (
            aksi,
            tanggal,
            id_user
        ) VALUES (
            'Mengedit pengguna: $nama_lengkap',
            NOW(),
            '$idAdmin'
        )
    ");

    $pesan = 'Data pengguna berhasil diperbarui!';

    if ($role === 'dpa') {
        if ($jumlahTerhubung > 0) {
            $pesan .= " $jumlahTerhubung mahasiswa berhasil dihubungkan otomatis ke akun ini.";
        } else {
            $pesan .= ' PERHATIAN: belum ada mahasiswa yang cocok dengan nama ini di data akademik. Pastikan nama lengkap PERSIS SAMA dengan kolom "Dosen PA" di file yang diimpor.';
        }
    }

    $pesanJs = json_encode($pesan);

    echo "
        <script>
            alert($pesanJs);
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