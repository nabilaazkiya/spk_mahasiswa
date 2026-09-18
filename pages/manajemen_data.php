<?php
session_start();
include "../config/database.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit;
}

/* status_sia_mahasiswa DIHAPUS (permintaan user) - status_sia
   (aktif/cuti/do/-) sekarang satu-satunya sumber kebenaran status
   mahasiswa, dipakai langsung di badge tabel di bawah. */

/* =============================================
   AUTO-FIX: REFRESH VIEW data_akademik_terbaru
   Dibuat ulang dengan CREATE OR REPLACE supaya kolom
   terbaru di data_akademik selalu ikut masuk ke VIEW.
   Aman dijalankan berkali-kali (tidak merusak data).
   ============================================= */
mysqli_query($conn, "
    CREATE OR REPLACE VIEW data_akademik_terbaru AS
    SELECT da.*
    FROM data_akademik da
    INNER JOIN (
        SELECT nim, MAX(id_data) AS id_data_terbaru
        FROM data_akademik
        GROUP BY nim
    ) terbaru
    ON da.nim = terbaru.nim
    AND da.id_data = terbaru.id_data_terbaru
");

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

/* FITUR BARU (tanpa tabel baru): daftar batch upload, dikelompokkan
   dari kesamaan tanggal_upload persis di riwayat_akademik (satu
   waktu yang sama dipakai untuk semua baris dalam satu kali proses
   import - lihat $waktuImpor di proses/input_data.php), untuk modal
   Lihat/Hapus Data Upload. */
$daftarBatchUpload = [];
$batchQuery = mysqli_query($conn, "
    SELECT ra.tanggal_upload, COUNT(*) AS jumlah_data
    FROM riwayat_akademik ra
    GROUP BY ra.tanggal_upload
    ORDER BY ra.tanggal_upload DESC
");
if ($batchQuery) {
    while ($b = mysqli_fetch_assoc($batchQuery)) {
        $daftarBatchUpload[] = $b;
    }
}

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
    <link rel="stylesheet" href="../assets/css/style.css?v=10">
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
            <a href="monitoring.php" class="nav-link">Monitoring</a>
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

                <div class="dropdown-tools" id="dropdownImport">
                    <button type="button" class="btn-add" onclick="document.getElementById('dropdownImport').classList.toggle('open')">
                        Import dari CSV/XLSX &#9662;
                    </button>
                    <div class="dropdown-tools-menu">
                        <a href="#" onclick="document.getElementById('fileImport').click(); document.getElementById('dropdownImport').classList.remove('open'); return false;">
                            &#128228; Upload Dokumen
                        </a>
                        <a href="../assets/templates/Template_Format_Data.xlsx" download="Template_Format_Data.xlsx">
                            &#128229; Download Template Data Excel
                        </a>
                        <a href="#" onclick="document.getElementById('modalDokumen').style.display='flex'; document.getElementById('dropdownImport').classList.remove('open'); return false;">
                            &#128193; Lihat / Hapus Data Upload
                        </a>
                        <a href="#" onclick="if(confirm('Hitung ulang TOPSIS, SAW, dan Spearman sekarang dari data akademik terkini?')){ document.getElementById('formHitungUlang').submit(); } document.getElementById('dropdownImport').classList.remove('open'); return false;">
                            &#128260; Hitung Ulang TOPSIS &amp; SAW
                        </a>
                    </div>

                    <form id="formHitungUlang" method="POST" action="../proses/hitung_ulang.php"></form>

                    <form id="formImport" method="POST" action="../proses/input_data.php" enctype="multipart/form-data">
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
                            <th>Status SIA</th>
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
                            <td>
                                <?php
                                $statusSia = strtolower(trim((string) ($akademik['status_sia'] ?? 'aktif')));
                                $labelStatus = 'Aktif';
                                $kelasStatus = 'status-aktif';
                                if ($statusSia === 'do') {
                                    $labelStatus = 'DO';
                                    $kelasStatus = 'status-nonaktif';
                                } elseif ($statusSia !== 'aktif' && $statusSia !== '') {
                                    $labelStatus = 'Tidak Aktif';
                                    $kelasStatus = 'status-nonaktif';
                                }
                                ?>
                                <span class="status-badge <?php echo $kelasStatus; ?>">
                                    <?php echo $labelStatus; ?>
                                </span>
                            </td>
                        </tr>
                        <?php } ?>
                    </tbody>
                </table>
                </div>
            </div>

        </section>
    </main>
</div>

<!-- ═══════════════════════════════════════
     MODAL: LIHAT / HAPUS DOKUMEN TERUPLOAD
     ═══════════════════════════════════════ -->
<div id="modalDokumen" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:1000;align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:10px;padding:24px;max-width:700px;width:90%;max-height:80vh;overflow-y:auto;font-family:Arial,sans-serif;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
            <h3 style="margin:0;font-size:18px;color:#333;">Data Upload Tersimpan</h3>
            <button type="button" onclick="document.getElementById('modalDokumen').style.display='none';" style="background:none;border:none;font-size:22px;cursor:pointer;color:#888;line-height:1;">&times;</button>
        </div>

        <?php if (empty($daftarBatchUpload)): ?>
            <p style="color:#888;">Belum ada data yang diupload.</p>
        <?php else: ?>
            <p style="color:#888;font-size:12px;margin-top:0;">Dikelompokkan berdasarkan waktu upload (semua data dari satu kali proses import yang sama).</p>
            <table style="width:100%;border-collapse:collapse;font-size:13px;">
                <thead>
                    <tr style="text-align:left;border-bottom:2px solid #eee;">
                        <th style="padding:8px 6px;">Waktu Upload</th>
                        <th style="padding:8px 6px;">Jumlah Data</th>
                        <th style="padding:8px 6px;"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($daftarBatchUpload as $batch): ?>
                        <tr style="border-bottom:1px solid #f0f0f0;">
                            <td style="padding:8px 6px;white-space:nowrap;"><?php echo date('d/m/Y H:i:s', strtotime($batch['tanggal_upload'])); ?></td>
                            <td style="padding:8px 6px;"><?php echo (int) $batch['jumlah_data']; ?> baris</td>
                            <td style="padding:8px 6px;">
                                <form method="POST" action="../proses/hapus_batch_upload.php" onsubmit="return confirm('Hapus data upload tanggal <?php echo date('d/m/Y H:i:s', strtotime($batch['tanggal_upload'])); ?>?\n\nSeluruh data akademik mahasiswa (<?php echo (int) $batch['jumlah_data']; ?> baris) dari batch ini akan IKUT TERHAPUS dari database. Tindakan ini tidak bisa dibatalkan.');">
                                    <input type="hidden" name="waktu_upload" value="<?php echo htmlspecialchars($batch['tanggal_upload']); ?>">
                                    <button type="submit" style="background:#e74c3c;color:#fff;border:none;border-radius:5px;padding:6px 12px;cursor:pointer;font-size:12px;">
                                        Hapus
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<script>
document.getElementById('fileImport').addEventListener('change', function() {
    if (this.files.length > 0) {
        this.form.submit();
    }
});

document.addEventListener('click', function(e) {
    var dropdown = document.getElementById('dropdownImport');
    if (dropdown && !dropdown.contains(e.target)) {
        dropdown.classList.remove('open');
    }
});

document.getElementById('modalDokumen').addEventListener('click', function(e) {
    if (e.target === this) {
        this.style.display = 'none';
    }
});
</script>

<script src="../assets/js/sidebar.js?v=2"></script>
</body>
</html>