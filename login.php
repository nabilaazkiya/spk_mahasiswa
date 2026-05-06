<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Login SPK Mahasiswa</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="login-body">

    <div class="login-card">
        <img src="assets/img/logo psti.jpg" class="logo-img">
        <h2 class="login-title">Prioritas Mahasiswa Bimbingan</h2>

        <form action="proses/login_proses.php" method="POST" class="login-form">
            <div class="form-group">
                <label>Nama Akun</label>
                <input type="text" name="username" class="form-input" required>
            </div>

            <div class="form-group">
                <label>Kata Sandi</label>
                <input type="password" name="password" class="form-input" required>
            </div>

            <a href="#" class="forgot-link">Lupa Kata Sandi?</a>

            <button type="submit" class="btn-primary">Log In</button>
        </form>
    </div>

</body>
</html>