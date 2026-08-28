<?php
session_start();

include "../config/database.php";
require "../includes/dpa_sync.php";

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

/* PERBAIKAN: cek nama lengkap duplikat HANYA DI ROLE YANG
   SAMA - bukan lintas semua role. Satu orang yang sama boleh
   punya 2 akun berbeda (misal Kaprodi + DPA sekaligus), karena
   data yang ditampilkan ke masing-masing role memang berbeda.
   Risiko ambigu (sinkronkanMahasiswaDpa() salah sambung) HANYA
   terjadi kalau ada 2 akun DENGAN ROLE SAMA (khususnya 2 akun
   'dpa') bernama identik - karena fungsi sync itu sendiri sudah
   memfilter role='dpa', jadi duplikat lintas role tidak
   berisiko sama sekali. */
$namaLengkapTrim = trim($_POST['nama_lengkap']);
$cekNama = mysqli_query($conn, "
    SELECT nama_lengkap, role FROM user
    WHERE TRIM(nama_lengkap) = '" . mysqli_real_escape_string($conn, $namaLengkapTrim) . "'
    AND role = '$role'
");

if ($cekNama && mysqli_num_rows($cekNama) > 0) {
    $pesanDuplikat = json_encode(
        "Nama \"$namaLengkapTrim\" sudah dipakai oleh akun lain dengan role \"$role\" yang sama. " .
        "Nama lengkap harus unik di dalam role yang sama untuk mencegah kesalahan pencocokan data mahasiswa."
    );

    echo "
        <script>
            alert($pesanDuplikat);
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

    $idAdmin = $_SESSION['id_user'];

    /* PERBAIKAN BUG: setelah akun DPA baru dibuat, langsung
       coba hubungkan ke mahasiswa yang datanya sudah lebih
       dulu diimpor (lihat includes/dpa_sync.php untuk detail
       akar masalahnya). Tanpa ini, dashboard DPA yang baru
       dibuat akan selalu menampilkan 0 data. */
    $jumlahTerhubung = 0;

    if ($role === 'dpa') {
        $idUserBaru = mysqli_insert_id($conn);
        /* Pakai nilai mentah dari $_POST (bukan $nama_lengkap yang
           sudah di-escape untuk query manual di atas), karena
           sinkronkanMahasiswaDpa() pakai prepared statement -
           kalau dikasih string yang sudah di-escape, akan
           ter-escape dua kali dan pencocokan nama jadi salah. */
        $jumlahTerhubung = sinkronkanMahasiswaDpa($conn, $idUserBaru, trim($_POST['nama_lengkap']));
    }

    mysqli_query($conn, "
        INSERT INTO log_aktivitas (
            aksi,
            tanggal,
            id_user
        ) VALUES (
            'Menambahkan pengguna baru: $nama_lengkap sebagai $role',
            NOW(),
            '$idAdmin'
        )
    ");

    $pesan = 'Pengguna berhasil ditambahkan!';

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
            alert('Gagal menambahkan pengguna!');
            window.location='../pages/tambah_user.php';
        </script>
    ";

}
?>