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

<div class="dashboard-wrapper">

    <!-- SECTION 1: SIDEBAR -->
    <aside class="section-sidebar">
        <div class="logo-area">
            <img src="../assets/img/logo_psti.jpg" class="sidebar-logo" alt="Logo PSTI">
            <span class="logo-text">Prioritas Mahasiswa<br>Bimbingan</span>
        </div>

        <nav class="nav-menu">
            <a href="dashboard_admin.php" class="nav-link">Dashboard</a>
            <a href="manajemen_data.php" class="nav-link">Manajemen Data</a>
            <a href="konfigurasi_kriteria.php" class="nav-link active">Konfigurasi Kriteria</a>
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

        <!-- SECTION 3: KONTEN KRITERIA -->
        <section class="section-content criteria-content">
            <div class="criteria-header">
                <h2>Manajemen Bobot Kriteria (Metode TOPSIS)</h2>
                <button type="button" class="btn-add">+ Tambah Kriteria Baru</button>
            </div>

            <table class="data-table criteria-table">
                <thead>
                    <tr>
                        <th>Kode</th>
                        <th>Kriteria</th>
                        <th>Atribut (Tipe)</th>
                        <th>Nilai Bobot (Weight)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = mysqli_fetch_assoc($kriteria)) { ?>
                    <tr>
                        <td><?php echo $row['kode_kriteria']; ?></td>
                        <td><?php echo $row['nama_kriteria']; ?></td>
                        <td>
                            <select class="filter-select criteria-select">
                                <option value="benefit" <?php if ($row['jenis'] == 'benefit') echo 'selected'; ?>>
                                    Benefit
                                </option>
                                <option value="cost" <?php if ($row['jenis'] == 'cost') echo 'selected'; ?>>
                                    Cost
                                </option>
                            </select>
                        </td>
                        <td>
                            <input 
                                type="number" 
                                step="0.01" 
                                class="form-input bobot-input" 
                                value="<?php echo $row['bobot']; ?>"
                            >
                        </td>
                    </tr>
                    <?php } ?>
                </tbody>
            </table>
        </section>

        <!-- SECTION 4: TOTAL BOBOT -->
        <section class="weight-card">
            <div>
                <h2>Total Bobot : <span id="totalBobot">1.00</span> (100%) <span class="check-icon">✓</span></h2>
                <p>Total bobot harus tepat 1.00 untuk keakuratan model.</p>
            </div>

            <button class="save-weight-btn">Simpan Bobot</button>
        </section>

    </main>
</div>

<script src="../assets/js/script.js"></script>
</body>
</html>