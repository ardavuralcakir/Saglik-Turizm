<?php
session_start();
include_once './config/database.php';

// 1) Dil Dosyasını Yükle
$lang = $_SESSION['lang'] ?? 'tr';
$translations_file = __DIR__ . "/translations/translation_{$lang}.php";
if (file_exists($translations_file)) {
    $t = require $translations_file;
} else {
    die("Translation file not found: {$translations_file}");
}

header('Content-Type: application/json');

// 2) Yetkisiz erişim / method kontrolü
if (!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => $t['unauthorized']
    ]);
    exit();
}

// 3) Veritabanı bağlantısı
$database = new Database();
$db = $database->getConnection();

// POST değişkenlerini al
$service_id = $_POST['service_id'];
$doctor_id  = $_POST['doctor_id'];

// Kart bilgileri
$card_name   = trim($_POST['card_name']);
$card_number = str_replace(' ', '', $_POST['card_number']);
$card_expiry = $_POST['card_expiry'];
$card_cvv    = $_POST['card_cvv'];

// 4) Kart bilgisi validasyonu

// Kart sahibi adı
if (!preg_match('/^[a-zA-ZğüşıöçĞÜŞİÖÇ\s]+$/', $card_name)) {
    echo json_encode([
        'success' => false,
        'message' => $t['card_name_letters']
    ]);
    exit();
}

// Kart numarası
if (strlen($card_number) !== 16 || !ctype_digit($card_number)) {
    echo json_encode([
        'success' => false,
        'message' => $t['invalid_card_number']
    ]);
    exit();
}

// CVV
if (!preg_match('/^\d{3}$/', $card_cvv)) {
    echo json_encode([
        'success' => false,
        'message' => $t['invalid_cvv']
    ]);
    exit();
}

// Son kullanma
list($month, $year) = explode('/', $card_expiry);
$currentYear  = date('y');
$currentMonth = date('m');

if (!$month || !$year ||
    $month < 1 || $month > 12 ||
    ($year < $currentYear || 
    ($year == $currentYear && $month < $currentMonth))) {

    echo json_encode([
        'success' => false,
        'message' => $t['invalid_expiry']
    ]);
    exit();
}

// 5) Veritabanı işlemleri
try {
    $db->beginTransaction();

    // Hizmet fiyatını al
    $query = "SELECT price FROM services WHERE id = ?";
    $stmt  = $db->prepare($query);
    $stmt->execute([$service_id]);
    $price = $stmt->fetchColumn();

    if (!$price) {
        throw new Exception($t['service_not_found']);
    }

    // Demo: Ödeme başarılı varsayıyoruz

    // Sipariş oluştur
    $query = "INSERT INTO orders (user_id, service_id, doctor_id, total_amount, status)
              VALUES (?, ?, ?, ?, 'pending')";
    $stmt = $db->prepare($query);
    $stmt->execute([$_SESSION['user_id'], $service_id, $doctor_id, $price]);
    $order_id = $db->lastInsertId();

    // Yol haritası adımları ekle
    $steps = [
        $t['step_1'],
        $t['step_2'],
        $t['step_3'],
        $t['step_4']
    ];

    $query = "INSERT INTO roadmap (order_id, step_number, step_description) VALUES (?, ?, ?)";
    $stmt  = $db->prepare($query);
    foreach ($steps as $index => $step) {
        $stmt->execute([$order_id, $index + 1, $step]);
    }

    // Commit
    $db->commit();

    echo json_encode([
        'success' => true,
        'message' => $t['success_purchase']
    ]);

} catch (Exception $e) {
    $db->rollBack();
    echo json_encode([
        'success' => false,
        'message' => $t['transaction_error'] . $e->getMessage()
    ]);
}
