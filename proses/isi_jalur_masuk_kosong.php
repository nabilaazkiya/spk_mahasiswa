<?php
session_start();
include "../config/database.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit;
}

/**
 * =============================================
 * ISI JALUR MASUK YANG KOSONG (ATURAN ALFABETIS)
 * =============================================
 * HANYA menyentuh mahasiswa yang jalur_masuk-nya KOSONG di
 * SEMUA baris riwayat data_akademik mereka (belum pernah ada
 * datanya sama sekali). Mahasiswa yang sudah punya nilai
 * jalur_masuk di baris manapun TIDAK disentuh sama sekali,
 * datanya dibiarkan apa adanya.
 *
 * Mahasiswa yang kosong tsb diurutkan alfabetis berdasarkan
 * nama (nama_mahasiswa), dibagi rata jadi 3 kelompok:
 *   - Kelompok 1 (A-Z paling awal) -> SNMPTN
 *   - Kelompok 2 (tengah)          -> SBMPTN
 *   - Kelompok 3 (paling akhir)    -> Mandiri
 * =============================================
 */

/* 1. Cari NIM yang jalur_masuk-nya kosong di SEMUA baris */
$qKosong = mysqli_query($conn, "
    SELECT nim, MAX(nama_mahasiswa) AS nama_mahasiswa
    FROM data_akademik
    GROUP BY nim
    HAVING SUM(CASE WHEN jalur_masuk IS NOT NULL AND TRIM(jalur_masuk) != '' THEN 1 ELSE 0 END) = 0
    ORDER BY nama_mahasiswa ASC
");

$mahasiswaKosong = [];
if ($qKosong) {
    while ($row = mysqli_fetch_assoc($qKosong)) {
        $mahasiswaKosong[] = $row;
    }
}

if (count($mahasiswaKosong) == 0) {
    echo "<script>
        alert('Tidak ada mahasiswa dengan jalur_masuk kosong di seluruh baris riwayatnya. Tidak ada yang perlu diisi.');
        window.location='../pages/manajemen_data.php';
    </script>";
    exit;
}

/* 2. Bagi rata jadi 3 kelompok (alfabetis, sudah ke-ORDER BY tadi) */
$totalMhs   = count($mahasiswaKosong);
$ukuranGrup = (int) ceil($totalMhs / 3);

$grup = [
    'SNMPTN' => array_slice($mahasiswaKosong, 0, $ukuranGrup),
    'SBMPTN' => array_slice($mahasiswaKosong, $ukuranGrup, $ukuranGrup),
    'Mandiri' => array_slice($mahasiswaKosong, $ukuranGrup * 2)
];

$totalDiisi = 0;
$rincian = [];

foreach ($grup as $jalur => $daftarMhs) {
    if (count($daftarMhs) == 0) {
        continue;
    }
    $jalurEsc = mysqli_real_escape_string($conn, $jalur);
    foreach ($daftarMhs as $mhs) {
        $nimEsc = mysqli_real_escape_string($conn, $mhs['nim']);
        $hasil = mysqli_query($conn, "
            UPDATE data_akademik
            SET jalur_masuk = '$jalurEsc'
            WHERE nim = '$nimEsc'
            AND (jalur_masuk IS NULL OR TRIM(jalur_masuk) = '')
        ");
        if ($hasil) {
            $totalDiisi++;
        }
    }
    $rincian[] = "$jalur: " . count($daftarMhs) . " mahasiswa";
}

/* CATAT LOG */
if (isset($_SESSION['id_user'])) {
    $idUser  = mysqli_real_escape_string($conn, $_SESSION['id_user']);
    $aksiLog = mysqli_real_escape_string($conn, 'Mengisi jalur_masuk kosong berdasarkan urutan alfabetis nama (' . $totalDiisi . ' mahasiswa)');
    mysqli_query($conn, "
        INSERT INTO log_aktivitas (id_user, aksi, tanggal)
        VALUES ('$idUser', '$aksiLog', NOW())
    ");
}

$pesan = "Berhasil mengisi jalur_masuk untuk $totalDiisi mahasiswa (dari total $totalMhs mahasiswa yang datanya kosong):\\n\\n" . implode('\\n', $rincian);
$pesanJs = json_encode($pesan);

echo "<script>
    alert($pesanJs);
    window.location='../pages/manajemen_data.php';
</script>";
