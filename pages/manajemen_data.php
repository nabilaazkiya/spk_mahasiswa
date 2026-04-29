<?php
        <header class="topbar">
            <div>
                <h3>Manajemen Data</h3>
                <p>Kelola akun pengguna sistem</p>
            </div>
            <span class="user-info"><?php echo $_SESSION['nama_lengkap']; ?></span>
        </header>

        <section class="content-section">
            <form method="GET" class="table-toolbar">
                <input type="text" name="keyword" class="search-input" placeholder="Cari nama atau username" value="<?php echo $keyword; ?>">

                <select name="role" class="filter-select">
                    <option value="">Semua Role</option>
                    <option value="admin" <?php if ($role == 'admin') echo 'selected'; ?>>Admin</option>
                    <option value="kaprodi" <?php if ($role == 'kaprodi') echo 'selected'; ?>>Kaprodi</option>
                    <option value="dpa" <?php if ($role == 'dpa') echo 'selected'; ?>>DPA</option>
                    <option value="mahasiswa" <?php if ($role == 'mahasiswa') echo 'selected'; ?>>Mahasiswa</option>
                </select>

                <button type="submit" class="btn-secondary">Filter</button>
                <button type="button" class="btn-primary">+ Tambah Pengguna</button>
                <button type="button" class="btn-import">Import Excel / CSV</button>
            </form>

            <div class="sync-info">
                SIA Sync: belum terhubung otomatis. Data dapat diimpor manual melalui file Excel/CSV.
            </div>

            <table class="data-table">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Username</th>
                        <th>Nama Lengkap</th>
                        <th>Status SIA</th>
                        <th>Role</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $no = 1; while ($row = mysqli_fetch_assoc($userQuery)) { ?>
                    <tr>
                        <td><?php echo $no++; ?></td>
                        <td><?php echo $row['username']; ?></td>
                        <td><?php echo $row['nama_lengkap']; ?></td>
                        <td><span class="status-badge"><?php echo $row['status_sia']; ?></span></td>
                        <td><span class="role-badge"><?php echo $row['role']; ?></span></td>
                        <td>
                            <a href="#" class="action-edit">Edit</a>
                            <a href="#" class="action-delete">Hapus</a>
                        </td>
                    </tr>
                    <?php } ?>
                </tbody>
            </table>
        </section>
    </main>
</div>

</body>
</html>