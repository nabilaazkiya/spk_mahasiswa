<?php
session_start();
include "../config/database.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'dpa') {
    header("Location: ../login.php");
    exit;
}

$namaDpa = mysqli_real_escape_string($conn, $_SESSION['nama_lengkap']);

$keyword = isset($_GET['keyword']) ? $_GET['keyword'] : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'ranking';

$where = "WHERE d.dosen_pa = '$namaDpa'";

if ($keyword != '') {
    $keywordSafe = mysqli_real_escape_string($conn, $keyword);
    $where .= " AND (
        d.nim LIKE '%$keywordSafe%' 
        OR d.nama_mahasiswa LIKE '%$keywordSafe%'
        OR h.status_early_warning LIKE '%$keywordSafe%'
    )";
}

$orderBy = "r.ranking ASC";

if ($sort == 'ipk') {
    $orderBy = "d.ipk DESC";
} elseif ($sort == 'skor') {
    $orderBy = "r.nilai_preferensi DESC";
} elseif ($sort == 'status') {
    $orderBy = "h.status_early_warning ASC";
}

$query = mysqli_query($conn, "
    SELECT 
        d.nim,
        d.nama_mahasiswa,
        d.dosen_pa,
        d.ipk,
        d.sks_lulus,
        r.nilai_preferensi,
        r.ranking,
        h.status_early_warning
    FROM data_akademik d
    LEFT JOIN ranking_topsis r ON d.nim = r.nim
    LEFT JOIN hasil_evaluasi h ON d.nim = h.nim
    $where
    ORDER BY $orderBy
");

$totalData = mysqli_num_rows($query);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Monitoring Dosen PA</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

<div class="dashboard-wrapper">
    <aside class="section-sidebar">
        <div class="logo-area">
            <img src="../assets/img/logo_psti.jpg" class="sidebar-logo">
            <span class="logo-text">Prioritas Mahasiswa<br>Bimbingan</span>
        </div>

        <nav class="nav-menu">
            <a href="dashboard_dpa.php" class="nav-link">Dashboard</a>
            <a href="monitoring_dpa.php" class="nav-link active">Monitoring</a>
        </nav>

        <a href="../logout.php" class="logout-button">LOGOUT</a>
    </aside>

    <main class="dashboard-main">
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

        <form method="GET" action="monitoring_dpa.php" class="monitor-filter-card">
            <div class="sort-area">
                <span>Urutkan Berdasarkan</span>
                <div class="sort-select-wrapper">
                    <select name="sort" class="sort-select" onchange="this.form.submit()">
                        <option value="ranking" <?php if ($sort == 'ranking') echo 'selected'; ?>>Ranking</option>
                        <option value="ipk" <?php if ($sort == 'ipk') echo 'selected'; ?>>IPK</option>
                        <option value="skor" <?php if ($sort == 'skor') echo 'selected'; ?>>Skor TOPSIS</option>
                        <option value="status" <?php if ($sort == 'status') echo 'selected'; ?>>Status</option>
                    </select>
                </div>
            </div>

            <div class="search-area">
                <input 
                    type="text" 
                    name="keyword"
                    class="monitor-search" 
                    placeholder="Search"
                    value="<?php echo htmlspecialchars($keyword); ?>"
                >
                <button type="submit" style="border:none;background:none;cursor:pointer;">🔍</button>
            </div>
        </form>

        <section class="section-content monitoring-content">
            <div class="monitoring-title">
                <h2>Mahasiswa Bimbingan</h2>
                <p><?php echo $totalData; ?> total</p>
            </div>

            <table class="data-table monitoring-table">
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
                    <?php if ($totalData > 0) { ?>
                        <?php while ($row = mysqli_fetch_assoc($query)) { ?>
                        <tr>
                            <td><?php echo $row['ranking'] ?? '-'; ?></td>
                            <td><?php echo $row['nim']; ?></td>
                            <td><?php echo $row['nama_mahasiswa']; ?></td>
                            <td><?php echo $row['ipk']; ?></td>
                            <td><?php echo $row['sks_lulus']; ?></td>
                            <td><?php echo $row['nilai_preferensi'] ? number_format($row['nilai_preferensi'], 4) : '-'; ?></td>
                            <td><?php echo $row['status_early_warning'] ?? 'Belum Diproses'; ?></td>
                            <td>
                                <a class="btn-detail" href="detail_mahasiswa.php?nim=<?php echo $row['nim']; ?>">
                                    Detail
                                </a>
                            </td>
                        </tr>
                        <?php } ?>
                    <?php } else { ?>
                        <tr>
                            <td colspan="8" style="text-align:center;">
                                Tidak ada mahasiswa bimbingan
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