<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include "../config/database.php";

if (!isset($_SESSION['role'])) {
    header("Location: ../login.php");
    exit; 
}

/* PERBAIKAN BUG (revisi ke-3): sebelumnya "periode_evaluasi"
   diambil dari TANGGAL proses TOPSIS dijalankan
   (date('Y-m-d')). Karena TOPSIS otomatis dijalankan ulang
   setiap kali admin upload data (lihat proses/input_data.php),
   dan admin sering upload beberapa semester di HARI YANG SAMA,
   upload kedua akan memakai periode (tanggal) yang SAMA dengan
   upload pertama - sehingga baris ranking_topsis/hasil_evaluasi
   yang sudah ada untuk hari itu hanya DITIMPA (UPDATE), bukan
   dicatat sebagai titik tren baru. Akibatnya grafik tren TOPSIS
   di Detail Mahasiswa selalu mentok di 1 titik walau datanya
   sudah mencakup beberapa semester.

   Solusi: "periode_evaluasi" sekarang diambil PER MAHASISWA dari
   kolom semester miliknya sendiri di data_akademik (bukan
   tanggal global) - lihat langkah 8 & 10/11 di bawah. Setiap
   mahasiswa progres semesternya sendiri-sendiri, jadi ini juga
   lebih akurat mencerminkan histori akademik masing-masing
   dibanding tanggal proses. */

/* =============================================
   1. HAPUS DATA LAMA
   PERBAIKAN: ranking_topsis dan hasil_evaluasi TIDAK LAGI
   dihapus semua di sini. Sebelumnya setiap proses TOPSIS
   dijalankan ulang, seluruh histori periode sebelumnya ikut
   terhapus - sehingga grafik tren skor TOPSIS antar periode
   (di Detail Mahasiswa) tidak pernah bisa punya lebih dari
   1 titik data. Sekarang keduanya di-UPSERT per (nim, periode)
   di bagian bawah (lihat langkah 10 & 11).

   solusi_ideal TETAP dihapus setiap run karena sifatnya hanya
   data referensi transien untuk perhitungan (nilai ideal
   positif/negatif per kriteria pada saat itu), bukan histori
   yang perlu ditampilkan ke pengguna.
   ============================================= */
mysqli_query($conn, "DELETE FROM solusi_ideal");

/* =============================================
   2. AMBIL DATA AKADEMIK TERBARU PER MAHASISWA
   ============================================= */
$dataMahasiswa = [];

$qMahasiswa = mysqli_query($conn, "
    SELECT da.*
    FROM data_akademik da
    INNER JOIN (
        SELECT nim, MAX(id_data) AS id_data_terbaru
        FROM data_akademik
        GROUP BY nim
    ) terbaru
    ON da.nim = terbaru.nim
    AND da.id_data = terbaru.id_data_terbaru
");

while ($row = mysqli_fetch_assoc($qMahasiswa)) {
    $dataMahasiswa[] = $row;
}

/* =============================================
   3. AMBIL KRITERIA + BOBOT
   ============================================= */
$dataKriteria = [];

$qKriteria = mysqli_query($conn, "
    SELECT *
    FROM kriteria
    WHERE kolom_data IS NOT NULL
    AND kolom_data != ''
    AND bobot_delphi > 0
    ORDER BY id_kriteria ASC
");

while ($row = mysqli_fetch_assoc($qKriteria)) {
    $dataKriteria[] = $row;
}

/* =============================================
   VALIDASI DATA
   ============================================= */
if (count($dataMahasiswa) == 0) {
    echo "
    <script>
        alert('Data akademik mahasiswa masih kosong.');
        window.location='../pages/monitoring.php';
    </script>";
    exit;
}

if (count($dataKriteria) == 0) {
    echo "
    <script>
        alert('Data kriteria atau bobot Delphi belum tersedia.');
        window.location='../pages/konfigurasi_kriteria.php';
    </script>";
    exit;
}

/* =============================================
   FUNGSI BANTUAN: AMANKAN NILAI NUMERIK
   Mengubah NAN / INF / -INF menjadi 0 agar
   tidak pernah gagal disimpan ke kolom DECIMAL
   di database (yang akan membuatnya NULL diam-diam).
   ============================================= */
function amankanNilai($nilai)
{
    if (!is_numeric($nilai)) {
        return 0;
    }

    $nilai = floatval($nilai);

    if (is_nan($nilai) || is_infinite($nilai)) {
        return 0;
    }

    return $nilai;
}

/* =============================================
   FUNGSI AMBIL NILAI KRITERIA
   ============================================= */
function ambilNilaiTopsis($mhs, $kolomData)
{
    if (!isset($mhs[$kolomData])) {
        return 0;
    }

    $nilai = $mhs[$kolomData];

    if ($nilai === null || $nilai === '') {
        return 0;
    }

    /* PERBAIKAN: Jalur Masuk sekarang 5 tingkat sesuai urutan
       prioritas yang ditetapkan (dari tertinggi ke terendah):
       Beasiswa Mahasiswa Internasional > SNMPTN/SNBP >
       SBMPTN/SNBT > Mandiri > Mahasiswa Pindahan. SNBP/SNBT
       tetap dikenali sebagai alias SNMPTN/SBMPTN (nama baru
       jalur yang sama sejak 2023), supaya kompatibel dengan
       data lama maupun baru. */
    if ($kolomData == 'jalur_masuk') {
        $nilaiLower = strtolower(trim($nilai));

        if (strpos($nilaiLower, 'beasiswa') !== false && strpos($nilaiLower, 'internasional') !== false) {
            return 5;
        } elseif ($nilaiLower == 'snbp' || $nilaiLower == 'snmptn') {
            return 4;
        } elseif ($nilaiLower == 'snbt' || $nilaiLower == 'sbmptn') {
            return 3;
        } elseif ($nilaiLower == 'mandiri') {
            return 2;
        } elseif (strpos($nilaiLower, 'pindahan') !== false) {
            return 1;
        } else {
            /* Jalur tidak dikenali - taruh di tingkat terendah
               daripada diam-diam dianggap setara "Pindahan",
               supaya kalau ada data ganjil tetap konservatif. */
            return 1;
        }
    }

    /* PERBAIKAN: SKS Lulus & SKS Diambil dibandingkan secara
       MENTAH akan selalu merugikan mahasiswa semester awal
       (SKS mereka wajar jauh lebih sedikit dari mahasiswa
       semester akhir). Sekarang dinormalisasi jadi RASIO
       terhadap SKS ideal (semester berjalan x 20 SKS/semester),
       supaya yang dibandingkan adalah seberapa sesuai progres
       SKS mahasiswa dengan kecepatan idealnya sendiri - bukan
       jumlah SKS absolut. Patokan 20 SKS/semester dipakai apa
       adanya sesuai semester mahasiswa (tidak dibatasi di
       semester 7 untuk mahasiswa yang sudah lebih dari itu). */
    if ($kolomData == 'sks_lulus' || $kolomData == 'sks_diambil') {
        $semester = isset($mhs['semester']) ? floatval($mhs['semester']) : 0;
        if ($semester <= 0) {
            return 0;
        }
        $sksIdeal = $semester * 20;
        $sksAktual = is_numeric($nilai) ? floatval($nilai) : 0;
        return $sksIdeal == 0 ? 0 : ($sksAktual / $sksIdeal);
    }

    /* Jika nilai bukan numerik (misal ada teks tersisip di kolom
       yang seharusnya angka), amankan menjadi 0 alih-alih
       membiarkan floatval() menghasilkan nilai tak terduga. */
    if (!is_numeric($nilai)) {
        return 0;
    }

    return floatval($nilai);
}

/* =============================================
   4. BENTUK MATRIKS KEPUTUSAN
   ============================================= */
$matriks = [];

foreach ($dataMahasiswa as $mhs) {
    $nim = $mhs['nim'];

    foreach ($dataKriteria as $krit) {
        $idKriteria = $krit['id_kriteria'];
        $kolomData  = $krit['kolom_data'];

        $matriks[$nim][$idKriteria] = amankanNilai(
            ambilNilaiTopsis($mhs, $kolomData)
        );
    }
}

/* =============================================
   5. NORMALISASI MATRIKS (EUCLIDEAN)
      r_ij = x_ij / sqrt(sum(x_ij^2))
   ============================================= */
$pembagi     = [];
$normalisasi = [];

foreach ($dataKriteria as $krit) {
    $idKriteria   = $krit['id_kriteria'];
    $totalKuadrat = 0;

    foreach ($dataMahasiswa as $mhs) {
        $totalKuadrat += pow($matriks[$mhs['nim']][$idKriteria], 2);
    }

    $pembagi[$idKriteria] = amankanNilai(sqrt($totalKuadrat));
}

foreach ($dataMahasiswa as $mhs) {
    $nim = $mhs['nim'];

    foreach ($dataKriteria as $krit) {
        $idKriteria = $krit['id_kriteria'];

        $normalisasi[$nim][$idKriteria] = amankanNilai(
            $pembagi[$idKriteria] == 0
            ? 0
            : $matriks[$nim][$idKriteria] / $pembagi[$idKriteria]
        );
    }
}

/* =============================================
   6. NORMALISASI TERBOBOT
      v_ij = w_j * r_ij
   ============================================= */
$normalisasiTerbobot = [];

foreach ($dataMahasiswa as $mhs) {
    $nim = $mhs['nim'];

    foreach ($dataKriteria as $krit) {
        $idKriteria = $krit['id_kriteria'];
        $bobot      = amankanNilai($krit['bobot_delphi']);

        $normalisasiTerbobot[$nim][$idKriteria] = amankanNilai(
            $normalisasi[$nim][$idKriteria] * $bobot
        );
    }
}

/* =============================================
   7. TENTUKAN SOLUSI IDEAL POSITIF (A+) DAN
      NEGATIF (A−) LALU SIMPAN KE solusi_ideal
   ============================================= */
$solusiPositif = [];
$solusiNegatif = [];

foreach ($dataKriteria as $krit) {
    $idKriteria    = $krit['id_kriteria'];
    $jenis         = strtolower(trim($krit['jenis']));
    $nilaiKriteria = [];

    foreach ($dataMahasiswa as $mhs) {
        $nilaiKriteria[] = $normalisasiTerbobot[$mhs['nim']][$idKriteria];
    }

    if ($jenis == 'benefit') {
        $solusiPositif[$idKriteria] = amankanNilai(max($nilaiKriteria));
        $solusiNegatif[$idKriteria] = amankanNilai(min($nilaiKriteria));
    } else {
        $solusiPositif[$idKriteria] = amankanNilai(min($nilaiKriteria));
        $solusiNegatif[$idKriteria] = amankanNilai(max($nilaiKriteria));
    }

    /* Simpan ke tabel solusi_ideal */
    $nilaiPositif = $solusiPositif[$idKriteria];
    $nilaiNegatif = $solusiNegatif[$idKriteria];

    $insertSolusi = mysqli_query($conn, "
        INSERT INTO solusi_ideal (
            id_kriteria,
            nilai_positif,
            nilai_negatif
        ) VALUES (
            '$idKriteria',
            '$nilaiPositif',
            '$nilaiNegatif'
        )
    ");

    if (!$insertSolusi) {
        error_log("Gagal INSERT solusi_ideal untuk id_kriteria=$idKriteria: " . mysqli_error($conn));
    }
}

/* =============================================
   8. HITUNG JARAK D+ DAN D− (EUCLIDEAN)
      SERTA NILAI PREFERENSI
      C_i = D− / (D+ + D−)
   ============================================= */
$hasilTopsis  = [];
$nimBermasalah = [];

foreach ($dataMahasiswa as $mhs) {
    $nim   = $mhs['nim'];
    $dPlus = 0;
    $dMinus = 0;

    foreach ($dataKriteria as $krit) {
        $idKriteria = $krit['id_kriteria'];
        $nilai      = $normalisasiTerbobot[$nim][$idKriteria];

        $dPlus  += pow($nilai - $solusiPositif[$idKriteria], 2);
        $dMinus += pow($nilai - $solusiNegatif[$idKriteria], 2);
    }

    $dPlus  = sqrt($dPlus);
    $dMinus = sqrt($dMinus);

    /* ── VALIDASI DEFENSIF ──
       Jika hasil perhitungan menghasilkan NaN atau INF (misal akibat
       data akademik yang tidak normal), catat sebagai mahasiswa
       bermasalah dan amankan nilainya menjadi 0 alih-alih membiarkan
       MySQL menyimpannya sebagai NULL secara diam-diam. */
    if (is_nan($dPlus) || is_infinite($dPlus) || is_nan($dMinus) || is_infinite($dMinus)) {
        $nimBermasalah[] = $nim;
    }

    $dPlus  = amankanNilai($dPlus);
    $dMinus = amankanNilai($dMinus);

    $nilaiPreferensi = 0;

    if (($dPlus + $dMinus) > 0) {
        $nilaiPreferensi = amankanNilai($dMinus / ($dPlus + $dMinus));
    }

    $hasilTopsis[] = [
        'nim'              => $nim,
        'semester'         => (int) $mhs['semester'],
        'nilai_preferensi' => $nilaiPreferensi,
        'jarak_positif'    => $dPlus,
        'jarak_negatif'    => $dMinus
    ];
}

/* =============================================
   9. URUTKAN RANKING (DESC nilai preferensi)
   ============================================= */
usort($hasilTopsis, function ($a, $b) {
    if ($a['nilai_preferensi'] == $b['nilai_preferensi']) {
        return 0;
    }
    return ($a['nilai_preferensi'] < $b['nilai_preferensi']) ? 1 : -1;
});

/* =============================================
   9b. DETEKSI APAKAH INI PERIODE BARU YANG SAH
   Bandingkan skor TOPSIS hasil hitung SEKARANG dengan
   snapshot PERIODE TERAKHIR yang sudah tersimpan di
   database (via view ranking_topsis_terbaru). Kalau
   identik persis (nama mahasiswa & skornya sama semua),
   berarti ini kemungkinan besar file yang SAMA diupload
   ulang (misal testing/tidak sengaja) - maka periode baru
   TIDAK dibuat sama sekali, supaya grafik tren tidak penuh
   titik-titik redundan dengan skor yang identik.

   Kalau ADA perbedaan sekecil apapun (skor berubah, atau
   ada mahasiswa baru/hilang), baru dianggap periode evaluasi
   yang sah dan akan tersimpan sebagai titik tren baru.
   ============================================= */
$snapshotLama = [];
$qSnapshotLama = mysqli_query($conn, "SELECT nim, nilai_preferensi FROM ranking_topsis_terbaru");
if ($qSnapshotLama) {
    while ($rowLama = mysqli_fetch_assoc($qSnapshotLama)) {
        $snapshotLama[$rowLama['nim']] = round((float) $rowLama['nilai_preferensi'], 4);
    }
}

$snapshotBaru = [];
foreach ($hasilTopsis as $hasil) {
    $snapshotBaru[$hasil['nim']] = round((float) $hasil['nilai_preferensi'], 4);
}

ksort($snapshotLama);
ksort($snapshotBaru);

$adaPerubahanDibandingPeriodeTerakhir = empty($snapshotLama) || ($snapshotLama !== $snapshotBaru);

/* =============================================
   10. SIMPAN KE ranking_topsis
   PERBAIKAN: upsert per (nim, periode_evaluasi) - bukan
   INSERT polos ke tabel yang sudah dikosongkan. Kalau
   proses ini dijalankan berkali-kali di HARI YANG SAMA
   (periode sama), baris yang sama akan diperbarui, bukan
   duplikat. Kalau tanggalnya beda (periode baru), baris
   baru akan tersimpan sebagai titik histori baru.

   Loop ini hanya berjalan kalau memang ADA PERUBAHAN
   dibanding periode terakhir (lihat langkah 9b) - supaya
   tidak membuat titik histori baru yang redundan.
   ============================================= */
$ranking = 1;
$gagalSimpanRanking = [];

if ($adaPerubahanDibandingPeriodeTerakhir) {
foreach ($hasilTopsis as $hasil) {
    $nim             = mysqli_real_escape_string($conn, $hasil['nim']);
    $periode         = mysqli_real_escape_string($conn, sprintf('Semester %02d', $hasil['semester']));

    $cekRanking = mysqli_query($conn, "
        SELECT id_ranking FROM ranking_topsis
        WHERE nim = '$nim' AND periode_evaluasi = '$periode'
    ");

    if ($cekRanking && mysqli_num_rows($cekRanking) > 0) {
        $rowRanking = mysqli_fetch_assoc($cekRanking);
        $insertRanking = mysqli_query($conn, "
            UPDATE ranking_topsis SET
                nilai_preferensi = '$nilaiPreferensi',
                ranking          = '$ranking',
                jarak_positif    = '$jarakPositif',
                jarak_negatif    = '$jarakNegatif'
            WHERE id_ranking = '{$rowRanking['id_ranking']}'
        ");
    } else {
        $insertRanking = mysqli_query($conn, "
            INSERT INTO ranking_topsis (
                nim,
                nilai_preferensi,
                ranking,
                jarak_positif,
                jarak_negatif,
                periode_evaluasi
            ) VALUES (
                '$nim',
                '$nilaiPreferensi',
                '$ranking',
                '$jarakPositif',
                '$jarakNegatif',
                '$periode'
            )
        ");
    }

    if (!$insertRanking) {
        $gagalSimpanRanking[] = $nim;
        error_log("Gagal simpan ranking_topsis untuk nim=$nim: " . mysqli_error($conn));
    }

    $ranking++;
}
} // end if ($adaPerubahanDibandingPeriodeTerakhir)

/* =============================================
   11. SIMPAN KATEGORI KE hasil_evaluasi
       Threshold Opsi A:
       0.00 - 0.25 = Kritis
       0.26 - 0.50 = Waspada
       0.51 - 0.75 = Aman
       0.76 - 1.00 = Sangat Baik

       PERBAIKAN: upsert per (nim, periode_evaluasi), sama
       seperti ranking_topsis di atas - histori periode lain
       tidak lagi terhapus. Loop ini juga hanya berjalan kalau
       ADA PERUBAHAN dibanding periode terakhir (flag yang
       sama dengan ranking_topsis di atas).
   ============================================= */
if ($adaPerubahanDibandingPeriodeTerakhir) {
foreach ($hasilTopsis as $hasil) {
    $nim             = mysqli_real_escape_string($conn, $hasil['nim']);
    $periode         = mysqli_real_escape_string($conn, sprintf('Semester %02d', $hasil['semester']));
    $nilaiPreferensi = $hasil['nilai_preferensi'];

    if ($nilaiPreferensi <= 0.25) {
        $statusEarlyWarning = 'Kritis';
    } elseif ($nilaiPreferensi <= 0.50) {
        $statusEarlyWarning = 'Waspada';
    } elseif ($nilaiPreferensi <= 0.75) {
        $statusEarlyWarning = 'Aman';
    } else {
        $statusEarlyWarning = 'Sangat Baik';
    }

    $cekEvaluasi = mysqli_query($conn, "
        SELECT id_hasil FROM hasil_evaluasi
        WHERE nim = '$nim' AND periode_evaluasi = '$periode'
    ");

    if ($cekEvaluasi && mysqli_num_rows($cekEvaluasi) > 0) {
        $rowEvaluasi = mysqli_fetch_assoc($cekEvaluasi);
        $insertEvaluasi = mysqli_query($conn, "
            UPDATE hasil_evaluasi SET
                nilai_preferensi      = '$nilaiPreferensi',
                status_early_warning  = '$statusEarlyWarning'
            WHERE id_hasil = '{$rowEvaluasi['id_hasil']}'
        ");
    } else {
        $insertEvaluasi = mysqli_query($conn, "
            INSERT INTO hasil_evaluasi (
                nim,
                nilai_preferensi,
                status_early_warning,
                periode_evaluasi
            ) VALUES (
                '$nim',
                '$nilaiPreferensi',
                '$statusEarlyWarning',
                '$periode'
            )
        ");
    }

    if (!$insertEvaluasi) {
        error_log("Gagal simpan hasil_evaluasi untuk nim=$nim: " . mysqli_error($conn));
    }
}
} // end if ($adaPerubahanDibandingPeriodeTerakhir)

/* =============================================
   12. LOG AKTIVITAS
   ============================================= */
if (isset($_SESSION['id_user'])) {
    $idUser = mysqli_real_escape_string($conn, $_SESSION['id_user']);

    $aksiLog = 'Menjalankan proses perhitungan TOPSIS';

    /* Jika ada mahasiswa yang nilainya bermasalah (NaN/INF terdeteksi)
       saat perhitungan, catat di log aktivitas agar bisa ditelusuri
       tanpa perlu mengubah struktur tabel apapun. */
    if (!empty($nimBermasalah)) {
        $daftarNim = implode(', ', array_unique($nimBermasalah));
        $aksiLog  .= " (Peringatan: ditemukan nilai tidak valid pada NIM: $daftarNim, nilai diamankan ke 0)";
    }

    $aksiLogEscaped = mysqli_real_escape_string($conn, $aksiLog);

    mysqli_query($conn, "
        INSERT INTO log_aktivitas (
            id_user,
            aksi,
            tanggal
        ) VALUES (
            '$idUser',
            '$aksiLogEscaped',
            NOW()
        )
    ");
}

/* =============================================
   13. LANJUT KE PROSES SAW
   (Jika dipanggil otomatis via chain dari
   input_data.php, biarkan caller yang
   melanjutkan ke saw_proses.php)
   ============================================= */
if (!defined('SPK_CHAIN')) {
    header("Location: saw_proses.php");
    exit;
}
?>