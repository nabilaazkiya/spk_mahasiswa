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
            <a href="dashboard_dpa.php" class="nav-link">Dashboard</a>
            <a href="monitoring.php" class="nav-link active">Monitoring</a>
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

        <!-- SECTION 3: FILTER -->
        <section class="monitor-filter-card">
            <div class="sort-area">
                <span>Urutkan Berdasarkan</span>

                <div class="sort-select-wrapper">
                    <select class="sort-select">
                        <option>Default</option>
                        <option>Ranking</option>
                        <option>IPK</option>
                        <option>Skor TOPSIS</option>
                    </select>
                </div>
            </div>

            <div class="search-area">
                <input type="text" class="monitor-search" placeholder="Search">
                <i class="fa-solid fa-magnifying-glass"></i>
            </div>
        </section>

        <!-- SECTION 4: TABLE -->
        <section class="section-content monitoring-content">
            <div class="monitoring-title">
                <h2>Mahasiswa bimbingan</h2>
                <p>30 total</p>
            </div>

            <table class="data-table monitoring-table">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>NIM</th>
                        <th>Nama</th>
                        <th>IPK</th>
                        <th>SKS</th>
                        <th>Skor Akhir</th>
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
                        <td>
                            <span class="status-pill 
                                <?php 
                                    $status = strtolower($row['status_early_warning']);
                                    if ($status == 'kritis') echo 'status-kritis';
                                    elseif ($status == 'waspada') echo 'status-waspada';
                                    elseif ($status == 'aman') echo 'status-aman';
                                    else echo 'status-baik';
                                ?>">
                                <?= $row['status_early_warning']; ?>
                            </span>
                        </td>
                        <td>
                            <a class="btn-detail" href="detail_mahasiswa.php?nim=<?= $row['nim']; ?>">Detail</a>
                        </td>
                    </tr>
                    <?php } ?>
                </tbody>
            </table>

            <div class="table-footer">
                <button>10 ▾</button>
                <span>items per page</span>

                <div class="pagination">
                    <button>&lt; prev</button>
                    <span>1</span>
                    <span>2</span>
                    <span>3</span>
                    <button>Next &gt;</button>
                </div>

                <span>of 3 pages</span>
            </div>
        </section>

    </main>
</div>

</body>
</html>