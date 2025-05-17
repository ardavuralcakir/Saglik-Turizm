<?php
session_start();
include_once './config/database.php';

// 1) Dil ayarlarını yükle
$lang = $_SESSION['lang'] ?? 'tr';
$translations_file = __DIR__ . "/translations/translation_{$lang}.php";
if (file_exists($translations_file)) {
    // İsterseniz "array_merge" benzeri yapıya ihtiyacınız yoksa
    // doğrudan $pt = require $translations_file; diyebilirsiniz.
    $translations = require $translations_file;
    // Eğer bu dosyada ['tr'] veya ['en'] gibi alt diziler kullanıyorsanız:
    $pt = array_merge($translations['tr'] ?? [], $translations ?? []);
} else {
    die("Translation file not found: {$translations_file}");
}

// 2) Kullanıcı giriş kontrolü
if (!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] != 'POST') {
    header("Location: login.php");
    exit();
}

// 3) Veritabanı bağlantısı
$database = new Database();
$db = $database->getConnection();

// 4) Form verilerini al
$full_name = trim($_POST['full_name']);
$phone = trim($_POST['phone']);

// 5) Sorgu hazırlığı
$query = "UPDATE users SET full_name = ?, phone = ? WHERE id = ?";
$stmt = $db->prepare($query);

// 6) Güncelleme ve hata yakalama
try {
    if ($stmt->execute([$full_name, $phone, $_SESSION['user_id']])) {
        // Başarılı oldu -> Çeviri dosyasından al
        $_SESSION['profile_success'] = $pt['profile_update_success'];
    } else {
        // Başarısız -> Çeviri dosyasından al
        $_SESSION['profile_error'] = $pt['profile_update_error'];
    }
} catch (PDOException $e) {
    // Özel hata mesajı
    $_SESSION['profile_error'] = $pt['profile_update_exception'] . $e->getMessage();
}

// 7) Son olarak profile.php'ye dön
header("Location: profile.php");
exit();
