<?php

    mysqli_query($conn, "
        INSERT INTO solusi_ideal (id_kriteria, nilai_positif, nilai_negatif)
        VALUES ('$idKriteria', '{$solusiPositif[$idKriteria]}', '{$solusiNegatif[$idKriteria]}')
    ");


$hasilTopsis = [];

foreach ($dataMahasiswa as $mhs) {
    $dPlus = 0;
    $dMinus = 0;

    foreach ($dataKriteria as $krit) {
        $idKriteria = $krit['id_kriteria'];
        $nilai = $normalisasiTerbobot[$mhs['nim']][$idKriteria];

        $dPlus += pow($nilai - $solusiPositif[$idKriteria], 2);
        $dMinus += pow($nilai - $solusiNegatif[$idKriteria], 2);
    }

    $dPlus = sqrt($dPlus);
    $dMinus = sqrt($dMinus);

    $preferensi = 0;
    if (($dPlus + $dMinus) != 0) {
        $preferensi = $dMinus / ($dPlus + $dMinus);
    }

    $hasilTopsis[] = [
        'nim' => $mhs['nim'],
        'nilai_preferensi' => $preferensi
    ];
}

usort($hasilTopsis, function ($a, $b) {
    return $b['nilai_preferensi'] <=> $a['nilai_preferensi'];
});

$ranking = 1;
foreach ($hasilTopsis as $hasil) {
    $status = 'Aman';

    if ($hasil['nilai_preferensi'] < 0.40) {
        $status = 'Kritis';
    } elseif ($hasil['nilai_preferensi'] < 0.60) {
        $status = 'Waspada';
    } elseif ($hasil['nilai_preferensi'] >= 0.80) {
        $status = 'Sangat Baik';
    }

    mysqli_query($conn, "
        INSERT INTO ranking_topsis (nim, nilai_preferensi, ranking, periode_evaluasi)
        VALUES ('{$hasil['nim']}', '{$hasil['nilai_preferensi']}', '$ranking', '2026')
    ");

    mysqli_query($conn, "
        INSERT INTO hasil_evaluasi (nim, periode_evaluasi, nilai_preferensi, status_early_warning)
        VALUES ('{$hasil['nim']}', CURDATE(), '{$hasil['nilai_preferensi']}', '$status')
    ");

    $ranking++;
}

header("Location: ../pages/monitoring.php");
exit;
?>