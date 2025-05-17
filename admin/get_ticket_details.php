<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$ticketId = $_GET['id'] ?? null;

if (!$ticketId) {
    echo json_encode(['success' => false, 'message' => 'Ticket ID required']);
    exit;
}

$database = new Database();
$db = $database->getConnection();

$stmt = $db->prepare("
    SELECT st.*, sr.message as response_message, sr.responded_at
    FROM support_tickets st
    LEFT JOIN support_responses sr ON st.id = sr.ticket_id
    WHERE st.id = ? AND st.user_id = ?
");

$stmt->execute([$ticketId, $_SESSION['user_id']]);
$ticket = $stmt->fetch(PDO::FETCH_ASSOC);

if ($ticket) {
    echo json_encode([
        'success' => true,
        'ticket' => $ticket,
        'response' => $ticket['response_message'] ? [
            'message' => $ticket['response_message'],
            'responded_at' => $ticket['responded_at']
        ] : null
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Ticket not found']);
}