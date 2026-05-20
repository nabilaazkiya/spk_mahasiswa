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

    $baris = 0;
    $berhasil = 0;
    $gagal = 0;

    while (($data = fgetcsv($file, 10000, ",")) !== FALSE) {

        // Skip header
        if ($baris == 0) {
            $baris++;
            continue;
        }

        // Skip baris kosong
        if (count($data) < 16) {
            $gagal++;
            $baris++;
            continue;
        }

        $semester            = mysqli_real_escape_string($conn, $data[1]);
        $nama_mahasiswa      = mysqli_real_escape_string($conn, $data[2]);
        $nim                 = mysqli_real_escape_string($conn, $data[3]);
        $dosen_pa            = mysqli_real_escape_string($conn, $data[4]);

        $sks_diambil         = mysqli_real_escape_string($conn, $data[6]);
        $ipk                 = mysqli_real_escape_string($conn, $data[7]);
        $sks_lulus           = mysqli_real_escape_string($conn, $data[8]);
        $skor_toefl          = mysqli_real_escape_string($conn, $data[9]);
        $jml_mengulang       = mysqli_real_escape_string($conn, $data[10]);
        $sisa_masa_studi     = mysqli_real_escape_string($conn, $data[12]);
        $jalur_masuk         = mysqli_real_escape_string($conn, $data[13]);
        $absensi             = mysqli_real_escape_string($conn, $data[14]);
        $sks_nilai_kurang_c  = mysqli_real_escape_string($conn, $data[15]);

        if ($nim == '') {
            $baris++;
            continue;
        }

        $cek = mysqli_query($conn, "SELECT * FROM data_akademik WHERE nim='$nim'");

        if (mysqli_num_rows($cek) > 0) {

            $query = mysqli_query($conn, "
                UPDATE data_akademik SET
                    nama_mahasiswa = '$nama_mahasiswa',
                    dosen_pa = '$dosen_pa',
                    semester = '$semester',
                    ipk = '$ipk',
                    skor_toefl = '$skor_toefl',
                    jml_mengulang = '$jml_mengulang',
                    sks_lulus = '$sks_lulus',
                    sisa_masa_studi = '$sisa_masa_studi',
                    jalur_masuk = '$jalur_masuk',
                    absensi = '$absensi',
                    sks_diambil = '$sks_diambil',
                    sks_nilai_kurang_c = '$sks_nilai_kurang_c'
                WHERE nim='$nim'
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

    echo "
    <script>
        alert('Import selesai! Berhasil: $berhasil | Gagal: $gagal');
        window.location='../pages/manajemen_data.php';
    </script>
    ";
}
?>