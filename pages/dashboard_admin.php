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

<div class="dashboard-wrapper">

    <!-- SECTION 1: SIDEBAR -->
    <aside class="section-sidebar">
        <div class="logo-area">
            <img src="../assets/img/logo_psti.jpg" class="sidebar-logo" alt="Logo PSTI">
            <span class="logo-text">Prioritas Mahasiswa<br>Bimbingan</span>
        </div>

        <nav class="nav-menu">
            <a href="dashboard_admin.php" class="nav-link active">Dashboard</a>
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

        <!-- SECTION 4: LOG AKTIVITAS -->
        <section class="section-log">
            <h3>Log Aktivitas Terakhir</h3>

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
        </section>

    </main>
</div>

</body>
</html>