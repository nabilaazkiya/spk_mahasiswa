<?php
session_start();
include "../config/database.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit;
}

if (!isset($_GET['id'])) {
    header("Location: manajemen_data.php");
    exit;
}

$id_user = mysqli_real_escape_string($conn, $_GET['id']);

$result = mysqli_query($conn, "
    SELECT * FROM user 
    WHERE id_user = '$id_user'
");

if (mysqli_num_rows($result) == 0) {
    echo "Data pengguna tidak ditemukan.";
    exit;
}

$user = mysqli_fetch_assoc($result);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Pengguna</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css?v=8">
</head>
<body>

<div class="dashboard-wrapper">

    <button type="button" class="sidebar-toggle-btn" id="sidebarToggleBtn" onclick="toggleSidebar()" aria-label="Buka menu">
        &#9776;
    </button>
    <div class="sidebar-backdrop" id="sidebarBackdrop" onclick="closeSidebar()"></div>

    <aside class="section-sidebar" id="sectionSidebar">
        <button type="button" class="sidebar-close-btn" onclick="closeSidebar()" aria-label="Tutup menu">&#10005;</button>
        <div class="logo-area">
            <img src="../assets/img/logo_psti.jpg" class="sidebar-logo" alt="Logo">
            <span class="logo-text">Prioritas Mahasiswa<br>Bimbingan</span>
        </div>

        <nav class="nav-menu">
            <a href="dashboard_admin.php" class="nav-link">Dashboard</a>
            <a href="manajemen_data.php" class="nav-link active">Manajemen Data</a>
            <a href="konfigurasi_kriteria.php" class="nav-link">Konfigurasi Kriteria</a>
        </nav>

        <a href="../logout.php" class="logout-button">LOGOUT</a>
    </aside>

    <main class="dashboard-main">

        <section class="section-topbar">
            <h3>Edit Pengguna</h3>

            <div class="admin-info">
                <div>
                    <strong><?php echo $_SESSION['nama_lengkap']; ?></strong><br>
                    <small>ADMIN</small>
                </div>
                <div class="admin-avatar"></div>
            </div>
        </section>

        <section class="section-content">

            <div class="section-title">
                <h2>Form Edit Pengguna</h2>
                <p>Ubah data akun pengguna</p>
            </div>

            <form action="../proses/edit_user_proses.php" method="POST" class="user-form">

                <input type="hidden" name="id_user" value="<?php echo $user['id_user']; ?>">

                <div class="form-group">
                    <label>Username</label>
                    <input 
                        type="text"
                        name="username"
                        class="form-input"
                        value="<?php echo htmlspecialchars($user['username']); ?>"
                        required
                    >
                </div>

                <div class="form-group">
                    <label>Password Baru</label>
                    <input 
                        type="password"
                        name="password"
                        class="form-input"
                        placeholder="Kosongkan jika tidak ingin mengubah password"
                    >
                </div>

                <div class="form-group">
                    <label>Nama Lengkap</label>
                    <input 
                        type="text"
                        name="nama_lengkap"
                        class="form-input"
                        value="<?php echo htmlspecialchars($user['nama_lengkap']); ?>"
                        required
                    >
                </div>

                <div class="form-group">
                    <label>Status SIA</label>
                    <select name="status_sia" class="form-input" required>
                        <option value="aktif" <?php if ($user['status_sia'] == 'aktif') echo 'selected'; ?>>Aktif</option>
                        <option value="nonaktif" <?php if ($user['status_sia'] == 'nonaktif') echo 'selected'; ?>>Nonaktif</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Role Pengguna</label>
                    <select name="role" class="form-input" required>
                        <option value="admin" <?php if ($user['role'] == 'admin') echo 'selected'; ?>>Admin</option>
                        <option value="kaprodi" <?php if ($user['role'] == 'kaprodi') echo 'selected'; ?>>Kaprodi</option>
                        <option value="dpa" <?php if ($user['role'] == 'dpa') echo 'selected'; ?>>DPA</option>
                        <option value="mahasiswa" <?php if ($user['role'] == 'mahasiswa') echo 'selected'; ?>>Mahasiswa</option>
                    </select>
                </div>

                <div class="form-action">
                    <a href="manajemen_data.php" class="btn-secondary">Kembali</a>
                    <button type="submit" class="btn-add">Simpan Perubahan</button>
                </div>

            </form>

        </section>

    </main>

</div>

<script src="../assets/js/sidebar.js?v=2"></script>
</body>
</html>