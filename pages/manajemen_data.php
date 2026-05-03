<?php
session_start();
include "../config/database.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit;
}

$keyword = isset($_GET['keyword']) ? $_GET['keyword'] : '';
$role = isset($_GET['role']) ? $_GET['role'] : '';

$where = "WHERE 1=1";

if ($keyword != '') {
    $where .= " AND (username LIKE '%$keyword%' OR nama_lengkap LIKE '%$keyword%')";
}

if ($role != '') {
    $where .= " AND role='$role'";
}

$userQuery = mysqli_query($conn, "SELECT * FROM user $where ORDER BY id_user DESC");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Manajemen Data</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

<div class="app-layout">
    <aside class="sidebar">
        <h2 class="sidebar-title">SPK Mahasiswa</h2>

        <nav class="nav-menu">
            <a href="dashboard_admin.php" class="nav-link">Dashboard</a>
            <a href="manajemen_data.php" class="nav-link active">Manajemen Data</a>
            <a href="konfigurasi_kriteria.php" class="nav-link">Konfigurasi Kriteria</a>
            <a href="../logout.php" class="nav-link logout-link">Logout</a>
        </nav>
    </aside>

    <main class="main-content">
        <header class="topbar">
            <div>
                <h3>Manajemen Data</h3>
                <p>Kelola akun pengguna sistem</p>
            </div>
            <span class="user-info"><?php echo $_SESSION['nama_lengkap']; ?></span>
        </header>

        <section class="content-section">
            <form method="GET" class="table-toolbar">
                <input type="text" name="keyword" class="search-input" placeholder="Cari nama atau username" value="<?php echo $keyword; ?>">

                <select name="role" class="filter-select">
                    <option value="">Semua Role</option>
                    <option value="admin" <?php if ($role == 'admin') echo 'selected'; ?>>Admin</option>
                    <option value="kaprodi" <?php if ($role == 'kaprodi') echo 'selected'; ?>>Kaprodi</option>
                    <option value="dpa" <?php if ($role == 'dpa') echo 'selected'; ?>>DPA</option>
                    <option value="mahasiswa" <?php if ($role == 'mahasiswa') echo 'selected'; ?>>Mahasiswa</option>
                </select>

                <button type="submit" class="btn-secondary">Filter</button>
                <button type="button" class="btn-primary">+ Tambah Pengguna</button>
                <button type="button" class="btn-import">Import Excel / CSV</button>
            </form>

            <div class="sync-info">
                SIA Sync: belum terhubung otomatis. Data dapat diimpor manual melalui file Excel/CSV.
            </div>

            <table class="data-table">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Username</th>
                        <th>Nama Lengkap</th>
                        <th>Status SIA</th>
                        <th>Role</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $no = 1; while ($row = mysqli_fetch_assoc($userQuery)) { ?>
                    <tr>
                        <td><?php echo $no++; ?></td>
                        <td><?php echo $row['username']; ?></td>
                        <td><?php echo $row['nama_lengkap']; ?></td>
                        <td><span class="status-badge"><?php echo $row['status_sia']; ?></span></td>
                        <td><span class="role-badge"><?php echo $row['role']; ?></span></td>
                        <td>
                            <a href="#" class="action-edit">Edit</a>
                            <a href="#" class="action-delete">Hapus</a>
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