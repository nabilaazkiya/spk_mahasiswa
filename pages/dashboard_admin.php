<?php
session_start();
include "../config/database.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit;
}

$totalMahasiswa = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COUNT(DISTINCT nim) AS total 
    FROM data_akademik
"));

$totalDosen = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COUNT(DISTINCT dosen_pa) AS total 
    FROM data_akademik 
    WHERE dosen_pa IS NOT NULL 
    AND dosen_pa != ''
"));

$totalKriteria = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COUNT(*) AS total 
    FROM kriteria
"));

/* PERBAIKAN (paritas Admin = Kaprodi, sesuai hasil UAT):
   query kategori & scatter chart di bawah ini SAMA PERSIS
   seperti yang dipakai di dashboard_kaprodi.php - supaya
   Admin bisa ikut memantau tanpa perlu login sebagai Kaprodi. */
$kritis = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COUNT(*) AS total 
    FROM hasil_evaluasi_terbaru 
    WHERE status_early_warning='Kritis'
"));

$waspada = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COUNT(*) AS total 
    FROM hasil_evaluasi_terbaru 
    WHERE status_early_warning='Waspada'
"));

$aman = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COUNT(*) AS total 
    FROM hasil_evaluasi_terbaru 
    WHERE status_early_warning='Aman'
"));

$sangatBaik = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COUNT(*) AS total 
    FROM hasil_evaluasi_terbaru 
    WHERE status_early_warning='Sangat Baik'
"));

include "../includes/scatter_helper.php";
$scatterData = ambilDataScatter($conn);

$logAktivitas = mysqli_query($conn, "
    SELECT l.*, u.nama_lengkap
    FROM log_aktivitas l
    LEFT JOIN user u ON l.id_user = u.id_user
    ORDER BY l.tanggal DESC
    
");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=8">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/hammer.js/2.0.8/hammer.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-zoom@2.0.1/dist/chartjs-plugin-zoom.min.js"></script>
    <script src="../assets/js/scatter_chart.js?v=2"></script>
</head>
<body>

<div class="dashboard-wrapper">

    <!-- SECTION 1: SIDEBAR -->
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
            <a href="dashboard_admin.php" class="nav-link active">Dashboard</a>
            <a href="monitoring.php" class="nav-link">Monitoring</a>
            <a href="manajemen_data.php" class="nav-link">Manajemen Data</a>
            <a href="konfigurasi_kriteria.php" class="nav-link">Konfigurasi Kriteria</a>
        </nav>

        <a href="../logout.php" class="logout-button">LOGOUT</a>
    </aside>

    <!-- AREA KANAN -->
    <main class="dashboard-main">

        <!-- SECTION 2: TOPBAR -->
        <section class="section-topbar">
            <h3>Dashboard</h3>
            <div class="admin-info">
                <span><?php echo $_SESSION['nama_lengkap']; ?></span>
                <div class="admin-avatar"></div>
            </div>
        </section>

        <!-- SECTION 3: SUMMARY CARD -->
        <section class="section-summary">
            <div class="summary-card danger">
                <div class="card-icon">●</div>
                <h4>Total Mahasiswa</h4>
                <p><?php echo $totalMahasiswa['total']; ?></p>
                <small>Mahasiswa</small>
            </div>

            <div class="summary-card warning">
                <div class="card-icon">●</div>
                <h4>Total Dosen PA</h4>
                <p><?php echo $totalDosen['total']; ?></p>
                <small>Dosen PA</small>
            </div>

            <div class="summary-card success">
                <div class="card-icon">●</div>
                <h4>Total Kriteria</h4>
                <p><?php echo $totalKriteria['total']; ?></p>
                <small>Kriteria</small>
            </div>
        </section>

        <!-- SECTION 3B: KATEGORI (paritas dengan Kaprodi) -->
        <section class="dpa-category-grid">

            <div class="summary-card danger">
                <div class="card-header">
                    <div class="category-icon"><i class="fa-solid fa-user"></i></div>
                    <h4>Kategori Kritis</h4>
                </div>
                <p><?php echo $kritis['total']; ?></p>
                <small>Mahasiswa</small>
            </div>

            <div class="summary-card warning">
                <div class="card-header">
                    <div class="category-icon"><i class="fa-solid fa-user"></i></div>
                    <h4>Kategori Waspada</h4>
                </div>
                <p><?php echo $waspada['total']; ?></p>
                <small>Mahasiswa</small>
            </div>

            <div class="summary-card success">
                <div class="card-header">
                    <div class="category-icon"><i class="fa-solid fa-user"></i></div>
                    <h4>Kategori Aman</h4>
                </div>
                <p><?php echo $aman['total']; ?></p>
                <small>Mahasiswa</small>
            </div>

            <div class="summary-card excellent">
                <div class="card-header">
                    <div class="category-icon"><i class="fa-solid fa-user"></i></div>
                    <h4>Kategori Sangat Baik</h4>
                </div>
                <p><?php echo $sangatBaik['total']; ?></p>
                <small>Mahasiswa</small>
            </div>

        </section>

        <!-- SECTION 3C: PIE CHART (paritas dengan Kaprodi) -->
        <section class="chart-area">
            <div>
                <h4 style="text-align:center;">Grafik Sebaran Kategori</h4>
                <div class="chart-box">
                    <div class="pie-container">
                        <canvas id="pieChart"></canvas>
                    </div>
                </div>
            </div>

            <div class="analysis-text">
                <p>
                    Dashboard ini menampilkan hasil evaluasi akademik mahasiswa
                    berdasarkan metode TOPSIS.
                </p>
                <p>
                    Mahasiswa dengan kategori Kritis dan Waspada perlu menjadi
                    prioritas pembinaan akademik oleh Kaprodi dan Dosen PA.
                </p>

                <a href="monitoring.php" class="detail-button">Lihat Detail</a>
            </div>
        </section>

        <!-- SECTION 3D: SCATTER CHART (paritas dengan Kaprodi) -->
        <section class="scatter-box">
            <h4 style="text-align:center;">Sebaran Mahasiswa terhadap Solusi Ideal TOPSIS</h4>
            <div class="chart-canvas-wrapper">
                <canvas id="scatterChart"></canvas>
            </div>
            <div style="text-align:right;margin-top:8px;">
                <button id="btnResetZoomAdmin" style="padding:4px 12px;font-size:12px;border:1px solid #ccc;border-radius:4px;background:#f8f9fa;cursor:pointer;">
                    🔍 Reset Zoom
                </button>
            </div>
            <small style="color:#888;display:block;margin-top:4px;text-align:center;">
                Gunakan scroll mouse untuk zoom, klik+geser untuk pan, klik titik untuk detail mahasiswa.
            </small>
        </section>

        <!-- SECTION 4: LOG AKTIVITAS -->
        <section class="section-log">
            <h3>Log Aktivitas Terakhir</h3>

            <div class="table-scroll-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Aksi</th>
                        <th>Tanggal</th>
                        <th>Oleh</th>
                    </tr>
                </thead>

                <tbody>

                    <?php if (mysqli_num_rows($logAktivitas) > 0) { ?>

                        <?php while ($log = mysqli_fetch_assoc($logAktivitas)) { ?>

                        <tr>
                            <td><?php echo $log['aksi']; ?></td>

                            <td>
                                <?php echo date('d-m-Y H:i', strtotime($log['tanggal'])); ?>
                            </td>

                            <td>
                                <?php echo !empty($log['nama_lengkap']) ? $log['nama_lengkap'] : 'System'; ?>
                            </td>
                        </tr>

                        <?php } ?>

                    <?php } else { ?>

                        <tr>
                            <td colspan="3" style="text-align:center;">
                                Belum ada aktivitas
                            </td>
                        </tr>

                    <?php } ?>

                </tbody>
            </table>
            </div>
        </section>

    </main>
</div>

<script src="../assets/js/sidebar.js?v=2"></script>

<script>
const pieCtx = document.getElementById('pieChart');

new Chart(pieCtx, {
    type: 'pie',
    data: {
        labels: ['Kritis', 'Waspada', 'Aman', 'Sangat Baik'],
        datasets: [{
            data: [
                <?php echo $kritis['total']; ?>,
                <?php echo $waspada['total']; ?>,
                <?php echo $aman['total']; ?>,
                <?php echo $sangatBaik['total']; ?>
            ],
            backgroundColor: [
                '#ff6b6b',
                '#ffc46b',
                '#9cff7a',
                '#00c781'
            ]
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
        legend: {
            position: 'top',
            labels: {
                boxWidth: 0,
                padding: 0,
                font: {
                    size:0
                 }
                }
            }
        }
    }
});

var scatterDataAdmin = <?php echo json_encode($scatterData); ?>;

renderScatterChart(
    'scatterChart',
    scatterDataAdmin,
    'btnResetZoomAdmin',
    function(titik) {
        window.location.href = 'detail_mahasiswa.php?nim=' + encodeURIComponent(titik.nim);
    }
);
</script>
</body>
</html>