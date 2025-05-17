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

$doctor_id = $_GET['doctor_id'] ?? null;

if (!$doctor_id) {
    echo json_encode(['has_orders' => false]);
    exit();
}

try {
    // Sadece 'pending' (beklemede) durumundaki siparişleri kontrol et
    $query = "SELECT COUNT(*) FROM orders WHERE doctor_id = ? AND status = 'pending'";
    $stmt = $db->prepare($query);
    $stmt->execute([$doctor_id]);
    $orderCount = $stmt->fetchColumn();

    echo json_encode(['has_orders' => $orderCount > 0]);
} catch (Exception $e) {
    echo json_encode(['has_orders' => false]);
}
?>