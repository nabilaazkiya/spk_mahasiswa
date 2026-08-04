<?php
session_start();
include "../config/database.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit;
}

$keyword = isset($_GET['keyword']) ? $_GET['keyword'] : '';
$role = isset($_GET['role']) ? $_GET['role'] : '';

$whereUser = "WHERE 1=1";
$whereAkademik = "WHERE 1=1";

if ($keyword != '') {
    $keywordSafe = mysqli_real_escape_string($conn, $keyword);

    $whereUser .= " AND (
        username LIKE '%$keywordSafe%' 
        OR nama_lengkap LIKE '%$keywordSafe%'
    )";

    $keywordNoSpace = str_replace(' ', '', $keywordSafe);

    $whereAkademik .= " AND (
        nim LIKE '%$keywordSafe%' 
        OR nama_mahasiswa LIKE '%$keywordSafe%'
        OR REPLACE(nama_mahasiswa, ' ', '') LIKE '%$keywordNoSpace%'
        OR dosen_pa LIKE '%$keywordSafe%'
    )";
}

if ($role != '') {
    $roleSafe = mysqli_real_escape_string($conn, $role);
    $whereUser .= " AND role = '$roleSafe'";
}

$userQuery = mysqli_query($conn, "SELECT * FROM user $whereUser ORDER BY id_user DESC");

$akademikQuery = mysqli_query($conn, "SELECT * FROM data_akademik_terbaru $whereAkademik ORDER BY id_data DESC");

/* DIAGNOSTIK: nama Dosen PA di data akademik yang belum
   punya akun DPA sama sekali - supaya kalau sinkronisasi
   tetap menghasilkan 0, admin langsung tahu penyebabnya
   (nama belum ada akunnya) tanpa harus menebak-nebak. */
$dosenBelumPunyaAkun = [];
$cekDosen = mysqli_query($conn, "
    SELECT DISTINCT d.dosen_pa
    FROM data_akademik_terbaru d
    WHERE d.dosen_pa IS NOT NULL AND TRIM(d.dosen_pa) != ''
    AND NOT EXISTS (
        SELECT 1 FROM user u
        WHERE u.role = 'dpa' AND TRIM(u.nama_lengkap) = TRIM(d.dosen_pa)
    )
");
if ($cekDosen) {
    while ($rowDosen = mysqli_fetch_assoc($cekDosen)) {
        $dosenBelumPunyaAkun[] = $rowDosen['dosen_pa'];
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Data</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=7">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
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

                <div class="dropdown-tools" id="dropdownTools">
                    <button type="button" class="btn-add" onclick="document.getElementById('dropdownTools').classList.toggle('open')">
                        &#9881; Alat Sinkronisasi &#9662;
                    </button>
                    <div class="dropdown-tools-menu">
                        <a
                            href="../proses/sinkron_dpa_manual.php"
                            title="Hubungkan ulang semua akun DPA ke mahasiswa yang sudah ada di database (untuk akun DPA lama yang belum pernah tersambung)"
                            onclick="return confirm('Sinkronkan ulang SEMUA akun DPA dengan data mahasiswa yang sudah ada di database sekarang?');"
                        >
                            &#8635; Sinkronkan Ulang Semua DPA
                        </a>
                        <a
                            href="../proses/topsis_backfill.php"
                            title="Hitung ulang TOPSIS untuk setiap semester lama yang datanya sudah ada di database, supaya grafik tren TOPSIS ikut menampilkan histori semester-semester sebelumnya"
                            onclick="return confirm('Hitung ulang histori TOPSIS untuk semua semester yang datanya sudah ada di database? Proses ini bisa memakan waktu jika data cukup banyak.');"
                        >
                            &#8635; Hitung Ulang Histori TOPSIS
                        </a>
                    </div>
                </div>

                <form method="POST" action="../proses/input_data.php" enctype="multipart/form-data">
                    <label for="fileImport" class="btn-add">Import dari CSV/XLSX</label>
                    <input 
                        type="file" 
                        id="fileImport" 
                        name="file_import" 
                        accept=".csv,.xlsx" 
                        hidden 
                        required
                    >
                </form>
            </div>

            <div class="sync-info">
                <span>Sumber : Data SIA Terintegrasi (Terpisah)</span>
                <!-- <span class="sync-date">● SIA Sync: 10/03/26</span> -->
            </div>

            <?php if (!empty($dosenBelumPunyaAkun)): ?>
            <div style="background:#fff3cd;border:1px solid #ffe69c;border-radius:8px;padding:12px 16px;margin-bottom:16px;color:#664d03;font-size:14px;">
                <strong>&#9888; <?php echo count($dosenBelumPunyaAkun); ?> nama Dosen PA di data akademik belum punya akun DPA:</strong>
                <?php echo htmlspecialchars(implode(', ', $dosenBelumPunyaAkun)); ?>.
                Mahasiswa bimbingan mereka tidak akan muncul di dashboard DPA manapun sampai akun dibuat dengan nama yang <u>persis sama</u>.
            </div>
            <?php endif; ?>

            <div class="table-scroll-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>NIM/NIP</th>
                        <th>Nama</th>
                        <th>Peran (Role)</th>
                        <th>Status (SIA)</th>
                        <!-- <th>Dosen PA (mhs)</th> -->
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
                        <!-- <td>N/A</td> -->
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
            </div>

            <div style="margin-top:40px;">
                <h2>Data Akademik Mahasiswa</h2>

                <div class="table-scroll-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>NIM</th>
                            <th>Nama Mahasiswa</th>
                            <th>Dosen PA</th>
                            <th>Semester</th>
                            <th>IP Semester</th>
                            <th>IPK</th>
                            <th>Skor TOEFL</th>
                            <th>Jumlah Mengulang</th>
                            <th>SKS Lulus</th>
                            <th>Sisa Masa Studi</th>
                            <th>Jalur Masuk</th>
                            <th>Absensi</th>
                            <th>SKS Diambil</th>
                            <th>SKS Nilai Kurang B</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php $noAkademik = 1; while ($akademik = mysqli_fetch_assoc($akademikQuery)) { ?>
                        <tr>
                            <td><?php echo $noAkademik++; ?></td>
                            <td><?php echo htmlspecialchars($akademik['nim']); ?></td>
                            <td><?php echo htmlspecialchars($akademik['nama_mahasiswa']); ?></td>
                            <td><?php echo htmlspecialchars($akademik['dosen_pa']); ?></td>
                            <td><?php echo htmlspecialchars($akademik['semester']); ?></td>
                            <td><?php echo $akademik['ip_semester'] !== null ? htmlspecialchars($akademik['ip_semester']) : '-'; ?></td>
                            <td><?php echo htmlspecialchars($akademik['ipk']); ?></td>
                            <td><?php echo htmlspecialchars($akademik['skor_toefl']); ?></td>
                            <td><?php echo htmlspecialchars($akademik['jml_mengulang']); ?></td>
                            <td><?php echo htmlspecialchars($akademik['sks_lulus']); ?></td>
                            <td><?php echo htmlspecialchars($akademik['sisa_masa_studi']); ?></td>
                            <td><?php echo htmlspecialchars($akademik['jalur_masuk']); ?></td>
                            <td><?php echo htmlspecialchars($akademik['absensi']); ?></td>
                            <td><?php echo htmlspecialchars($akademik['sks_diambil']); ?></td>
                            <td><?php echo htmlspecialchars($akademik['sks_nilai_kurang_b']); ?></td>
                        </tr>
                        <?php } ?>
                    </tbody>
                </table>
                </div>
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

document.addEventListener('click', function(e) {
    var dropdown = document.getElementById('dropdownTools');
    if (dropdown && !dropdown.contains(e.target)) {
        dropdown.classList.remove('open');
    }
});
</script>

<script src="../assets/js/sidebar.js?v=2"></script>
</body>
</html>