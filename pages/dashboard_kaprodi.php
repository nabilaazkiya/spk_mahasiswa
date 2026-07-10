<?php
session_start();
include "../config/database.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'kaprodi') {
    header("Location: ../login.php");
    exit;
}

/* TOTAL MAHASISWA */
$totalMahasiswa = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COUNT(DISTINCT nim) AS total 
    FROM data_akademik
"));

/* JUMLAH KATEGORI */
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
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Dashboard Kaprodi</title>

    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
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
            <a href="dashboard_kaprodi.php" class="nav-link active">Dashboard</a>
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
                    <small>Kaprodi</small>
                </div>
                <div class="admin-avatar"></div>
            </div>
        </section>

        <!-- SECTION 3: TOTAL MAHASISWA -->
        <section class="dpa-total-card">
            <div class="total-info">
                <div class="total-header">
                    <div class="total-icon">
                        <i class="fa-solid fa-users"></i>
                    </div>
                    <h4>Total Mahasiswa</h4>
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
                <h4 style="text-align:center;">Grafik Sebaran Kategori</h4>
                <div class="chart-box">
                    <div class="pie-container">
                        <canvas id="pieChart"></canvas>
                    </div>
                </div>
            </div>

            <div class="analysis-text">
                <h4>Kesimpulan Sebaran Mahasiswa</h4>
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

        <section class="scatter-box">
            <h4 style="text-align:center;">Sebaran Mahasiswa terhadap Solusi Ideal TOPSIS</h4>
            <canvas id="scatterChart"></canvas>
            <div style="text-align:right;margin-top:8px;">
                <button id="btnResetZoomKaprodi" style="padding:4px 12px;font-size:12px;border:1px solid #ccc;border-radius:4px;background:#f8f9fa;cursor:pointer;">
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
                    size:0
                 }
                }
            }
        }
    }
});

var scatterDataKaprodi = <?php echo json_encode($scatterData); ?>;

renderScatterChart(
    'scatterChart',
    scatterDataKaprodi,
    'btnResetZoomKaprodi',
    function(titik) {
        window.location.href = 'detail_mahasiswa.php?nim=' + encodeURIComponent(titik.nim);
    }
);
</script>

</body>
</html>