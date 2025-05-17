<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$ticketId = $data['ticket_id'] ?? null;
$status = $data['status'] ?? 'read'; // Yeni parametre: 'read' veya 'unread'

if (!$ticketId) {
    echo json_encode(['success' => false, 'message' => 'Ticket ID required']);
    exit;
}

$database = new Database();
$db = $database->getConnection();

// Status parametresine göre is_read değerini ayarla
$isRead = ($status === 'read') ? 1 : 0;

$stmt = $db->prepare("UPDATE support_tickets SET is_read = ? WHERE id = ? AND user_id = ?");
$result = $stmt->execute([$isRead, $ticketId, $_SESSION['user_id']]);

if ($result) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Update failed']);
}