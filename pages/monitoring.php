<?php
session_start();
include "../config/database.php";

$query = mysqli_query($conn, "
    SELECT m.nim, m.nama, d.ipk, d.sks_lulus, r.nilai_preferensi, r.ranking, h.status_early_warning
    FROM mahasiswa m
    JOIN data_akademik d ON m.nim = d.nim
    JOIN ranking_topsis r ON m.nim = r.nim
    JOIN hasil_evaluasi h ON m.nim = h.nim
    ORDER BY r.ranking ASC
");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Monitoring Mahasiswa</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

<div class="app-layout">
    <aside class="sidebar">
        <h2 class="sidebar-title">SPK Mahasiswa</h2>
        <nav class="nav-menu">
            <a href="dashboard_dpa.php" class="nav-link">Dashboard</a>
            <a href="monitoring.php" class="nav-link active">Monitoring</a>
        </nav>
    </aside>

    <main class="main-content">
        <header class="topbar">
            <h3>Monitoring Mahasiswa</h3>
        </header>

        <section class="content-section">
            <div class="table-toolbar">
                <input type="text" class="search-input" placeholder="Cari mahasiswa...">
                <select class="filter-select">
                    <option>Semua Status</option>
                    <option>Kritis</option>
                    <option>Waspada</option>
                    <option>Aman</option>
                    <option>Sangat Baik</option>
                </select>
            </div>

            <table class="data-table">
                <thead>
                    <tr>
                        <th>Ranking</th>
                        <th>NIM</th>
                        <th>Nama</th>
                        <th>IPK</th>
                        <th>SKS</th>
                        <th>Skor TOPSIS</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = mysqli_fetch_assoc($query)) { ?>
                    <tr>
                        <td><?= $row['ranking']; ?></td>
                        <td><?= $row['nim']; ?></td>
                        <td><?= $row['nama']; ?></td>
                        <td><?= $row['ipk']; ?></td>
                        <td><?= $row['sks_lulus']; ?></td>
                        <td><?= $row['nilai_preferensi']; ?></td>
                        <td><span class="status-badge"><?= $row['status_early_warning']; ?></span></td>
                        <td>
                            <a class="btn-detail" href="detail_mahasiswa.php?nim=<?= $row['nim']; ?>">Detail</a>
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