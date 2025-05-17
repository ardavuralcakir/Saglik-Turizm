<?php
session_start();
include_once '../config/database.php';

// Admin kontrolü (uygulamanızın ihtiyaçlarına göre güncelleyebilirsiniz)
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    // 403 => Forbidden
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Yetkisiz erişim.'
    ]);
    exit;
}

// POST veya JSON gövdesinden gelen veriyi alalım
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data || !isset($data['note_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Not ID eksik.'
    ]);
    exit;
}

$note_id = $data['note_id'];

// Veritabanı bağlantısını oluşturalım
$database = new Database();
$db = $database->getConnection();

// Silme sorgusu
try {
    $query = "DELETE FROM order_notes WHERE id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$note_id]);

    // Etkilenen satır sayısına göre işlem sonucu
    if ($stmt->rowCount() > 0) {
        echo json_encode([
            'success' => true,
            'message' => 'Not silindi.'
        ]);
    } else {
        // Not yoksa veya silme başarısız olduysa
        echo json_encode([
            'success' => false,
            'message' => 'Not bulunamadı veya silinemedi.'
        ]);
    }
} catch (PDOException $e) {
    // Veritabanı hatası
    echo json_encode([
        'success' => false,
        'message' => 'Bir hata oluştu: ' . $e->getMessage()
    ]);
}
