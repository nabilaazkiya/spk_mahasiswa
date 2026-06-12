<?php
session_start();
include "../config/database.php";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    if (!isset($_FILES['file_import']) || $_FILES['file_import']['error'] != 0) {
        echo "
        <script>
            alert('File gagal diupload atau belum dipilih.');
            window.location='../pages/manajemen_data.php';
        </script>
        ";
        exit;
    }

    $fileName = $_FILES['file_import']['tmp_name'];

    if ($_FILES['file_import']['size'] <= 0) {
        echo "
        <script>
            alert('File kosong.');
            window.location='../pages/manajemen_data.php';
        </script>
        ";
        exit;
    }

    $file = fopen($fileName, "r");

    if ($file === false) {
        echo "
        <script>
            alert('File CSV gagal dibuka.');
            window.location='../pages/manajemen_data.php';
        </script>
        ";
        exit;
    }

    $baris   = 0;
    $berhasil = 0;
    $gagal   = 0;

    while (($data = fgetcsv($file, 10000, ",")) !== FALSE) {

        if ($baris == 0) {
            $baris++;
            continue;
        }

        if (count($data) < 16) {
            $gagal++;
            $baris++;
            continue;
        }

        $nama_mahasiswa     = mysqli_real_escape_string($conn, $data[2]);
        $nim                = mysqli_real_escape_string($conn, $data[3]);
        $dosen_pa           = mysqli_real_escape_string($conn, $data[4]);
        $semester           = mysqli_real_escape_string($conn, $data[5]);
        $sks_diambil        = mysqli_real_escape_string($conn, $data[6]);
        $ipk                = mysqli_real_escape_string($conn, $data[7]);
        $sks_lulus          = mysqli_real_escape_string($conn, $data[8]);
        $skor_toefl         = mysqli_real_escape_string($conn, $data[9]);
        $jml_mengulang      = mysqli_real_escape_string($conn, $data[10]);
        $sisa_masa_studi    = mysqli_real_escape_string($conn, $data[12]);
        $jalur_masuk        = mysqli_real_escape_string($conn, $data[13]);
        $absensi            = mysqli_real_escape_string($conn, $data[14]);
        $sks_nilai_kurang_c = mysqli_real_escape_string($conn, $data[15]);

        if ($nim == '') {
            $baris++;
            continue;
        }

        $angkatan = substr($nim, 0, 4);

        /* CEK DOSEN PA */
        $cekDpa = mysqli_query($conn, "
            SELECT id_user
            FROM user
            WHERE nama_lengkap = '$dosen_pa'
            AND role = 'dpa'
            LIMIT 1
        ");

        $idUserDpa = "NULL";

        if ($cekDpa && mysqli_num_rows($cekDpa) > 0) {
            $rowDpa    = mysqli_fetch_assoc($cekDpa);
            $idUserDpa = $rowDpa['id_user'];
        }

        /* 1. INSERT atau UPDATE tabel mahasiswa */
        mysqli_query($conn, "
            INSERT INTO mahasiswa (
                nim,
                id_user,
                nama,
                angkatan
            ) VALUES (
                '$nim',
                $idUserDpa,
                '$nama_mahasiswa',
                '$angkatan'
            )
            ON DUPLICATE KEY UPDATE
                id_user  = VALUES(id_user),
                nama     = VALUES(nama),
                angkatan = VALUES(angkatan)
        ");

        /* 2. INSERT atau UPDATE tabel data_akademik (hanya simpan data terbaru) */
        $cek = mysqli_query($conn, "
            SELECT id_data FROM data_akademik
            WHERE nim = '$nim'
        ");

        $idData = null;

        if (mysqli_num_rows($cek) > 0) {

            $rowAkademik = mysqli_fetch_assoc($cek);
            $idData      = $rowAkademik['id_data'];

            $query = mysqli_query($conn, "
                UPDATE data_akademik SET
                    nama_mahasiswa      = '$nama_mahasiswa',
                    dosen_pa            = '$dosen_pa',
                    semester            = '$semester',
                    ipk                 = '$ipk',
                    skor_toefl          = '$skor_toefl',
                    jml_mengulang       = '$jml_mengulang',
                    sks_lulus           = '$sks_lulus',
                    sisa_masa_studi     = '$sisa_masa_studi',
                    jalur_masuk         = '$jalur_masuk',
                    absensi             = '$absensi',
                    sks_diambil         = '$sks_diambil',
                    sks_nilai_kurang_c  = '$sks_nilai_kurang_c'
                WHERE nim = '$nim'
            ");

        } else {

            $query = mysqli_query($conn, "
                INSERT INTO data_akademik (
                    nim,
                    nama_mahasiswa,
                    dosen_pa,
                    semester,
                    ipk,
                    skor_toefl,
                    jml_mengulang,
                    sks_lulus,
                    sisa_masa_studi,
                    jalur_masuk,
                    absensi,
                    sks_diambil,
                    sks_nilai_kurang_c
                ) VALUES (
                    '$nim',
                    '$nama_mahasiswa',
                    '$dosen_pa',
                    '$semester',
                    '$ipk',
                    '$skor_toefl',
                    '$jml_mengulang',
                    '$sks_lulus',
                    '$sisa_masa_studi',
                    '$jalur_masuk',
                    '$absensi',
                    '$sks_diambil',
                    '$sks_nilai_kurang_c'
                )
            ");

            /* Ambil id_data yang baru di-INSERT */
            $idData = mysqli_insert_id($conn);
        }

        /* 3. Simpan histori ke riwayat_akademik (junction table) */
        if ($query && $idData) {
            mysqli_query($conn, "
                INSERT INTO riwayat_akademik (
                    nim,
                    id_data,
                    tanggal_upload
                ) VALUES (
                    '$nim',
                    '$idData',
                    NOW()
                )
            ");
        }

        if ($query) {
            $berhasil++;
        } else {
            $gagal++;
        }

        $baris++;
    }

    if ($file) {
        fclose($file);
    }

    $idAdmin = $_SESSION['id_user'];

    mysqli_query($conn, "
        INSERT INTO log_aktivitas (
            aksi,
            tanggal,
            id_user
        ) VALUES (
            'Import data akademik mahasiswa',
            NOW(),
            '$idAdmin'
        )
    ");

    echo "
    <script>
        alert('Import selesai! Berhasil: $berhasil | Gagal: $gagal');
        window.location='../pages/manajemen_data.php';
    </script>
    ";
}
?>