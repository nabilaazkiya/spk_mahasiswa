<?php
session_start();
include "../config/database.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit;
}

/**
 * =============================================
 * PERHITUNGAN BOBOT DELPHI (SATU-SATUNYA SUMBER)
 * =============================================
 * PERBAIKAN BUG BESAR:
 * 1. Sebelumnya ada DUA sumber bobot Delphi yang saling
 *    bertentangan - array hardcode di konfigurasi_kriteria.php
 *    (yang otomatis menimpa ulang tabel `kriteria` SETIAP kali
 *    halaman itu dibuka) dan file ini (yang malah tidak pernah
 *    dipanggil dari halaman manapun). konfigurasi_kriteria.php
 *    sudah diubah jadi HALAMAN TAMPILAN SAJA (read-only) -
 *    file inilah sekarang satu-satunya yang menulis ke tabel
 *    `kriteria`, dipicu tombol "Hitung Ulang Bobot Delphi" di
 *    halaman itu.
 * 2. assets/data_delphi.csv sebelumnya adalah file Excel (.xlsx)
 *    yang cuma diganti ekstensinya jadi .csv, sehingga tidak
 *    pernah bisa benar-benar dibaca sebagai CSV. Sudah diganti
 *    dengan CSV asli.
 * 3. Kolom kriteria C6 sebelumnya salah menunjuk ke
 *    "sks_nilai_kurang_c" (kolom yang tidak ada di database) -
 *    sudah diperbaiki ke "sks_nilai_kurang_b".
 *
 * PENGECEKAN KONSENSUS:
 * Selain rata-rata (dipakai untuk bobot), dihitung juga standar
 * deviasi skor antar pakar per kriteria untuk transparansi
 * tingkat kesepakatan. Status konsensus memakai aturan umum yang
 * dipakai pada banyak penelitian SPK-Delphi: kriteria dianggap
 * DISEPAKATI PAKAR jika rata-rata skornya >= 3 (skala 1-5).
 * Kriteria dengan rata-rata < 3 ditandai "Belum Konsensus" -
 * mengindikasikan kriteria tsb perlu didiskusikan ulang ke pakar
 * pada iterasi berikutnya, bukan langsung dipakai.
 * =============================================
 */

/* Sumber file: upload baru (iterasi lanjutan) kalau ada,
   kalau tidak pakai file default assets/data_delphi.csv */
if (isset($_FILES['file_pakar']) && $_FILES['file_pakar']['error'] === UPLOAD_ERR_OK) {
    $fileCsv = $_FILES['file_pakar']['tmp_name'];
} else {
    $fileCsv = "../assets/data_delphi.csv";
}

if (!file_exists($fileCsv)) {
    echo "<script>
        alert('File data pakar tidak ditemukan.');
        window.location='../pages/konfigurasi_kriteria.php';
    </script>";
    exit;
}

$dataDelphi = [];
$totalRataRata = 0;

$file = fopen($fileCsv, "r");
$header = fgetcsv($file);

if ($header === false) {
    echo "<script>
        alert('File CSV kosong atau formatnya tidak valid.');
        window.location='../pages/konfigurasi_kriteria.php';
    </script>";
    exit;
}

while (($row = fgetcsv($file)) !== false) {
    if (count($row) < count($header)) {
        continue;
    }
    $data = array_combine($header, $row);

    $kode  = trim($data['kode_kriteria'] ?? '');
    $nama  = trim($data['nama_kriteria'] ?? '');
    $kolom = trim($data['kolom_data'] ?? '');
    $jenis = strtolower(trim($data['jenis'] ?? ''));

    if ($kode === '') {
        continue;
    }

    $nilaiPakar = [];
    foreach ($data as $key => $value) {
        if (strpos($key, 'pakar_') === 0 && $value !== '') {
            $nilaiPakar[] = floatval($value);
        }
    }

    if (count($nilaiPakar) == 0) {
        continue;
    }

    $rataRata = array_sum($nilaiPakar) / count($nilaiPakar);

    /* Standar deviasi (populasi) skor antar pakar untuk kriteria ini */
    $variansi = 0;
    foreach ($nilaiPakar as $n) {
        $variansi += pow($n - $rataRata, 2);
    }
    $variansi = $variansi / count($nilaiPakar);
    $stdDev   = sqrt($variansi);

    $dataDelphi[] = [
        'kode_kriteria' => $kode,
        'nama_kriteria' => $nama,
        'kolom_data'    => $kolom,
        'jenis'         => $jenis,
        'rata_rata'     => $rataRata,
        'std_dev'       => $stdDev
    ];

    $totalRataRata += $rataRata;
}

fclose($file);

if (count($dataDelphi) == 0 || $totalRataRata <= 0) {
    echo "<script>
        alert('Data nilai pakar tidak valid atau kosong.');
        window.location='../pages/konfigurasi_kriteria.php';
    </script>";
    exit;
}

/* Ambang konsensus: rata-rata skor pakar >= 3 (skala 1-5) */
define('AMBANG_KONSENSUS', 3.0);

/* Ambang konvergensi: perubahan rata-rata antar iterasi <= 0.2
   (skala 1-5) dianggap sudah stabil / pakar tidak lagi berubah
   pikiran secara signifikan - iterasi bisa dihentikan. */
define('AMBANG_KONVERGENSI', 0.2);

$jumlahBelumKonsensus   = 0;
$jumlahBelumKonvergen   = 0;

foreach ($dataDelphi as $item) {
    $kode  = mysqli_real_escape_string($conn, $item['kode_kriteria']);
    $nama  = mysqli_real_escape_string($conn, $item['nama_kriteria']);
    $kolom = mysqli_real_escape_string($conn, $item['kolom_data']);
    $jenis = mysqli_real_escape_string($conn, $item['jenis']);

    $rataRata    = $item['rata_rata'];
    $stdDev      = $item['std_dev'];
    $bobotDelphi = $rataRata / $totalRataRata;
    $konsensus   = ($rataRata >= AMBANG_KONSENSUS) ? 'Konsensus Tercapai' : 'Belum Konsensus';

    if ($konsensus == 'Belum Konsensus') {
        $jumlahBelumKonsensus++;
    }

    $cek = mysqli_query($conn, "
        SELECT id_kriteria, iterasi_delphi
        FROM kriteria
        WHERE kode_kriteria = '$kode'
    ");

    $iterasiBaru = 1;
    if ($cek && mysqli_num_rows($cek) > 0) {
        $existing    = mysqli_fetch_assoc($cek);
        $iterasiBaru = (int) $existing['iterasi_delphi'] + 1;
    }

    /* =============================================
       CEK KONVERGENSI TERHADAP ITERASI SEBELUMNYA
       =============================================
       Ambil snapshot rata-rata dari iterasi TERAKHIR yang
       tercatat di riwayat_delphi_iterasi untuk kriteria ini
       (kalau ada), lalu bandingkan dengan rata-rata iterasi
       yang baru saja dihitung. */
    $konvergensi = 'Iterasi Pertama';
    $qRiwayat = mysqli_query($conn, "
        SELECT rata_rata_pakar
        FROM riwayat_delphi_iterasi
        WHERE kode_kriteria = '$kode'
        ORDER BY iterasi_ke DESC
        LIMIT 1
    ");
    if ($qRiwayat && mysqli_num_rows($qRiwayat) > 0) {
        $riwayatTerakhir = mysqli_fetch_assoc($qRiwayat);
        $rataLama = floatval($riwayatTerakhir['rata_rata_pakar']);
        $selisih  = abs($rataRata - $rataLama);
        $konvergensi = ($selisih <= AMBANG_KONVERGENSI) ? 'Konvergen' : 'Belum Konvergen';
    }

    if ($konvergensi == 'Belum Konvergen') {
        $jumlahBelumKonvergen++;
    }

    $adaDiDatabase = ($cek && mysqli_num_rows($cek) > 0);

    if ($adaDiDatabase) {
        mysqli_query($conn, "
            UPDATE kriteria SET
                nama_kriteria         = '$nama',
                kolom_data            = '$kolom',
                jenis                 = '$jenis',
                bobot_delphi          = '$bobotDelphi',
                rata_rata_pakar       = '$rataRata',
                standar_deviasi_pakar = '$stdDev',
                status_konsensus      = '$konsensus',
                status_konvergensi    = '$konvergensi',
                iterasi_delphi        = '$iterasiBaru'
            WHERE kode_kriteria = '$kode'
        ");
    } else {
        mysqli_query($conn, "
            INSERT INTO kriteria (
                kode_kriteria, nama_kriteria, kolom_data, jenis,
                bobot_delphi, rata_rata_pakar, standar_deviasi_pakar,
                status_konsensus, status_konvergensi, iterasi_delphi
            ) VALUES (
                '$kode', '$nama', '$kolom', '$jenis',
                '$bobotDelphi', '$rataRata', '$stdDev',
                '$konsensus', '$konvergensi', 1
            )
        ");
    }

    /* Catat snapshot iterasi ini ke riwayat, supaya iterasi
       BERIKUTNYA punya angka untuk dibandingkan. Riwayat ini
       tidak pernah ditimpa - satu baris baru per iterasi. */
    mysqli_query($conn, "
        INSERT INTO riwayat_delphi_iterasi (
            kode_kriteria, iterasi_ke, rata_rata_pakar, standar_deviasi_pakar
        ) VALUES (
            '$kode', '$iterasiBaru', '$rataRata', '$stdDev'
        )
    ");
}

if (isset($_SESSION['id_user'])) {
    $idUser  = mysqli_real_escape_string($conn, $_SESSION['id_user']);
    $aksiLog = mysqli_real_escape_string($conn, 'Menghitung ulang bobot Delphi (' . count($dataDelphi) . ' kriteria, ' . $jumlahBelumKonsensus . ' belum konsensus, ' . $jumlahBelumKonvergen . ' belum konvergen)');
    mysqli_query($conn, "
        INSERT INTO log_aktivitas (id_user, aksi, tanggal)
        VALUES ('$idUser', '$aksiLog', NOW())
    ");
}

$pesan = "Bobot Delphi berhasil dihitung ulang untuk " . count($dataDelphi) . " kriteria.";
if ($jumlahBelumKonsensus > 0) {
    $pesan .= "\\n\\nPERHATIAN: $jumlahBelumKonsensus kriteria BELUM mencapai konsensus (rata-rata skor pakar < 3) - pertimbangkan untuk mendiskusikan ulang ke pakar pada iterasi berikutnya.";
}
if ($jumlahBelumKonvergen > 0) {
    $pesan .= "\\n\\nPERHATIAN: $jumlahBelumKonvergen kriteria BELUM konvergen (rata-rata berubah lebih dari " . AMBANG_KONVERGENSI . " dibanding iterasi sebelumnya) - disarankan tunjukkan hasil ini ke pakar dan lakukan iterasi lagi.";
}
$pesanJs = json_encode($pesan);

echo "<script>
    alert($pesanJs);
    window.location='../pages/konfigurasi_kriteria.php';
</script>";
