<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
include_once '../config/database.php';

header('Content-Type: application/json');

$lang = isset($_SESSION['lang']) ? $_SESSION['lang'] : 'tr';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => ($lang === 'en' ? 'Unauthorized access' : 'Yetkisiz erişim')]);
    exit();
}

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => ($lang === 'en') ? 'Invalid doctor ID' : 'Geçersiz doktor ID']);
    exit();
}

try {
    $database = new Database();
    $db = $database->getConnection();

    $query = "SELECT d.*, GROUP_CONCAT(ds.service_id) as service_ids 
              FROM doctors d
              LEFT JOIN doctor_services ds ON d.id = ds.doctor_id
              WHERE d.id = :id
              GROUP BY d.id 
              LIMIT 1";
    $stmt = $db->prepare($query);
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        echo json_encode(['success' => false, 'message' => 'Doktor bulunamadı']);
        exit();
    }

    $serviceIds = [];
    if (!empty($row['service_ids'])) {
        $serviceIds = array_map('intval', explode(',', $row['service_ids']));
    }

    $result = [
        'id'        => (int)$row['id'],
        'name'      => $row['name'],
        'specialty' => $row['specialty'],
        'bio_tr'    => $row['bio_tr'],
        'bio_en'    => $row['bio_en'],
        'image_url' => $row['image_url'],
        'service_ids' => $serviceIds
    ];

    echo json_encode(['success' => true, 'data' => $result]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'DB error: ' . $e->getMessage()]);
}
