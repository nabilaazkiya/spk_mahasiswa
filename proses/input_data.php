<?php
// Import data akademik mahasiswa dari file CSV/XLSX yang diupload admin, lalu upsert ke tabel data_akademik.
session_start();
include "../config/database.php";

/* =============================================
   PROTEKSI AKSES
   (Sebelumnya file ini bisa diakses tanpa login
   sama sekali. Ditambahkan agar hanya admin yang
   bisa melakukan import data akademik.)
   ============================================= */
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

require '../includes/xlsx_reader.php';

/* status_sia_mahasiswa DIHAPUS (permintaan user): sekarang
   status_sia adalah satu-satunya sumber kebenaran untuk status
   mahasiswa (aktif/cuti/do/-), termasuk untuk keperluan yang dulu
   dipegang status_sia_mahasiswa (aktif/tidak_aktif). Tidak ada lagi
   auto-migration untuk kolom ini di sini. */

/* =============================================
   PASTIKAN KOLOM sks_diambil BOLEH NULL
   (dibutuhkan untuk mendukung semester "Cuti" -
   lihat penjelasan di bagian parsing SKS di bawah.
   Sebelumnya kolom ini int(3) NOT NULL, sehingga
   semester cuti terpaksa disimpan sebagai SKS = 0,
   padahal 0 dan "tidak ada data karena cuti" adalah
   dua hal yang berbeda dan harus diperlakukan beda
   saat perhitungan TOPSIS/SAW. Aman dijalankan
   berkali-kali: hanya MODIFY kalau kolom saat ini
   masih NOT NULL.) */
$cekKolomSks = mysqli_query($conn, "SHOW COLUMNS FROM data_akademik LIKE 'sks_diambil'");
$rowKolomSks = $cekKolomSks ? mysqli_fetch_assoc($cekKolomSks) : null;
if ($rowKolomSks && strtoupper($rowKolomSks['Null']) === 'NO') {
    mysqli_query($conn, "ALTER TABLE data_akademik MODIFY sks_diambil INT(3) NULL DEFAULT NULL");
}

/* Fitur "daftar & hapus dokumen terupload" SEKARANG memakai
   waktu upload (tanggal_upload di riwayat_akademik, yang sudah
   ada) sebagai pengelompok batch - TIDAK ada tabel baru. Lihat
   $waktuImpor di bawah dan pages/manajemen_data.php +
   proses/hapus_batch_upload.php. */

/* =============================================
   FUNGSI BANTUAN
   ============================================= */

/**
 * Normalisasi nama header CSV: rapikan spasi ganda
 * dan lowercase, supaya pencocokan header tidak
 * sensitif terhadap spasi ekstra dari hasil export Excel.
 */
function normalisasiHeader($header)
{
    $header = preg_replace('/\s+/', ' ', (string) $header);
    return strtolower(trim($header));
}

/**
 * Amankan nilai numerik dari CSV.
 * Mengembalikan $default jika nilai bukan angka valid
 * (misal "-", kosong, atau teks lain), supaya tidak
 * menyimpan string tidak valid ke kolom numerik/decimal.
 *
 * $default = 0     -> untuk kolom NOT NULL di data_akademik
 *                      (semester, ipk, skor_toefl, dst) agar
 *                      insert tidak gagal saat data kosong.
 * $default = null  -> untuk kolom yang memang nullable
 *                      (saat ini hanya ip_semester).
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
 * Cegah CSV Injection: jika nilai teks diawali karakter
 * yang bisa diinterpretasikan sebagai formula oleh
 * Excel/Sheets (=, +, -, @), beri prefix aman agar tidak
 * tereksekusi sebagai formula saat data ini nanti
 * diekspor ulang menjadi CSV/Excel.
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

    if (!isset($_FILES['file_import']) || $_FILES['file_import']['error'] != 0) {
        echo "
        <script>
            alert('File gagal diupload atau belum dipilih.');
            window.location='../pages/manajemen_data.php';
        </script>
        ";
        exit;
    }

    /* =============================================
       VALIDASI EKSTENSI FILE
       (Sebelumnya file apapun diterima tanpa cek
       ekstensi, sehingga file .xlsx yang di-rename
       menjadi .csv akan lolos dan menghasilkan
       data kacau saat dibaca fgetcsv(). Sekarang
       .csv dan .xlsx didukung resmi, masing-masing
       dengan pembaca yang sesuai.)
       ============================================= */
    $namaFile = $_FILES['file_import']['name'];
    $ekstensi = strtolower(pathinfo($namaFile, PATHINFO_EXTENSION));

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

    /* FITUR BARU (tanpa tabel baru): satu waktu upload yang SAMA
       dipakai untuk SEMUA baris riwayat_akademik dalam satu kali
       proses import ini - dipakai sebagai "batch id" alami supaya
       admin bisa melihat & menghapus data akademik per batch upload
       (lihat pages/manajemen_data.php & proses/hapus_batch_upload.php),
       tanpa perlu tabel/kolom tambahan. */
    $waktuImpor = date('Y-m-d H:i:s');

    /* =============================================
       BACA FILE MENJADI ARRAY BARIS
       (baik CSV maupun XLSX diproses menjadi
       struktur array yang sama, sehingga logika
       mapping header & import di bawah ini
       tidak perlu tahu format asal file)
       ============================================= */
    if ($ekstensi === 'xlsx') {
        if (!class_exists('ZipArchive') || !function_exists('simplexml_load_string')) {
            echo "
            <script>
                alert('Server belum mendukung pembacaan file .xlsx (ekstensi PHP zip/simplexml tidak aktif). Silakan hubungi admin server, atau gunakan format .csv untuk sementara.');
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
       MAPPING HEADER CSV -> FIELD DATABASE
       Dicocokkan berdasarkan NAMA header (bukan posisi
       kolom seperti sebelumnya), supaya tahan terhadap
       perubahan urutan kolom pada file export SIA.

       Referensi header asli (dari sample export SIA):
       NIM, Nama, DPA, Semester, SKS, IP Semester,
       SKS Kumulatif, IP Kumulatif, Jumlah Ngulang,
       Sisa Masa Studi, SKS kurang < B, Jalur Masuk,
       Toefl, Absensi

       CATATAN: kolom "IP Semester" sudah dipetakan.
       Sesuai skema data_akademik yang diberikan,
       kolom ip_semester memang sudah ada (nullable),
       dan kriteria C2 di tabel kriteria memang benar
       mengacu ke 'ip_semester'. Jadi tidak ada
       perubahan skema yang diperlukan.
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
        /* Kolom baru (hasil UAT Kaprodi) - status mahasiswa
           tidak aktif di SIA per periode akademik. Diketik
           bebas di file sumber sebagai "Aktif" / "Tidak Aktif"
           (case-insensitive), dinormalisasi di bawah.

           PERBAIKAN BUG: sebelumnya key mapping ini 'status sia',
           padahal header asli pada file export SIA adalah 'Status'
           saja (dikonfirmasi user). Karena 'status sia' tidak
           pernah cocok dengan header manapun, kolom ini SELALU
           dianggap tidak ada di file (kolom opsional) dan semua
           mahasiswa otomatis tercatat 'aktif', termasuk yang
           sebenarnya berstatus 'Tidak Aktif' di SIA. */
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

    /* Hilangkan BOM UTF-8 yang sering menempel di kolom pertama
       hasil export Excel/CSV (terlihat pada sample file). */
    $headerRow[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $headerRow[0]);

    $indexKolom = [];

    foreach ($headerRow as $idx => $namaKolom) {
        $indexKolom[normalisasiHeader($namaKolom)] = $idx;
    }

    $kolomHilang = [];

    foreach ($mappingHeader as $headerCsv => $fieldDb) {
        /* Kolom status_mentah (Status SIA mentah) dibuat OPSIONAL -
           supaya file Excel versi lama (sebelum kolom ini ada) tetap
           bisa diimport, tidak langsung ditolak. Kalau kolom
           ini tidak ada di file, semua mahasiswa dianggap
           'aktif' secara default (lihat normalisasi di bawah). */
        if ($fieldDb === 'status_mentah') {
            continue;
        }
        if (!array_key_exists($headerCsv, $indexKolom)) {
            $kolomHilang[] = $headerCsv;
        }
    }

    if (!empty($kolomHilang)) {
        $daftarHilang = implode(', ', $kolomHilang);
        echo "
        <script>
            alert('Header file tidak sesuai format yang diharapkan. Kolom berikut tidak ditemukan: $daftarHilang');
            window.location='../pages/manajemen_data.php';
        </script>
        ";
        exit;
    }

    $berhasil = 0;
    $gagal    = 0;

    /* Kumpulkan nama dosen PA dari file yang TIDAK ditemukan
       akun user-nya (role dpa) - supaya admin tahu persis
       kenapa nanti data mahasiswa tertentu tidak muncul di
       dashboard DPA yang bersangkutan (id_user jadi NULL). */
    $dosenTidakDitemukan = [];

    /* PERBAIKAN: sebelumnya nilai kosong/tidak valid pada kolom
       NOT NULL (semester, ipk, absensi, dst) di-default ke 0
       secara DIAM-DIAM. Ini membuat admin tidak bisa membedakan
       "memang 0" vs "datanya kosong di file sumber". Sekarang
       setiap kali default 0 dipakai, dicatat di sini supaya bisa
       dilaporkan setelah import selesai. */
    $kolomDidefaultKosong = []; // nim => [nama kolom, ...]

    /**
     * Bungkus parseNumerik() sekaligus mencatat NIM+kolom mana
     * saja yang nilainya kosong/tidak valid di file sumber dan
     * karena itu di-default ke $default.
     */
    $parseNumerikTerlacak = function ($nilaiMentah, $default, $nim, $labelKolom) use (&$kolomDidefaultKosong) {
        $hasil = parseNumerik($nilaiMentah, $default);
        $mentahTrim = trim((string) $nilaiMentah);

        if (($mentahTrim === '' || $mentahTrim === '-' || !is_numeric($mentahTrim)) && $default !== null) {
            $kolomDidefaultKosong[$nim][] = $labelKolom;
        }

        return $hasil;
    };

    foreach ($rows as $data) {

        /* Lewati baris kosong (baris terakhir file kadang kosong) */
        if (count($data) === 1 && trim((string) $data[0]) === '') {
            continue;
        }

        $baris = [];

        foreach ($mappingHeader as $headerCsv => $fieldDb) {
            if (!array_key_exists($headerCsv, $indexKolom)) {
                /* Kolom opsional (status_mentah) tidak ada
                   di file ini - biarkan null, dinormalisasi jadi
                   'aktif' di bawah. */
                $baris[$fieldDb] = null;
                continue;
            }
            $idx = $indexKolom[$headerCsv];
            $baris[$fieldDb] = $data[$idx] ?? null;
        }

        $nim = trim((string) $baris['nim']);

        if ($nim === '') {
            $gagal++;
            continue;
        }

        $nama_mahasiswa     = amankanTeks($baris['nama_mahasiswa']);
        $dosen_pa           = amankanTeks($baris['dosen_pa']);

        /* PERBAIKAN (permintaan: hilangkan Semester sebagai kriteria
           TOPSIS/SAW): kolom "Semester" dari file Excel TETAP dibaca
           supaya validasi header tidak berubah, tapi NILAINYA TIDAK
           DIPAKAI lagi. Semester sekarang selalu dihitung otomatis
           dari Sisa Masa Studi dengan rumus yang ditetapkan:

               Semester = 14 - Sisa Masa Studi

           Nilai ini tetap disimpan di kolom `semester` (dipakai untuk
           tampilan, grafik tren, dan bantuan rasio SKS ideal di
           ambilNilaiTopsis/ambilNilaiSaw) - hanya SUMBER datanya yang
           berubah, bukan strukturnya, dan kolom ini TIDAK lagi
           didaftarkan sebagai kriteria TOPSIS/SAW (lihat perubahan di
           assets/data_delphi.csv). */
        $semesterMentahDariFile = $parseNumerikTerlacak($baris['semester'], 0, $nim, 'Semester');
        // sengaja tidak dipakai - lihat penjelasan di atas
        unset($semesterMentahDariFile);

        /* PERBAIKAN (permintaan user): status_sia_mahasiswa DIHAPUS,
           dan nilai untuk semester cuti/tidak aktif DISEDERHANAKAN
           jadi 'tidak_aktif' (bukan 'cuti' lagi). status_sia dibaca
           langsung dari kolom "Status" pada file Excel:
             - mengandung "do"                 -> status_sia = 'do'
             - kosong atau "aktif"              -> status_sia = 'aktif'
             - selain itu (cuti, tidak aktif,
               nonaktif, dll)                   -> status_sia = 'tidak_aktif'
           SKS tetap diproses sebagai angka biasa (0 kalau kosong) -
           TIDAK lagi dipaksa NULL untuk semester tidak aktif, karena
           pengecualian dari TOPSIS/SAW sekarang murni berdasarkan
           status_sia (lihat topsis_proses.php/saw_proses.php), bukan
           dari nilai SKS-nya. */
        $statusMentah = strtolower(trim((string) ($baris['status_mentah'] ?? '')));

        if ($statusMentah === '' || $statusMentah === 'aktif') {
            $status_sia = 'aktif';
        } elseif ($statusMentah === 'do' || strpos($statusMentah, 'drop out') !== false) {
            $status_sia = 'do';
        } else {
            $status_sia = 'tidak_aktif';
        }

        $sks_diambil = $parseNumerikTerlacak($baris['sks_diambil'], 0, $nim, 'SKS Diambil');

        $ip_semester        = parseNumerik($baris['ip_semester'], null); // nullable, tidak perlu dilacak
        $ipk                = $parseNumerikTerlacak($baris['ipk'], 0, $nim, 'IPK');
        $sks_lulus          = $parseNumerikTerlacak($baris['sks_lulus'], 0, $nim, 'SKS Lulus');
        $skor_toefl         = $parseNumerikTerlacak($baris['skor_toefl'], 0, $nim, 'Skor TOEFL');
        $jml_mengulang      = $parseNumerikTerlacak($baris['jml_mengulang'], 0, $nim, 'Jumlah Mengulang');
        $sisa_masa_studi    = $parseNumerikTerlacak($baris['sisa_masa_studi'], 0, $nim, 'Sisa Masa Studi');
        $jalur_masuk        = amankanTeks($baris['jalur_masuk']);
        $absensi            = $parseNumerikTerlacak($baris['absensi'], 0, $nim, 'Absensi');
        $sks_nilai_kurang_b = $parseNumerikTerlacak($baris['sks_nilai_kurang_b'], 0, $nim, 'SKS Nilai Kurang B');

        $semester = 14 - $sisa_masa_studi;
        if ($semester < 1) {
            $gagal++;
            continue;
        }

        $duaDigitTahun = substr($nim, 4, 2);
        $angkatan = ctype_digit($duaDigitTahun) ? '20' . $duaDigitTahun : null;

        /* Kolom mahasiswa.angkatan bertipe YEAR(4) NOT NULL.
           Jika format NIM tidak sesuai pola yang dikenali,
           jangan paksa insert dengan nilai yang salah -
           tandai baris ini gagal agar admin bisa cek manual. */
        if ($angkatan === null) {
            $gagal++;
            continue;
        }

        /* CEK DOSEN PA (prepared statement) */
        $idUserDpa = null;

        $stmtDpa = mysqli_prepare($conn, "
            SELECT id_user FROM user
            WHERE nama_lengkap = ? AND role = 'dpa'
            LIMIT 1
        ");
        mysqli_stmt_bind_param($stmtDpa, "s", $dosen_pa);
        mysqli_stmt_execute($stmtDpa);
        $resultDpa = mysqli_stmt_get_result($stmtDpa);

        if ($resultDpa && mysqli_num_rows($resultDpa) > 0) {
            $rowDpa    = mysqli_fetch_assoc($resultDpa);
            $idUserDpa = $rowDpa['id_user'];
        } elseif ($dosen_pa !== '') {
            $dosenTidakDitemukan[$dosen_pa] = true;
        }
        mysqli_stmt_close($stmtDpa);

        /* 1. INSERT atau UPDATE tabel mahasiswa (prepared statement) */
        $stmtMhs = mysqli_prepare($conn, "
            INSERT INTO mahasiswa (nim, id_user, nama, angkatan)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                id_user  = VALUES(id_user),
                nama     = VALUES(nama),
                angkatan = VALUES(angkatan)
        ");
        mysqli_stmt_bind_param($stmtMhs, "siss", $nim, $idUserDpa, $nama_mahasiswa, $angkatan);
        mysqli_stmt_execute($stmtMhs);
        mysqli_stmt_close($stmtMhs);

        /* UPDATE status_sia pada tabel user jika akun mahasiswa sudah ada.
           CATATAN: ini kolom status_sia di tabel `user` (ENUM aktif/nonaktif,
           konsep beda dari status_sia di data_akademik yang punya 4 nilai
           aktif/cuti/do/-). Selain 'aktif' (cuti/do/-) dianggap 'nonaktif'. */
        $statusSiaUser = ($status_sia === 'aktif') ? 'aktif' : 'nonaktif';
        $stmtUser = mysqli_prepare($conn, "UPDATE user SET status_sia = ? WHERE username = ? AND role = 'mahasiswa'");
        mysqli_stmt_bind_param($stmtUser, "ss", $statusSiaUser, $nim);
        mysqli_stmt_execute($stmtUser);
        mysqli_stmt_close($stmtUser);

        /* 2. INSERT (semester baru = simpan sebagai histori baru)
           atau UPDATE (semester yang SAMA diimpor ulang = anggap
           koreksi data, bukan duplikat histori) tabel data_akademik.
           kunci pengecekan adalah (nim, semester), sehingga tiap
           semester tersimpan sebagai baris terpisah & permanen. */
        $stmtCek = mysqli_prepare($conn, "SELECT id_data FROM data_akademik WHERE nim = ? AND semester = ?");
        mysqli_stmt_bind_param($stmtCek, "sd", $nim, $semester);
        mysqli_stmt_execute($stmtCek);
        $resultCek = mysqli_stmt_get_result($stmtCek);

        $idData     = null;
        $okAkademik = false;

        if ($resultCek && mysqli_num_rows($resultCek) > 0) {

            $rowAkademik = mysqli_fetch_assoc($resultCek);
            $idData      = $rowAkademik['id_data'];

            $stmtUpdate = mysqli_prepare($conn, "
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
                WHERE nim = ? AND semester = ?
            ");
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
            $okAkademik = mysqli_stmt_execute($stmtUpdate);
            mysqli_stmt_close($stmtUpdate);

        } else {

            $stmtInsert = mysqli_prepare($conn, "
                INSERT INTO data_akademik (
                    nim, nama_mahasiswa, dosen_pa, semester, ip_semester, ipk,
                    skor_toefl, jml_mengulang, sks_lulus, sisa_masa_studi,
                    jalur_masuk, absensi, sks_diambil, sks_nilai_kurang_b,
                    status_sia
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
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
            $okAkademik = mysqli_stmt_execute($stmtInsert);

            if ($okAkademik) {
                $idData = mysqli_insert_id($conn);
            }
            mysqli_stmt_close($stmtInsert);
        }
        mysqli_stmt_close($stmtCek);

        /* 3. Simpan histori ke riwayat_akademik (junction table).
           PERBAIKAN: pakai $waktuImpor (satu waktu yang sama untuk
           seluruh baris dalam import ini) alih-alih NOW() per baris,
           supaya seluruh data dari satu file upload bisa dikenali
           sebagai satu "batch" yang sama persis waktunya - dipakai
           untuk fitur hapus per-batch di manajemen_data.php, tanpa
           perlu tabel/kolom tambahan. */
        if ($okAkademik && $idData) {
            $stmtRiwayat = mysqli_prepare($conn, "
                INSERT INTO riwayat_akademik (nim, id_data, tanggal_upload)
                VALUES (?, ?, ?)
            ");
            mysqli_stmt_bind_param($stmtRiwayat, "sis", $nim, $idData, $waktuImpor);
            mysqli_stmt_execute($stmtRiwayat);
            mysqli_stmt_close($stmtRiwayat);
        }

        if ($okAkademik) {
            $berhasil++;
        } else {
            $gagal++;
        }
    }

    $idAdmin = $_SESSION['id_user'] ?? null;

    $stmtLog = mysqli_prepare($conn, "
        INSERT INTO log_aktivitas (aksi, tanggal, id_user)
        VALUES ('Import data akademik mahasiswa', NOW(), ?)
    ");
    mysqli_stmt_bind_param($stmtLog, "i", $idAdmin);
    mysqli_stmt_execute($stmtLog);
    mysqli_stmt_close($stmtLog);

    /* =============================================
       OPSI B: JALANKAN OTOMATIS PERHITUNGAN
       TOPSIS -> SAW -> SPEARMAN SETELAH IMPORT
       SELESAI, supaya dashboard Kaprodi/DPA selalu
       berisi data terbaru tanpa perlu trigger manual.
       ============================================= */
    define('SPK_CHAIN', true);
    require '../proses/topsis_proses.php';
    require '../proses/saw_proses.php';
    require '../proses/spearman_proses.php';

    $pesanAkhir = "Import selesai! Berhasil: $berhasil | Gagal: $gagal.";

    if (isset($adaPerubahanDibandingPeriodeTerakhir)) {
        if ($adaPerubahanDibandingPeriodeTerakhir) {
            $pesanAkhir .= " Skor TOPSIS/SAW berubah dibanding periode sebelumnya - periode evaluasi baru tercatat untuk grafik tren.";
        } else {
            $pesanAkhir .= " Skor TOPSIS identik dengan periode terakhir (kemungkinan file yang sama diupload ulang) - tidak ada periode/titik tren baru yang dibuat.";
        }
    }

    if (!empty($dosenTidakDitemukan)) {
        $daftarDosen = implode(', ', array_keys($dosenTidakDitemukan));
        $pesanAkhir .= "\n\nPERHATIAN: " . count($dosenTidakDitemukan) . " nama Dosen PA di file TIDAK ditemukan akunnya (role dpa) di sistem, sehingga mahasiswa bimbingannya TIDAK akan muncul di dashboard DPA terkait sampai akun dibuat dengan nama yang PERSIS SAMA:\n" . $daftarDosen;
    }

    if (!empty($kolomDidefaultKosong)) {
        $jumlahNimBermasalah = count($kolomDidefaultKosong);
        $pesanAkhir .= "\n\nPERHATIAN: $jumlahNimBermasalah baris punya kolom kosong/tidak valid di file sumber, nilainya otomatis diisi 0 (bukan berarti datanya memang 0):";

        $contoh = 0;
        foreach ($kolomDidefaultKosong as $nimBermasalah => $daftarKolom) {
            if ($contoh >= 5) {
                $pesanAkhir .= "\n... dan " . ($jumlahNimBermasalah - 5) . " baris lainnya.";
                break;
            }
            $pesanAkhir .= "\n- $nimBermasalah: " . implode(', ', array_unique($daftarKolom));
            $contoh++;
        }
    }

    /* json_encode dipakai (bukan addslashes) karena aman untuk
       konteks string JavaScript: menangani quote, backslash,
       dan newline dengan benar sekaligus - addslashes akan
       merusak urutan escape \n menjadi tidak valid di JS. */
    $pesanAkhirJs = json_encode($pesanAkhir);

    echo "
    <script>
        alert($pesanAkhirJs);
        window.location='../pages/manajemen_data.php';
    </script>
    ";
}
?>
