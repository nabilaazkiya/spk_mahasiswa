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
    $keywordSafe = mysqli_real_escape_string($conn, $keyword);
    $where .= " AND (username LIKE '%$keywordSafe%' OR nama_lengkap LIKE '%$keywordSafe%')";
}

if ($role != '') {
    $roleSafe = mysqli_real_escape_string($conn, $role);
    $where .= " AND role = '$roleSafe'";
}

$userQuery = mysqli_query($conn, "SELECT * FROM user $where ORDER BY id_user DESC");
$akademikQuery = mysqli_query($conn, "SELECT * FROM data_akademik ORDER BY id_data DESC");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Manajemen Data</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body>

<div class="dashboard-wrapper">
    <aside class="section-sidebar">
        <div class="logo-area">
            <img src="../assets/img/logo_psti.jpg" class="sidebar-logo" alt="Logo PSTI">
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
            <h3>Dashboard</h3>
            <div class="admin-info">
                <span><?php echo $_SESSION['nama_lengkap']; ?></span>
                <div class="admin-avatar"></div>
            </div>
        </section>

        <section class="section-content">
            <div class="section-title">
                <h2>Daftar Pengguna & Manajemen Peran</h2>
            </div>

            <div class="table-toolbar">
                <form method="GET" action="manajemen_data.php" style="display:flex; gap:10px; align-items:center;">
                    <input 
                        type="text" 
                        name="keyword" 
                        class="search-input" 
                        placeholder="Search" 
                        value="<?php echo htmlspecialchars($keyword); ?>"
                    >

                    <select name="role" class="filter-select" onchange="this.form.submit()">
                        <option value="">Semua Peran</option>
                        <option value="admin" <?php if ($role == 'admin') echo 'selected'; ?>>Admin</option>
                        <option value="kaprodi" <?php if ($role == 'kaprodi') echo 'selected'; ?>>Kaprodi</option>
                        <option value="dpa" <?php if ($role == 'dpa') echo 'selected'; ?>>DPA</option>
                        <option value="mahasiswa" <?php if ($role == 'mahasiswa') echo 'selected'; ?>>Mahasiswa</option>
                    </select>
                </form>

                <a href="tambah_user.php" class="btn-add">+ Tambah Pengguna Baru</a>

                <form method="POST" action="../proses/input_data.php" enctype="multipart/form-data">
                    <label for="fileImport" class="btn-add">Import dari CSV</label>
                    <input 
                        type="file" 
                        id="fileImport" 
                        name="file_import" 
                        accept=".csv" 
                        hidden 
                        required
                    >
                </form>
            </div>

            <div class="sync-info">
                <span>Sumber : Data SIA Terintegrasi (Terpisah)</span>
                <span class="sync-date">● SIA Sync: 10/03/26</span>
            </div>

            <table class="data-table">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>NIM/NIP</th>
                        <th>Nama</th>
                        <th>Peran (Role)</th>
                        <th>Status (SIA)</th>
                        <th>Dosen PA (mhs)</th>
                        <th>Aksi</th>
                    </tr>
                </thead>

                <tbody>
                    <?php $no = 1; while ($row = mysqli_fetch_assoc($userQuery)) { ?>
                    <tr>
                        <td><?php echo $no++; ?></td>
                        <td><?php echo $row['username']; ?></td>
                        <td><?php echo $row['nama_lengkap']; ?></td>
                        <td>
                            <span class="role-badge <?php echo $row['role']; ?>">
                                <?php echo $row['role']; ?>
                            </span>
                        </td>
                        <td>
                            <span class="status-badge <?php echo $row['status_sia'] == 'nonaktif' ? 'status-nonaktif' : 'status-aktif'; ?>">
                                <?php echo $row['status_sia']; ?>
                            </span>
                        </td>
                        <td>N/A</td>
                        <td>
                            <a href="edit_user.php?id=<?php echo $row['id_user']; ?>" class="action-edit" title="Edit">
                                <i class="fa-solid fa-pen"></i>
                            </a>

                            <a href="../proses/hapus_user.php?id=<?php echo $row['id_user']; ?>" class="action-delete" title="Hapus" onclick="return confirm('Yakin ingin menghapus pengguna ini?')">
                                <i class="fa-solid fa-trash"></i>
                            </a>
                        </td>
                    </tr>
                    <?php } ?>
                </tbody>
            </table>

            <div style="margin-top:40px;">
                <h2>Data Akademik Mahasiswa</h2>

                <table class="data-table">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>NIM</th>
                            <th>Nama Mahasiswa</th>
                            <th>Dosen PA</th>
                            <th>Semester</th>
                            <th>IPK</th>
                            <th>Skor TOEFL</th>
                            <th>Jumlah Mengulang</th>
                            <th>SKS Lulus</th>
                            <th>Sisa Masa Studi</th>
                            <th>Jalur Masuk</th>
                            <th>Absensi</th>
                            <th>SKS Diambil</th>
                            <th>SKS Nilai Kurang C</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php $noAkademik = 1; while ($akademik = mysqli_fetch_assoc($akademikQuery)) { ?>
                        <tr>
                            <td><?php echo $noAkademik++; ?></td>
                            <td><?php echo $akademik['nim']; ?></td>
                            <td><?php echo $akademik['nama_mahasiswa']; ?></td>
                            <td><?php echo $akademik['dosen_pa']; ?></td>
                            <td><?php echo $akademik['semester']; ?></td>
                            <td><?php echo $akademik['ipk']; ?></td>
                            <td><?php echo $akademik['skor_toefl']; ?></td>
                            <td><?php echo $akademik['jml_mengulang']; ?></td>
                            <td><?php echo $akademik['sks_lulus']; ?></td>
                            <td><?php echo $akademik['sisa_masa_studi']; ?></td>
                            <td><?php echo $akademik['jalur_masuk']; ?></td>
                            <td><?php echo $akademik['absensi']; ?></td>
                            <td><?php echo $akademik['sks_diambil']; ?></td>
                            <td><?php echo $akademik['sks_nilai_kurang_c']; ?></td>
                        </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>

        </section>
    </main>
</div>

<script>
document.getElementById('fileImport').addEventListener('change', function() {
    if (this.files.length > 0) {
        this.form.submit();
    }
});
</script>

</body>
</html>