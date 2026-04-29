<?php
include "../config/database.php";
$kriteria = mysqli_query($conn, "SELECT * FROM kriteria");
?>

<section class="content-section">
    <h3>Konfigurasi Kriteria</h3>

    <table class="data-table">
        <thead>
            <tr>
                <th>Kode</th>
                <th>Nama Kriteria</th>
                <th>Jenis</th>
                <th>Bobot</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($row = mysqli_fetch_assoc($kriteria)) { ?>
            <tr>
                <td><?= $row['kode_kriteria']; ?></td>
                <td><?= $row['nama_kriteria']; ?></td>
                <td>
                    <select name="jenis[]" class="filter-select">
                        <option value="benefit">Benefit</option>
                        <option value="cost">Cost</option>
                    </select>
                </td>
                <td>
                    <input type="number" step="0.01" class="form-input bobot-input" value="<?= $row['bobot']; ?>">
                </td>
            </tr>
            <?php } ?>
        </tbody>
    </table>

    <div class="weight-info">
        Total Bobot: <span id="totalBobot">0</span>
    </div>

    <button class="btn-primary">Simpan Bobot</button>
</section>