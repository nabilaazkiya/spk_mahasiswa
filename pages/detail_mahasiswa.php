<?php
session_start();
include "../config/database.php";

if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['kaprodi', 'dpa'])) {
    header("Location: ../login.php");
    exit;
}

$nim = isset($_GET['nim']) ? mysqli_real_escape_string($conn, $_GET['nim']) : '';

if ($nim == '') {
    header("Location: monitoring.php");
    exit;
}

$backPage = ($_SESSION['role'] == 'dpa') ? 'monitoring_dpa.php' : 'monitoring.php';
$dashboardPage = ($_SESSION['role'] == 'dpa') ? 'dashboard_dpa.php' : 'dashboard_kaprodi.php';
$roleLabel = ($_SESSION['role'] == 'dpa') ? 'Dosen PA' : 'Kaprodi';

$whereDpa = "";

if ($_SESSION['role'] == 'dpa') {
    $namaDpa = mysqli_real_escape_string($conn, $_SESSION['nama_lengkap']);
    $whereDpa = " AND d.dosen_pa = '$namaDpa'";
}

$query = mysqli_query($conn, "
    SELECT 
        d.*,

        r.nilai_preferensi AS nilai_topsis,
        r.ranking AS ranking_topsis,

        s.nilai_preferensi AS nilai_saw,
        s.ranking AS ranking_saw,

        h.status_early_warning
    FROM data_akademik d
    LEFT JOIN ranking_topsis r ON d.nim = r.nim
    LEFT JOIN ranking_saw s ON d.nim = s.nim
    LEFT JOIN hasil_evaluasi h ON d.nim = h.nim
    WHERE d.nim = '$nim'
    $whereDpa
    LIMIT 1
");

$data = mysqli_fetch_assoc($query);

if (!$data) {
    echo "
    <script>
        alert('Data mahasiswa tidak ditemukan.');
        window.location='$backPage';
    </script>
    ";
    exit;
}

/* AMBIL HASIL SPEARMAN TERBARU */
$spearman = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT *
    FROM uji_spearman
    ORDER BY id_spearman DESC
    LIMIT 1
"));

/* RIWAYAT IPK */
$riwayatQuery = mysqli_query($conn, "
    SELECT semester, ipk
    FROM data_akademik
    WHERE nim = '$nim'
    ORDER BY semester ASC
");

$labelSemester = [];
$dataIpk = [];

while ($r = mysqli_fetch_assoc($riwayatQuery)) {
    $labelSemester[] = 'Semester ' . $r['semester'];
    $dataIpk[] = floatval($r['ipk']);
}

/* DATA SCATTER TOPSIS */
$scatterSelected = [];

$scatterQuery = mysqli_query($conn, "
    SELECT 
        nim,
        jarak_positif,
        jarak_negatif
    FROM ranking_topsis
    WHERE nim = '$nim'
    AND jarak_positif IS NOT NULL
    AND jarak_negatif IS NOT NULL
    LIMIT 1
");

$scatterData = mysqli_fetch_assoc($scatterQuery);

if ($scatterData) {
    $scatterSelected[] = [
        'x' => round(floatval($scatterData['jarak_positif']), 4),
        'y' => round(floatval($scatterData['jarak_negatif']), 4)
    ];
}

$status = $data['status_early_warning'] ?? 'Belum Diproses';
$statusClass = 'status-aktif';

if (strtolower($status) == 'kritis') {
    $statusClass = 'status-nonaktif';
} elseif (strtolower($status) == 'waspada') {
    $statusClass = 'status-waspada';
} elseif (strtolower($status) == 'sangat baik') {
    $statusClass = 'status-aktif';
} elseif (strtolower($status) == 'aman') {
    $statusClass = 'status-aktif';
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Detail Mahasiswa</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>

<body>

<div class="dashboard-wrapper">

    <aside class="section-sidebar">
        <div class="logo-area">
            <img src="../assets/img/logo_psti.jpg" class="sidebar-logo" alt="Logo PSTI">
            <span class="logo-text">Prioritas Mahasiswa<br>Bimbingan</span>
        </div>

        <nav class="nav-menu">
            <a href="<?php echo $dashboardPage; ?>" class="nav-link">Dashboard</a>
            <a href="<?php echo $backPage; ?>" class="nav-link active">Monitoring</a>
        </nav>

        <a href="../logout.php" class="logout-button">LOGOUT</a>
    </aside>

    <main class="dashboard-main">

        <section class="section-topbar">
            <h3>Detail Mahasiswa</h3>

            <div class="admin-info">
                <div>
                    <strong><?php echo $_SESSION['nama_lengkap']; ?></strong><br>
                    <small><?php echo $roleLabel; ?></small>
                </div>
                <div class="admin-avatar"></div>
            </div>
        </section>

        <section class="detail-profile">
            <div class="profile-icon">👤</div>

            <div>
                <h2><?php echo $data['nama_mahasiswa']; ?></h2>
                <p><?php echo $data['nim']; ?></p>
                <p>Semester <?php echo $data['semester']; ?></p>

                <span class="status-badge <?php echo $statusClass; ?>">
                    <?php echo $status; ?>
                </span>
            </div>
        </section>

        <section class="detail-grid">

            <div class="detail-card">
                <h4>Indeks Prestasi Kumulatif (IPK)</h4>
                <p><?php echo $data['ipk']; ?></p>
                <small>Tren: berdasarkan data akademik terbaru</small>
            </div>

            <div class="detail-card">
                <h4>SKS Lulus</h4>
                <p><?php echo $data['sks_lulus']; ?></p>
                <small>SKS Diambil: <?php echo $data['sks_diambil']; ?></small>
            </div>

            <div class="detail-card">
                <h4>Preferensi Model</h4>

                <p>
                    <?php 
                    echo $spearman 
                        ? number_format($spearman['rs'], 4) 
                        : '-'; 
                    ?>
                </p>

                <small>
                    <?php echo $spearman['preferensi_model'] ?? 'Belum diuji'; ?>
                    <br><br>

                    TOPSIS:
                    <?php
                    echo isset($data['nilai_topsis'])
                        ? number_format($data['nilai_topsis'], 4)
                        : '-';
                    ?>

                    <br>

                    SAW:
                    <?php
                    echo isset($data['nilai_saw'])
                        ? number_format($data['nilai_saw'], 4)
                        : '-';
                    ?>
                </small>
            </div>

        </section>

        <section class="chart-detail-box">
            <h3>Tren Performa Semester</h3>
            <canvas id="grafikIpk"></canvas>
        </section>

        <section class="chart-detail-box">
            <h3>Posisi Mahasiswa terhadap Solusi Ideal TOPSIS</h3>
            <canvas id="grafikTopsis"></canvas>
        </section>

        <section class="info-table-box">
            <h3>Informasi Akademik Tambahan</h3>

            <table class="data-table">
                <thead>
                    <tr>
                        <th>Dosen PA</th>
                        <th>Skor TOEFL</th>
                        <th>Jumlah Mengulang</th>
                        <th>Sisa Masa Studi</th>
                        <th>Jalur Masuk</th>
                        <th>Absensi</th>
                        <th>SKS Nilai Kurang C</th>
                    </tr>
                </thead>

                <tbody>
                    <tr>
                        <td><?php echo $data['dosen_pa']; ?></td>
                        <td><?php echo $data['skor_toefl']; ?></td>
                        <td><?php echo $data['jml_mengulang']; ?></td>
                        <td><?php echo $data['sisa_masa_studi']; ?></td>
                        <td><?php echo $data['jalur_masuk']; ?></td>
                        <td><?php echo $data['absensi']; ?></td>
                        <td><?php echo $data['sks_nilai_kurang_c']; ?></td>
                    </tr>
                </tbody>
            </table>

            <br>

            <a href="<?php echo $backPage; ?>" class="btn-add" style="text-decoration:none;">
                Kembali
            </a>
        </section>

    </main>
</div>

<script>
new Chart(document.getElementById('grafikIpk'), {
    type: 'line',
    data: {
        labels: <?php echo json_encode($labelSemester); ?>,
        datasets: [{
            label: 'IPK',
            data: <?php echo json_encode($dataIpk); ?>,
            borderWidth: 2,
            tension: 0.3
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            y: {
                beginAtZero: true,
                max: 4
            }
        }
    }
});

new Chart(document.getElementById('grafikTopsis'), {
    type: 'scatter',
    data: {
        datasets: [
            {
                label: 'Posisi Mahasiswa',
                data: <?php echo json_encode($scatterSelected); ?>,
                pointRadius: 9,
                pointHoverRadius: 11,
                backgroundColor: '#ff0000'
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,

        plugins: {
            legend: {
                position: 'top'
            }
        },

        scales: {
            x: {
                title: {
                    display: true,
                    text: 'Jarak ke Solusi Ideal Positif (D+)'
                },
                beginAtZero: true
            },
            y: {
                title: {
                    display: true,
                    text: 'Jarak ke Solusi Ideal Negatif (D-)'
                },
                beginAtZero: true
            }
        }
    }
});
</script>

</body>
</html>