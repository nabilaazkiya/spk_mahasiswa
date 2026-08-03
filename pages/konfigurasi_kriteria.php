<?php
session_start();
include "../config/database.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit;
}

/**
 * PERBAIKAN BUG BESAR: halaman ini SEBELUMNYA menyimpan array
 * bobot pakar hardcode di kode PHP dan otomatis menimpa ulang
 * tabel `kriteria` SETIAP kali halaman ini dibuka - membuat
 * proses/bobot_delphi.php (yang membaca dari CSV) jadi tidak
 * berguna walau berhasil dijalankan, karena hasilnya langsung
 * tertimpa lagi begitu admin membuka halaman ini.
 *
 * Sekarang halaman ini HANYA MENAMPILKAN isi tabel `kriteria`
 * apa adanya (read-only) - satu-satunya cara mengubahnya adalah
 * lewat tombol "Hitung Ulang Bobot Delphi" di bawah, yang
 * memanggil proses/bobot_delphi.php.
 */

$kriteria = mysqli_query($conn, "
    SELECT * FROM kriteria
    ORDER BY bobot_delphi DESC, id_kriteria ASC
");

$totalBobot    = 0;
$totalKriteria = mysqli_num_rows($kriteria);
$adaBelumKonsensus = false;
$adaBelumKonvergen = false;
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Konfigurasi Kriteria</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=7">
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
                    Halaman ini menampilkan bobot Delphi yang tersimpan di database (dihitung dari
                    skor pakar pada <code>assets/data_delphi.csv</code>, atau dari file yang Anda
                    upload sendiri di bawah). Halaman ini TIDAK lagi mengubah data secara otomatis -
                    perubahan hanya terjadi saat tombol "Hitung Ulang Bobot Delphi" ditekan.
                </p>
            </div>

            <div style="background:white;border-radius:16px;padding:20px 24px;margin-bottom:20px;box-shadow:0 3px 8px rgba(0,0,0,0.08);">
                <form method="POST" action="../proses/bobot_delphi.php" enctype="multipart/form-data" style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
                    <div>
                        <label style="font-size:13px;font-weight:600;display:block;margin-bottom:4px;">
                            File CSV Skor Pakar (opsional - kosongkan untuk pakai assets/data_delphi.csv)
                        </label>
                        <input type="file" name="file_pakar" accept=".csv">
                    </div>
                    <button type="submit" class="btn-add" onclick="return confirm('Hitung ulang bobot Delphi sekarang? Ini akan menimpa bobot yang tersimpan saat ini dan menambah nomor iterasi.');">
                        &#8635; Hitung Ulang Bobot Delphi
                    </button>
                </form>
                <p style="font-size:12px;color:#888;margin-top:10px;margin-bottom:0;">
                    Format CSV: kolom <code>kode_kriteria, nama_kriteria, kolom_data, jenis, pakar_1, pakar_2, ...</code> (skor pakar skala 1-5).
                    Kriteria dengan rata-rata skor pakar &lt; 3 ditandai "Belum Konsensus".
                </p>
            </div>

            <table class="data-table criteria-table">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Kode</th>
                        <th>Kriteria</th>
                        <th>Kolom Data</th>
                        <th>Atribut</th>
                        <th>Rata-rata Pakar</th>
                        <th>Std. Deviasi</th>
                        <th>Konsensus</th>
                        <th>Konvergensi</th>
                        <th>Bobot Delphi</th>
                        <th>Persentase</th>
                        <th>Iterasi</th>
                    </tr>
                </thead>

                <tbody>
                    <?php if ($totalKriteria > 0) { ?>
                        <?php
                        $no = 1;
                        while ($row = mysqli_fetch_assoc($kriteria)) {
                            $bobot = floatval($row['bobot_delphi']);
                            $totalBobot += $bobot;

                            $konsensus = $row['status_konsensus'] ?? null;
                            if ($konsensus === 'Belum Konsensus') {
                                $adaBelumKonsensus = true;
                            }
                        ?>
                        <tr>
                            <td><?php echo $no; ?></td>
                            <td><?php echo htmlspecialchars($row['kode_kriteria']); ?></td>
                            <td><?php echo htmlspecialchars($row['nama_kriteria']); ?></td>
                            <td><?php echo htmlspecialchars($row['kolom_data']); ?></td>
                            <td><?php echo ucfirst(htmlspecialchars($row['jenis'])); ?></td>
                            <td><?php echo $row['rata_rata_pakar'] !== null ? number_format($row['rata_rata_pakar'], 2) : '-'; ?></td>
                            <td><?php echo $row['standar_deviasi_pakar'] !== null ? number_format($row['standar_deviasi_pakar'], 2) : '-'; ?></td>
                            <td>
                                <?php if ($konsensus === 'Konsensus Tercapai'): ?>
                                    <span style="color:#27ae60;font-weight:600;">&#10003; Konsensus</span>
                                <?php elseif ($konsensus === 'Belum Konsensus'): ?>
                                    <span style="color:#e74c3c;font-weight:600;">&#9888; Belum Konsensus</span>
                                <?php else: ?>
                                    <span style="color:#999;">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php
                                $konvergensi = $row['status_konvergensi'] ?? null;
                                if ($konvergensi === 'Belum Konvergen') {
                                    $adaBelumKonvergen = true;
                                }
                                ?>
                                <?php if ($konvergensi === 'Konvergen'): ?>
                                    <span style="color:#27ae60;font-weight:600;">&#10003; Konvergen</span>
                                <?php elseif ($konvergensi === 'Belum Konvergen'): ?>
                                    <span style="color:#e67e22;font-weight:600;">&#8635; Belum Konvergen</span>
                                <?php elseif ($konvergensi === 'Iterasi Pertama'): ?>
                                    <span style="color:#999;">Iterasi Pertama</span>
                                <?php else: ?>
                                    <span style="color:#999;">-</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo number_format($bobot, 6); ?></td>
                            <td><?php echo number_format($bobot * 100, 2); ?>%</td>
                            <td><?php echo (int) $row['iterasi_delphi']; ?></td>
                        </tr>
                        <?php
                            $no++;
                        }
                        ?>
                    <?php } else { ?>
                        <tr>
                            <td colspan="12" style="text-align:center;">
                                Belum ada data kriteria. Klik "Hitung Ulang Bobot Delphi" di atas untuk memulai.
                            </td>
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
                    <?php if ($adaBelumKonsensus): ?>
                        <p style="color:#e74c3c;font-weight:600;">
                            &#9888; Ada kriteria yang belum mencapai konsensus pakar - pertimbangkan iterasi Delphi lanjutan.
                        </p>
                    <?php endif; ?>
                    <?php if ($adaBelumKonvergen): ?>
                        <p style="color:#e67e22;font-weight:600;">
                            &#8635; Ada kriteria yang rata-ratanya masih berubah signifikan dibanding iterasi sebelumnya (belum konvergen) - tunjukkan hasil ini ke pakar dan lakukan iterasi lagi sebelum dipakai untuk keputusan akhir.
                        </p>
                    <?php endif; ?>
                </div>
            </section>
        </section>

    </main>
</div>

<script src="../assets/js/sidebar.js?v=2"></script>
</body>
</html>
