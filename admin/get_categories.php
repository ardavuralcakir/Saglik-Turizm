<?php
session_start();

// Hata ayarları (isteğe bağlı, development ortamında faydalı)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Cevabın JSON olarak döneceğini belirtiyoruz
header('Content-Type: application/json; charset=utf-8');

// Database config vb.
include_once '../config/database.php';

$lang = $_SESSION['lang'] ?? 'tr';
$translations_file = dirname(__DIR__) . "/translations/translation_{$lang}.php";

if (file_exists($translations_file)) {
    $t = require $translations_file;
} else {
    die("Translation file not found: {$translations_file}");
}

// Çeviri dosyasını yükle
$t = require $translations_file;

// Admin kontrolü
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => ($lang === 'en') ? 'Unauthorized access' : 'Yetkisiz erişim'
    ]);
    exit;
}

// Veritabanına bağlan
$database = new Database();
$db = $database->getConnection();

// Dil seçimine göre kategori adını belirle
$category_name_field = ($lang === 'en') ? 'name_en AS name' : 'name AS name';

try {
    // Kategorileri çek
    $query = "SELECT id, $category_name_field 
              FROM service_categories 
              ORDER BY name ASC";
    $stmt = $db->prepare($query);
    $stmt->execute();
    
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Başarılı JSON cevabı
    echo json_encode([
        'success' => true,
        'data' => $categories
    ], JSON_UNESCAPED_UNICODE);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => ($lang === 'en') 
            ? 'Database error: ' . $e->getMessage()
            : 'Veritabanı hatası: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => ($lang === 'en') 
            ? 'An error occurred: ' . $e->getMessage()
            : 'Bir hata oluştu: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
