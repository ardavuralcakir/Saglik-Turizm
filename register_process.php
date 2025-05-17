<?php
header('Content-Type: application/json');
session_start();

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

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

// 3) Veritabanı
include_once './config/database.php';
$database = new Database();
$db = $database->getConnection();

// 4) Method kontrol
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die(json_encode(['error' => $t['invalid_request']]));
}

// 5) POST verilerini al
$username         = trim($_POST['username'] ?? '');
$email            = trim($_POST['email'] ?? '');
$full_name        = trim($_POST['full_name'] ?? '');
$phone            = trim($_POST['phone'] ?? '');
$password         = trim($_POST['password'] ?? '');
$confirm_password = trim($_POST['confirm_password'] ?? '');

// 6) Basit form validasyonları
$errors = [];

// Kullanıcı adı min 3 karakter
if (strlen($username) < 3) {
    $errors[] = $t['username_min_length'];
}

// E-posta format
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = $t['invalid_email'];
}

// Şifre min 6 karakter
if (strlen($password) < 6) {
    $errors[] = $t['password_min_length'];
}

// Şifre eşleşme
if ($password !== $confirm_password) {
    $errors[] = $t['passwords_not_match'];
}

// Aynı kullanıcı adı veya e-posta var mı?
$query = "SELECT id FROM users WHERE username = ? OR email = ?";
$stmt = $db->prepare($query);
$stmt->execute([$username, $email]);
if ($stmt->rowCount() > 0) {
    $errors[] = $t['user_or_email_exists'];
}

// 7) Hata yoksa kaydı oluşturmaya çalış
if (empty($errors)) {
    // Kod ve süresi
    $verification_code    = sprintf("%06d", mt_rand(0, 999999));
    $hashed_password      = password_hash($password, PASSWORD_DEFAULT);
    $verification_expires = date('Y-m-d H:i:s', strtotime('+30 minutes'));

    require 'vendor/autoload.php'; // PHPMailer

    // DEBUG loglar
    error_log('register_process >> Current server time: ' . date('Y-m-d H:i:s'));
    error_log('register_process >> Code expiration time: ' . $verification_expires);

    $query = "INSERT INTO users (
        username,
        email,
        full_name,
        phone,
        password,
        role,
        verification_code,
        verification_expires,
        is_verified
    ) VALUES (?, ?, ?, ?, ?, 'user', ?, ?, FALSE)";

    $stmt = $db->prepare($query);

    try {
        if ($stmt->execute([
            $username,
            $email,
            $full_name,
            $phone,
            $hashed_password,
            $verification_code,
            $verification_expires
        ])) {
            // E-posta gönderme
            $mail = new PHPMailer(true);

            try {
                $mail->isSMTP();
                $mail->Host       = 'smtp-relay.brevo.com';
                $mail->SMTPAuth   = true;
                // SMTP kullanıcı adı ve şifre
                $mail->Username   = '8d279d001@smtp-brevo.com';
                $mail->Password   = '369DWI8z70gAaX5q';
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = 587;
                $mail->CharSet    = 'UTF-8';

                $mail->setFrom('agaskmag@gmail.com', 'HealthTurkey');
                $mail->addAddress($email, $full_name);

                $mail->isHTML(true);

                // 1) Subject
                // (örneğin $t['email_subject'] => "E-posta Doğrulama" veya "Email Verification")
                $mail->Subject = $t['email_subject'];

                // 2) Body => placeholders {name}, {expires} değiştirerek
                // $t['email_greeting'] => "Merhaba {name}," 
                // $t['email_welcome_text'] => "HealthTurkey'e hoş geldiniz! Hesabınızı doğrulamak için..."
                // $t['email_code_valid_until'] => "Bu kod 30 dakika süreyle geçerlidir (bitiş saati: {expires})."
                
                $greeting  = str_replace('{name}', htmlspecialchars($full_name), $t['email_greeting']);
                $validText = str_replace('{expires}', $verification_expires, $t['email_code_valid_until']);
                
                $mailContent = "
                    <h2>{$greeting}</h2>
                    <p>{$t['email_welcome_text']}</p>
                    <h1 style='color: #512da8;'>{$verification_code}</h1>
                    <p>{$validText}</p>
                ";

                $mail->Body = $mailContent;

                $mail->send();

                // Verify işleminde kullanmak için email'i saklıyoruz
                $_SESSION['temp_user_email'] = $email;

                // Başarılı kayıt cevabı
                die(json_encode(['success' => true]));

            } catch (Exception $e) {
                // E-posta gönderilemediğinde DB'den kayıt sil
                $deleteQuery = "DELETE FROM users WHERE email = ?";
                $deleteStmt  = $db->prepare($deleteQuery);
                $deleteStmt->execute([$email]);

                die(json_encode(['error' => $t['email_failed'] . ' ' . $mail->ErrorInfo]));
            }
        }
    } catch (PDOException $e) {
        die(json_encode(['error' => $t['db_error'] . ' ' . $e->getMessage()]));
    }
}

// 8) Herhangi bir hata varsa
die(json_encode(['errors' => $errors]));
