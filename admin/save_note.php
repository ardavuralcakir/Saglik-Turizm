<?php
session_start();
include_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    exit(json_encode(['success' => false, 'message' => 'Yetkisiz erişim']));
}

$database = new Database();
$db = $database->getConnection();

$note = $_POST['note'];
$user_id = $_POST['user_id'];

$query = "INSERT INTO user_notes (user_id, admin_id, note) VALUES (?, ?, ?)";
$stmt = $db->prepare($query);

try {
    if($stmt->execute([$user_id, $_SESSION['user_id'], $note])) {
        $data = [
            'success' => true,
            'note_id' => $db->lastInsertId(),
            'note' => htmlspecialchars($note),
            'admin_name' => $_SESSION['full_name'],
            'created_at' => date('d.m.Y H:i')
        ];
        echo json_encode($data);
    }
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Not eklenirken hata oluştu']);
}
?>