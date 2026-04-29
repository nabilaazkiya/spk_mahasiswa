<?php
session_start();
include "../config/database.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit;
}

$totalMahasiswa = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM mahasiswa"));
$totalDosen = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM dosen_pa"));
$totalKriteria = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM kriteria"));

$logAktivitas = mysqli_query($conn, "
    SELECT l.*, u.nama_lengkap
    FROM log_aktivitas l
    LEFT JOIN user u ON l.id_user = u.id_user
    ORDER BY l.tanggal DESC
    LIMIT 10
");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Dashboard Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

<div class="app-layout">
    <aside class="sidebar">
        <h2 class="sidebar-title">SPK Mahasiswa</h2>

        <nav class="nav-menu">
            <a href="dashboard_admin.php" class="nav-link active">Dashboard</a>
            <a href="manajemen_data.php" class="nav-link">Manajemen Data</a>
            <a href="konfigurasi_kriteria.php" class="nav-link">Konfigurasi Kriteria</a>
            <a href="../logout.php" class="nav-link logout-link">Logout</a>
        </nav>
    </aside>

    <main class="main-content">
        <header class="topbar">
            <div>
                <h3>Dashboard Admin</h3>
                <p>Ringkasan operasional sistem</p>
            </div>
            <span class="user-info"><?php echo $_SESSION['nama_lengkap']; ?></span>
        </header>

        <section class="summary-grid admin-grid">
            <div class="summary-card">
                <h4>Total Mahasiswa</h4>
                <p><?php echo $totalMahasiswa['total']; ?></p>
            </div>

            <div class="summary-card">
                <h4>Total Dosen PA</h4>
                <p><?php echo $totalDosen['total']; ?></p>
            </div>

            <div class="summary-card">
                <h4>Total Kriteria</h4>
                <p><?php echo $totalKriteria['total']; ?></p>
            </div>
        </section>

        <section class="content-section">
            <div class="section-header">
                <h3>Log Aktivitas Terakhir</h3>
            </div>

            <table class="data-table">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Admin</th>
                        <th>Aksi</th>
                        <th>Tanggal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $no = 1; while ($log = mysqli_fetch_assoc($logAktivitas)) { ?>
                    <tr>
                        <td><?php echo $no++; ?></td>
                        <td><?php echo $log['nama_lengkap'] ?? '-'; ?></td>
                        <td><?php echo $log['aksi']; ?></td>
                        <td><?php echo $log['tanggal']; ?></td>
                    </tr>
                    <?php } ?>
                </tbody>
            </table>
        </section>
    </main>
</div>

</body>
</html>