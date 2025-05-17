<?php
session_start();
include_once '../config/database.php';

header('Content-Type: application/json; charset=utf-8');

// Admin kontrolü
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Yetkisiz erişim']);
    exit();
}

$database = new Database();
$db = $database->getConnection();

$note_id = $_POST['note_id'] ?? null;

if (!$note_id) {
    echo json_encode(['success' => false, 'message' => 'Not ID\'si gerekli']);
    exit();
}

try {
    $query = "DELETE FROM user_notes WHERE id = ? AND admin_id = ?";
    $stmt = $db->prepare($query);
    
    if ($stmt->execute([$note_id, $_SESSION['user_id']])) {
        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => true, 'message' => 'Not başarıyla silindi']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Not bulunamadı veya silme yetkiniz yok']);
        }
    } else {
        throw new Exception('Not silinirken bir hata oluştu');
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}