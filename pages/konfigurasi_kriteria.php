<?php
session_start();
include "../config/database.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit;
}

/* TAMBAH KRITERIA */
if (isset($_POST['tambah'])) {
    $kode       = mysqli_real_escape_string($conn, $_POST['kode_kriteria']);
    $nama       = mysqli_real_escape_string($conn, $_POST['nama_kriteria']);
    $kolom_data = mysqli_real_escape_string($conn, $_POST['kolom_data']);
    $jenis      = mysqli_real_escape_string($conn, $_POST['jenis']);
    $bobot      = mysqli_real_escape_string($conn, $_POST['bobot']);

    mysqli_query($conn, "
        INSERT INTO kriteria 
        (kode_kriteria, nama_kriteria, kolom_data, jenis, bobot)
        VALUES 
        ('$kode', '$nama', '$kolom_data', '$jenis', '$bobot')
    ");

    mysqli_query($conn, "
        INSERT INTO log_aktivitas (aksi, tanggal, id_user)
        VALUES ('Menambahkan kriteria: $nama', NOW(), '{$_SESSION['id_user']}')
    ");

    header("Location: konfigurasi_kriteria.php");
    exit;
}

/* EDIT KRITERIA */
if (isset($_POST['edit'])) {
    $id         = mysqli_real_escape_string($conn, $_POST['id_kriteria']);
    $kode       = mysqli_real_escape_string($conn, $_POST['kode_kriteria']);
    $nama       = mysqli_real_escape_string($conn, $_POST['nama_kriteria']);
    $kolom_data = mysqli_real_escape_string($conn, $_POST['kolom_data']);
    $jenis      = mysqli_real_escape_string($conn, $_POST['jenis']);
    $bobot      = mysqli_real_escape_string($conn, $_POST['bobot']);

    mysqli_query($conn, "
        UPDATE kriteria SET
            kode_kriteria = '$kode',
            nama_kriteria = '$nama',
            kolom_data = '$kolom_data',
            jenis = '$jenis',
            bobot = '$bobot'
        WHERE id_kriteria = '$id'
    ");

    mysqli_query($conn, "
        INSERT INTO log_aktivitas (aksi, tanggal, id_user)
        VALUES ('Mengedit kriteria: $nama', NOW(), '{$_SESSION['id_user']}')
    ");

    header("Location: konfigurasi_kriteria.php");
    exit;
}

/* SIMPAN SEMUA BOBOT */
if (isset($_POST['simpan'])) {
    foreach ($_POST['id_kriteria'] as $index => $id) {
        $id    = mysqli_real_escape_string($conn, $id);
        $jenis = mysqli_real_escape_string($conn, $_POST['jenis'][$index]);
        $bobot = mysqli_real_escape_string($conn, $_POST['bobot'][$index]);

        mysqli_query($conn, "
            UPDATE kriteria SET
                jenis = '$jenis',
                bobot = '$bobot'
            WHERE id_kriteria = '$id'
        ");
    }

    mysqli_query($conn, "
        INSERT INTO log_aktivitas (aksi, tanggal, id_user)
        VALUES ('Memperbarui bobot kriteria TOPSIS', NOW(), '{$_SESSION['id_user']}')
    ");

    echo "
    <script>
        alert('Bobot kriteria berhasil disimpan!');
        window.location='konfigurasi_kriteria.php';
    </script>
    ";
    exit;
}

/* HAPUS KRITERIA */
if (isset($_GET['hapus'])) {
    $id = mysqli_real_escape_string($conn, $_GET['hapus']);

    $data = mysqli_fetch_assoc(mysqli_query($conn, "
        SELECT nama_kriteria FROM kriteria WHERE id_kriteria='$id'
    "));

    $namaKriteria = $data ? $data['nama_kriteria'] : 'Kriteria';

    mysqli_query($conn, "DELETE FROM kriteria WHERE id_kriteria='$id'");

    mysqli_query($conn, "
        INSERT INTO log_aktivitas (aksi, tanggal, id_user)
        VALUES ('Menghapus kriteria: $namaKriteria', NOW(), '{$_SESSION['id_user']}')
    ");

    header("Location: konfigurasi_kriteria.php");
    exit;
}

$editData = null;

if (isset($_GET['edit'])) {
    $idEdit = mysqli_real_escape_string($conn, $_GET['edit']);
    $editData = mysqli_fetch_assoc(mysqli_query($conn, "
        SELECT * FROM kriteria WHERE id_kriteria='$idEdit'
    "));
}

/* BOBOT TERTINGGI DI ATAS */
$kriteria = mysqli_query($conn, "
    SELECT * FROM kriteria 
    ORDER BY bobot DESC, id_kriteria ASC
");
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
            </div>

            <!-- FORM TAMBAH KRITERIA -->
            <form method="POST" style="display:flex; gap:10px; margin-bottom:20px; flex-wrap:wrap;">
                <input 
                    type="hidden" 
                    name="id_kriteria" 
                    value="<?php echo $editData ? $editData['id_kriteria'] : ''; ?>"
                >

                <input 
                    type="text" 
                    name="kode_kriteria" 
                    class="form-input" 
                    placeholder="Kode, contoh: C1"
                    value="<?php echo $editData ? $editData['kode_kriteria'] : ''; ?>"
                    required
                >

                <input 
                    type="text" 
                    name="nama_kriteria" 
                    class="form-input" 
                    placeholder="Nama Kriteria"
                    value="<?php echo $editData ? $editData['nama_kriteria'] : ''; ?>"
                    required
                >

                <select name="kolom_data" class="filter-select" required>
                    <option value="">Pilih Kolom Data</option>

                    <option value="ipk" <?php if ($editData && $editData['kolom_data'] == 'ipk') echo 'selected'; ?>>
                        IPK
                    </option>

                    <option value="skor_toefl" <?php if ($editData && $editData['kolom_data'] == 'skor_toefl') echo 'selected'; ?>>
                        Skor TOEFL
                    </option>

                    <option value="jml_mengulang" <?php if ($editData && $editData['kolom_data'] == 'jml_mengulang') echo 'selected'; ?>>
                        Jumlah Mengulang
                    </option>

                    <option value="sks_lulus" <?php if ($editData && $editData['kolom_data'] == 'sks_lulus') echo 'selected'; ?>>
                        SKS Lulus
                    </option>

                    <option value="sisa_masa_studi" <?php if ($editData && $editData['kolom_data'] == 'sisa_masa_studi') echo 'selected'; ?>>
                        Sisa Masa Studi
                    </option>

                    <option value="jalur_masuk" <?php if ($editData && $editData['kolom_data'] == 'jalur_masuk') echo 'selected'; ?>>
                        Jalur Masuk
                    </option>

                    <option value="absensi" <?php if ($editData && $editData['kolom_data'] == 'absensi') echo 'selected'; ?>>
                        Absensi
                    </option>

                    <option value="sks_diambil" <?php if ($editData && $editData['kolom_data'] == 'sks_diambil') echo 'selected'; ?>>
                        SKS Diambil
                    </option>

                    <option value="sks_nilai_kurang_c" <?php if ($editData && $editData['kolom_data'] == 'sks_nilai_kurang_c') echo 'selected'; ?>>
                        SKS Nilai Kurang C
                    </option>
                </select>

                <select name="jenis" class="filter-select" required>
                    <option value="benefit" <?php if ($editData && $editData['jenis'] == 'benefit') echo 'selected'; ?>>
                        Benefit
                    </option>
                    <option value="cost" <?php if ($editData && $editData['jenis'] == 'cost') echo 'selected'; ?>>
                        Cost
                    </option>
                </select>

                <input 
                    type="number" 
                    step="0.01" 
                    name="bobot" 
                    class="form-input" 
                    placeholder="Bobot, contoh: 0.25"
                    value="<?php echo $editData ? $editData['bobot'] : ''; ?>"
                    required
                >

                <?php if ($editData) { ?>
                    <button type="submit" name="edit" class="btn-add">
                        Simpan Edit
                    </button>

                    <a href="konfigurasi_kriteria.php" class="btn-add" style="text-decoration:none;">
                        Batal
                    </a>
                <?php } else { ?>
                    <button type="submit" name="tambah" class="btn-add">
                        + Tambah Kriteria Baru
                    </button>
                <?php } ?>
            </form>

            <!-- FORM UPDATE BOBOT -->
            <form method="POST" id="formBobot">
                <table class="data-table criteria-table">
                    <thead>
                        <tr>
                            <th>Kode</th>
                            <th>Kriteria</th>
                            <th>Kolom Data</th>
                            <th>Atribut</th>
                            <th>Nilai Bobot</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php if (mysqli_num_rows($kriteria) > 0) { ?>
                            <?php while ($row = mysqli_fetch_assoc($kriteria)) { ?>
                            <tr>
                                <td>
                                    <?php echo $row['kode_kriteria']; ?>
                                    <input type="hidden" name="id_kriteria[]" value="<?php echo $row['id_kriteria']; ?>">
                                </td>

                                <td><?php echo $row['nama_kriteria']; ?></td>

                                <td>
                                    <?php echo isset($row['kolom_data']) ? $row['kolom_data'] : '-'; ?>
                                </td>
                                <td>
                                    <select name="jenis[]" class="filter-select criteria-select">
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
                                        name="bobot[]" 
                                        class="form-input bobot-input" 
                                        value="<?php echo $row['bobot']; ?>"
                                        required
                                    >
                                </td>

                                <td>
                                    <a 
                                        href="konfigurasi_kriteria.php?edit=<?php echo $row['id_kriteria']; ?>" 
                                        style="color:#007bff; text-decoration:none; margin-right:10px;"
                                    >
                                        Edit
                                    </a>

                                    <a 
                                        href="konfigurasi_kriteria.php?hapus=<?php echo $row['id_kriteria']; ?>" 
                                        onclick="return confirm('Yakin ingin menghapus kriteria ini?')"
                                        style="color:red; text-decoration:none;"
                                    >
                                        Hapus
                                    </a>
                                </td>
                            </tr>
                            <?php } ?>
                        <?php } else { ?>
                            <tr>
                                <td colspan="6" style="text-align:center;">Belum ada data kriteria</td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>

                <section class="weight-card">
                    <div>
                        <h2>
                            Total Bobot : 
                            <span id="totalBobot">0.00</span> 
                            (<span id="persenBobot">0</span>%)
                            <span id="statusBobot">❌</span>
                        </h2>
                        <p>Total bobot harus tepat 1.00 untuk keakuratan model TOPSIS.</p>
                        <p>Kriteria otomatis diurutkan berdasarkan bobot tertinggi.</p>
                    </div>

                    <button type="submit" name="simpan" class="save-weight-btn">
                        Simpan Bobot
                    </button>
                </section>
            </form>
        </section>

    </main>
</div>

<script>
function hitungTotalBobot() {
    let inputs = document.querySelectorAll('.bobot-input');
    let total = 0;

    inputs.forEach(function(input) {
        total += parseFloat(input.value) || 0;
    });

    document.getElementById('totalBobot').innerText = total.toFixed(2);
    document.getElementById('persenBobot').innerText = Math.round(total * 100);

    let status = document.getElementById('statusBobot');

    if (Math.abs(total - 1.00) < 0.001) {
        status.innerText = '✅';
    } else {
        status.innerText = '❌';
    }
}

document.querySelectorAll('.bobot-input').forEach(function(input) {
    input.addEventListener('input', hitungTotalBobot);
});

document.getElementById('formBobot').addEventListener('submit', function(e) {
    let total = 0;

    document.querySelectorAll('.bobot-input').forEach(function(input) {
        total += parseFloat(input.value) || 0;
    });

    if (Math.abs(total - 1.00) > 0.001) {
        e.preventDefault();
        alert('Total bobot harus tepat 1.00 sebelum disimpan.');
    }
});

hitungTotalBobot();
</script>

</body>
</html>