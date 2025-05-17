<?php
header('Content-Type: application/json');
session_start();

require_once __DIR__ . '/../vendor/autoload.php'; 
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Yetki kontrolü
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Yetkisiz erişim']);
    exit();
}

// POST verilerini al
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['success' => false, 'message' => 'Geçersiz veri']);
    exit();
}

$subject = $input['subject'] ?? '';
$message = $input['message'] ?? '';
$priority = $input['priority'] ?? '';

if (empty($subject) || empty($message) || empty($priority)) {
    echo json_encode(['success' => false, 'message' => 'Tüm alanları doldurunuz']);
    exit();
}

// Aciliyet durumuna göre başlık ön eki ve renk
$priorityPrefix = [
    'low' => '[Düşük] ',
    'medium' => '[Orta] ',
    'high' => '[Yüksek] '
];

$priorityColor = [
    'low' => '#059669',
    'medium' => '#d97706',
    'high' => '#dc2626'
];

try {
    // Veritabanı bağlantısını include et
    require_once __DIR__ . '/../config/database.php';
    $database = new Database();
    $db = $database->getConnection();
    
    // Veritabanına kaydet
    $stmt = $db->prepare("INSERT INTO support_tickets (user_id, subject, message, priority) VALUES (?, ?, ?, ?)");
    $stmt->execute([$_SESSION['user_id'], $subject, $message, $priority]);
    $ticketId = $db->lastInsertId();

    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = 'agaskmag@gmail.com';
    $mail->Password = 'bmpt jcdt eiqv mmwq';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = 587;
    $mail->CharSet = 'UTF-8';

    $mail->setFrom('agaskmag@gmail.com', 'HealthTurkey Support');
    $mail->addAddress('akdeniz.emirhan@hotmail.com', 'Developer');

    $mail->isHTML(true);
    $mail->Subject = "[Ticket #{$ticketId}] " . $priorityPrefix[$priority] . $subject;

    // Mail içeriği
    $mail->Body = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; }
            .container { padding: 20px; }
            .priority { font-weight: bold; color: {$priorityColor[$priority]}; }
        </style>
    </head>
    <body>
        <div class='container'>
            <h2>Yeni Destek Talebi #$ticketId</h2>
            <p><strong>Gönderen:</strong> {$_SESSION['full_name']} ({$_SESSION['email']})</p>
            <p><strong>Aciliyet:</strong> <span class='priority'>{$priority}</span></p>
            <p><strong>Konu:</strong> {$subject}</p>
            <hr>
            <p><strong>Mesaj:</strong></p>
            <p>" . nl2br(htmlspecialchars($message)) . "</p>
        </div>
    </body>
    </html>";

    $mail->send();
    
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Mail gönderilemedi: ' . $mail->ErrorInfo
    ]);
}