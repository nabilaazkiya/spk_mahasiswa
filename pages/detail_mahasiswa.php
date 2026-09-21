<?php
session_start();
include "../config/database.php";

if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['kaprodi', 'dpa', 'admin'])) {
    header("Location: ../login.php");
    exit;
}

$nim = isset($_GET['nim']) ? mysqli_real_escape_string($conn, $_GET['nim']) : '';

if ($nim == '') {
    header("Location: monitoring.php");
    exit;
}

$backPage      = 'monitoring.php';
$dashboardPage = ($_SESSION['role'] == 'dpa') ? 'dashboard_dpa.php' : (($_SESSION['role'] == 'admin') ? 'dashboard_admin.php' : 'dashboard_kaprodi.php');
$roleLabel     = ($_SESSION['role'] == 'dpa') ? 'Dosen PA' : (($_SESSION['role'] == 'admin') ? 'Admin' : 'Kaprodi');

$whereDpa = "";

if ($_SESSION['role'] == 'dpa') {
    $idDpa    = mysqli_real_escape_string($conn, $_SESSION['id_user']);
    $whereDpa = " AND m.id_user = '$idDpa'";
}

$query = mysqli_query($conn, "
    SELECT 
        d.*,

        r.nilai_preferensi AS nilai_topsis,
        r.ranking AS ranking_topsis,

        s.nilai_preferensi AS nilai_saw,
        s.ranking AS ranking_saw,

        h.status_early_warning
    FROM data_akademik_terbaru d
    INNER JOIN mahasiswa m ON d.nim = m.nim
    LEFT JOIN ranking_topsis_terbaru r ON d.nim = r.nim
    LEFT JOIN ranking_saw s ON d.nim = s.nim
    LEFT JOIN hasil_evaluasi_terbaru h ON d.nim = h.nim
    WHERE d.nim = '$nim'
    $whereDpa
    LIMIT 1
");

$data = mysqli_fetch_assoc($query);

if (!$data) {
    echo "
    <script>
        alert('Data mahasiswa tidak ditemukan.');
        window.location='$backPage';
    </script>
    ";
    exit;
}

/* ═══════════════════════════════════════════════════════
   ANALISIS CUTI & INDIKASI DO
   =========================================================== */
$riwayatLengkapQuery = mysqli_query($conn, "
    SELECT semester, status_sia, ipk, sks_lulus, sks_diambil
    FROM data_akademik
    WHERE nim = '$nim'
    ORDER BY semester ASC
");

$riwayatPerSemester = [];
while ($rr = mysqli_fetch_assoc($riwayatLengkapQuery)) {
    $riwayatPerSemester[(int) $rr['semester']] = $rr;
}

$semesterTerbaruMhs = !empty($riwayatPerSemester) ? max(array_keys($riwayatPerSemester)) : 0;

/* Jumlah semester cuti/tidak aktif yang terjadi PADA semester <= 4 */
$jumlahCutiHingga4 = 0;
foreach ($riwayatPerSemester as $smt => $rr) {
    if ($smt <= 4 && strtolower(trim((string) $rr['status_sia'])) === 'tidak_aktif') {
        $jumlahCutiHingga4++;
    }
}

/* Checkpoint pertama bergeser sebanyak jumlah cuti:
   0 cuti -> semester 4, 1 cuti -> semester 5,
   2 cuti -> semester 6 */
$checkpointPertama = 4 + $jumlahCutiHingga4;

$kesimpulanCuti = '';
$indikasiDoPertama = null; // null = belum bisa dievaluasi (belum sampai checkpoint)

if ($jumlahCutiHingga4 === 0) {
    $kesimpulanCuti = 'Tidak terdapat data cuti pada semester hingga semester 4 yang memengaruhi periode analisis DO.';
} else {
    $kesimpulanCuti = sprintf(
        'Mahasiswa memiliki %d semester cuti pada masa studi hingga semester 4. Semester cuti tidak diperhitungkan dalam proses TOPSIS, namun tetap diperhitungkan dalam masa studi. Oleh karena itu, analisis indikasi DO ditinjau pada semester %d.',
        $jumlahCutiHingga4,
        $checkpointPertama
    );
}

/* Evaluasi kriteria DO baseline PADA data semester checkpoint
   (kalau datanya sudah ada / semester itu sudah dilewati):
     - IPK < 2.00, ATAU
     - cuti >= 2 kali (pada semester <= 4), ATAU
     - SKS Lulus kumulatif < 40 pada checkpoint tsb. */
$alasanDoPertama = [];
if ($semesterTerbaruMhs >= $checkpointPertama && isset($riwayatPerSemester[$checkpointPertama])) {
    $dataCheckpoint = $riwayatPerSemester[$checkpointPertama];

    if (is_numeric($dataCheckpoint['ipk']) && floatval($dataCheckpoint['ipk']) < 2.00) {
        $alasanDoPertama[] = 'IPK di semester ' . $checkpointPertama . ' kurang dari 2,00 (' . number_format($dataCheckpoint['ipk'], 2) . ')';
    }
    if ($jumlahCutiHingga4 >= 2) {
        $alasanDoPertama[] = 'terdapat ' . $jumlahCutiHingga4 . ' kali cuti pada semester <= 4';
    }
    if (is_numeric($dataCheckpoint['sks_lulus']) && floatval($dataCheckpoint['sks_lulus']) < 40) {
        $alasanDoPertama[] = 'jumlah SKS lulus kumulatif di semester ' . $checkpointPertama . ' belum mencapai 40 (' . floatval($dataCheckpoint['sks_lulus']) . ')';
    }

    $indikasiDoPertama = !empty($alasanDoPertama);
}

/* Checkpoint kedua: sekitar batas akhir masa studi (semester 14).
   Risiko DO tinggi jika mahasiswa sudah mencapai/lewat semester 14
   namun Sisa Masa Studi (data terbaru) sudah habis (<= 0). */
$indikasiDoKedua = null;
if ($semesterTerbaruMhs >= 14) {
    $sisaMasaStudiTerbaru = isset($data['sisa_masa_studi']) ? floatval($data['sisa_masa_studi']) : null;
    $indikasiDoKedua = ($sisaMasaStudiTerbaru !== null && $sisaMasaStudiTerbaru <= 0);
}

/* Ringkasan indikasi DO dalam SATU kalimat, dipakai untuk
   digabung ke paragraf "Keterangan" di kotak Kesimpulan
   Evaluasi Akademik (lihat $ringkasanDo di bawah).*/
if ($indikasiDoPertama === true || $indikasiDoKedua === true) {
    $ringkasanDo = 'Terdapat indikasi risiko DO (Drop Out).'
        . ($jumlahCutiHingga4 > 0 ? ' ' . $kesimpulanCuti : '')
        . (!empty($alasanDoPertama) ? ' Penyebab pada checkpoint semester ' . $checkpointPertama . ': ' . implode('; ', $alasanDoPertama) . '.' : '')
        . ($indikasiDoKedua === true ? ' Mahasiswa juga sudah melewati batas masa studi (checkpoint semester 14).' : '')
        . ' Rekomendasi: Dosen PA disarankan segera melakukan konsultasi akademik intensif, meninjau ulang rencana studi, dan melibatkan Kaprodi/pihak terkait untuk mencegah risiko Drop Out.';
} elseif ($indikasiDoPertama === false || $indikasiDoKedua === false) {
    $ringkasanDo = 'Belum terdeteksi indikasi risiko DO (Drop Out) dari kriteria IPK, cuti, maupun SKS pada checkpoint yang sudah dilewati.';
} elseif ($semesterTerbaruMhs < $checkpointPertama) {
    /* Mahasiswa memang BELUM sampai semester checkpoint - wajar
       kalau belum bisa dievaluasi, jadi pesan ini tetap tampil. */
    $ringkasanDo = 'Indikasi risiko DO (Drop Out) belum dapat dievaluasi karena mahasiswa belum mencapai checkpoint semester ' . $checkpointPertama . '.';
} else {
    /* mahasiswa SUDAH di atas semester checkpoint,
       hanya saja data akademik pada semester checkpoint itu
       tidak ditemukan (mis. ada semester yang datanya belum
       diupload). Pesan "belum mencapai checkpoint" akan
       menyesatkan kalau tetap ditampilkan di sini, jadi bagian
       Indikasi DO cukup dikosongkan (tidak ditampilkan). */
    $ringkasanDo = '';
}

/* Ringkasan predikat akademik (cumlaude) berdasarkan IPK,
   SKS bernilai di bawah B, dan skor TOEFL - mengikuti syarat
   cumlaude yang diminta: IPK >= 3,50, TIDAK ADA mata kuliah
   dengan nilai di bawah B (sks_nilai_kurang_b = 0), dan skor
   TOEFL minimal 450. Ketiga syarat harus terpenuhi sekaligus. */
$ipkTerbaru        = isset($data['ipk']) && is_numeric($data['ipk']) ? floatval($data['ipk']) : null;
$sksKurangBTerbaru = isset($data['sks_nilai_kurang_b']) && is_numeric($data['sks_nilai_kurang_b']) ? floatval($data['sks_nilai_kurang_b']) : null;
$toeflTerbaru      = isset($data['skor_toefl']) && is_numeric($data['skor_toefl']) ? floatval($data['skor_toefl']) : null;

if ($ipkTerbaru === null) {
    $ringkasanPredikat = 'Predikat akademik (cumlaude) belum dapat ditentukan karena data IPK belum tersedia.';
} else {
    $syaratCumlaude = [];
    if ($ipkTerbaru < 3.50) {
        $syaratCumlaude[] = 'IPK belum mencapai 3,50 (saat ini ' . number_format($ipkTerbaru, 2) . ')';
    }
    if ($sksKurangBTerbaru === null) {
        $syaratCumlaude[] = 'data jumlah SKS nilai di bawah B belum tersedia';
    } elseif ($sksKurangBTerbaru > 0) {
        $syaratCumlaude[] = 'masih terdapat mata kuliah dengan nilai di bawah B (' . $sksKurangBTerbaru . ' SKS)';
    }
    if ($toeflTerbaru === null) {
        $syaratCumlaude[] = 'data skor TOEFL belum tersedia';
    } elseif ($toeflTerbaru < 450) {
        $syaratCumlaude[] = 'skor TOEFL belum mencapai 450 (saat ini ' . $toeflTerbaru . ')';
    }

    if (empty($syaratCumlaude)) {
        $ringkasanPredikat = 'Mahasiswa memenuhi seluruh syarat predikat Cumlaude saat ini: IPK ' . number_format($ipkTerbaru, 2) . ' (>= 3,50), tidak ada mata kuliah dengan nilai di bawah B, dan skor TOEFL ' . $toeflTerbaru . ' (>= 450). Predikat final tetap ditentukan saat kelulusan dalam masa studi yang wajar.';
    } else {
        $ringkasanPredikat = 'Mahasiswa belum memenuhi syarat predikat Cumlaude karena: ' . implode('; ', $syaratCumlaude) . '.';
    }
}

/* Bagian HTML berjudul untuk Indikasi DO & Predikat Cumlaude,
   dipakai di kotak Kesimpulan Evaluasi Akademik (baik saat
   TOPSIS sudah maupun belum dijalankan). Bagian Indikasi DO
   dikosongkan total (termasuk judulnya) kalau $ringkasanDo
   kosong, supaya tidak menampilkan judul tanpa isi. */
$bagianDo = $ringkasanDo !== ''
    ? '<strong>Indikasi DO (Drop Out):</strong> ' . htmlspecialchars($ringkasanDo)
    : '';
$bagianPredikat = '<strong>Predikat Kelulusan (Cumlaude):</strong> ' . htmlspecialchars($ringkasanPredikat);

$spearman = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT *
    FROM uji_spearman
    ORDER BY id_spearman DESC
    LIMIT 1
"));

$riwayatQuery = mysqli_query($conn, "
    SELECT semester, ip_semester
    FROM data_akademik
    WHERE nim = '$nim'
    ORDER BY semester ASC
");

$labelSemester = [];
$dataIpk = [];

while ($r = mysqli_fetch_assoc($riwayatQuery)) {
    $labelSemester[] = 'Semester ' . $r['semester'];
    $dataIpk[] = floatval($r['ip_semester']);
}

/* RIWAYAT SKOR TOPSIS PER PERIODE
   Diambil dari tabel ranking_topsis ASLI (bukan view
   ranking_topsis_terbaru), supaya semua periode evaluasi
   yang pernah dihitung untuk mahasiswa ini ikut tampil -
   ini yang jadi sumber grafik tren TOPSIS antar periode.

   FILTER: hanya periode berformat "Semester XX" yang dipakai.
   Baris lama berlabel tanggal (mis. "2026-07-09") adalah sisa
   dari sebelum periode_evaluasi memakai nomor semester -
   disaring di sini supaya tidak muncul bercampur di grafik.
   Data lama tsb sudah tergantikan oleh hasil "Hitung Ulang
   Histori TOPSIS" yang berformat semester. */
$riwayatTopsisQuery = mysqli_query($conn, "
    SELECT periode_evaluasi, nilai_preferensi, ranking
    FROM ranking_topsis
    WHERE nim = '$nim'
    AND periode_evaluasi LIKE 'Semester %'
    ORDER BY periode_evaluasi ASC
");

$labelPeriodeTopsis = [];
$dataSkorTopsis     = [];
$dataRankingTopsis  = [];

while ($rt = mysqli_fetch_assoc($riwayatTopsisQuery)) {
    /* kosongkan/lewati titik data pada semester di mana
       mahasiswa berstatus cuti (tidak_aktif) - semester cuti tidak
       punya aktivitas akademik sehingga tidak relevan ditampilkan
       di grafik tren TOPSIS. */
    $semesterAngka = (int) preg_replace('/\D/', '', $rt['periode_evaluasi']);
    $statusSemesterIni = $riwayatPerSemester[$semesterAngka]['status_sia'] ?? null;
    if (strtolower(trim((string) $statusSemesterIni)) === 'tidak_aktif') {
        continue;
    }

    $labelPeriodeTopsis[] = $rt['periode_evaluasi'];
    $dataSkorTopsis[]     = floatval($rt['nilai_preferensi']);
    $dataRankingTopsis[]  = intval($rt['ranking']);
}

$status = $data['status_early_warning'] ?? 'Belum Diproses';
$statusClass = 'status-aktif';

if (strtolower($status) == 'kritis') {
    $statusClass = 'status-nonaktif';
} elseif (strtolower($status) == 'waspada') {
    $statusClass = 'status-waspada';
} elseif (strtolower($status) == 'sangat baik') {
    $statusClass = 'status-aktif';
} elseif (strtolower($status) == 'aman') {
    $statusClass = 'status-aktif';
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Mahasiswa</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=12">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>

<body>

<div class="dashboard-wrapper">

    <button type="button" class="sidebar-toggle-btn" id="sidebarToggleBtn" onclick="toggleSidebar()" aria-label="Buka menu">
        &#9776;
    </button>
    <div class="sidebar-backdrop" id="sidebarBackdrop" onclick="closeSidebar()"></div>

    <aside class="section-sidebar" id="sectionSidebar">
        <button type="button" class="sidebar-close-btn" onclick="closeSidebar()" aria-label="Tutup menu">&#10005;</button>
        <div class="logo-area">
            <img src="../assets/img/logo_psti.jpg" class="sidebar-logo" alt="Logo PSTI">
            <span class="logo-text">Prioritas Mahasiswa<br>Bimbingan</span>
        </div>

        <nav class="nav-menu">
            <a href="<?php echo $dashboardPage; ?>" class="nav-link">Dashboard</a>
            <a href="<?php echo $backPage; ?>" class="nav-link active">Monitoring</a>
            <?php if ($_SESSION['role'] === 'admin'): ?>
            <a href="manajemen_data.php" class="nav-link">Manajemen Data</a>
            <a href="konfigurasi_kriteria.php" class="nav-link">Konfigurasi Kriteria</a>
            <?php endif; ?>
        </nav>

        <a href="../logout.php" class="logout-button">LOGOUT</a>
    </aside>

    <main class="dashboard-main">

        <section class="section-topbar">
            <h3>Detail Mahasiswa</h3>

            <div class="admin-info">
                <div>
                    <strong><?php echo $_SESSION['nama_lengkap']; ?></strong><br>
                    <small><?php echo $roleLabel; ?></small>
                </div>
                <div class="admin-avatar"></div>
            </div>
        </section>

        <section class="detail-profile">
            <div class="profile-icon">👤</div>

            <div>
                <h2><?php echo $data['nama_mahasiswa']; ?></h2>
                <p><?php echo $data['nim']; ?></p>
                <p>Semester <?php echo $data['semester']; ?></p>

                <span class="status-badge <?php echo $statusClass; ?>">
                    <?php echo $status; ?>
                </span>
                <?php if (strtolower(trim((string) ($data['status_sia'] ?? ''))) === 'tidak_aktif'): ?>
                    <span class="status-badge status-cuti">Tidak Aktif</span>
                <?php endif; ?>
            </div>
        </section>

        <section class="detail-grid">

            <div class="detail-card">
                <h4>Indeks Prestasi Kumulatif (IPK)</h4>
                <p><?php echo $data['ipk']; ?></p>
                <small>Tren: berdasarkan data akademik terbaru</small>
            </div>

            <div class="detail-card">
                <h4>SKS Lulus</h4>
                <p><?php echo $data['sks_lulus']; ?></p>
                <small>SKS Diambil: <?php echo $data['sks_diambil']; ?></small>
            </div>

            <div class="detail-card">
                <h4 class="info-clickable-text" onclick="showInfoModal('preferensi_model')">Preferensi Model</h4>

                <p>
                    <?php 
                    echo $spearman 
                        ? number_format($spearman['rs'], 4) 
                        : '-'; 
                    ?>
                </p>

                <small>
                    <?php echo $spearman['preferensi_model'] ?? 'Belum diuji'; ?>
                    <br><br>

                    Nilai mahasiswa ini:
                    <br>

                    TOPSIS:
                    <?php
                    echo isset($data['nilai_topsis'])
                        ? number_format($data['nilai_topsis'], 4)
                        : '-';
                    ?>

                    <br>

                    SAW:
                    <?php
                    echo isset($data['nilai_saw'])
                        ? number_format($data['nilai_saw'], 4)
                        : '-';
                    ?>
                </small>
            </div>

        </section>

        <section class="chart-detail-row">

            <div class="chart-detail-box">
                <h3 class="info-clickable-text" onclick="showInfoModal('grafik_ipk_semester')">Tren Performa Semester (IP Semester)</h3>
                <div class="chart-canvas-wrapper"><canvas id="grafikIpk"></canvas></div>
            </div>

            <div class="chart-detail-box">
                <h3 class="info-clickable-text" onclick="showInfoModal('grafik_tren_topsis')">Tren Performa Mahasiswa Berdasarkan TOPSIS</h3>
                <?php if (count($dataSkorTopsis) >= 1): ?>
                    <div class="chart-canvas-wrapper"><canvas id="grafikTrenTopsis"></canvas></div>
                    <small class="small-note-top8">
                        <?php if (count($dataSkorTopsis) >= 2): ?>
                            Menampilkan perubahan skor preferensi TOPSIS mahasiswa ini dari periode ke periode. Skor mendekati 1.0 = performa lebih baik.
                        <?php else: ?>
                            Baru ada 1 periode evaluasi TOPSIS. Grafik tren antar periode akan muncul setelah periode berikutnya diproses.
                        <?php endif; ?>
                    </small>
                <?php else: ?>
                    <div class="chart-placeholder-box">
                        <span class="chart-placeholder-icon">&#128202;</span>
                        <span>Data belum tersedia. Proses TOPSIS belum dijalankan.</span>
                    </div>
                <?php endif; ?>
            </div>

        </section>

        <section class="info-table-box">
            <h3>Informasi Akademik Tambahan</h3>

            <div class="table-scroll-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Dosen PA</th>
                        <th>Skor TOEFL</th>
                        <th>Jumlah Mengulang</th>
                        <th>Sisa Masa Studi</th>
                        <th>Jalur Masuk</th>
                        <th>Absensi</th>
                        <th>SKS Nilai Kurang B</th>
                    </tr>
                </thead>

                <tbody>
                    <tr>
                        <td><?php echo $data['dosen_pa']; ?></td>
                        <td><?php echo $data['skor_toefl']; ?></td>
                        <td><?php echo $data['jml_mengulang']; ?></td>
                        <td><?php echo $data['sisa_masa_studi']; ?></td>
                        <td><?php echo $data['jalur_masuk']; ?></td>
                        <td><?php echo $data['absensi']; ?></td>
                        <td><?php echo $data['sks_nilai_kurang_b']; ?></td>
                    </tr>
                </tbody>
            </table>
            </div>

            <!-- ═══════════════════════════════════════
                 KESIMPULAN KATEGORI MAHASISWA
                 ═══════════════════════════════════════ -->
            <?php
            $nilaiPref   = isset($data['nilai_topsis']) ? floatval($data['nilai_topsis']) : null;
            $rankingMhs  = $data['ranking_topsis'] ?? null;
            $statusLabel = $data['status_early_warning'] ?? null;

            if ($nilaiPref !== null && $statusLabel !== null):

                /* Warna badge sesuai kategori */
                $warnaBg = [
                    'Kritis'      => '#fdecea',
                    'Waspada'     => '#fff8e1',
                    'Aman'        => '#e8f5e9',
                    'Sangat Baik' => '#e0f2f1'
                ];
                $warnaBorder = [
                    'Kritis'      => '#e74c3c',
                    'Waspada'     => '#f39c12',
                    'Aman'        => '#2ecc71',
                    'Sangat Baik' => '#27ae60'
                ];
                $warnaIkon = [
                    'Kritis'      => '🔴',
                    'Waspada'     => '🟠',
                    'Aman'        => '🟢',
                    'Sangat Baik' => '✅'
                ];
                $rekomendasiMap = [
                    'Kritis'      => 'Mahasiswa memerlukan perhatian segera. Dosen PA disarankan untuk segera melakukan konsultasi akademik, mengevaluasi beban studi, dan mempertimbangkan program pendampingan intensif.',
                    'Waspada'     => 'Mahasiswa perlu dipantau secara berkala. Dosen PA disarankan untuk menjadwalkan konsultasi rutin dan membantu mahasiswa mengidentifikasi kendala akademik yang dihadapi.',
                    'Aman'        => 'Kondisi akademik mahasiswa cukup baik. Dosen PA disarankan untuk mempertahankan motivasi dan mendorong peningkatan performa di semester berikutnya.',
                    'Sangat Baik' => 'Kondisi akademik mahasiswa sangat baik. Dosen PA disarankan untuk mendorong mahasiswa mengikuti kegiatan akademik atau penelitian yang lebih menantang.'
                ];

                $rentangMap = [
                    'Kritis'      => '0.00 – 0.25',
                    'Waspada'     => '0.26 – 0.50',
                    'Aman'        => '0.51 – 0.75',
                    'Sangat Baik' => '0.76 – 1.00'
                ];
                $rentang    = $rentangMap[$statusLabel] ?? '-';

               
                function amankanNilaiAnalisis($n)
                {
                    if (!is_numeric($n)) return 0;
                    $n = floatval($n);
                    return (is_nan($n) || is_infinite($n)) ? 0 : $n;
                }

                function ambilNilaiAnalisis($mhs, $kolom)
                {
                    if (!isset($mhs[$kolom]) || $mhs[$kolom] === null || $mhs[$kolom] === '') return 0;
                    $nilai = $mhs[$kolom];
                    if ($kolom == 'jalur_masuk') {
                        $l = strtolower(trim($nilai));
                        if (strpos($l, 'beasiswa') !== false && strpos($l, 'internasional') !== false) return 5;
                        if ($l == 'snbp' || $l == 'snmptn') return 4;
                        if ($l == 'snbt' || $l == 'sbmptn') return 3;
                        if ($l == 'mandiri') return 2;
                        return 1;
                    }
                    /* PERBAIKAN: syarat kelulusan program studi adalah
                       minimal 145 SKS. Kalau SKS Lulus + SKS Diambil
                       (semester berjalan) SUDAH mencapai/melewati 145,
                       mahasiswa sudah aman secara total beban studi -
                       SKS Diambil semester ini yang sedikit BUKAN
                       masalah. Disamakan dengan ambilNilaiTopsis() di
                       proses/topsis_proses.php supaya konsisten dan
                       tidak ada lagi kriteria yang salah tercantum
                       sebagai "menekan skor" di halaman ini. */
                    if ($kolom == 'sks_lulus' || $kolom == 'sks_diambil') {
                        $sksLulusVal   = (isset($mhs['sks_lulus']) && is_numeric($mhs['sks_lulus']))
                            ? floatval($mhs['sks_lulus']) : 0;
                        $sksDiambilVal = (isset($mhs['sks_diambil']) && is_numeric($mhs['sks_diambil']))
                            ? floatval($mhs['sks_diambil']) : 0;
                        $totalSksMenujuKelulusan = $sksLulusVal + $sksDiambilVal;

                        if ($totalSksMenujuKelulusan >= 145) {
                            return 1;
                        }

                        $semester = isset($mhs['semester']) ? floatval($mhs['semester']) : 0;
                        if ($semester <= 0) return 0;
                        $sksIdeal  = $semester * 20;
                        $sksAktual = is_numeric($nilai) ? floatval($nilai) : 0;
                        return $sksIdeal == 0 ? 0 : ($sksAktual / $sksIdeal);
                    }
                    if ($kolom == 'skor_toefl') {
                        $skor = is_numeric($nilai) ? floatval($nilai) : 0;
                        if ($skor < 400) return 0;
                        if ($skor < 450) return 1;
                        return 2;
                    }
                    return is_numeric($nilai) ? floatval($nilai) : 0;
                }

                /* Untuk teks "Keterangan" yang ditampilkan ke pengguna,
                   SKS Lulus/Diambil sebaiknya tetap tampil sebagai
                   angka SKS asli (mis. "143"), bukan rasio (mis.
                   "0.85") yang dipakai di balik layar untuk
                   perhitungan kontribusi - supaya mudah dibaca. */
                function ambilNilaiTampilan($mhs, $kolom)
                {
                    if ($kolom == 'sks_lulus' || $kolom == 'sks_diambil' || $kolom == 'skor_toefl') {
                        return isset($mhs[$kolom]) && is_numeric($mhs[$kolom]) ? floatval($mhs[$kolom]) : 0;
                    }
                    /* PERBAIKAN: Jalur Masuk ditampilkan teks aslinya
                       (mis. "SNMPTN"), bukan angka tingkat ordinal
                       (1-5) yang dipakai di balik layar untuk
                       perhitungan TOPSIS - supaya tidak membingungkan
                       Dosen PA yang membaca Keterangan. */
                    if ($kolom == 'jalur_masuk') {
                        return isset($mhs[$kolom]) && $mhs[$kolom] !== '' ? $mhs[$kolom] : '-';
                    }
                    return ambilNilaiAnalisis($mhs, $kolom);
                }

                $kontribusiKriteria = [];

                $qKritDetail = mysqli_query($conn, "
                    SELECT * FROM kriteria
                    WHERE kolom_data IS NOT NULL AND kolom_data != '' AND bobot_delphi > 0
                    ORDER BY id_kriteria ASC
                ");
                $daftarKriteriaAktif = [];
                while ($rk = mysqli_fetch_assoc($qKritDetail)) {
                    $daftarKriteriaAktif[] = $rk;
                }

                if (!empty($daftarKriteriaAktif) && isset($data['semester'])) {
                    $semesterMhs = (int) $data['semester'];

                    /* Kohort: mahasiswa lain di semester yang sama,
                       dari snapshot data_akademik terbaru mereka
                       PADA semester itu (sama seperti backfill). */
                    $kohort = [];
                    $stmtKohort = mysqli_prepare($conn, "
                        SELECT da.*
                        FROM data_akademik da
                        INNER JOIN (
                            SELECT nim, MAX(id_data) AS id_data_terbaru
                            FROM data_akademik
                            WHERE semester = ?
                            GROUP BY nim
                        ) t ON da.nim = t.nim AND da.id_data = t.id_data_terbaru
                    ");
                    mysqli_stmt_bind_param($stmtKohort, "i", $semesterMhs);
                    mysqli_stmt_execute($stmtKohort);
                    $resKohort = mysqli_stmt_get_result($stmtKohort);
                    while ($rowK = mysqli_fetch_assoc($resKohort)) {
                        $kohort[] = $rowK;
                    }
                    mysqli_stmt_close($stmtKohort);

                    /* Perlu minimal 2 mahasiswa (termasuk diri sendiri)
                       supaya normalisasi & solusi ideal punya arti. */
                    if (count($kohort) >= 2) {
                        $matriksA = [];
                        foreach ($kohort as $mhs) {
                            foreach ($daftarKriteriaAktif as $krit) {
                                $matriksA[$mhs['nim']][$krit['id_kriteria']] = amankanNilaiAnalisis(
                                    ambilNilaiAnalisis($mhs, $krit['kolom_data'])
                                );
                            }
                        }

                        $pembagiA = [];
                        foreach ($daftarKriteriaAktif as $krit) {
                            $idK = $krit['id_kriteria'];
                            $totalKuadrat = 0;
                            foreach ($kohort as $mhs) {
                                $totalKuadrat += pow($matriksA[$mhs['nim']][$idK], 2);
                            }
                            $pembagiA[$idK] = amankanNilaiAnalisis(sqrt($totalKuadrat));
                        }

                        $terbobotA = [];
                        foreach ($kohort as $mhs) {
                            foreach ($daftarKriteriaAktif as $krit) {
                                $idK   = $krit['id_kriteria'];
                                $bobot = amankanNilaiAnalisis($krit['bobot_delphi']);
                                $r     = $pembagiA[$idK] == 0 ? 0 : $matriksA[$mhs['nim']][$idK] / $pembagiA[$idK];
                                $terbobotA[$mhs['nim']][$idK] = amankanNilaiAnalisis($r * $bobot);
                            }
                        }

                        $idealPos = [];
                        $idealNeg = [];
                        foreach ($daftarKriteriaAktif as $krit) {
                            $idK   = $krit['id_kriteria'];
                            $jenis = strtolower(trim($krit['jenis']));
                            $nilaiKolom = [];
                            foreach ($kohort as $mhs) {
                                $nilaiKolom[] = $terbobotA[$mhs['nim']][$idK];
                            }
                            if ($jenis == 'benefit') {
                                $idealPos[$idK] = amankanNilaiAnalisis(max($nilaiKolom));
                                $idealNeg[$idK] = amankanNilaiAnalisis(min($nilaiKolom));
                            } else {
                                $idealPos[$idK] = amankanNilaiAnalisis(min($nilaiKolom));
                                $idealNeg[$idK] = amankanNilaiAnalisis(max($nilaiKolom));
                            }
                        }

                        if (isset($terbobotA[$nim])) {
                            foreach ($daftarKriteriaAktif as $krit) {
                                $idK    = $krit['id_kriteria'];
                                $vij    = $terbobotA[$nim][$idK];
                                $rentangIdeal = abs($idealPos[$idK] - $idealNeg[$idK]);

                                /* kontribusi: 1 = persis di solusi ideal positif,
                                   0 = persis di solusi ideal negatif.
                                   CATATAN: ini rasio DALAM rentang kriteria itu
                                   sendiri, jadi bobot Delphi-nya saling coret
                                   (cancel out) - dipakai hanya untuk menentukan
                                   SISI (menekan/mendukung), bukan seberapa besar
                                   pengaruhnya. */
                                $kontribusi = $rentangIdeal == 0
                                    ? 0.5
                                    : amankanNilaiAnalisis(1 - (abs($vij - $idealPos[$idK]) / $rentangIdeal));

                                /* PERBAIKAN: "dampak" = jarak terbobot MENTAH ke
                                   solusi ideal positif (|vij - idealPos|, dengan
                                   vij & idealPos SUDAH dikali bobot Delphi).
                                   Beda dengan $kontribusi di atas, nilai ini TIDAK
                                   dinormalisasi ulang per kriteria, sehingga bobot
                                   Delphi-nya tetap berpengaruh - kriteria berbobot
                                   rendah otomatis punya dampak kecil meski posisi
                                   relatifnya ekstrem, sesuai kontribusi aslinya ke
                                   D+/D- pada rumus TOPSIS. Ini yang dipakai untuk
                                   memilih & mengurutkan kriteria yang paling
                                   menekan/mendukung skor. */
                                $dampak = amankanNilaiAnalisis(abs($vij - $idealPos[$idK]));

                                $kontribusiKriteria[] = [
                                    'nama'       => $krit['nama_kriteria'],
                                    'nilai_asli' => ambilNilaiTampilan($data, $krit['kolom_data']),
                                    'jenis'      => strtolower(trim($krit['jenis'])),
                                    'kontribusi' => $kontribusi,
                                    'dampak'     => $dampak
                                ];
                            }
                        }
                    }
                }

                /* Urutkan dari yang PALING MENEKAN (kontribusi terendah)
                   ke yang PALING MENDUKUNG (kontribusi tertinggi) */
                usort($kontribusiKriteria, function ($a, $b) {
                    return $a['kontribusi'] <=> $b['kontribusi'];
                });

                /* PERBAIKAN: kalau mahasiswa sedang cuti/tidak aktif,
                   skor TOPSIS-nya dihitung dari data akademik lama
                   (semester sebelum cuti), jadi tidak relevan menuding
                   kriteria tertentu "menekan" atau "mendukung" skor
                   saat ini - daftar ini disembunyikan untuk mahasiswa
                   berstatus tidak aktif. */
                $mahasiswaTidakAktif = strtolower(trim((string) ($data['status_sia'] ?? ''))) === 'tidak_aktif';

                $penjelasanKriteria = '';
                if (!$mahasiswaTidakAktif && count($kontribusiKriteria) >= 2) {
                    /* kriteria hanya dianggap "menekan" jika kontribusinya
                       benar-benar < 0.5 (lebih dekat ke solusi ideal NEGATIF), dan
                       hanya dianggap "mendukung" jika > 0.5 (lebih dekat ke solusi
                       ideal POSITIF). */
                    $kandidatMenekan = array_filter($kontribusiKriteria, function ($k) {
                        return $k['kontribusi'] < 0.5;
                    });
                    $kandidatMendukung = array_filter($kontribusiKriteria, function ($k) {
                        return $k['kontribusi'] > 0.5;
                    });

                    /* dari kandidat menekan/mendukung, urutkan
                       berdasarkan 'dampak' (yang memuat bobot Delphi). */
                    $kandidatMenekan   = array_values($kandidatMenekan);
                    $kandidatMendukung = array_values($kandidatMendukung);

                    usort($kandidatMenekan, function ($a, $b) {
                        return $b['dampak'] <=> $a['dampak'];
                    });
                    usort($kandidatMendukung, function ($a, $b) {
                        return $a['dampak'] <=> $b['dampak'];
                    });

                    $jumlahSorotMenekan   = min(2, count($kandidatMenekan));
                    $jumlahSorotMendukung = min(2, count($kandidatMendukung));

                    $menekan   = array_slice($kandidatMenekan, 0, $jumlahSorotMenekan);
                    $mendukung = array_slice($kandidatMendukung, 0, $jumlahSorotMendukung);

                    $formatDaftarList = function ($daftar) {
                        $items = array_map(function ($k) {
                            $nilai = is_numeric($k['nilai_asli'])
                                ? rtrim(rtrim(number_format($k['nilai_asli'], 2), '0'), '.')
                                : $k['nilai_asli'];
                            return '<li>' . htmlspecialchars($k['nama']) . ' (' . htmlspecialchars($nilai) . ')</li>';
                        }, $daftar);
                        return '<ul class="list-tight">' . implode('', $items) . '</ul>';
                    };

                    if (!empty($menekan)) {
                        $penjelasanKriteria .= '<br><strong>Kriteria yang paling menekan skor:</strong>'
                            . $formatDaftarList($menekan);
                    }
                    if (!empty($mendukung)) {
                        $penjelasanKriteria .= '<br><strong>Kriteria yang paling mendukung skor:</strong>'
                            . $formatDaftarList($mendukung);
                    }
                }

                /*prediksi predikat Cumlaude hanya relevan
                   ditampilkan untuk mahasiswa berkategori "Sangat Baik"
                   atau "Aman" */
                $tampilkanPredikat = in_array($statusLabel, ['Sangat Baik', 'Aman'], true);

                $keterangan = sprintf(
                    'Mahasiswa masuk kategori "%s" karena nilai preferensi TOPSIS-nya %s berada pada rentang %s.%s%s%s',
                    $statusLabel,
                    number_format($nilaiPref, 4),
                    $rentang,
                    $penjelasanKriteria,
                    ($bagianDo !== '' ? '<div class="keterangan-block">' . $bagianDo . '</div>' : ''),
                    ($tampilkanPredikat ? '<div class="keterangan-block">' . $bagianPredikat . '</div>' : '')
                );

                $kelasKesimpulanMap = [
                    'Kritis'      => 'kesimpulan-kritis',
                    'Waspada'     => 'kesimpulan-waspada',
                    'Aman'        => 'kesimpulan-aman',
                    'Sangat Baik' => 'kesimpulan-sangat-baik'
                ];
                $bg          = $warnaBg[$statusLabel]     ?? '#f8f9fa';
                $border      = $warnaBorder[$statusLabel] ?? '#ccc';
                $ikon        = $warnaIkon[$statusLabel]    ?? 'ℹ️';
                $rekomendasi = $rekomendasiMap[$statusLabel] ?? '-';
                $kelasKesimpulan = $kelasKesimpulanMap[$statusLabel] ?? 'kesimpulan-netral';

                /* Faktor akademik yang berkontribusi */
                $faktor = [];
                if (!empty($data['ipk']))               $faktor[] = 'IPK: ' . number_format($data['ipk'], 2);
                if (!empty($data['sks_lulus']))          $faktor[] = 'SKS Lulus: ' . $data['sks_lulus'];
                if (!empty($data['absensi']))            $faktor[] = 'Absensi: ' . $data['absensi'] . '%';
                if (!empty($data['jml_mengulang']))      $faktor[] = 'Jumlah Mengulang: ' . $data['jml_mengulang'];
                if (!empty($data['sks_nilai_kurang_b'])) $faktor[] = 'SKS Nilai < B: ' . $data['sks_nilai_kurang_b'];
                if (!empty($data['sisa_masa_studi']))    $faktor[] = 'Sisa Masa Studi: ' . $data['sisa_masa_studi'] . ' semester';
                if (!empty($data['skor_toefl']))         $faktor[] = 'Skor TOEFL: ' . $data['skor_toefl'];
            ?>

            <div class="kesimpulan-box <?php echo $kelasKesimpulan; ?>">
                <h4>
                    <?php echo $ikon; ?> Kesimpulan Evaluasi Akademik
                </h4>

                <div class="table-scroll-wrapper">
                <table class="kv-table">
                    <tr>
                        <td class="kv-label">
                            <span class="info-clickable-text" onclick="showInfoModal('kategori_status')">Kategori</span>
                        </td>
                        <td>:&nbsp;
                            <span class="kategori-value">
                                <?php echo $statusLabel; ?>
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <td class="kv-label">
                            <span class="info-clickable-text" onclick="showInfoModal('nilai_preferensi_topsis')">Nilai Preferensi TOPSIS</span>
                        </td>
                        <td>:&nbsp;<?php echo number_format($nilaiPref, 4); ?></td>
                    </tr>
                    <?php if ($rankingMhs): ?>
                    <tr>
                        <td class="kv-label">Ranking</td>
                        <td>:&nbsp;#<?php echo $rankingMhs; ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if (!empty($faktor)): ?>
                    <tr>
                        <td class="kv-label">Faktor Akademik</td>
                        <td>:&nbsp;<?php echo implode(' &nbsp;|&nbsp; ', $faktor); ?></td>
                    </tr>
                    <?php endif; ?>
                    <tr class="kv-row-spaced">
                        <td class="kv-label">Keterangan</td>
                        <td>:&nbsp;<?php echo $keterangan; ?></td>
                    </tr>
                    <tr class="kv-row-spaced">
                        <td class="kv-label">Rekomendasi</td>
                        <td>:&nbsp;<?php echo $rekomendasi; ?></td>
                    </tr>
                </table>
                </div>
            </div>

            <?php else: ?>

            <div class="kesimpulan-box kesimpulan-netral">
                ℹ️ Kesimpulan kategori TOPSIS belum dapat ditampilkan karena proses TOPSIS belum dijalankan untuk mahasiswa ini.
                <?php if ($bagianDo !== ''): ?>
                <div class="keterangan-block"><?php echo $bagianDo; ?></div>
                <?php endif; ?>
            </div>

            <?php endif; ?>

            <!-- ═══════════════════════════════════════
                 DETAIL ANALISIS INDIKASI DO
                 (rinciannya per-checkpoint - ringkasan
                 kesimpulannya sudah digabung ke paragraf
                 "Keterangan" pada kotak Kesimpulan Evaluasi
                 Akademik di atas)
                 ═══════════════════════════════════════ -->
            <?php if ($indikasiDoPertama !== null || $indikasiDoKedua !== null): ?>
            <div class="kesimpulan-box <?php echo $jumlahCutiHingga4 > 0 ? 'kesimpulan-cuti-ada' : 'kesimpulan-cuti-tidak-ada'; ?>">
                <h4>
                    📌 Detail Analisis Indikasi DO
                </h4>

                <p class="p-note-tight"><?php echo htmlspecialchars($kesimpulanCuti); ?></p>

                <?php if ($indikasiDoPertama === true): ?>
                    <p class="p-alert-danger">
                        ⚠️ Indikasi DO pada checkpoint semester <?php echo $checkpointPertama; ?>: TERDETEKSI RISIKO.
                    </p>
                    <ul class="list-tight-alt">
                        <?php foreach ($alasanDoPertama as $alasan): ?>
                            <li><?php echo htmlspecialchars($alasan); ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php elseif ($indikasiDoPertama === false): ?>
                    <p class="p-alert-success">
                        ✅ Indikasi DO pada checkpoint semester <?php echo $checkpointPertama; ?>: tidak terdeteksi risiko dari kriteria IPK, cuti, maupun SKS.
                    </p>
                <?php endif; ?>

                <?php if ($indikasiDoKedua !== null): ?>
                    <p class="<?php echo $indikasiDoKedua ? 'p-alert-danger-top10' : 'p-alert-success-top10'; ?>">
                        <?php echo $indikasiDoKedua ? '⚠️' : '✅'; ?> Checkpoint semester 14: <?php echo $indikasiDoKedua ? 'mahasiswa sudah melewati batas masa studi (Sisa Masa Studi habis) - risiko DO tinggi.' : 'mahasiswa masih dalam batas masa studi yang wajar.'; ?>
                    </p>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <br>

            <a href="<?php echo $backPage; ?>" class="btn-add text-underline-none">
                Kembali
            </a>
        </section>

    </main>
</div>

<script>
/* =============================================
   GRAFIK TREN IPK PER SEMESTER (REDESIGN)
   ============================================= */
(function () {
    var ctxIpk = document.getElementById('grafikIpk');
    if (!ctxIpk) return;

    var gradientIpk = ctxIpk.getContext('2d').createLinearGradient(0, 0, 0, 280);
    gradientIpk.addColorStop(0, 'rgba(54, 108, 235, 0.35)');
    gradientIpk.addColorStop(1, 'rgba(54, 108, 235, 0.02)');

    new Chart(ctxIpk, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($labelSemester); ?>,
            datasets: [{
                label: 'IP Semester',
                data: <?php echo json_encode($dataIpk); ?>,
                backgroundColor: gradientIpk,
                borderColor: '#366ceb',
                borderWidth: 2,
                borderRadius: 6,
                maxBarThickness: 48
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#1f2937',
                    padding: 10,
                    titleFont: { size: 13, weight: 'bold' },
                    bodyFont: { size: 13 },
                    callbacks: {
                        label: function (ctx) {
                            return 'IP Semester: ' + ctx.parsed.y.toFixed(2);
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    max: 4,
                    grid: { color: 'rgba(0,0,0,0.05)' },
                    ticks: { stepSize: 1 }
                },
                x: {
                    grid: { display: false }
                }
            }
        }
    });
})();

/* =============================================
   GRAFIK TREN SKOR TOPSIS ANTAR PERIODE (BARU)
   ============================================= */
(function () {
    var ctxTopsis = document.getElementById('grafikTrenTopsis');
    if (!ctxTopsis) return;

    var labelPeriode  = <?php echo json_encode($labelPeriodeTopsis); ?>;
    var skorTopsis    = <?php echo json_encode($dataSkorTopsis); ?>;
    var rankingTopsis = <?php echo json_encode($dataRankingTopsis); ?>;

    if (skorTopsis.length < 1) return;

    var gradientTopsis = ctxTopsis.getContext('2d').createLinearGradient(0, 0, 0, 280);
    gradientTopsis.addColorStop(0, 'rgba(16, 163, 74, 0.35)');
    gradientTopsis.addColorStop(1, 'rgba(16, 163, 74, 0.02)');

    /* Warna titik menyesuaikan zona skor, konsisten dengan
       kategori early warning (Kritis/Waspada/Aman/Sangat Baik) */
    function warnaSkor(skor) {
        if (skor <= 0.25) return '#ff6b6b';
        if (skor <= 0.50) return '#ffc46b';
        if (skor <= 0.75) return '#9cff7a';
        return '#00c781';
    }

    var warnaTitik = skorTopsis.map(warnaSkor);

    new Chart(ctxTopsis, {
        type: 'bar',
        data: {
            labels: labelPeriode,
            datasets: [{
                label: 'Skor Preferensi TOPSIS',
                data: skorTopsis,
                backgroundColor: warnaTitik,
                borderColor: '#10a34a',
                borderWidth: 1,
                borderRadius: 6,
                maxBarThickness: 48
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#1f2937',
                    padding: 10,
                    titleFont: { size: 13, weight: 'bold' },
                    bodyFont: { size: 13 },
                    callbacks: {
                        label: function (ctx) {
                            var idx = ctx.dataIndex;
                            var baris = ['Skor: ' + ctx.parsed.y.toFixed(4)];
                            if (rankingTopsis[idx]) {
                                baris.push('Ranking: #' + rankingTopsis[idx]);
                            }
                            return baris;
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    max: 1,
                    grid: { color: 'rgba(0,0,0,0.05)' },
                    title: { display: true, text: 'Skor Preferensi (0 - 1)' }
                },
                x: {
                    grid: { display: false },
                    title: { display: true, text: 'Periode Evaluasi' }
                }
            }
        }
    });
})();
</script>

<script src="../assets/js/sidebar.js?v=2"></script>
<script src="../assets/js/info_modal.js?v=3"></script>
</body>
</html>