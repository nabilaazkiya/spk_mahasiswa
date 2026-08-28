<?php
session_start();
include "../config/database.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit;
}

/* CEK KOLOM bobot_delphi */
$cekKolom = mysqli_query($conn, "SHOW COLUMNS FROM kriteria LIKE 'bobot_delphi'");
if (mysqli_num_rows($cekKolom) == 0) {
    mysqli_query($conn, "
        ALTER TABLE kriteria 
        ADD COLUMN bobot_delphi DECIMAL(10,6) DEFAULT 0
    ");
}

$fileCsvDelphi = "../assets/data_delphi.csv";
$dataDelphi = [];

if (file_exists($fileCsvDelphi)) {
    $fileHandle = fopen($fileCsvDelphi, "r");
    $headerCsv = fgetcsv($fileHandle);

    while (($rowCsv = fgetcsv($fileHandle)) !== false) {
        $rowAssoc = array_combine($headerCsv, $rowCsv);

        $nilaiPakar = [];
        foreach ($rowAssoc as $key => $value) {
            if (strpos($key, 'pakar_') === 0) {
                $nilaiPakar[] = floatval($value);
            }
        }

        if (count($nilaiPakar) == 0) {
            continue;
        }

        $dataDelphi[] = [
            "kode"  => trim($rowAssoc['kode_kriteria']),
            "nama"  => trim($rowAssoc['nama_kriteria']),
            "kolom" => trim($rowAssoc['kolom_data']),
            "jenis" => strtolower(trim($rowAssoc['jenis'])),
            "nilai" => $nilaiPakar
        ];
    }
    fclose($fileHandle);
}

/* HITUNG RATA-RATA DELPHI */
$totalRataRata = 0;

foreach ($dataDelphi as $index => $item) {
    $rata = array_sum($item['nilai']) / count($item['nilai']);
    $dataDelphi[$index]['rata'] = $rata;
    $totalRataRata += $rata;
}

/* SIMPAN / UPDATE HASIL DELPHI KE DATABASE */
if ($totalRataRata > 0) {
    foreach ($dataDelphi as $item) {
        $kode = mysqli_real_escape_string($conn, $item['kode']);
        $nama = mysqli_real_escape_string($conn, $item['nama']);
        $kolom = mysqli_real_escape_string($conn, $item['kolom']);
        $jenis = mysqli_real_escape_string($conn, $item['jenis']);
        $bobot = $item['rata'] / $totalRataRata;

        $cek = mysqli_query($conn, "
            SELECT id_kriteria 
            FROM kriteria 
            WHERE kode_kriteria = '$kode'
        ");

        if (mysqli_num_rows($cek) > 0) {
            mysqli_query($conn, "
                UPDATE kriteria SET
                    nama_kriteria = '$nama',
                    kolom_data = '$kolom',
                    jenis = '$jenis',
                    bobot_delphi = '$bobot'
                WHERE kode_kriteria = '$kode'
            ");
        } else {
            mysqli_query($conn, "
                INSERT INTO kriteria (
                    kode_kriteria,
                    nama_kriteria,
                    kolom_data,
                    jenis,
                    bobot_delphi
                ) VALUES (
                    '$kode',
                    '$nama',
                    '$kolom',
                    '$jenis',
                    '$bobot'
                )
            ");
        }
    }
}

/* AMBIL KRITERIA BERDASARKAN BOBOT DELPHI TERTINGGI */
$kriteria = mysqli_query($conn, "
    SELECT * FROM kriteria 
    ORDER BY bobot_delphi DESC, id_kriteria ASC
");

$totalBobot = 0;
$totalKriteria = mysqli_num_rows($kriteria);
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

    <aside class="section-sidebar">
        <div class="logo-area">
            <img src="../assets/img/logo_psti.jpg" class="sidebar-logo" alt="Logo PSTI">
            <span class="logo-text">Prioritas Mahasiswa<br>Bimbingan</span>
        </div>

        <nav class="nav-menu">
            <a href="dashboard_admin.php" class="nav-link">Dashboard</a>
            <a href="monitoring.php" class="nav-link">Monitoring</a>
            <a href="manajemen_data.php" class="nav-link">Manajemen Data</a>
            <a href="konfigurasi_kriteria.php" class="nav-link active">Konfigurasi Kriteria</a>
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

        <section class="section-content criteria-content">
            <div class="criteria-header">
                <h2>Konfigurasi Kriteria</h2>
                <p>
                    Halaman ini menampilkan seluruh kriteria hasil pembobotan Delphi
                    yang dihitung otomatis dari file <code>assets/data_delphi.csv</code>
                    dan digunakan dalam proses TOPSIS dan SAW.
                </p>
                <?php if (!file_exists($fileCsvDelphi)): ?>
                    <p style="color:#b91c1c; font-weight:600;">
                        &#9888; File assets/data_delphi.csv tidak ditemukan. Bobot Delphi tidak dapat dihitung ulang -
                        data kriteria yang tampil di bawah adalah data lama dari database.
                    </p>
                <?php endif; ?>
            </div>

            <table class="data-table criteria-table">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Kode</th>
                        <th>Kriteria</th>
                        <th>Kolom Data</th>
                        <th>Atribut</th>
                        <th>Rata-rata Delphi</th>
                        <th>Bobot Delphi</th>
                        <th>Persentase</th>
                        <th>Prioritas</th>
                    </tr>
                </thead>

                <tbody>
                    <?php if ($totalKriteria > 0) { ?>
                        <?php 
                        $no = 1;

                        while ($row = mysqli_fetch_assoc($kriteria)) { 
                            $bobot = floatval($row['bobot_delphi']);
                            $totalBobot += $bobot;

                            $rataDelphi = 0;
                            foreach ($dataDelphi as $d) {
                                if ($d['kode'] == $row['kode_kriteria']) {
                                    $rataDelphi = $d['rata'];
                                    break;
                                }
                            }
                        ?>
                        <tr>
                            <td><?php echo $no; ?></td>
                            <td><?php echo htmlspecialchars($row['kode_kriteria']); ?></td>
                            <td><?php echo htmlspecialchars($row['nama_kriteria']); ?></td>
                            <td><?php echo htmlspecialchars($row['kolom_data']); ?></td>
                            <td><?php echo ucfirst(htmlspecialchars($row['jenis'])); ?></td>
                            <td><?php echo number_format($rataDelphi, 2); ?></td>
                            <td><?php echo number_format($bobot, 6); ?></td>
                            <td><?php echo number_format($bobot * 100, 2); ?>%</td>
                            <td>Prioritas <?php echo $no; ?></td>
                        </tr>
                        <?php 
                            $no++;
                        } 
                        ?>
                    <?php } else { ?>
                        <tr>
                            <td colspan="9" style="text-align:center;">Belum ada data kriteria</td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>

            <section class="weight-card">
                <div>
                    <h2>
                        Total Bobot :
                        <?php echo number_format($totalBobot, 6); ?>
                        (<?php echo number_format($totalBobot * 100, 2); ?>%)
                        <?php echo (abs($totalBobot - 1.00) < 0.001) ? '✅' : '❌'; ?>
                    </h2>
                    <p>Total kriteria: <?php echo $totalKriteria; ?></p>
                    <p>Bobot Delphi dihitung otomatis dari nilai pakar di file assets/data_delphi.csv.</p>
                </div>
            </section>
        </section>

    </main>
</div>

</body>
</html>