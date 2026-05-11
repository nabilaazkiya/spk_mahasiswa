<?php
session_start();
include "../config/database.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'kaprodi') {
    header("Location: ../login.php");
    exit;
}

$totalMahasiswa = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COUNT(*) AS total FROM mahasiswa
"));

$kritis = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COUNT(*) AS total FROM hasil_evaluasi 
    WHERE status_early_warning = 'Kritis'
"));

$waspada = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COUNT(*) AS total FROM hasil_evaluasi 
    WHERE status_early_warning = 'Waspada'
"));

$aman = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COUNT(*) AS total FROM hasil_evaluasi 
    WHERE status_early_warning = 'Aman'
"));

$sangatBaik = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COUNT(*) AS total FROM hasil_evaluasi 
    WHERE status_early_warning = 'Sangat Baik'
"));
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Dashboard Kaprodi</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

<div class="dashboard-wrapper">

    <!-- SECTION 1: SIDEBAR -->
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
                    <div class="category-icon">
                        <i class="fa-solid fa-user"></i>
                    </div>
                    <h4>Kategori Kritis</h4>
                </div>
                <p><?php echo $kritis['total']; ?></p>
                <small>Mahasiswa</small>
            </div>

            <div class="summary-card warning">
                <div class="card-header">
                    <div class="category-icon">
                        <i class="fa-solid fa-user"></i>
                    </div>
                    <h4>Kategori Waspada</h4>
                </div>
                <p><?php echo $waspada['total']; ?></p>
                <small>Mahasiswa</small>
            </div>

            <div class="summary-card success">
                <div class="card-header">
                    <div class="category-icon">
                        <i class="fa-solid fa-user"></i>
                    </div>
                    <h4>Kategori Aman</h4>
                </div>
                <p><?php echo $aman['total']; ?></p>
                <small>Mahasiswa</small>
            </div>

            <div class="summary-card excellent">
                <div class="card-header">
                    <div class="category-icon">
                        <i class="fa-solid fa-user"></i>
                    </div>
                    <h4>Kategori Sangat Baik</h4>
                </div>
                <p><?php echo $sangatBaik['total']; ?></p>
                <small>Mahasiswa</small>
            </div>

        </section>

        <!-- SECTION 5: GRAFIK DAN ANALISIS -->
        <section class="dpa-chart-section">
            <div class="chart-box">
                <h4>Grafik Sebaran Mahasiswa</h4>
                <div class="chart-placeholder">Area Grafik</div>
            </div>

            <div class="analysis-text">
                <h4>Kesimpulan Sistem</h4>
                <p>
                    Dashboard ini menampilkan hasil klasifikasi mahasiswa berdasarkan evaluasi akademik.
                    Mahasiswa dengan status Kritis dan Waspada perlu menjadi prioritas pemantauan akademik.
                </p>

                <a href="monitoring.php" class="detail-button">Lihat Detail</a>
            </div>
        </section>

    </main>
</div>

</body>
</html>