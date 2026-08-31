<?php
session_start();
include "../config/database.php";

if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['kaprodi', 'admin', 'dpa'])) {
    header("Location: ../login.php");
    exit;
}

$role = $_SESSION['role'];

$keyword = isset($_GET['keyword']) ? trim($_GET['keyword']) : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'ranking';

$where = "WHERE 1=1";

/* PERBAIKAN (gabungan monitoring.php + monitoring_dpa.php):
   DPA hanya boleh melihat mahasiswa bimbingannya sendiri,
   Kaprodi & Admin melihat SELURUH mahasiswa. Sebelumnya ini
   2 file terpisah dengan kode hampir identik (rawan tidak
   sinkron kalau ada perbaikan di satu file tapi lupa di
   file lainnya - seperti yang sempat terjadi). */
if ($role === 'dpa') {
    $idDpa = mysqli_real_escape_string($conn, $_SESSION['id_user']);
    $where .= " AND m.id_user = '$idDpa'";
}

if ($keyword != '') {
    $keywordSafe = mysqli_real_escape_string($conn, $keyword);

    $where .= " AND (
        d.nim LIKE '%$keywordSafe%'
        OR d.nama_mahasiswa LIKE '%$keywordSafe%'
        OR d.dosen_pa LIKE '%$keywordSafe%'
        OR d.ipk LIKE '%$keywordSafe%'
        OR d.sks_lulus LIKE '%$keywordSafe%'
        OR r.nilai_preferensi LIKE '%$keywordSafe%'
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
} elseif ($sort == 'angkatan') {
    $orderBy = "m.angkatan DESC";
}

$query = mysqli_query($conn, "
    SELECT 
        d.nim,
        d.nama_mahasiswa,
        d.dosen_pa,
        d.ipk,
        d.sks_lulus,
        m.angkatan,
        r.nilai_preferensi,
        r.ranking,
        h.status_early_warning
    FROM data_akademik_terbaru d
    " . ($role === 'dpa' ? "INNER JOIN mahasiswa m ON d.nim = m.nim" : "LEFT JOIN mahasiswa m ON d.nim = m.nim") . "
    LEFT JOIN ranking_topsis_terbaru r ON d.nim = r.nim
    LEFT JOIN hasil_evaluasi_terbaru h ON d.nim = h.nim
    $where
    ORDER BY $orderBy
");

$totalData = mysqli_num_rows($query);

/* Halaman ini melayani 3 role sekaligus - tentukan
   link Dashboard, judul, dan label sesuai role aktif. */
$dashboardPage = ($role === 'dpa') ? 'dashboard_dpa.php' : (($role === 'admin') ? 'dashboard_admin.php' : 'dashboard_kaprodi.php');
$roleLabel     = ($role === 'dpa') ? 'Dosen PA' : (($role === 'admin') ? 'Admin' : 'Kaprodi');
$judulHalaman  = ($role === 'dpa') ? 'Mahasiswa Bimbingan' : 'Monitoring Seluruh Mahasiswa';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monitoring Mahasiswa</title>

    <link rel="stylesheet" href="../assets/css/style.css?v=10">
</head>
<body>

<div class="dashboard-wrapper">

    <!-- SECTION 1: SIDEBAR -->
    <button type="button" class="sidebar-toggle-btn" id="sidebarToggleBtn" onclick="toggleSidebar()" aria-label="Buka menu">
        &#9776;
    </button>
    <div class="sidebar-backdrop" id="sidebarBackdrop" onclick="closeSidebar()"></div>

    <aside class="section-sidebar" id="sectionSidebar">
        <button type="button" class="sidebar-close-btn" onclick="closeSidebar()" aria-label="Tutup menu">&#10005;</button>
        <div class="logo-area">
            <img src="../assets/img/logo_psti.jpg" class="sidebar-logo">
            <span class="logo-text">Prioritas Mahasiswa<br>Bimbingan</span>
        </div>

        <nav class="nav-menu">
            <a href="<?php echo $dashboardPage; ?>" class="nav-link">Dashboard</a>
            <a href="monitoring.php" class="nav-link active">Monitoring</a>
            <?php if ($role === 'admin'): ?>
            <a href="manajemen_data.php" class="nav-link">Manajemen Data</a>
            <a href="konfigurasi_kriteria.php" class="nav-link">Konfigurasi Kriteria</a>
            <?php endif; ?>
        </nav>

        <a href="../logout.php" class="logout-button">LOGOUT</a>
    </aside>

    <!-- AREA KANAN -->
    <main class="dashboard-main">
        <section class="section-topbar">
            <h3>Dashboard</h3>
            <div class="admin-info">
                <div>
                    <strong><?php echo $_SESSION['nama_lengkap']; ?></strong><br>
                    <small><?php echo $roleLabel; ?></small>
                </div>
                <div class="admin-avatar"></div>
            </div>
        </section>

        <form method="GET" action="" class="monitor-filter-card">
            <div class="sort-area">
                <span>Urutkan Berdasarkan</span>

                <div class="sort-select-wrapper">
                    <select name="sort" class="sort-select" onchange="this.form.submit()">
                        <option value="ranking" <?php if ($sort == 'ranking') echo 'selected'; ?>>Ranking</option>
                        <option value="ipk" <?php if ($sort == 'ipk') echo 'selected'; ?>>IPK</option>
                        <option value="skor" <?php if ($sort == 'skor') echo 'selected'; ?>>Skor TOPSIS</option>
                        <option value="angkatan" <?php if ($sort == 'angkatan') echo 'selected'; ?>>Angkatan</option>
                        <!-- <option value="status" <?php if ($sort == 'status') echo 'selected'; ?>>Status</option> -->
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

                <button type="submit" style="border:none;background:none;cursor:pointer;">
                    <i class="fa-solid fa-magnifying-glass"></i>
                </button>
            </div>
        </form>

        <section class="section-content monitoring-content">
            <div class="monitoring-title">
                <h2><?php echo $judulHalaman; ?></h2>
                <p><?php echo $totalData; ?> total</p>
            </div>

            <div class="table-scroll-wrapper">
            <table class="data-table monitoring-table">
                <thead>
                    <tr>
                        <th>Ranking</th>
                        <th>NIM</th>
                        <th>Nama</th>
                        <?php if ($role !== 'dpa'): ?>
                        <th>Dosen PA</th>
                        <?php endif; ?>
                        <th>IPK</th>
                        <th>SKS</th>
                        <th class="info-clickable-text" onclick="showInfoModal('nilai_preferensi_topsis')">Skor TOPSIS</th>
                        <th class="info-clickable-text" onclick="showInfoModal('kategori_status')">Status</th>
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
                            <?php if ($role !== 'dpa'): ?>
                            <td><?php echo $row['dosen_pa']; ?></td>
                            <?php endif; ?>
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
                            <td colspan="<?php echo $role !== 'dpa' ? 9 : 8; ?>" style="text-align:center;">
                                <?php echo $role === 'dpa' ? 'Tidak ada mahasiswa bimbingan' : 'Data tidak ditemukan'; ?>
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
            </div>
        </section>
    </main>
</div>

<script>
/* PERBAIKAN: pastikan tabel dengan scroll horizontal selalu
   mulai dari posisi PALING KIRI saat halaman dimuat - beberapa
   browser (terutama saat kembali dari halaman lain via tombol
   back, atau reload) bisa mempertahankan posisi scroll lama
   dari kunjungan sebelumnya, membuat kolom pertama (Ranking,
   NIM) tersembunyi di luar area terlihat. */
document.querySelectorAll('.table-scroll-wrapper').forEach(function (el) {
    el.scrollLeft = 0;
});
</script>

<script src="../assets/js/sidebar.js?v=2"></script>
<script src="../assets/js/info_modal.js?v=1"></script>
</body>
</html>