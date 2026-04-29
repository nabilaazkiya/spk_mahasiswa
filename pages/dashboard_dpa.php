<?php
session_start();
include "../config/database.php";
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Dashboard DPA</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

<div class="app-layout">
    <aside class="sidebar">
        <h2 class="sidebar-title">SPK Mahasiswa</h2>
        <nav class="nav-menu">
            <a href="dashboard_dpa.php" class="nav-link active">Dashboard</a>
            <a href="monitoring.php" class="nav-link">Monitoring</a>
            <a href="../logout.php" class="nav-link">Logout</a>
        </nav>
    </aside>

    <main class="main-content">
        <header class="topbar">
            <h3>Dashboard DPA</h3>
            <span><?php echo $_SESSION['nama_lengkap']; ?></span>
        </header>

        <section class="summary-grid">
            <div class="summary-card">
                <h4>Total Mahasiswa Bimbingan</h4>
                <p>35</p>
            </div>
            <div class="summary-card danger">
                <h4>Kritis</h4>
                <p>5</p>
            </div>
            <div class="summary-card warning">
                <h4>Waspada</h4>
                <p>8</p>
            </div>
            <div class="summary-card success">
                <h4>Aman</h4>
                <p>15</p>
            </div>
            <div class="summary-card excellent">
                <h4>Sangat Baik</h4>
                <p>7</p>
            </div>
        </section>

        <section class="content-section">
            <h3>Grafik Sebaran Mahasiswa</h3>
            <div class="chart-placeholder">Area Grafik</div>
        </section>
    </main>
</div>

</body>
</html>