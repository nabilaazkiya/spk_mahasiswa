<?php
session_start();
include "../config/database.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'kaprodi') {
    header("Location: ../login.php");
    exit;
}

/* TOTAL MAHASISWA */
$totalMahasiswa = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COUNT(DISTINCT nim) AS total 
    FROM data_akademik
"));

/* JUMLAH KATEGORI */
$kritis = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COUNT(*) AS total 
    FROM hasil_evaluasi 
    WHERE status_early_warning='Kritis'
"));

$waspada = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COUNT(*) AS total 
    FROM hasil_evaluasi 
    WHERE status_early_warning='Waspada'
"));

$aman = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COUNT(*) AS total 
    FROM hasil_evaluasi 
    WHERE status_early_warning='Aman'
"));

$sangatBaik = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COUNT(*) AS total 
    FROM hasil_evaluasi 
    WHERE status_early_warning='Sangat Baik'
"));

/* =========================
   DATA SCATTER TOPSIS
========================= */

$dataMahasiswa = [];
$qMahasiswa = mysqli_query($conn, "SELECT * FROM data_akademik");

while ($row = mysqli_fetch_assoc($qMahasiswa)) {
    $dataMahasiswa[] = $row;
}

$dataKriteria = [];
$qKriteria = mysqli_query($conn, "
    SELECT * FROM kriteria 
    WHERE kolom_data IS NOT NULL 
    AND kolom_data != ''
    ORDER BY bobot DESC
");

while ($row = mysqli_fetch_assoc($qKriteria)) {
    $dataKriteria[] = $row;
}

function ambilNilaiKriteriaDashboard($mhs, $kolomData)
{
    return isset($mhs[$kolomData]) ? floatval($mhs[$kolomData]) : 0;
}

$scatterData = [];

if (count($dataMahasiswa) > 0 && count($dataKriteria) > 0) {

    $matriks = [];
    $normalisasi = [];
    $normalisasiTerbobot = [];
    $pembagi = [];
    $solusiPositif = [];
    $solusiNegatif = [];

    foreach ($dataMahasiswa as $mhs) {
        foreach ($dataKriteria as $krit) {
            $idKriteria = $krit['id_kriteria'];
            $kolomData = $krit['kolom_data'];

            $matriks[$mhs['nim']][$idKriteria] = ambilNilaiKriteriaDashboard($mhs, $kolomData);
        }
    }

    foreach ($dataKriteria as $krit) {
        $idKriteria = $krit['id_kriteria'];
        $totalKuadrat = 0;

        foreach ($dataMahasiswa as $mhs) {
            $totalKuadrat += pow($matriks[$mhs['nim']][$idKriteria], 2);
        }

        $pembagi[$idKriteria] = sqrt($totalKuadrat);

        foreach ($dataMahasiswa as $mhs) {
            $normalisasi[$mhs['nim']][$idKriteria] =
                $pembagi[$idKriteria] == 0
                ? 0
                : $matriks[$mhs['nim']][$idKriteria] / $pembagi[$idKriteria];
        }
    }

    foreach ($dataMahasiswa as $mhs) {
        foreach ($dataKriteria as $krit) {
            $idKriteria = $krit['id_kriteria'];
            $bobot = floatval($krit['bobot']);

            $normalisasiTerbobot[$mhs['nim']][$idKriteria] =
                $normalisasi[$mhs['nim']][$idKriteria] * $bobot;
        }
    }

    foreach ($dataKriteria as $krit) {
        $idKriteria = $krit['id_kriteria'];
        $jenis = strtolower($krit['jenis']);
        $nilaiKriteria = [];

        foreach ($dataMahasiswa as $mhs) {
            $nilaiKriteria[] = $normalisasiTerbobot[$mhs['nim']][$idKriteria];
        }

        if ($jenis == 'benefit') {
            $solusiPositif[$idKriteria] = max($nilaiKriteria);
            $solusiNegatif[$idKriteria] = min($nilaiKriteria);
        } else {
            $solusiPositif[$idKriteria] = min($nilaiKriteria);
            $solusiNegatif[$idKriteria] = max($nilaiKriteria);
        }
    }

    foreach ($dataMahasiswa as $mhs) {
        $dPlus = 0;
        $dMinus = 0;

        foreach ($dataKriteria as $krit) {
            $idKriteria = $krit['id_kriteria'];
            $nilai = $normalisasiTerbobot[$mhs['nim']][$idKriteria];

            $dPlus += pow($nilai - $solusiPositif[$idKriteria], 2);
            $dMinus += pow($nilai - $solusiNegatif[$idKriteria], 2);
        }

        $scatterData[] = [
            'x' => round(sqrt($dPlus), 4),
            'y' => round(sqrt($dMinus), 4)
        ];
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Dashboard Kaprodi</title>

    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        .chart-area {
            display: grid;
            grid-template-columns: 45% 55%;
            gap: 25px;
            align-items: center;
            background: #fff;
            padding: 25px;
            border-radius: 18px;
            margin-top: 20px;
        }

        .chart-box {
            height: 260px;
        }

        .scatter-box {
            background: #fff;
            padding: 25px;
            border-radius: 18px;
            margin-top: 20px;
            height: 320px;
        }

        .analysis-text {
            line-height: 1.8;
            font-size: 14px;
        }

        .detail-button {
            display: inline-block;
            margin-top: 15px;
        }
    </style>
</head>
<body>

<div class="dashboard-wrapper">


    <aside class="section-sidebar">
        <div class="logo-area">
            <img src="../assets/img/logo_psti.jpg" class="sidebar-logo" alt="Logo PSTI">
            <span class="logo-text">Prioritas Mahasiswa<br>Bimbingan</span>
        </div>

        <nav class="nav-menu">
            <a href="dashboard_kaprodi.php" class="nav-link active">Dashboard</a>
            <a href="monitoring.php" class="nav-link">Monitoring</a>
        </nav>

        <a href="../logout.php" class="logout-button">LOGOUT</a>
    </aside>

    <!-- AREA KANAN -->
    <main class="dashboard-main">

    <!-- SECTION 2: TOPBAR -->
        <section class="section-topbar">
            <h3>Dashboard</h3>

            <div class="admin-info">
                <div>
                    <strong><?php echo $_SESSION['nama_lengkap']; ?></strong><br>
                    <small>Kaprodi</small>
                </div>
                <div class="admin-avatar"></div>
            </div>
        </section>

        <!-- SECTION 3: TOTAL MAHASISWA -->
        <section class="dpa-total-card">
            <div class="total-info">
                <div class="total-header">
                    <div class="total-icon">
                        <i class="fa-solid fa-users"></i>
                    </div>
                    <h4>Total Mahasiswa</h4>
                </div>

                <p><?php echo $totalMahasiswa['total']; ?></p>
                <small>Mahasiswa</small>
            </div>

            <a href="monitoring.php" class="detail-button">Lihat Detail</a>
        </section>

        <!-- SECTION 4: KATEGORI -->
        <section class="dpa-category-grid">

            <div class="summary-card danger">
                <div class="card-header">
                    <div class="category-icon"><i class="fa-solid fa-user"></i></div>
                    <h4>Kategori Kritis</h4>
                </div>
                <p><?php echo $kritis['total']; ?></p>
                <small>Mahasiswa</small>
            </div>

            <div class="summary-card warning">
                <div class="card-header">
                    <div class="category-icon"><i class="fa-solid fa-user"></i></div>
                    <h4>Kategori Waspada</h4>
                </div>
                <p><?php echo $waspada['total']; ?></p>
                <small>Mahasiswa</small>
            </div>

            <div class="summary-card success">
                <div class="card-header">
                    <div class="category-icon"><i class="fa-solid fa-user"></i></div>
                    <h4>Kategori Aman</h4>
                </div>
                <p><?php echo $aman['total']; ?></p>
                <small>Mahasiswa</small>
            </div>

            <div class="summary-card excellent">
                <div class="card-header">
                    <div class="category-icon"><i class="fa-solid fa-user"></i></div>
                    <h4>Kategori Sangat Baik</h4>
                </div>
                <p><?php echo $sangatBaik['total']; ?></p>
                <small>Mahasiswa</small>
            </div>

        </section>

        <section class="chart-area">
            <div>
                <h4 style="text-align:center;">Grafik Sebaran Kategori</h4>
                <div class="chart-box">
                    <div class="pie-container">
                        <canvas id="pieChart"></canvas>
                    </div>
                </div>
            </div>

            <div class="analysis-text">
                <h4>Kesimpulan Sebaran Mahasiswa</h4>
                <p>
                    Dashboard ini menampilkan hasil evaluasi akademik mahasiswa
                    berdasarkan metode TOPSIS.
                </p>
                <p>
                    Mahasiswa dengan kategori Kritis dan Waspada perlu menjadi
                    prioritas pembinaan akademik oleh Kaprodi dan Dosen PA.
                </p>

                <a href="monitoring.php" class="detail-button">Lihat Detail</a>
            </div>
        </section>

        <section class="scatter-box">
            <h4 style="text-align:center;">Sebaran Mahasiswa terhadap Solusi Ideal TOPSIS</h4>
            <canvas id="scatterChart"></canvas>
        </section>

    </main>
</div>

<script>
const pieCtx = document.getElementById('pieChart');

new Chart(pieCtx, {
    type: 'pie',
    data: {
        labels: ['Kritis', 'Waspada', 'Aman', 'Sangat Baik'],
        datasets: [{
            data: [
                <?php echo $kritis['total']; ?>,
                <?php echo $waspada['total']; ?>,
                <?php echo $aman['total']; ?>,
                <?php echo $sangatBaik['total']; ?>
            ],
            backgroundColor: [
                '#ff6b6b',
                '#ffc46b',
                '#9cff7a',
                '#00c781'
            ]
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
        legend: {
            position: 'top',

            labels: {
                boxWidth: 0,
                padding: 0,
                font: {
                    size:0
                 }
                }
            }
        }
    }
});

const scatterCtx = document.getElementById('scatterChart');

new Chart(scatterCtx, {
    type: 'scatter',
    data: {
        datasets: [{
            label: 'Mahasiswa',
            data: <?php echo json_encode($scatterData); ?>,
            pointRadius: 5,
            pointHoverRadius: 7,
            backgroundColor: '#08003a'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            tooltip: {
                enabled: false
            }
        },
        scales: {
            x: {
                title: {
                    display: true,
                    text: 'Jarak ke Solusi Ideal Positif (D+)'
                }
            },
            y: {
                title: {
                    display: true,
                    text: 'Jarak ke Solusi Ideal Negatif (D-)'
                }
            }
        }
    }
});
</script>

</body>
</html>