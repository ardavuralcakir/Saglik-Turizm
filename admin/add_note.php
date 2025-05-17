<?php
session_start();
include_once '../config/database.php';

// Admin kontrolü
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Yetkisiz erişim']);
    exit;
}

// POST verilerini kontrol et
if (!isset($_POST['user_id']) || !isset($_POST['note']) || empty(trim($_POST['note']))) {
    echo json_encode(['success' => false, 'message' => 'Geçersiz veri']);
    exit;
}

$database = new Database();
$db = $database->getConnection();

try {
    $query = "INSERT INTO user_notes (user_id, admin_id, note) VALUES (?, ?, ?)";
    $stmt = $db->prepare($query);
    $stmt->execute([
        $_POST['user_id'],
        $_SESSION['user_id'],
        trim($_POST['note'])
    ]);

    echo json_encode(['success' => true, 'message' => 'Not başarıyla eklendi']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Not eklenirken bir hata oluştu']);
}