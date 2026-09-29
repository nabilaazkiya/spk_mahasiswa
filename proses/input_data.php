<?php
// Import data akademik mahasiswa dari file CSV/XLSX yang diupload admin, lalu upsert ke tabel data_akademik.
session_start();
include "../config/database.php";

/* =============================================
   PROTEKSI AKSES
   ============================================= */
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

require '../includes/xlsx_reader.php';

/* =============================================
   PASTIKAN KOLOM sks_diambil BOLEH NULL
   ============================================= */
$cekKolomSks = mysqli_query($conn, "SHOW COLUMNS FROM data_akademik LIKE 'sks_diambil'");
$rowKolomSks = $cekKolomSks ? mysqli_fetch_assoc($cekKolomSks) : null;

if ($rowKolomSks && strtoupper($rowKolomSks['Null']) === 'NO') {
    mysqli_query(
        $conn,
        "ALTER TABLE data_akademik MODIFY sks_diambil INT(3) NULL DEFAULT NULL"
    );
}

/* =============================================
   FUNGSI BANTUAN
   ============================================= */

/**
 * Normalisasi nama header CSV:
 * rapikan spasi ganda dan lowercase.
 */
function normalisasiHeader($header)
{
    $header = preg_replace('/\s+/', ' ', (string) $header);
    return strtolower(trim($header));
}

/**
 * Amankan nilai numerik dari CSV.
 *
 * Jika nilai kosong, "-", atau bukan angka,
 * maka menggunakan nilai default.
 */
function parseNumerik($nilai, $default = 0)
{
    $nilai = trim((string) $nilai);

    if ($nilai === '' || $nilai === '-') {
        return $default;
    }

    if (!is_numeric($nilai)) {
        return $default;
    }

    return $nilai + 0;
}

/**
 * Validasi khusus IPK.
 *
 * IPK harus berada pada rentang 0 sampai 4.
 *
 * Mengembalikan:
 * - true  jika valid
 * - false jika tidak valid
 *
 * Nilai kosong atau "-" tetap diperlakukan seperti
 * perilaku lama, yaitu akan menggunakan default 0.
 */
function validasiIPK($nilai)
{
    $nilai = trim((string) $nilai);

    // Pertahankan perilaku lama untuk data kosong / "-"
    if ($nilai === '' || $nilai === '-') {
        return true;
    }

    // IPK harus berupa angka
    if (!is_numeric($nilai)) {
        return false;
    }

    $ipk = (float) $nilai;

    // IPK hanya boleh 0 sampai 4
    if ($ipk < 0 || $ipk > 4) {
        return false;
    }

    return true;
}

/**
 * Cegah CSV Injection.
 */
function amankanTeks($nilai)
{
    $nilai = trim((string) $nilai);

    if ($nilai !== '' && in_array($nilai[0], ['=', '+', '-', '@'], true)) {
        $nilai = "'" . $nilai;
    }

    return $nilai;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    /* =============================================
       VALIDASI FILE
       ============================================= */

    if (
        !isset($_FILES['file_import']) ||
        $_FILES['file_import']['error'] != 0
    ) {
        echo "
        <script>
            alert('File gagal diupload atau belum dipilih.');
            window.location='../pages/manajemen_data.php';
        </script>
        ";
        exit;
    }

    $namaFile = $_FILES['file_import']['name'];

    /* Pastikan kolom nama_file ada */
    $cekKolomNamaFile = mysqli_query(
        $conn,
        "SHOW COLUMNS FROM riwayat_akademik LIKE 'nama_file'"
    );

    if (
        $cekKolomNamaFile &&
        mysqli_num_rows($cekKolomNamaFile) == 0
    ) {
        mysqli_query(
            $conn,
            "ALTER TABLE riwayat_akademik ADD COLUMN nama_file VARCHAR(255) NULL"
        );
    }

    $ekstensi = strtolower(
        pathinfo($namaFile, PATHINFO_EXTENSION)
    );

    /* =============================================
       VALIDASI EKSTENSI
       ============================================= */

    if (!in_array($ekstensi, ['csv', 'xlsx'], true)) {
        echo "
        <script>
            alert('Format file harus .csv atau .xlsx. Format .xls (Excel lama) belum didukung, silakan simpan ulang sebagai .xlsx atau .csv.');
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

    /* =============================================
       WAKTU IMPORT
       ============================================= */

    $waktuImpor = date('Y-m-d H:i:s');

    /* =============================================
       BACA FILE CSV / XLSX
       ============================================= */

    if ($ekstensi === 'xlsx') {

        if (
            !class_exists('ZipArchive') ||
            !function_exists('simplexml_load_string')
        ) {
            echo "
            <script>
                alert('Server belum mendukung pembacaan file .xlsx (ekstensi PHP zip/simplexml tidak aktif). Silakan gunakan format .csv untuk sementara.');
                window.location='../pages/manajemen_data.php';
            </script>
            ";
            exit;
        }

        $rows = bacaXlsxKeArray($fileName);

        if ($rows === false) {
            echo "
            <script>
                alert('File .xlsx gagal dibaca atau rusak. Pastikan file benar-benar format Excel 2007+ (.xlsx), bukan .xls lama atau file yang di-rename.');
                window.location='../pages/manajemen_data.php';
            </script>
            ";
            exit;
        }

    } else {

        $rows = bacaCsvKeArray($fileName);

        if ($rows === false) {
            echo "
            <script>
                alert('File CSV gagal dibuka.');
                window.location='../pages/manajemen_data.php';
            </script>
            ";
            exit;
        }
    }

    if (empty($rows)) {
        echo "
        <script>
            alert('File tidak memiliki baris data sama sekali.');
            window.location='../pages/manajemen_data.php';
        </script>
        ";
        exit;
    }

    /* =============================================
       MAPPING HEADER
       ============================================= */

    $mappingHeader = [
        'nim'             => 'nim',
        'nama'            => 'nama_mahasiswa',
        'dpa'             => 'dosen_pa',
        'semester'        => 'semester',
        'sks'             => 'sks_diambil',
        'ip semester'     => 'ip_semester',
        'sks kumulatif'   => 'sks_lulus',
        'ip kumulatif'    => 'ipk',
        'jumlah ngulang'  => 'jml_mengulang',
        'sisa masa studi' => 'sisa_masa_studi',
        'sks kurang < b'  => 'sks_nilai_kurang_b',
        'jalur masuk'     => 'jalur_masuk',
        'toefl'           => 'skor_toefl',
        'absensi'         => 'absensi',
        'status'          => 'status_mentah',
    ];

    $headerRow = array_shift($rows);

    if ($headerRow === null) {
        echo "
        <script>
            alert('File tidak memiliki baris header.');
            window.location='../pages/manajemen_data.php';
        </script>
        ";
        exit;
    }

    /* Hilangkan BOM UTF-8 */
    $headerRow[0] = preg_replace(
        '/^\xEF\xBB\xBF/',
        '',
        (string) $headerRow[0]
    );

    $indexKolom = [];

    foreach ($headerRow as $idx => $namaKolom) {
        $indexKolom[
            normalisasiHeader($namaKolom)
        ] = $idx;
    }

    /* =============================================
       CEK HEADER YANG HILANG
       ============================================= */

    $kolomHilang = [];

    foreach ($mappingHeader as $headerCsv => $fieldDb) {

        // Status bersifat opsional
        if ($fieldDb === 'status_mentah') {
            continue;
        }

        if (!array_key_exists($headerCsv, $indexKolom)) {
            $kolomHilang[] = $headerCsv;
        }
    }

    if (!empty($kolomHilang)) {

        $daftarHilang = implode(
            ', ',
            $kolomHilang
        );

        echo "
        <script>
            alert('Header file tidak sesuai format yang diharapkan. Kolom berikut tidak ditemukan: $daftarHilang');
            window.location='../pages/manajemen_data.php';
        </script>
        ";
        exit;
    }

    /* =============================================
       VARIABEL HASIL IMPORT
       ============================================= */

    $berhasil = 0;
    $gagal    = 0;

    $dosenTidakDitemukan = [];

    $kolomDidefaultKosong = [];

    /*
     * Menyimpan daftar NIM yang IPK-nya tidak valid.
     *
     * Contoh:
     * F1D022001 => 9.99
     * F1D022002 => 4.50
     */
    $ipkTidakValid = [];

    /**
     * Bungkus parseNumerik()
     */
    $parseNumerikTerlacak = function (
        $nilaiMentah,
        $default,
        $nim,
        $labelKolom
    ) use (&$kolomDidefaultKosong) {

        $hasil = parseNumerik(
            $nilaiMentah,
            $default
        );

        $mentahTrim = trim(
            (string) $nilaiMentah
        );

        if (
            (
                $mentahTrim === '' ||
                $mentahTrim === '-' ||
                !is_numeric($mentahTrim)
            ) &&
            $default !== null
        ) {
            $kolomDidefaultKosong[$nim][] =
                $labelKolom;
        }

        return $hasil;
    };

    /* =============================================
       PROSES SETIAP BARIS
       ============================================= */

    foreach ($rows as $data) {

        /* Lewati baris kosong */
        if (
            count($data) === 1 &&
            trim((string) $data[0]) === ''
        ) {
            continue;
        }

        $baris = [];

        foreach ($mappingHeader as $headerCsv => $fieldDb) {

            if (!array_key_exists(
                $headerCsv,
                $indexKolom
            )) {
                $baris[$fieldDb] = null;
                continue;
            }

            $idx = $indexKolom[$headerCsv];

            $baris[$fieldDb] =
                $data[$idx] ?? null;
        }

        /* =============================================
           VALIDASI NIM
           ============================================= */

        $nim = trim(
            (string) $baris['nim']
        );

        if ($nim === '') {
            $gagal++;
            continue;
        }

        /* =============================================
           VALIDASI IPK
           ============================================= */

        $ipkMentah = trim(
            (string) $baris['ipk']
        );

        /*
         * IPK harus berada pada rentang 0 sampai 4.
         *
         * Jika IPK = 9.99:
         * - baris tidak diproses
         * - tidak INSERT
         * - tidak UPDATE
         * - dihitung sebagai gagal
         */
        if (!validasiIPK($ipkMentah)) {

            $gagal++;

            $ipkTidakValid[$nim] =
                $ipkMentah;

            continue;
        }

        /* =============================================
           DATA TEKS
           ============================================= */

        $nama_mahasiswa = amankanTeks(
            $baris['nama_mahasiswa']
        );

        $dosen_pa = amankanTeks(
            $baris['dosen_pa']
        );

        /* =============================================
           SEMESTER
           ============================================= */

        $semesterMentahDariFile =
            $parseNumerikTerlacak(
                $baris['semester'],
                0,
                $nim,
                'Semester'
            );

        /*
         * Nilai semester dari file tidak digunakan.
         * Semester dihitung dari Sisa Masa Studi.
         */
        unset($semesterMentahDariFile);

        /* =============================================
           STATUS SIA
           ============================================= */

        $statusMentah = strtolower(
            trim(
                (string) (
                    $baris['status_mentah'] ?? ''
                )
            )
        );

        if (
            $statusMentah === '' ||
            $statusMentah === 'aktif'
        ) {

            $status_sia = 'aktif';

        } elseif (
            $statusMentah === 'do' ||
            strpos(
                $statusMentah,
                'drop out'
            ) !== false
        ) {

            $status_sia = 'do';

        } else {

            $status_sia = 'tidak_aktif';
        }

        /* =============================================
           PARSING DATA NUMERIK
           ============================================= */

        $sks_diambil =
            $parseNumerikTerlacak(
                $baris['sks_diambil'],
                0,
                $nim,
                'SKS Diambil'
            );

        $ip_semester =
            parseNumerik(
                $baris['ip_semester'],
                null
            );

        /*
         * Karena sudah divalidasi sebelumnya,
         * nilai IPK di sini aman berada di 0–4.
         */
        $ipk =
            parseNumerik(
                $baris['ipk'],
                0
            );

        $sks_lulus =
            $parseNumerikTerlacak(
                $baris['sks_lulus'],
                0,
                $nim,
                'SKS Lulus'
            );

        $skor_toefl =
            $parseNumerikTerlacak(
                $baris['skor_toefl'],
                0,
                $nim,
                'Skor TOEFL'
            );

        $jml_mengulang =
            $parseNumerikTerlacak(
                $baris['jml_mengulang'],
                0,
                $nim,
                'Jumlah Mengulang'
            );

        $sisa_masa_studi =
            $parseNumerikTerlacak(
                $baris['sisa_masa_studi'],
                0,
                $nim,
                'Sisa Masa Studi'
            );

        $jalur_masuk =
            amankanTeks(
                $baris['jalur_masuk']
            );

        $absensi =
            $parseNumerikTerlacak(
                $baris['absensi'],
                0,
                $nim,
                'Absensi'
            );

        $sks_nilai_kurang_b =
            $parseNumerikTerlacak(
                $baris['sks_nilai_kurang_b'],
                0,
                $nim,
                'SKS Nilai Kurang B'
            );

        /* =============================================
           HITUNG SEMESTER
           ============================================= */

        $semester =
            14 - $sisa_masa_studi;

        if ($semester < 1) {
            $gagal++;
            continue;
        }

        /* =============================================
           HITUNG ANGKATAN DARI NIM
           ============================================= */

        $kodeAngkatan =
            substr($nim, 3, 3);

        $angkatan =
            ctype_digit($kodeAngkatan)
                ? (string) (
                    2000 +
                    (int) $kodeAngkatan
                )
                : null;

        if ($angkatan === null) {
            $gagal++;
            continue;
        }

        /* =============================================
           CEK DOSEN PA
           ============================================= */

        $idUserDpa = null;

        $stmtDpa = mysqli_prepare(
            $conn,
            "
            SELECT id_user
            FROM user
            WHERE nama_lengkap = ?
              AND role = 'dpa'
            LIMIT 1
            "
        );

        mysqli_stmt_bind_param(
            $stmtDpa,
            "s",
            $dosen_pa
        );

        mysqli_stmt_execute(
            $stmtDpa
        );

        $resultDpa =
            mysqli_stmt_get_result(
                $stmtDpa
            );

        if (
            $resultDpa &&
            mysqli_num_rows($resultDpa) > 0
        ) {

            $rowDpa =
                mysqli_fetch_assoc(
                    $resultDpa
                );

            $idUserDpa =
                $rowDpa['id_user'];

        } elseif ($dosen_pa !== '') {

            $dosenTidakDitemukan[
                $dosen_pa
            ] = true;
        }

        mysqli_stmt_close(
            $stmtDpa
        );

        /* =============================================
           INSERT / UPDATE MAHASISWA
           ============================================= */

        $stmtMhs = mysqli_prepare(
            $conn,
            "
            INSERT INTO mahasiswa
                (nim, id_user, nama, angkatan)
            VALUES
                (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                id_user  = VALUES(id_user),
                nama     = VALUES(nama),
                angkatan = VALUES(angkatan)
            "
        );

        mysqli_stmt_bind_param(
            $stmtMhs,
            "siss",
            $nim,
            $idUserDpa,
            $nama_mahasiswa,
            $angkatan
        );

        mysqli_stmt_execute(
            $stmtMhs
        );

        mysqli_stmt_close(
            $stmtMhs
        );

        /* =============================================
           UPDATE STATUS USER MAHASISWA
           ============================================= */

        $statusSiaUser =
            ($status_sia === 'aktif')
                ? 'aktif'
                : 'nonaktif';

        $stmtUser = mysqli_prepare(
            $conn,
            "
            UPDATE user
            SET status_sia = ?
            WHERE username = ?
              AND role = 'mahasiswa'
            "
        );

        mysqli_stmt_bind_param(
            $stmtUser,
            "ss",
            $statusSiaUser,
            $nim
        );

        mysqli_stmt_execute(
            $stmtUser
        );

        mysqli_stmt_close(
            $stmtUser
        );

        /* =============================================
           CEK DATA AKADEMIK
           Berdasarkan NIM + Semester
           ============================================= */

        $stmtCek = mysqli_prepare(
            $conn,
            "
            SELECT id_data
            FROM data_akademik
            WHERE nim = ?
              AND semester = ?
            "
        );

        mysqli_stmt_bind_param(
            $stmtCek,
            "sd",
            $nim,
            $semester
        );

        mysqli_stmt_execute(
            $stmtCek
        );

        $resultCek =
            mysqli_stmt_get_result(
                $stmtCek
            );

        $idData = null;
        $okAkademik = false;

        /* =============================================
           UPDATE DATA AKADEMIK
           ============================================= */

        if (
            $resultCek &&
            mysqli_num_rows($resultCek) > 0
        ) {

            $rowAkademik =
                mysqli_fetch_assoc(
                    $resultCek
                );

            $idData =
                $rowAkademik['id_data'];

            $stmtUpdate = mysqli_prepare(
                $conn,
                "
                UPDATE data_akademik SET
                    nama_mahasiswa      = ?,
                    dosen_pa            = ?,
                    ip_semester         = ?,
                    ipk                 = ?,
                    skor_toefl          = ?,
                    jml_mengulang       = ?,
                    sks_lulus           = ?,
                    sisa_masa_studi     = ?,
                    jalur_masuk         = ?,
                    absensi             = ?,
                    sks_diambil         = ?,
                    sks_nilai_kurang_b  = ?,
                    status_sia          = ?
                WHERE nim = ?
                  AND semester = ?
                "
            );

            mysqli_stmt_bind_param(
                $stmtUpdate,
                "ssddddddsdddssd",
                $nama_mahasiswa,
                $dosen_pa,
                $ip_semester,
                $ipk,
                $skor_toefl,
                $jml_mengulang,
                $sks_lulus,
                $sisa_masa_studi,
                $jalur_masuk,
                $absensi,
                $sks_diambil,
                $sks_nilai_kurang_b,
                $status_sia,
                $nim,
                $semester
            );

            $okAkademik =
                mysqli_stmt_execute(
                    $stmtUpdate
                );

            mysqli_stmt_close(
                $stmtUpdate
            );

        } else {

            /* =============================================
               INSERT DATA AKADEMIK BARU
               ============================================= */

            $stmtInsert = mysqli_prepare(
                $conn,
                "
                INSERT INTO data_akademik (
                    nim,
                    nama_mahasiswa,
                    dosen_pa,
                    semester,
                    ip_semester,
                    ipk,
                    skor_toefl,
                    jml_mengulang,
                    sks_lulus,
                    sisa_masa_studi,
                    jalur_masuk,
                    absensi,
                    sks_diambil,
                    sks_nilai_kurang_b,
                    status_sia
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                "
            );

            mysqli_stmt_bind_param(
                $stmtInsert,
                "sssdddddddsddds",
                $nim,
                $nama_mahasiswa,
                $dosen_pa,
                $semester,
                $ip_semester,
                $ipk,
                $skor_toefl,
                $jml_mengulang,
                $sks_lulus,
                $sisa_masa_studi,
                $jalur_masuk,
                $absensi,
                $sks_diambil,
                $sks_nilai_kurang_b,
                $status_sia
            );

            $okAkademik =
                mysqli_stmt_execute(
                    $stmtInsert
                );

            if ($okAkademik) {
                $idData =
                    mysqli_insert_id(
                        $conn
                    );
            }

            mysqli_stmt_close(
                $stmtInsert
            );
        }

        mysqli_stmt_close(
            $stmtCek
        );

        /* =============================================
           SIMPAN HISTORI AKADEMIK
           ============================================= */

        if (
            $okAkademik &&
            $idData
        ) {

            $stmtRiwayat =
                mysqli_prepare(
                    $conn,
                    "
                    INSERT INTO riwayat_akademik
                        (nim, id_data, tanggal_upload, nama_file)
                    VALUES
                        (?, ?, ?, ?)
                    "
                );

            $namaFileSimpan =
                mb_substr(
                    basename(
                        (string) $namaFile
                    ),
                    0,
                    255
                );

            mysqli_stmt_bind_param(
                $stmtRiwayat,
                "siss",
                $nim,
                $idData,
                $waktuImpor,
                $namaFileSimpan
            );

            mysqli_stmt_execute(
                $stmtRiwayat
            );

            mysqli_stmt_close(
                $stmtRiwayat
            );
        }

        /* =============================================
           HITUNG HASIL IMPORT
           ============================================= */

        if ($okAkademik) {
            $berhasil++;
        } else {
            $gagal++;
        }
    }

    /* =============================================
       LOG AKTIVITAS
       ============================================= */

    $idAdmin =
        $_SESSION['id_user'] ?? null;

    $stmtLog = mysqli_prepare(
        $conn,
        "
        INSERT INTO log_aktivitas
            (aksi, tanggal, id_user)
        VALUES
            ('Import data akademik mahasiswa', NOW(), ?)
        "
    );

    mysqli_stmt_bind_param(
        $stmtLog,
        "i",
        $idAdmin
    );

    mysqli_stmt_execute(
        $stmtLog
    );

    mysqli_stmt_close(
        $stmtLog
    );

    /* =============================================
       JALANKAN OTOMATIS:
       TOPSIS -> SAW -> SPEARMAN
       ============================================= */

    define(
        'SPK_CHAIN',
        true
    );

    require '../proses/topsis_proses.php';
    require '../proses/saw_proses.php';
    require '../proses/spearman_proses.php';

    /* =============================================
       PESAN HASIL IMPORT
       ============================================= */

    $pesanAkhir =
        "Import selesai! Berhasil: $berhasil | Gagal: $gagal.";

    /* =============================================
       INFORMASI PERUBAHAN TOPSIS / SAW
       ============================================= */

    if (
        isset(
            $adaPerubahanDibandingPeriodeTerakhir
        )
    ) {

        if (
            $adaPerubahanDibandingPeriodeTerakhir
        ) {

            $pesanAkhir .=
                " Skor TOPSIS/SAW berubah dibanding periode sebelumnya - periode evaluasi baru tercatat untuk grafik tren.";

        } else {

            $pesanAkhir .=
                " Skor TOPSIS identik dengan periode terakhir (kemungkinan file yang sama diupload ulang) - tidak ada periode/titik tren baru yang dibuat.";
        }
    }

    /* =============================================
       LAPORAN DOSEN PA TIDAK DITEMUKAN
       ============================================= */

    if (!empty($dosenTidakDitemukan)) {

        $daftarDosen =
            implode(
                ', ',
                array_keys(
                    $dosenTidakDitemukan
                )
            );

        $pesanAkhir .=
            "\n\nPERHATIAN: " .
            count($dosenTidakDitemukan) .
            " nama Dosen PA di file TIDAK ditemukan akunnya (role dpa) di sistem, sehingga mahasiswa bimbingannya TIDAK akan muncul di dashboard DPA terkait sampai akun dibuat dengan nama yang PERSIS SAMA:\n" .
            $daftarDosen;
    }

    /* =============================================
       LAPORAN KOLOM DEFAULT
       ============================================= */

    if (!empty($kolomDidefaultKosong)) {

        $jumlahNimBermasalah =
            count(
                $kolomDidefaultKosong
            );

        $pesanAkhir .=
            "\n\nPERHATIAN: $jumlahNimBermasalah baris punya kolom kosong/tidak valid di file sumber, nilainya otomatis diisi 0 (bukan berarti datanya memang 0):";

        $contoh = 0;

        foreach (
            $kolomDidefaultKosong
            as $nimBermasalah => $daftarKolom
        ) {

            if ($contoh >= 5) {

                $pesanAkhir .=
                    "\n... dan " .
                    ($jumlahNimBermasalah - 5) .
                    " baris lainnya.";

                break;
            }

            $pesanAkhir .=
                "\n- $nimBermasalah: " .
                implode(
                    ', ',
                    array_unique(
                        $daftarKolom
                    )
                );

            $contoh++;
        }
    }

    /* =============================================
       LAPORAN IPK TIDAK VALID
       ============================================= */

    if (!empty($ipkTidakValid)) {

        $jumlahIPK =
            count($ipkTidakValid);

        $pesanAkhir .=
            "\n\nPERHATIAN: $jumlahIPK baris tidak diproses karena nilai IPK berada di luar rentang 0,00–4,00 atau bukan angka yang valid.";

        $contohIPK = 0;

        foreach (
            $ipkTidakValid
            as $nimInvalid => $nilaiIPK
        ) {

            if ($contohIPK >= 5) {

                $pesanAkhir .=
                    "\n... dan " .
                    ($jumlahIPK - 5) .
                    " baris lainnya.";

                break;
            }

            $pesanAkhir .=
                "\n- NIM $nimInvalid: IPK = $nilaiIPK";

            $contohIPK++;
        }
    }

    /* =============================================
       TAMPILKAN HASIL
       ============================================= */

    $pesanAkhirJs =
        json_encode(
            $pesanAkhir
        );

    echo "
    <script>
        alert($pesanAkhirJs);
        window.location='../pages/manajemen_data.php';
    </script>
    ";
}
?>