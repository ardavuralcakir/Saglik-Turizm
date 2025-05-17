<?php
session_start();
include_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Yetkisiz erişim']);
    exit;
}

$database = new Database();
$db = $database->getConnection();

$order_id = $_POST['order_id'];
$note = trim($_POST['note']);

if (empty($note)) {
    echo json_encode(['success' => false, 'message' => 'Not boş olamaz']);
    exit;
}

try {
    $query = "INSERT INTO order_notes (order_id, user_id, note) VALUES (?, ?, ?)";
    $stmt = $db->prepare($query);
    
    if ($stmt->execute([$order_id, $_SESSION['user_id'], $note])) {
        // Eklenen notun bilgilerini al
        $note_id = $db->lastInsertId();
        $query = "SELECT n.*, u.full_name as user_name 
                  FROM order_notes n 
                  JOIN users u ON n.user_id = u.id 
                  WHERE n.id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$note_id]);
        $note_data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'message' => 'Not başarıyla eklendi',
            'note' => [
                'note' => htmlspecialchars($note_data['note']),
                'user_name' => htmlspecialchars($note_data['user_name']),
                'created_at' => date('d.m.Y H:i', strtotime($note_data['created_at']))
            ]
        ]);
    } else {
        throw new Exception('Not eklenirken bir hata oluştu');
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>