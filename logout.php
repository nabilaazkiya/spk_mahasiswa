<?php
// Logout user: hapus session lalu redirect ke halaman login.
session_start();
session_unset();
session_destroy();

header("Location: login.php");
exit;
?>