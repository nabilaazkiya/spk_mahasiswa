<?php
session_start();
include "../config/database.php";

if (!isset($_SESSION['role'])) {
    header("Location: ../login.php");
    exit; 
}

$periode = date('Y-m-d');

/* =============================================
   1. HAPUS DATA LAMA
   ============================================= */
mysqli_query($conn, "DELETE FROM ranking_topsis");
mysqli_query($conn, "DELETE FROM solusi_ideal");
mysqli_query($conn, "DELETE FROM hasil_evaluasi");

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

    if ($kolomData == 'jalur_masuk') {
        $nilaiLower = strtolower(trim($nilai));

        if ($nilaiLower == 'snbp' || $nilaiLower == 'snmptn') {
            return 4;
        } elseif ($nilaiLower == 'snbt' || $nilaiLower == 'sbmptn') {
            return 3;
        } elseif ($nilaiLower == 'mandiri') {
            return 2;
        } else {
            return 1;
        }
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
   10. SIMPAN KE ranking_topsis
   ============================================= */
$ranking = 1;
$gagalSimpanRanking = [];

foreach ($hasilTopsis as $hasil) {
    $nim             = mysqli_real_escape_string($conn, $hasil['nim']);
    $nilaiPreferensi = $hasil['nilai_preferensi'];
    $jarakPositif    = $hasil['jarak_positif'];
    $jarakNegatif    = $hasil['jarak_negatif'];

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

    if (!$insertRanking) {
        $gagalSimpanRanking[] = $nim;
        error_log("Gagal INSERT ranking_topsis untuk nim=$nim: " . mysqli_error($conn));
    }

    $ranking++;
}

/* =============================================
   11. SIMPAN KATEGORI KE hasil_evaluasi
       Threshold Opsi A:
       0.00 - 0.25 = Kritis
       0.26 - 0.50 = Waspada
       0.51 - 0.75 = Aman
       0.76 - 1.00 = Sangat Baik
   ============================================= */
foreach ($hasilTopsis as $hasil) {
    $nim             = mysqli_real_escape_string($conn, $hasil['nim']);
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

    if (!$insertEvaluasi) {
        error_log("Gagal INSERT hasil_evaluasi untuk nim=$nim: " . mysqli_error($conn));
    }
}

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
   ============================================= */
header("Location: saw_proses.php");
exit;
?>