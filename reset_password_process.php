<?php
header('Content-Type: application/json');
session_start();

// 1) Dil dosyasını yükle
$lang = $_SESSION['lang'] ?? 'tr';
$translations_file = __DIR__ . "/translations/translation_{$lang}.php";
if (file_exists($translations_file)) {
    $t = require $translations_file;
} else {
    die("Translation file not found: {$translations_file}");
}

// 2) Zaman dilimi ayarı
date_default_timezone_set('Europe/Istanbul');

// 3) Veritabanı bağlantısı
include_once './config/database.php';
$database = new Database();
$db = $database->getConnection();

// 4) JSON olarak gelen body'yi al
$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);

// 5) Parametreleri al
$email        = trim($data['email'] ?? '');
$new_password = trim($data['new_password'] ?? '');

// 6) Eksik veri kontrol
if (empty($email) || empty($new_password)) {
    echo json_encode(['error' => $t['missing_data']]);
    exit;
}

// 7) Parolayı hash'le
$hashedPassword = password_hash($new_password, PASSWORD_DEFAULT);

// 8) Veritabanında güncelle
try {
    $query = "UPDATE users 
              SET password = :newPass 
              WHERE email = :email
              LIMIT 1";
    $stmt = $db->prepare($query);
    $ok   = $stmt->execute([
        ':newPass' => $hashedPassword,
        ':email'   => $email
    ]);
    
    if ($ok) {
        if ($stmt->rowCount() > 0) {
            // Başarılı
            echo json_encode(['success' => true]);
        } else {
            // rowCount() = 0 => Muhtemelen email eşleşmedi
            echo json_encode(['error' => $t['email_not_found_2']]);
        }
    } else {
        echo json_encode(['error' => $t['password_error']]);
    }
} catch (PDOException $e) {
    echo json_encode(['error' => $t['database_error'] . $e->getMessage()]);
}
