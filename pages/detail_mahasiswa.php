<?php
include "../config/database.php";

$nim = $_GET['nim'];

$query = mysqli_query($conn, "
    SELECT m.*, d.*, r.nilai_preferensi, r.ranking, h.status_early_warning
    FROM mahasiswa m
    JOIN data_akademik d ON m.nim = d.nim
    JOIN ranking_topsis r ON m.nim = r.nim
    JOIN hasil_evaluasi h ON m.nim = h.nim
    WHERE m.nim='$nim'
");

$data = mysqli_fetch_assoc($query);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Detail Mahasiswa</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

<div class="app-layout">
    <aside class="sidebar">
        <h2 class="sidebar-title">SPK Mahasiswa</h2>
        <nav class="nav-menu">
            <a href="monitoring.php" class="nav-link">Kembali</a>
        </nav>
    </aside>

    <main class="main-content">
        <header class="topbar">
            <h3>Detail Mahasiswa</h3>
        </header>

        <section class="profile-card">
            <h2><?= $data['nama']; ?></h2>
            <p>NIM: <?= $data['nim']; ?></p>
            <span class="status-badge"><?= $data['status_early_warning']; ?></span>
        </section>

        <section class="summary-grid">
            <div class="summary-card">
                <h4>IPK</h4>
                <p><?= $data['ipk']; ?></p>
            </div>
            <div class="summary-card">
                <h4>SKS Lulus</h4>
                <p><?= $data['sks_lulus']; ?></p>
            </div>
            <div class="summary-card">
                <h4>Skor TOPSIS</h4>
                <p><?= $data['nilai_preferensi']; ?></p>
            </div>
            <div class="summary-card">
                <h4>Ranking</h4>
                <p><?= $data['ranking']; ?></p>
            </div>
        </section>

        <section class="content-section">
            <h3>Tren Performa Semester</h3>
            <div class="chart-placeholder">Grafik IP Semester</div>
        </section>
    </main>
</div>

</body>
</html>