<?php
session_start();
include "../config/database.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit;
}

$kriteria = mysqli_query($conn, "SELECT * FROM kriteria ORDER BY id_kriteria ASC");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Konfigurasi Kriteria</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

<div class="app-layout">
    <aside class="sidebar">
        <h2 class="sidebar-title">SPK Mahasiswa</h2>

        <nav class="nav-menu">
            <a href="dashboard_admin.php" class="nav-link">Dashboard</a>
            <a href="manajemen_data.php" class="nav-link">Manajemen Data</a>
            <a href="konfigurasi_kriteria.php" class="nav-link active">Konfigurasi Kriteria</a>
            <a href="../logout.php" class="nav-link logout-link">Logout</a>
        </nav>
    </aside>

    <main class="main-content">
        <header class="topbar">
            <div>
                <h3>Konfigurasi Kriteria</h3>
                <p>Kelola bobot dan jenis kriteria SPK</p>
            </div>
            <span class="user-info"><?php echo $_SESSION['nama_lengkap']; ?></span>
        </header>

        <section class="content-section">
            <h3>Data Kriteria</h3>

            <table class="data-table">
                <thead>
                    <tr>
                        <th>Kode</th>
                        <th>Nama Kriteria</th>
                        <th>Jenis</th>
                        <th>Bobot</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = mysqli_fetch_assoc($kriteria)) { ?>
                    <tr>
                        <td><?php echo $row['kode_kriteria']; ?></td>
                        <td><?php echo $row['nama_kriteria']; ?></td>
                        <td>
                            <select class="filter-select">
                                <option value="benefit" <?php if ($row['jenis'] == 'benefit') echo 'selected'; ?>>Benefit</option>
                                <option value="cost" <?php if ($row['jenis'] == 'cost') echo 'selected'; ?>>Cost</option>
                            </select>
                        </td>
                        <td>
                            <input type="number" step="0.01" class="form-input bobot-input" value="<?php echo $row['bobot']; ?>">
                        </td>
                    </tr>
                    <?php } ?>
                </tbody>
            </table>

            <div class="weight-info">
                Total Bobot: <span id="totalBobot">0</span>
            </div>

            <button class="btn-primary">Simpan Bobot</button>
        </section>
    </main>
</div>

<script src="../assets/js/script.js"></script>
</body>
</html>