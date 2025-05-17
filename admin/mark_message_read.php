<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Veritabanı ve config
include_once '../config/database.php';

// 1) Dil dosyasını yükle
$lang = $_SESSION['lang'] ?? 'tr';
$translation_file = __DIR__ . "/../translations/translation_{$lang}.php";
if (file_exists($translation_file)) {
    $t = require $translation_file;
} else {
    // Dosya yoksa veya farklı bir hata
    echo json_encode(['success' => false, 'message' => "Translation file not found: {$translation_file}"]);
    exit();
}

// 2) Admin kontrolü
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => $t['error_occurred']]);
    exit();
}

// 3) Veritabanı bağlantısı
$database = new Database();
$db = $database->getConnection();

// 4) POST parametrelerini al
$mark_read  = $_POST['mark_read']  ?? null;
$message_id = $_POST['message_id'] ?? null;

// Geçersiz istek kontrolü
if ($mark_read !== '1' || empty($message_id)) {
    echo json_encode(['success' => false, 'message' => $t['invalid_request']]);
    exit();
}

try {
    // Mesajı okunmuş olarak işaretle
    $query = "UPDATE contact_requests SET is_read = 1 WHERE id = :id";
    $stmt  = $db->prepare($query);

    $ok = $stmt->execute([':id' => (int)$message_id]);
    if ($ok) {
        echo json_encode(['success' => true, 'message' => $t['success_mark_read']]);
    } else {
        echo json_encode(['success' => false, 'message' => $t['error_mark_read']]);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $t['error_occurred']]);
}
