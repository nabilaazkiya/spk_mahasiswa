<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Login SPK Mahasiswa</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="login-body">

    <div class="login-card">

        <img src="assets/img/logo_psti.jpg" class="logo-img">

        <p class="logo-label">Prioritas Mahasiswa Bimbingan</p>

        <form action="proses/login_proses.php" method="POST" class="login-form">

            <div class="login-input-group">
                <div class="input-icon">
                    <i class="fa-solid fa-user"></i>
                </div>
                <input 
                    type="text" 
                    name="username" 
                    class="login-input" 
                    placeholder="Nama Akun" 
                    required
                >
            </div>

            <div class="login-input-group">
                <div class="input-icon">
                    <i class="fa-solid fa-lock"></i>
                </div>
                <input 
                    type="password" 
                    name="password" 
                    class="login-input" 
                    placeholder="Kata Sandi" 
                    required
                >
                </div>

            <a href="#" class="forgot-link">Lupa Kata Sandi?</a>

            <button type="submit" class="login-button">Log In</button>
        </form>

    </div>

</body>
</html>