<?php
// Fungsi bantu untuk menyiapkan data scatter chart TOPSIS di dashboard.

/* PERBAIKAN: kategori/warna titik dihitung LANGSUNG dari
   nilai_preferensi yang sama dipakai untuk memposisikan titik
   itu di grafik (bukan diambil dari tabel hasil_evaluasi_terbaru
   yang terpisah). Sebelumnya posisi titik (dari ranking_topsis_terbaru)
   dan warna kategorinya (dari hasil_evaluasi_terbaru) di-JOIN
   hanya berdasarkan nim - kalau "terbaru" di kedua tabel itu
   sempat tidak sinkron untuk satu nim, titik & garis batas
   kategori jadi kelihatan tidak sesuai. Ambang batas ini harus
   SAMA PERSIS dengan yang dipakai saat menyimpan status_early_warning
   di proses/topsis_proses.php. */
function tentukanKategoriDariPreferensi($nilaiPreferensi)
{
    if ($nilaiPreferensi === null) {
        return 'Belum Diproses';
    }
    if ($nilaiPreferensi <= 0.25) {
        return 'Kritis';
    } elseif ($nilaiPreferensi <= 0.50) {
        return 'Waspada';
    } elseif ($nilaiPreferensi <= 0.75) {
        return 'Aman';
    }
    return 'Sangat Baik';
}

function ambilDataScatter($conn, $whereExtra = '')
{
    $hasil = [];

    $query = mysqli_query($conn, "
        SELECT 
            r.nim,
            r.jarak_positif,
            r.jarak_negatif,
            r.nilai_preferensi,
            d.nama_mahasiswa
        FROM ranking_topsis_terbaru r
        INNER JOIN mahasiswa m ON r.nim = m.nim
        LEFT JOIN data_akademik_terbaru d ON r.nim = d.nim
        WHERE r.jarak_positif IS NOT NULL
        AND r.jarak_negatif IS NOT NULL
        $whereExtra
    ");

    if ($query) {
        while ($row = mysqli_fetch_assoc($query)) {
            $nilaiPreferensi = round(floatval($row['nilai_preferensi']), 4);
            $hasil[] = [
                'nim'             => $row['nim'],
                'x'               => round(floatval($row['jarak_positif']), 4),
                'y'               => round(floatval($row['jarak_negatif']), 4),
                'nama'            => $row['nama_mahasiswa'],
                'kategori'        => tentukanKategoriDariPreferensi($nilaiPreferensi),
                'nilaiPreferensi' => $nilaiPreferensi
            ];
        }
    }

    return $hasil;
}
?>