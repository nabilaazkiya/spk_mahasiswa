<?php
session_start();
include "../config/database.php";

// Pastikan hanya admin yang bisa mengakses
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

// Pastikan request adalah POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$id_data = isset($_POST['id_data']) ? intval($_POST['id_data']) : 0;
$status_sia = isset($_POST['status_sia']) ? strtolower(trim($_POST['status_sia'])) : '';

// Validasi input
if ($id_data <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID Data tidak valid.']);
    exit;
}

$valid_statuses = ['aktif', 'cuti', 'do', '-'];
if (!in_array($status_sia, $valid_statuses, true)) {
    echo json_encode(['success' => false, 'message' => 'Status tidak valid.']);
    exit;
}

// Update database
$stmt = mysqli_prepare($conn, "UPDATE data_akademik SET status_sia = ? WHERE id_data = ?");
if ($stmt) {
    mysqli_stmt_bind_param($stmt, "si", $status_sia, $id_data);
    $exec = mysqli_stmt_execute($stmt);
    
    if ($exec) {
        echo json_encode(['success' => true, 'message' => 'Status SIA berhasil diperbarui.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal memperbarui database: ' . mysqli_error($conn)]);
    }
    mysqli_stmt_close($stmt);
} else {
    echo json_encode(['success' => false, 'message' => 'Kesalahan pada query database.']);
}
?>
