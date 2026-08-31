<?php
session_start();
include "../config/database.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'dpa') {
    header("Location: ../login.php");
    exit;
}

$namaDpa = mysqli_real_escape_string($conn, $_SESSION['nama_lengkap']);

/* TOTAL MAHASISWA BIMBINGAN */
$idDpa = $_SESSION['id_user'];

$totalMahasiswa = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COUNT(DISTINCT m.nim) AS total
    FROM mahasiswa m
    WHERE m.id_user = '$idDpa'
"));

/* JUMLAH KATEGORI KHUSUS DPA */
$kritis = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COUNT(*) AS total
    FROM mahasiswa m
    LEFT JOIN hasil_evaluasi_terbaru h ON m.nim = h.nim
    WHERE m.id_user = '$idDpa'
    AND h.status_early_warning = 'Kritis'
"));

$waspada = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COUNT(*) AS total
    FROM mahasiswa m
    LEFT JOIN hasil_evaluasi_terbaru h ON m.nim = h.nim
    WHERE m.id_user = '$idDpa'
    AND h.status_early_warning = 'Waspada'
"));

$aman = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COUNT(*) AS total
    FROM mahasiswa m
    LEFT JOIN hasil_evaluasi_terbaru h ON m.nim = h.nim
    WHERE m.id_user = '$idDpa'
    AND h.status_early_warning = 'Aman'
"));

$sangatBaik = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COUNT(*) AS total
    FROM mahasiswa m
    LEFT JOIN hasil_evaluasi_terbaru h ON m.nim = h.nim
    WHERE m.id_user = '$idDpa'
    AND h.status_early_warning = 'Sangat Baik'
"));

include "../includes/scatter_helper.php";

$scatterData = ambilDataScatter($conn, "AND m.id_user = '$idDpa'");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Dosen PA</title>

    <link rel="stylesheet" href="../assets/css/style.css?v=10">
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
            <a href="dashboard_dpa.php" class="nav-link active">Dashboard</a>
            <a href="monitoring.php" class="nav-link">Monitoring</a>
        </nav>

        <a href="../logout.php" class="logout-button">LOGOUT</a>
    </aside>

    <!-- AREA KANAN -->
    <main class="dashboard-main">

    <!-- SECTION 2: TOPBAR -->
        <section class="section-topbar">
            <h3>Dashboard</h3>

            <div class="admin-info">
                <div>
                    <strong><?php echo $_SESSION['nama_lengkap']; ?></strong><br>
                    <small>Dosen PA</small>
                </div>
                <div class="admin-avatar"></div>
            </div>
        </section>

        <!-- SECTION 3: TOTAL BIMBINGAN -->
        <section class="dpa-total-card">
            <div class="total-info">
                <div class="total-header">
                    <div class="total-icon">
                        <i class="fa-solid fa-users"></i>
                    </div>
                    <h4>Total Mahasiswa Bimbingan</h4>
                </div>

                <p><?php echo $totalMahasiswa['total']; ?></p>
                <small>Mahasiswa</small>
            </div>

            <a href="monitoring.php" class="detail-button">Lihat Detail</a>
        </section>

        <!-- SECTION 4: KATEGORI -->
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

        <section class="chart-area">
            <div>
                <h4 class="info-clickable-text" style="text-align:center;" onclick="showInfoModal('pie_kategori')">Grafik Sebaran Kategori</h4>
                <div class="chart-box">
                    <div class="pie-container">
                        <canvas id="pieChart"></canvas>
                    </div>
                </div>
            </div>

            <div class="analysis-text">
                <?php
                    $totalDievaluasi = (int) $kritis['total'] + (int) $waspada['total'] + (int) $aman['total'] + (int) $sangatBaik['total'];
                    $totalPerluPerhatian = (int) $kritis['total'] + (int) $waspada['total'];
                    $persenPerluPerhatian = $totalDievaluasi > 0 ? round(($totalPerluPerhatian / $totalDievaluasi) * 100) : 0;
                ?>
                <?php if ($totalDievaluasi == 0): ?>
                    <p>Belum ada mahasiswa bimbingan yang selesai dievaluasi.</p>
                <?php elseif ($totalPerluPerhatian == 0): ?>
                    <p>
                        Seluruh <strong><?php echo $totalDievaluasi; ?> mahasiswa bimbingan</strong>
                        Anda berada di kategori <strong>Aman</strong> atau
                        <strong>Sangat Baik</strong>. Tidak ada yang perlu prioritas
                        pembinaan saat ini.
                    </p>
                <?php else: ?>
                    <p>
                        <strong><?php echo $totalPerluPerhatian; ?> dari <?php echo $totalDievaluasi; ?> mahasiswa bimbingan</strong>
                        Anda (<?php echo $persenPerluPerhatian; ?>%) berada di kategori
                        <strong style="color:#ff6b6b;">Kritis</strong> atau
                        <strong style="color:#e6a400;">Waspada</strong> dan perlu
                        menjadi prioritas pembinaan.
                    </p>
                    <?php if ((int) $kritis['total'] > 0): ?>
                    <p>
                        <strong style="color:#ff6b6b;"><?php echo $kritis['total']; ?> mahasiswa</strong>
                        di antaranya berkategori Kritis - butuh perhatian segera.
                    </p>
                    <?php endif; ?>
                <?php endif; ?>

                <a href="monitoring.php" class="detail-button">Lihat Detail</a>
            </div>
        </section>

        <section class="scatter-box">
            <h4 class="info-clickable-text" style="text-align:center;" onclick="showInfoModal('scatter_topsis')">Sebaran Mahasiswa Bimbingan terhadap Solusi Ideal TOPSIS</h4>
            <div class="chart-canvas-wrapper">
                <canvas id="scatterChart"></canvas>
            </div>
            <div style="text-align:right;margin-top:8px;">
                <button id="btnResetZoomDpa" style="padding:4px 12px;font-size:12px;border:1px solid #ccc;border-radius:4px;background:#f8f9fa;cursor:pointer;">
                    🔍 Reset Zoom
                </button>
            </div>
            <small style="color:#888;display:block;margin-top:4px;text-align:center;">
                Gunakan scroll mouse untuk zoom, klik+geser untuk pan, klik titik untuk detail mahasiswa.
            </small>
        </section>

    </main>
</div>

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
                        size: 0
                    }
                }
            }
        }
    }
});

var scatterDataDpa = <?php echo json_encode($scatterData); ?>;

renderScatterChart(
    'scatterChart',
    scatterDataDpa,
    'btnResetZoomDpa',
    function(titik) {
        window.location.href = 'detail_mahasiswa.php?nim=' + encodeURIComponent(titik.nim);
    }
);
</script>

<script src="../assets/js/sidebar.js?v=2"></script>
<script src="../assets/js/info_modal.js?v=1"></script>
</body>
</html>