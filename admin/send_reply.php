<?php
header('Content-Type: application/json');
session_start();

require_once __DIR__ . '/../vendor/autoload.php'; 
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

include_once '../config/database.php';

// Admin kontrolü
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Yetkisiz erişim']);
    exit();
}

// POST verilerini kontrol et
if (!isset($_POST['message_id']) || !isset($_POST['email']) || !isset($_POST['reply'])) {
    echo json_encode(['success' => false, 'message' => 'Eksik parametreler']);
    exit();
}

try {
    $message_id = intval($_POST['message_id']);
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    $reply = trim($_POST['reply']);

    // E-posta doğrulama
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Geçersiz e-posta adresi']);
        exit();
    }

    // Veritabanı bağlantısı
    $database = new Database();
    $db = $database->getConnection();

    // İlgili mesajı veritabanından al
    $query = "SELECT name FROM contact_requests WHERE id = :message_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':message_id', $message_id);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'Mesaj bulunamadı']);
        exit();
    }

    // PHPMailer ayarları
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
    $mail->addAddress($email, $user['name']);

    $mail->isHTML(true);
    $mail->Subject = "Sağlık Turizmi - Mesajınıza Yanıt";

    // Mail içeriği
    $mail->Body = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; }
            .container { padding: 20px; }
            .message { margin: 20px 0; line-height: 1.6; }
            .footer { margin-top: 30px; color: #666; }
        </style>
    </head>
    <body>
        <div class='container'>
            <p>Sayın " . htmlspecialchars($user['name']) . ",</p>
            <div class='message'>
                <p>Mesajınıza yanıtımız aşağıdadır:</p>
                <p>" . nl2br(htmlspecialchars($reply)) . "</p>
            </div>
            <div class='footer'>
                <p>Saygılarımızla,<br>
                Sağlık Turizmi Ekibi</p>
            </div>
        </div>
    </body>
    </html>";

    // Düz metin alternatifi
    $mail->AltBody = "Sayın " . $user['name'] . ",\n\n" .
                     "Mesajınıza yanıtımız aşağıdadır:\n\n" .
                     $reply . "\n\n" .
                     "Saygılarımızla,\n" .
                     "Sağlık Turizmi Ekibi";

    // E-postayı gönder
    if ($mail->send()) {
        // Yanıtı veritabanına kaydet
        $save_query = "INSERT INTO message_replies (message_id, reply_text, replied_by, replied_at) 
                      VALUES (:message_id, :reply, :admin_id, NOW())";
        $save_stmt = $db->prepare($save_query);
        $save_stmt->bindParam(':message_id', $message_id);
        $save_stmt->bindParam(':reply', $reply);
        $save_stmt->bindParam(':admin_id', $_SESSION['user_id']);
        $save_stmt->execute();

        // Contact request'i okundu olarak işaretle
        $mark_read_query = "UPDATE contact_requests SET is_read = 1 WHERE id = :message_id";
        $mark_read_stmt = $db->prepare($mark_read_query);
        $mark_read_stmt->bindParam(':message_id', $message_id);
        $mark_read_stmt->execute();

        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'E-posta gönderilemedi']);
    }

} catch (Exception $e) {
    error_log($e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Bir hata oluştu: ' . $e->getMessage()
    ]);
} 