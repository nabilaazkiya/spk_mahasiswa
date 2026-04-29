<?php
    <title>Dashboard Kaprodi</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

<div class="app-layout">
    <aside class="sidebar">
        <h2 class="sidebar-title">SPK Mahasiswa</h2>

        <nav class="nav-menu">
            <a href="dashboard_kaprodi.php" class="nav-link active">Dashboard</a>
            <a href="monitoring.php" class="nav-link">Monitoring</a>
            <a href="../logout.php" class="nav-link logout-link">Logout</a>
        </nav>
    </aside>

    <main class="main-content">
        <header class="topbar">
            <div>
                <h3>Dashboard Ketua Program Studi</h3>
                <p>Ringkasan evaluasi seluruh mahasiswa</p>
            </div>
            <span class="user-info"><?php echo $_SESSION['nama_lengkap']; ?></span>
        </header>

        <section class="summary-grid">
            <div class="summary-card">
                <h4>Total Mahasiswa</h4>
                <p><?php echo $totalMahasiswa['total']; ?></p>
            </div>

            <div class="summary-card danger">
                <h4>Kritis</h4>
                <p><?php echo $kritis['total']; ?></p>
            </div>

            <div class="summary-card warning">
                <h4>Waspada</h4>
                <p><?php echo $waspada['total']; ?></p>
            </div>

            <div class="summary-card success">
                <h4>Aman</h4>
                <p><?php echo $aman['total']; ?></p>
            </div>

            <div class="summary-card excellent">
                <h4>Sangat Baik</h4>
                <p><?php echo $sangatBaik['total']; ?></p>
            </div>
        </section>

        <section class="content-section two-column-section">
            <div>
                <h3>Grafik Sebaran Mahasiswa</h3>
                <div class="chart-placeholder">Area Grafik</div>
            </div>

            <div class="analysis-box">
                <h3>Kesimpulan Sistem</h3>
                <p>
                    Dashboard ini menampilkan hasil klasifikasi mahasiswa berdasarkan hasil evaluasi akademik.
                    Mahasiswa dengan status Kritis dan Waspada perlu menjadi prioritas pemantauan akademik.
                </p>
            </div>
        </section>
    </main>
</div>

</body>
</html>