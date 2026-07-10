<?php
session_start();
include "../config/database.php";

if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['kaprodi', 'dpa'])) {
    header("Location: ../login.php");
    exit;
}

$nim = isset($_GET['nim']) ? mysqli_real_escape_string($conn, $_GET['nim']) : '';

if ($nim == '') {
    header("Location: monitoring.php");
    exit;
}

$backPage      = ($_SESSION['role'] == 'dpa') ? 'monitoring_dpa.php' : 'monitoring.php';
$dashboardPage = ($_SESSION['role'] == 'dpa') ? 'dashboard_dpa.php' : 'dashboard_kaprodi.php';
$roleLabel     = ($_SESSION['role'] == 'dpa') ? 'Dosen PA' : 'Kaprodi';

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

/* AMBIL HASIL SPEARMAN TERBARU */
$spearman = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT *
    FROM uji_spearman
    ORDER BY id_spearman DESC
    LIMIT 1
"));

/* RIWAYAT IPK */
$riwayatQuery = mysqli_query($conn, "
    SELECT semester, ipk
    FROM data_akademik
    WHERE nim = '$nim'
    ORDER BY semester ASC
");

$labelSemester = [];
$dataIpk = [];

while ($r = mysqli_fetch_assoc($riwayatQuery)) {
    $labelSemester[] = 'Semester ' . $r['semester'];
    $dataIpk[] = floatval($r['ipk']);
}

/* RIWAYAT SKOR TOPSIS PER PERIODE
   Diambil dari tabel ranking_topsis ASLI (bukan view
   ranking_topsis_terbaru), supaya semua periode evaluasi
   yang pernah dihitung untuk mahasiswa ini ikut tampil -
   ini yang jadi sumber grafik tren TOPSIS antar periode. */
$riwayatTopsisQuery = mysqli_query($conn, "
    SELECT periode_evaluasi, nilai_preferensi, ranking
    FROM ranking_topsis
    WHERE nim = '$nim'
    ORDER BY periode_evaluasi ASC
");

$labelPeriodeTopsis = [];
$dataSkorTopsis     = [];
$dataRankingTopsis  = [];

while ($rt = mysqli_fetch_assoc($riwayatTopsisQuery)) {
    $labelPeriodeTopsis[] = $rt['periode_evaluasi'];
    $dataSkorTopsis[]     = floatval($rt['nilai_preferensi']);
    $dataRankingTopsis[]  = intval($rt['ranking']);
}

/* =============================================
   DATA SCATTER TOPSIS (menggunakan helper bersama)
   ============================================= */
include "../includes/scatter_helper.php";

$scatterSelected = ambilDataScatter($conn, "AND r.nim = '$nim'");

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
    <title>Detail Mahasiswa</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/hammer.js/2.0.8/hammer.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-zoom@2.0.1/dist/chartjs-plugin-zoom.min.js"></script>
    <script src="../assets/js/scatter_chart.js"></script>
</head>

<body>

<div class="dashboard-wrapper">

    <aside class="section-sidebar">
        <div class="logo-area">
            <img src="../assets/img/logo_psti.jpg" class="sidebar-logo" alt="Logo PSTI">
            <span class="logo-text">Prioritas Mahasiswa<br>Bimbingan</span>
        </div>

        <nav class="nav-menu">
            <a href="<?php echo $dashboardPage; ?>" class="nav-link">Dashboard</a>
            <a href="<?php echo $backPage; ?>" class="nav-link active">Monitoring</a>
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
                <h4>Preferensi Model</h4>

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

        <section class="chart-detail-box">
            <h3>Tren Performa Semester</h3>
            <div class="chart-canvas-wrapper"><canvas id="grafikIpk"></canvas></div>
        </section>

        <section class="chart-detail-box">
            <h3>Posisi Mahasiswa terhadap Solusi Ideal TOPSIS</h3>
            <?php if (!empty($scatterSelected)): ?>
                <div class="chart-canvas-wrapper"><canvas id="grafikTopsis"></canvas></div>
                <div style="text-align:right;margin-top:8px;">
                    <button id="btnResetZoom" style="padding:4px 12px;font-size:12px;border:1px solid #ccc;border-radius:4px;background:#f8f9fa;cursor:pointer;">
                        🔍 Reset Zoom
                    </button>
                </div>
                <small style="color:#888;display:block;margin-top:4px;">
                    Gunakan scroll mouse untuk zoom, klik+geser untuk pan, klik titik untuk detail.
                </small>
            <?php else: ?>
                <div style="display:flex;align-items:center;justify-content:center;height:200px;background:#f8f9fa;border:2px dashed #dee2e6;border-radius:8px;color:#6c757d;font-size:14px;font-family:Arial,sans-serif;gap:10px;">
                    <span style="font-size:24px;">&#128202;</span>
                    <span>Data belum tersedia. Proses TOPSIS belum dijalankan.</span>
                </div>
            <?php endif; ?>
        </section>

        <section class="chart-detail-box">
            <h3>Tren Skor TOPSIS Antar Periode</h3>
            <?php if (count($dataSkorTopsis) >= 2): ?>
                <div class="chart-canvas-wrapper"><canvas id="grafikTrenTopsis"></canvas></div>
                <small style="color:#888;display:block;margin-top:8px;">
                    Menampilkan perubahan skor preferensi TOPSIS mahasiswa ini dari periode ke periode. Skor mendekati 1.0 = performa lebih baik.
                </small>
            <?php elseif (count($dataSkorTopsis) == 1): ?>
                <div style="display:flex;align-items:center;justify-content:center;height:200px;background:#f8f9fa;border:2px dashed #dee2e6;border-radius:8px;color:#6c757d;font-size:14px;font-family:Arial,sans-serif;gap:10px;text-align:center;padding:16px;">
                    <span>Baru ada 1 periode evaluasi TOPSIS. Grafik tren akan muncul setelah periode berikutnya diproses.</span>
                </div>
            <?php else: ?>
                <div style="display:flex;align-items:center;justify-content:center;height:200px;background:#f8f9fa;border:2px dashed #dee2e6;border-radius:8px;color:#6c757d;font-size:14px;font-family:Arial,sans-serif;gap:10px;">
                    <span style="font-size:24px;">&#128202;</span>
                    <span>Data belum tersedia. Proses TOPSIS belum dijalankan.</span>
                </div>
            <?php endif; ?>
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
                        <th>SKS Nilai Kurang C</th>
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

            <br>

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

                $bg          = $warnaBg[$statusLabel]     ?? '#f8f9fa';
                $border      = $warnaBorder[$statusLabel] ?? '#ccc';
                $ikon        = $warnaIkon[$statusLabel]    ?? 'ℹ️';
                $rekomendasi = $rekomendasiMap[$statusLabel] ?? '-';

                /* Faktor akademik yang berkontribusi */
                $faktor = [];
                if (!empty($data['ipk']))               $faktor[] = 'IPK: ' . number_format($data['ipk'], 2);
                if (!empty($data['sks_lulus']))          $faktor[] = 'SKS Lulus: ' . $data['sks_lulus'];
                if (!empty($data['absensi']))            $faktor[] = 'Absensi: ' . $data['absensi'] . '%';
                if (!empty($data['jml_mengulang']))      $faktor[] = 'Jumlah Mengulang: ' . $data['jml_mengulang'];
                if (!empty($data['sks_nilai_kurang_c'])) $faktor[] = 'SKS Nilai < C: ' . $data['sks_nilai_kurang_c'];
                if (!empty($data['sisa_masa_studi']))    $faktor[] = 'Sisa Masa Studi: ' . $data['sisa_masa_studi'] . ' semester';
                if (!empty($data['skor_toefl']))         $faktor[] = 'Skor TOEFL: ' . $data['skor_toefl'];
            ?>

            <div style="
                background: <?php echo $bg; ?>;
                border-left: 4px solid <?php echo $border; ?>;
                border-radius: 8px;
                padding: 18px 20px;
                margin-top: 20px;
                font-family: Arial, sans-serif;
            ">
                <h4 style="margin:0 0 12px 0;font-size:15px;color:#333;">
                    <?php echo $ikon; ?> Kesimpulan Evaluasi Akademik
                </h4>

                <div class="table-scroll-wrapper">
                <table style="width:100%;border-collapse:collapse;font-size:13px;color:#444;">
                    <tr>
                        <td style="padding:4px 0;width:180px;font-weight:bold;">Kategori</td>
                        <td style="padding:4px 0;">:&nbsp;
                            <span style="font-weight:bold;color:<?php echo $border; ?>;">
                                <?php echo $statusLabel; ?>
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:4px 0;font-weight:bold;">Nilai Preferensi TOPSIS</td>
                        <td style="padding:4px 0;">:&nbsp;<?php echo number_format($nilaiPref, 4); ?></td>
                    </tr>
                    <?php if ($rankingMhs): ?>
                    <tr>
                        <td style="padding:4px 0;font-weight:bold;">Ranking</td>
                        <td style="padding:4px 0;">:&nbsp;#<?php echo $rankingMhs; ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if (!empty($faktor)): ?>
                    <tr>
                        <td style="padding:4px 0;font-weight:bold;vertical-align:top;">Faktor Akademik</td>
                        <td style="padding:4px 0;">:&nbsp;<?php echo implode(' &nbsp;|&nbsp; ', $faktor); ?></td>
                    </tr>
                    <?php endif; ?>
                    <tr>
                        <td style="padding:10px 0 4px 0;font-weight:bold;vertical-align:top;">Rekomendasi</td>
                        <td style="padding:10px 0 4px 0;">:&nbsp;<?php echo $rekomendasi; ?></td>
                    </tr>
                </table>
                </div>
            </div>

            <?php else: ?>

            <div style="
                background: #f8f9fa;
                border-left: 4px solid #ccc;
                border-radius: 8px;
                padding: 18px 20px;
                margin-top: 20px;
                font-family: Arial, sans-serif;
                color: #6c757d;
                font-size: 13px;
            ">
                ℹ️ Kesimpulan belum dapat ditampilkan karena proses TOPSIS belum dijalankan untuk mahasiswa ini.
            </div>

            <?php endif; ?>

            <br>

            <a href="<?php echo $backPage; ?>" class="btn-add" style="text-decoration:none;">
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
        type: 'line',
        data: {
            labels: <?php echo json_encode($labelSemester); ?>,
            datasets: [{
                label: 'IPK',
                data: <?php echo json_encode($dataIpk); ?>,
                borderColor: '#366ceb',
                backgroundColor: gradientIpk,
                pointBackgroundColor: '#366ceb',
                pointBorderColor: '#ffffff',
                pointBorderWidth: 2,
                pointRadius: 5,
                pointHoverRadius: 7,
                borderWidth: 3,
                tension: 0.35,
                fill: true
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
                            return 'IPK: ' + ctx.parsed.y.toFixed(2);
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

    if (skorTopsis.length < 2) return;

    var gradientTopsis = ctxTopsis.getContext('2d').createLinearGradient(0, 0, 0, 280);
    gradientTopsis.addColorStop(0, 'rgba(16, 163, 74, 0.35)');
    gradientTopsis.addColorStop(1, 'rgba(16, 163, 74, 0.02)');

    /* Warna titik menyesuaikan zona skor, konsisten dengan
       kategori early warning (Kritis/Waspada/Aman/Sangat Baik) */
    function warnaSkor(skor) {
        if (skor <= 0.25) return '#dc3545';
        if (skor <= 0.50) return '#fd7e14';
        if (skor <= 0.75) return '#0d9f6e';
        return '#198754';
    }

    var warnaTitik = skorTopsis.map(warnaSkor);

    new Chart(ctxTopsis, {
        type: 'line',
        data: {
            labels: labelPeriode,
            datasets: [{
                label: 'Skor Preferensi TOPSIS',
                data: skorTopsis,
                borderColor: '#10a34a',
                backgroundColor: gradientTopsis,
                pointBackgroundColor: warnaTitik,
                pointBorderColor: '#ffffff',
                pointBorderWidth: 2,
                pointRadius: 6,
                pointHoverRadius: 8,
                borderWidth: 3,
                tension: 0.3,
                fill: true
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

<?php if (!empty($scatterSelected)): ?>

var scatterDataDetail = <?php echo json_encode($scatterSelected); ?>;

renderScatterChart('grafikTopsis', scatterDataDetail, 'btnResetZoom');

<?php endif; ?>
</script>

</body>
</html>