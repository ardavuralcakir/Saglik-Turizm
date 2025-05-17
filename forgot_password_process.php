<?php
header('Content-Type: application/json');
session_start();

// PHPMailer dahil edilecekse
require 'vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

include_once './config/database.php';

// 1) Dil dosyasını yükle
$lang = $_SESSION['lang'] ?? 'tr';
$translation_file = __DIR__ . "/translations/translation_{$lang}.php";
if (file_exists($translation_file)) {
    $t = require $translation_file;
} else {
    die("Translation file not found: {$translation_file}");
}

// 2) Zaman dilimi (opsiyonel)
date_default_timezone_set('Europe/Istanbul');

// 3) Request Method kontrol
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => $t['invalid_request_method']]);
    exit;
}

// 4) JSON ile mi geliyoruz (fetch JSON)?  
$rawData  = file_get_contents('php://input');
$postData = json_decode($rawData, true);

// 5) E-posta parametresini al
$email = trim($postData['email'] ?? '');
if (empty($email)) {
    echo json_encode(['error' => $t['empty_email']]);
    exit;
}

// 6) Veritabanı bağlantısı
$database = new Database();
$db = $database->getConnection();

// 7) Kullanıcı var mı diye kontrol
$query = "SELECT id, full_name FROM users WHERE email = ?";
$stmt  = $db->prepare($query);
$stmt->execute([$email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    echo json_encode(['error' => $t['email_not_registered']]);
    exit;
}

// 8) Kod üret
$resetCode = sprintf("%06d", mt_rand(0, 999999));
$expiresAt = date('Y-m-d H:i:s', strtotime('+30 minutes'));

// 9) Kodları users tablosunda saklama (örnek)
$update = "UPDATE users 
           SET verification_code = ?, verification_expires = ?, is_verified = 0
           WHERE id = ?";
$upstmt = $db->prepare($update);
if (!$upstmt->execute([$resetCode, $expiresAt, $user['id']])) {
    echo json_encode(['error' => $t['code_not_updated']]);
    exit;
}

// 10) Kod e-postayla gönder
try {
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    // SMTP kullanıcı adı ve şifre
    $mail->Username   = 'agaskmag@gmail.com';
    $mail->Password   = 'mtea ttza uwrc szff';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;
    $mail->CharSet    = 'UTF-8';
    
    // SMTP debug açık (sorun devam ederse)
    $mail->SMTPDebug = 2; // Debug seviyesi

    $mail->setFrom('agaskmag@gmail.com', 'HealthTurkey');
    $mail->addAddress($email, $user['full_name'] ?? 'Dear User');

    $mail->isHTML(true);
    $mail->Subject = $t['reset_subject'];

    // E-postanın gövdesi (placeholder vs. kullanıyorsanız str_replace ile yapabilirsiniz)
    $mail->Body = "
        <h2>{$t['reset_greeting']} {$user['full_name']},</h2>
        <p>{$t['reset_instructions']}</p>
        <h1 style='color: #512da8;'>{$resetCode}</h1>
        <p>{$t['reset_expires']}</p>
    ";

    $mail->send();
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    // E-posta gönderilemedi
    echo json_encode([
        'error' => $t['email_sent_error'] . ' ' . $mail->ErrorInfo
    ]);
}
