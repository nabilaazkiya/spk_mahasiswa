<?php
function ambilDataScatter($conn, $whereExtra = '')
{
    $hasil = [];

    $query = mysqli_query($conn, "
        SELECT 
            r.nim,
            r.jarak_positif,
            r.jarak_negatif,
            r.nilai_preferensi,
            d.nama_mahasiswa,
            h.status_early_warning
        FROM ranking_topsis_terbaru r
        INNER JOIN mahasiswa m ON r.nim = m.nim
        LEFT JOIN data_akademik_terbaru d ON r.nim = d.nim
        LEFT JOIN hasil_evaluasi_terbaru h ON r.nim = h.nim
        WHERE r.jarak_positif IS NOT NULL
        AND r.jarak_negatif IS NOT NULL
        $whereExtra
    ");

    if ($query) {
        while ($row = mysqli_fetch_assoc($query)) {
            $hasil[] = [
                'nim'             => $row['nim'],
                'x'               => round(floatval($row['jarak_positif']), 4),
                'y'               => round(floatval($row['jarak_negatif']), 4),
                'nama'            => $row['nama_mahasiswa'],
                'kategori'        => $row['status_early_warning'] ?? 'Belum Diproses',
                'nilaiPreferensi' => round(floatval($row['nilai_preferensi']), 4)
            ];
        }
    }

    return $hasil;
}
?>