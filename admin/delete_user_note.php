<?php
session_start();
include_once '../config/database.php';

// Admin kontrolü
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Yetkisiz erişim']);
    exit;
}

// POST verilerini kontrol et
if (!isset($_POST['note_id'])) {
    echo json_encode(['success' => false, 'message' => 'Geçersiz veri']);
    exit;
}

$database = new Database();
$db = $database->getConnection();

try {
    $query = "DELETE FROM user_notes WHERE id = ? AND (admin_id = ? OR EXISTS (SELECT 1 FROM users WHERE id = ? AND role = 'admin'))";
    $stmt = $db->prepare($query);
    $stmt->execute([
        $_POST['note_id'],
        $_SESSION['user_id'],
        $_SESSION['user_id']
    ]);

    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Not başarıyla silindi']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Not silinirken bir hata oluştu']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Not silinirken bir hata oluştu']);
}