<?php
/**
 * =============================================
 * SINKRONISASI MAHASISWA <-> AKUN DPA
 * =============================================
 * PERBAIKAN BUG: mahasiswa.id_user (penghubung ke akun
 * dosen PA) sebelumnya HANYA di-set di satu tempat, yaitu
 * saat proses import CSV/XLSX (proses/input_data.php),
 * dengan mencocokkan teks kolom "DPA" terhadap
 * user.nama_lengkap PADA SAAT ITU JUGA.
 *
 * Akibatnya, kalau alur kerja admin adalah:
 *   1. Import data akademik dulu (dosen PA belum ada akunnya)
 *   2. Baru buat akun DPA setelahnya
 * maka mahasiswa.id_user untuk DPA tsb SELAMANYA tetap NULL
 * (tidak pernah otomatis dihubungkan ulang) - akibatnya
 * dashboard & monitoring DPA menampilkan 0 data terus,
 * meskipun akunnya sudah benar dan datanya sudah ada.
 *
 * Fungsi ini dipanggil dari proses/tambah_user_proses.php
 * (saat akun DPA baru dibuat) dan proses/edit_user_proses.php
 * (saat nama_lengkap seorang DPA diperbaiki/diubah), supaya
 * link mahasiswa->DPA selalu disegarkan setiap ada perubahan
 * pada akun DPA - bukan hanya saat import data.
 * =============================================
 */

/**
 * Hubungkan ulang semua mahasiswa yang kolom "Dosen PA"-nya
 * (dari data akademik TERBARU) cocok dengan nama seorang DPA,
 * ke akun (id_user) DPA tersebut.
 *
 * Pencocokan nama dibuat toleran terhadap spasi berlebih di
 * awal/akhir (TRIM) - kolom nama_lengkap di tabel `user`
 * sudah pakai collation *_ci (case-insensitive) sehingga besar
 * kecil huruf otomatis diabaikan oleh MySQL.
 *
 * @return int Jumlah baris mahasiswa yang berhasil dihubungkan.
 */
function sinkronkanMahasiswaDpa($conn, $idUserDpa, $namaLengkapDpa)
{
    $namaLengkapDpa = trim($namaLengkapDpa);

    if ($namaLengkapDpa === '') {
        return 0;
    }

    $stmt = mysqli_prepare($conn, "
        UPDATE mahasiswa m
        INNER JOIN data_akademik_terbaru d ON m.nim = d.nim
        SET m.id_user = ?
        WHERE TRIM(d.dosen_pa) = ?
    ");
    mysqli_stmt_bind_param($stmt, "is", $idUserDpa, $namaLengkapDpa);
    mysqli_stmt_execute($stmt);
    $jumlahTerhubung = mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);

    return $jumlahTerhubung;
}
?>
