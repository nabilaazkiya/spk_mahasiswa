<?php
            $nilai = $mhs[$kode];
        }

        $matrix[$mhs['nim']][$krit['id_kriteria']] = $nilai;
    }
}

$maxMin = [];

foreach ($dataKriteria as $krit) {
    $idKriteria = $krit['id_kriteria'];
    $nilaiKolom = [];

    foreach ($dataMahasiswa as $mhs) {
        $nilaiKolom[] = $matrix[$mhs['nim']][$idKriteria];
    }

    $maxMin[$idKriteria] = [
        'max' => max($nilaiKolom),
        'min' => min($nilaiKolom)
    ];
}

$hasilSaw = [];

foreach ($dataMahasiswa as $mhs) {
    $total = 0;

    foreach ($dataKriteria as $krit) {
        $idKriteria = $krit['id_kriteria'];
        $nilai = $matrix[$mhs['nim']][$idKriteria];
        $normal = 0;

        if ($krit['jenis'] == 'benefit') {
            if ($maxMin[$idKriteria]['max'] != 0) {
                $normal = $nilai / $maxMin[$idKriteria]['max'];
            }
        } else {
            if ($nilai != 0) {
                $normal = $maxMin[$idKriteria]['min'] / $nilai;
            }
        }

        $total += $normal * $krit['bobot'];
    }

    $hasilSaw[] = [
        'nim' => $mhs['nim'],
        'nilai_preferensi' => $total
    ];
}

usort($hasilSaw, function ($a, $b) {
    return $b['nilai_preferensi'] <=> $a['nilai_preferensi'];
});

$ranking = 1;
foreach ($hasilSaw as $hasil) {
    mysqli_query($conn, "
        INSERT INTO ranking_saw (nim, nilai_preferensi, ranking, periode_evaluasi)
        VALUES ('{$hasil['nim']}', '{$hasil['nilai_preferensi']}', '$ranking', '2026')
    ");

    $ranking++;
}

header("Location: spearman_proses.php");
exit;
?>