<?php
session_start();
include "../config/database.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Tambah Pengguna</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

<div class="dashboard-wrapper">

    <!-- SIDEBAR -->
    <aside class="section-sidebar">

        <div class="logo-area">
            <img src="../assets/img/logo_psti.jpg" class="sidebar-logo" alt="Logo">
            <span class="logo-text">
                Prioritas Mahasiswa<br>
                Bimbingan
            </span>
        </div>

        <nav class="nav-menu">
            <a href="dashboard_admin.php" class="nav-link">
                Dashboard
            </a>

            <a href="manajemen_data.php" class="nav-link active">
                Manajemen Data
            </a>

            <a href="konfigurasi_kriteria.php" class="nav-link">
                Konfigurasi Kriteria
            </a>
        </nav>

        <a href="../logout.php" class="logout-button">
            LOGOUT
        </a>

    </aside>

    <!-- MAIN -->
    <main class="dashboard-main">

        <!-- TOPBAR -->
        <section class="section-topbar">

            <h3>Tambah Pengguna</h3>

            <div class="admin-info">
                <div>
                    <strong>
                        <?php echo $_SESSION['nama_lengkap']; ?>
                    </strong><br>

                    <small>ADMIN</small>
                </div>

                <div class="admin-avatar"></div>
            </div>

        </section>

        <!-- CONTENT -->
        <section class="section-content">

            <div class="section-title">
                <h2>Form Tambah Pengguna</h2>
                <p>Tambahkan akun baru ke sistem</p>
            </div>

            <form 
                action="../proses/tambah_user_proses.php" 
                method="POST"
                class="user-form"
            >

                <!-- USERNAME -->
                <div class="form-group">
                    <label>Username</label>

                    <input 
                        type="text"
                        name="username"
                        class="form-input"
                        placeholder="Masukkan username"
                        required
                    >
                </div>

                <!-- PASSWORD -->
                <div class="form-group">
                    <label>Password</label>

                    <input 
                        type="password"
                        name="password"
                        class="form-input"
                        placeholder="Masukkan password"
                        required
                    >
                </div>

                <!-- NAMA -->
                <div class="form-group">
                    <label>Nama Lengkap</label>

                    <input 
                        type="text"
                        name="nama_lengkap"
                        class="form-input"
                        placeholder="Masukkan nama lengkap"
                        required
                    >
                </div>

                <!-- STATUS -->
                <div class="form-group">
                    <label>Status SIA</label>

                    <select 
                        name="status_sia"
                        class="form-input"
                        required
                    >
                        <option value="aktif">Aktif</option>
                        <option value="nonaktif">Nonaktif</option>
                    </select>
                </div>

                <!-- ROLE -->
                <div class="form-group">
                    <label>Role Pengguna</label>

                    <select 
                        name="role"
                        class="form-input"
                        required
                    >
                        <option value="admin">Admin</option>
                        <option value="kaprodi">Kaprodi</option>
                        <option value="dpa">DPA</option>
                        <option value="mahasiswa">Mahasiswa</option>
                    </select>
                </div>

                <!-- BUTTON -->
                <div class="form-action">

                    <a 
                        href="manajemen_data.php"
                        class="btn-secondary"
                    >
                        Kembali
                    </a>

                    <button 
                        type="submit"
                        class="btn-add"
                    >
                        Simpan Pengguna
                    </button>

                </div>

            </form>

        </section>

    </main>

</div>

</body>
</html>