<?php
/**
 * =============================================
 * XLSX READER (NATIVE, TANPA LIBRARY EKSTERNAL)
 * =============================================
 * File .xlsx sebenarnya adalah arsip ZIP berisi
 * beberapa file XML. Helper ini membaca sheet
 * pertama dan mengembalikannya sebagai array baris
 * (array of array), sama persis strukturnya dengan
 * hasil fgetcsv(), supaya bisa dipakai bergantian
 * dengan proses import CSV yang sudah ada.
 *
 * Membutuhkan ekstensi PHP: zip, simplexml (dom).
 * Ini adalah ekstensi standar yang hampir selalu
 * aktif di hosting PHP pada umumnya.
 * =============================================
 */

/**
 * Konversi huruf kolom Excel (A, B, ..., Z, AA, AB, ...)
 * menjadi index kolom berbasis 0 (A=0, B=1, ...).
 */
function kolomHurufKeIndex($huruf)
{
    $huruf = strtoupper($huruf);
    $index = 0;

    for ($i = 0; $i < strlen($huruf); $i++) {
        $index = $index * 26 + (ord($huruf[$i]) - ord('A') + 1);
    }

    return $index - 1;
}

/**
 * Baca sheet pertama dari file .xlsx dan kembalikan
 * sebagai array baris (setiap baris = array kolom).
 *
 * Mengembalikan false jika file gagal dibuka/rusak,
 * atau ekstensi ZipArchive/SimpleXML tidak tersedia.
 *
 * CATATAN: untuk sel formula (misal "Sisa Masa Studi"
 * yang berisi rumus seperti "=14-D2"), yang diambil
 * adalah nilai HASIL PERHITUNGAN yang di-cache Excel
 * (elemen <v>), bukan teks rumusnya - sama seperti
 * yang terlihat saat file dibuka di Excel.
 */
function bacaXlsxKeArray($path)
{
    if (!class_exists('ZipArchive') || !function_exists('simplexml_load_string')) {
        return false;
    }

    $zip = new ZipArchive();

    if ($zip->open($path) !== true) {
        return false;
    }

    /* 1. BACA SHARED STRINGS (tabel teks terpakai bersama)
       Excel tidak menyimpan teks langsung di sel; teks
       disimpan sekali di sharedStrings.xml lalu sel hanya
       menyimpan index ke tabel ini (efisiensi penyimpanan). */
    $sharedStrings = [];
    $sharedXml = $zip->getFromName('xl/sharedStrings.xml');

    if ($sharedXml !== false) {
        libxml_use_internal_errors(true);
        $sharedDom = simplexml_load_string($sharedXml);
        libxml_use_internal_errors(false);

        if ($sharedDom !== false) {
            foreach ($sharedDom->si as $si) {
                if (isset($si->t)) {
                    $sharedStrings[] = (string) $si->t;
                } else {
                    /* Rich text (teks dengan format campuran)
                       tersimpan sebagai beberapa elemen <r><t>. */
                    $teks = '';
                    foreach ($si->r as $r) {
                        $teks .= (string) $r->t;
                    }
                    $sharedStrings[] = $teks;
                }
            }
        }
    }

    /* 2. BACA SHEET PERTAMA
       Diasumsikan sheet yang ingin diimpor adalah sheet
       pertama pada file (xl/worksheets/sheet1.xml). */
    $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
    $zip->close();

    if ($sheetXml === false) {
        return false;
    }

    libxml_use_internal_errors(true);
    $dom = simplexml_load_string($sheetXml);
    libxml_use_internal_errors(false);

    if ($dom === false || !isset($dom->sheetData)) {
        return false;
    }

    $rows = [];
    $kolomTerlebar = 0;

    foreach ($dom->sheetData->row as $rowXml) {
        $rowData = [];
        $kolomTerakhir = -1;

        foreach ($rowXml->c as $cell) {
            $ref = (string) $cell['r']; // contoh referensi sel: "C5"
            $kolomHuruf = preg_replace('/[0-9]/', '', $ref);
            $kolomIndex = $kolomHuruf !== '' ? kolomHurufKeIndex($kolomHuruf) : ($kolomTerakhir + 1);

            /* Isi kolom yang dilompati (sel kosong tidak selalu
               ditulis eksplisit oleh Excel) dengan string kosong,
               supaya index kolom tetap konsisten. */
            for ($i = $kolomTerakhir + 1; $i < $kolomIndex; $i++) {
                $rowData[$i] = '';
            }

            $tipe  = (string) $cell['t'];
            $nilai = isset($cell->v) ? (string) $cell->v : '';

            if ($tipe === 's') {
                /* Shared string: nilai di <v> adalah index,
                   bukan teksnya langsung. */
                $idx   = (int) $nilai;
                $nilai = $sharedStrings[$idx] ?? '';
            } elseif ($tipe === 'inlineStr') {
                $nilai = isset($cell->is->t) ? (string) $cell->is->t : '';
            }
            /* Untuk tipe numerik/kosong (tanpa atribut t) atau
               tipe 'str' (hasil formula berupa string), nilai
               dari <v> sudah berupa nilai final/cached - dipakai
               apa adanya. */

            $rowData[$kolomIndex] = $nilai;
            $kolomTerakhir = $kolomIndex;
        }

        if (count($rowData) > $kolomTerlebar) {
            $kolomTerlebar = count($rowData);
        }

        ksort($rowData);
        $rows[] = array_values($rowData);
    }

    /* PERBAIKAN BUG: Excel menghilangkan elemen <c> untuk sel
       kosong di AKHIR baris (bukan menulis nilai kosong). Tanpa
       ini, baris dengan kolom terakhir kosong (misal Absensi
       tidak diisi untuk mahasiswa tertentu) akan lebih PENDEK
       dari baris lain, sehingga index kolom bergeser secara
       tidak konsisten antar baris. Padding di sini menyamakan
       lebar semua baris ke kolom terlebar yang ditemukan di
       seluruh sheet (biasanya = lebar baris header). */
    foreach ($rows as &$row) {
        while (count($row) < $kolomTerlebar) {
            $row[] = '';
        }
    }
    unset($row);

    return $rows;
}

/**
 * Baca file CSV menjadi array baris, dengan struktur
 * yang sama seperti bacaXlsxKeArray(), supaya kedua
 * format bisa diproses dengan logika yang identik.
 */
function bacaCsvKeArray($path)
{
    $file = fopen($path, 'r');

    if ($file === false) {
        return false;
    }

    $rows = [];

    while (($data = fgetcsv($file, 10000, ",")) !== false) {
        $rows[] = $data;
    }

    fclose($file);

    return $rows;
}
?>
