<?php
session_start();
include "../config/database.php";
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Dashboard DPA</title>

    <head>
        <meta charset="UTF-8">
        <title>Login SPK Mahasiswa</title>

        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

        <link rel="stylesheet" href="assets/css/style.css">
    </head>

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
                    <small>NIP.XXXX XXXX X XXXX</small>
                </div>
                <div class="admin-avatar"></div>
            </div>
        </section>

        <!-- SECTION 3: TOTAL BIMBINGAN -->
        <section class="dpa-total-card">
            <div class="total-info">
                <div class="total-header">
                    <div class="total-icon">
                        <i class="fa-solid fa-user"></i>
                    </div>
                    <h4>Total Bimbingan</h4>
                </div>
                <p>30</p>
                <small>Mahasiswa</small>
            </div>

            <button class="detail-button">Lihat Detail</button>
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
                <p>2</p>
                <small>Mahasiswa</small>
            </div>

            <div class="summary-card warning">
                <div class="card-header">
                    <div class="category-icon">
                        <i class="fa-solid fa-user"></i>
                    </div>
                    <h4>Kategori Waspada</h4>
                </div>
                <p>5</p>
                <small>Mahasiswa</small>
            </div>

            <div class="summary-card success">
                <div class="card-header">
                    <div class="category-icon">
                        <i class="fa-solid fa-user"></i>
                    </div>
                    <h4>Kategori Aman</h4>
                </div>
                <p>15</p>
                <small>Mahasiswa</small>
            </div>

            <div class="summary-card excellent">
                <div class="card-header">
                    <div class="category-icon">
                        <i class="fa-solid fa-user"></i>
                    </div>
                    <h4>Kategori Sangat Baik</h4>
                </div>
                <p>8</p>
                <small>Mahasiswa</small>
            </div>
        </section>

        <!-- SECTION 5: GRAFIK DAN ANALISIS -->
        <section class="dpa-chart-section">
            <div class="chart-box">
                <h4>Grafik Sebaran</h4>
                <div class="chart-placeholder">Area Grafik</div>
            </div>

            <div class="analysis-text">
                <p>
                    Berdasarkan evaluasi metode TOPSIS semester ini, mayoritas mahasiswa
                    bimbingan Anda berada dalam kondisi Aman dan Sangat Baik.
                </p>

                <p>
                    Namun, terdapat beberapa mahasiswa di kategori Kritis dan Waspada
                    yang memerlukan peninjauan kembali.
                </p>

                <button class="detail-button">Lihat Detail</button>
            </div>
        </section>

    </main>
</div>

</body>
</html>