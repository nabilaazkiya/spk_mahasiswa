<?php
session_start();
include "../config/database.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit;
}

/**
 * =============================================
 * HITUNG ULANG HISTORI TOPSIS (BACKFILL)
 * =============================================
 * proses/topsis_proses.php hanya menghitung TOPSIS dari data
 * TERBARU tiap mahasiswa (data_akademik_terbaru) - sehingga
 * semester-semester LAMA yang datanya sudah lebih dulu tertimpa
 * status "terbaru"-nya tidak pernah tercatat sebagai titik tren
 * histori, walau datanya sendiri masih tersimpan lengkap di
 * tabel data_akademik (tidak pernah dihapus, hanya view
 * data_akademik_terbaru yang menyaring ke baris terbaru saja).
 *
 * Halaman ini menjalankan ulang perhitungan TOPSIS SATU KALI
 * PER NILAI SEMESTER yang pernah ada di data_akademik (bukan
 * cuma yang terbaru), lalu menyimpan tiap hasilnya sebagai titik
 * histori periode "Semester XX" tersendiri - termasuk semester-
 * semester lama yang sudah "tertimpa" statusnya.
 *
 * PENTING - keterbatasan yang disengaja:
 * - Perhitungan tetap per-kohort (skor tiap mahasiswa relatif
 *   terhadap mahasiswa lain), tapi kohort untuk semester X hanya
 *   berisi mahasiswa yang MEMANG punya data di semester X
 *   tersebut. Mahasiswa yang datanya baru mulai tercatat di
 *   semester berikutnya wajar tidak muncul di titik semester
 *   sebelum itu.
 * - Tabel solusi_ideal (referensi transien untuk proses TOPSIS
 *   LIVE) sengaja TIDAK disentuh oleh backfill ini, supaya tidak
 *   tertimpa oleh perhitungan histori. Nilai ideal untuk tiap
 *   semester dihitung lokal di sini saja.
 * - Berbeda dari topsis_proses.php, backfill ini TIDAK memakai
 *   pengecekan "ada perubahan dibanding periode terakhir" -
 *   setiap nilai semester memang dimaksudkan jadi titik histori
 *   sendiri-sendiri, jadi selalu di-upsert.
 * =============================================
 */

/* =============================================
   FUNGSI BANTUAN (sama seperti topsis_proses.php)
   ============================================= */
function amankanNilaiBackfill($nilai)
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

function ambilNilaiTopsisBackfill($mhs, $kolomData)
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
        if (strpos($nilaiLower, 'beasiswa') !== false && strpos($nilaiLower, 'internasional') !== false) {
            return 5;
        } elseif ($nilaiLower == 'snbp' || $nilaiLower == 'snmptn') {
            return 4;
        } elseif ($nilaiLower == 'snbt' || $nilaiLower == 'sbmptn') {
            return 3;
        } elseif ($nilaiLower == 'mandiri') {
            return 2;
        } else {
            return 1;
        }
    }
    if ($kolomData == 'sks_lulus' || $kolomData == 'sks_diambil') {
        $semester = isset($mhs['semester']) ? floatval($mhs['semester']) : 0;
        if ($semester <= 0) {
            return 0;
        }
        $sksIdeal  = $semester * 20;
        $sksAktual = is_numeric($nilai) ? floatval($nilai) : 0;
        return $sksIdeal == 0 ? 0 : ($sksAktual / $sksIdeal);
    }
    if ($kolomData == 'skor_toefl') {
        $skor = is_numeric($nilai) ? floatval($nilai) : 0;
        if ($skor < 400) {
            return 0;
        } elseif ($skor < 450) {
            return 1;
        } else {
            return 2;
        }
    }
    if (!is_numeric($nilai)) {
        return 0;
    }
    return floatval($nilai);
}

/**
 * Jalankan satu kali perhitungan TOPSIS untuk kohort mahasiswa
 * pada satu nilai semester tertentu, lalu upsert hasilnya ke
 * ranking_topsis & hasil_evaluasi dengan periode "Semester XX".
 *
 * @return array{jumlah_mahasiswa:int, gagal:int}
 */
function jalankanTopsisSatuSemester($conn, $semester, $dataKriteria)
{
    /* Ambil snapshot TERBARU tiap mahasiswa YANG SAMA DENGAN
       nilai semester ini (bukan snapshot terbaru keseluruhan).
       Dijaga dengan MAX(id_data) juga, untuk berjaga-jaga kalau
       ada lebih dari satu baris untuk (nim, semester) yang sama
       (misal re-upload) - ambil yang paling baru diupload. */
    $dataMahasiswa = [];
    $stmt = mysqli_prepare($conn, "
        SELECT da.*
        FROM data_akademik da
        INNER JOIN (
            SELECT nim, MAX(id_data) AS id_data_terbaru
            FROM data_akademik
            WHERE semester = ?
            GROUP BY nim
        ) terbaru
        ON da.nim = terbaru.nim AND da.id_data = terbaru.id_data_terbaru
    ");
    mysqli_stmt_bind_param($stmt, "i", $semester);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $dataMahasiswa[] = $row;
    }
    mysqli_stmt_close($stmt);

    if (count($dataMahasiswa) == 0) {
        return ['jumlah_mahasiswa' => 0, 'gagal' => 0];
    }

    /* 1. MATRIKS KEPUTUSAN */
    $matriks = [];
    foreach ($dataMahasiswa as $mhs) {
        $nim = $mhs['nim'];
        foreach ($dataKriteria as $krit) {
            $matriks[$nim][$krit['id_kriteria']] = amankanNilaiBackfill(
                ambilNilaiTopsisBackfill($mhs, $krit['kolom_data'])
            );
        }
    }

    /* 2. NORMALISASI EUCLIDEAN */
    $pembagi = [];
    foreach ($dataKriteria as $krit) {
        $idKriteria   = $krit['id_kriteria'];
        $totalKuadrat = 0;
        foreach ($dataMahasiswa as $mhs) {
            $totalKuadrat += pow($matriks[$mhs['nim']][$idKriteria], 2);
        }
        $pembagi[$idKriteria] = amankanNilaiBackfill(sqrt($totalKuadrat));
    }

    $normalisasi = [];
    foreach ($dataMahasiswa as $mhs) {
        $nim = $mhs['nim'];
        foreach ($dataKriteria as $krit) {
            $idKriteria = $krit['id_kriteria'];
            $normalisasi[$nim][$idKriteria] = amankanNilaiBackfill(
                $pembagi[$idKriteria] == 0
                ? 0
                : $matriks[$nim][$idKriteria] / $pembagi[$idKriteria]
            );
        }
    }

    /* 3. NORMALISASI TERBOBOT */
    $normalisasiTerbobot = [];
    foreach ($dataMahasiswa as $mhs) {
        $nim = $mhs['nim'];
        foreach ($dataKriteria as $krit) {
            $idKriteria = $krit['id_kriteria'];
            $bobot      = amankanNilaiBackfill($krit['bobot_delphi']);
            $normalisasiTerbobot[$nim][$idKriteria] = amankanNilaiBackfill(
                $normalisasi[$nim][$idKriteria] * $bobot
            );
        }
    }

    /* 4. SOLUSI IDEAL POSITIF/NEGATIF (LOKAL - tabel solusi_ideal TIDAK disentuh) */
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
            $solusiPositif[$idKriteria] = amankanNilaiBackfill(max($nilaiKriteria));
            $solusiNegatif[$idKriteria] = amankanNilaiBackfill(min($nilaiKriteria));
        } else {
            $solusiPositif[$idKriteria] = amankanNilaiBackfill(min($nilaiKriteria));
            $solusiNegatif[$idKriteria] = amankanNilaiBackfill(max($nilaiKriteria));
        }
    }

    /* 5. JARAK D+ / D- DAN NILAI PREFERENSI */
    $hasilTopsis = [];
    foreach ($dataMahasiswa as $mhs) {
        $nim    = $mhs['nim'];
        $dPlus  = 0;
        $dMinus = 0;
        foreach ($dataKriteria as $krit) {
            $idKriteria = $krit['id_kriteria'];
            $nilai      = $normalisasiTerbobot[$nim][$idKriteria];
            $dPlus  += pow($nilai - $solusiPositif[$idKriteria], 2);
            $dMinus += pow($nilai - $solusiNegatif[$idKriteria], 2);
        }
        $dPlus  = amankanNilaiBackfill(sqrt($dPlus));
        $dMinus = amankanNilaiBackfill(sqrt($dMinus));

        $nilaiPreferensi = 0;
        if (($dPlus + $dMinus) > 0) {
            $nilaiPreferensi = amankanNilaiBackfill($dMinus / ($dPlus + $dMinus));
        }

        $hasilTopsis[] = [
            'nim'              => $nim,
            'nilai_preferensi' => $nilaiPreferensi,
            'jarak_positif'    => $dPlus,
            'jarak_negatif'    => $dMinus
        ];
    }

    /* 6. RANKING */
    usort($hasilTopsis, function ($a, $b) {
        if ($a['nilai_preferensi'] == $b['nilai_preferensi']) {
            return 0;
        }
        return ($a['nilai_preferensi'] < $b['nilai_preferensi']) ? 1 : -1;
    });

    /* 7. UPSERT ke ranking_topsis & hasil_evaluasi,
       periode_evaluasi = "Semester XX" (padding 2 digit,
       konsisten dengan proses/topsis_proses.php). */
    $periode = sprintf('Semester %02d', $semester);
    $ranking = 1;
    $gagal   = 0;

    foreach ($hasilTopsis as $hasil) {
        $nim             = mysqli_real_escape_string($conn, $hasil['nim']);
        $nilaiPreferensi = $hasil['nilai_preferensi'];
        $jarakPositif    = $hasil['jarak_positif'];
        $jarakNegatif    = $hasil['jarak_negatif'];

        $cekRanking = mysqli_query($conn, "
            SELECT id_ranking FROM ranking_topsis
            WHERE nim = '$nim' AND periode_evaluasi = '$periode'
        ");
        if ($cekRanking && mysqli_num_rows($cekRanking) > 0) {
            $row = mysqli_fetch_assoc($cekRanking);
            $ok = mysqli_query($conn, "
                UPDATE ranking_topsis SET
                    nilai_preferensi = '$nilaiPreferensi',
                    ranking          = '$ranking',
                    jarak_positif    = '$jarakPositif',
                    jarak_negatif    = '$jarakNegatif'
                WHERE id_ranking = '{$row['id_ranking']}'
            ");
        } else {
            $ok = mysqli_query($conn, "
                INSERT INTO ranking_topsis (
                    nim, nilai_preferensi, ranking, jarak_positif, jarak_negatif, periode_evaluasi
                ) VALUES (
                    '$nim', '$nilaiPreferensi', '$ranking', '$jarakPositif', '$jarakNegatif', '$periode'
                )
            ");
        }
        if (!$ok) {
            $gagal++;
        }

        if ($nilaiPreferensi <= 0.25) {
            $status = 'Kritis';
        } elseif ($nilaiPreferensi <= 0.50) {
            $status = 'Waspada';
        } elseif ($nilaiPreferensi <= 0.75) {
            $status = 'Aman';
        } else {
            $status = 'Sangat Baik';
        }

        $cekEvaluasi = mysqli_query($conn, "
            SELECT id_hasil FROM hasil_evaluasi
            WHERE nim = '$nim' AND periode_evaluasi = '$periode'
        ");
        if ($cekEvaluasi && mysqli_num_rows($cekEvaluasi) > 0) {
            $row = mysqli_fetch_assoc($cekEvaluasi);
            mysqli_query($conn, "
                UPDATE hasil_evaluasi SET
                    nilai_preferensi     = '$nilaiPreferensi',
                    status_early_warning = '$status'
                WHERE id_hasil = '{$row['id_hasil']}'
            ");
        } else {
            mysqli_query($conn, "
                INSERT INTO hasil_evaluasi (
                    nim, nilai_preferensi, status_early_warning, periode_evaluasi
                ) VALUES (
                    '$nim', '$nilaiPreferensi', '$status', '$periode'
                )
            ");
        }

        $ranking++;
    }

    return ['jumlah_mahasiswa' => count($hasilTopsis), 'gagal' => $gagal];
}

/* =============================================
   JALANKAN BACKFILL UNTUK SEMUA NILAI SEMESTER
   ============================================= */
$dataKriteria = [];
$qKriteria = mysqli_query($conn, "
    SELECT * FROM kriteria
    WHERE kolom_data IS NOT NULL AND kolom_data != '' AND bobot_delphi > 0
    ORDER BY id_kriteria ASC
");
while ($row = mysqli_fetch_assoc($qKriteria)) {
    $dataKriteria[] = $row;
}

if (count($dataKriteria) == 0) {
    echo "
    <script>
        alert('Data kriteria atau bobot Delphi belum tersedia.');
        window.location='../pages/konfigurasi_kriteria.php';
    </script>";
    exit;
}

$daftarSemester = [];
$qSemester = mysqli_query($conn, "SELECT DISTINCT semester FROM data_akademik ORDER BY semester ASC");
while ($row = mysqli_fetch_assoc($qSemester)) {
    $daftarSemester[] = (int) $row['semester'];
}

if (count($daftarSemester) == 0) {
    echo "
    <script>
        alert('Data akademik mahasiswa masih kosong.');
        window.location='../pages/manajemen_data.php';
    </script>";
    exit;
}

$rincian = [];
$totalGagal = 0;

foreach ($daftarSemester as $semester) {
    $hasil = jalankanTopsisSatuSemester($conn, $semester, $dataKriteria);
    if ($hasil['jumlah_mahasiswa'] > 0) {
        $rincian[] = sprintf('Semester %02d', $semester) . ': ' . $hasil['jumlah_mahasiswa'] . ' mahasiswa';
    }
    $totalGagal += $hasil['gagal'];
}

/* CATAT LOG */
if (isset($_SESSION['id_user'])) {
    $idUser  = mysqli_real_escape_string($conn, $_SESSION['id_user']);
    $aksiLog = mysqli_real_escape_string($conn, 'Menjalankan hitung ulang histori TOPSIS (' . count($rincian) . ' periode semester diproses)');
    mysqli_query($conn, "
        INSERT INTO log_aktivitas (id_user, aksi, tanggal)
        VALUES ('$idUser', '$aksiLog', NOW())
    ");
}

$pesan = "Hitung ulang histori TOPSIS selesai untuk " . count($rincian) . " periode semester:\\n\\n" . implode('\\n', $rincian);
if ($totalGagal > 0) {
    $pesan .= "\\n\\nPERHATIAN: $totalGagal baris gagal disimpan, cek log server untuk detail.";
}
$pesanJs = json_encode($pesan);

echo "
    <script>
        alert($pesanJs);
        window.location='../pages/manajemen_data.php';
    </script>
";
