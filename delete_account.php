<?php
session_start();

// 1) Dil dosyasını yükle
$lang = $_SESSION['lang'] ?? 'tr';
$translation_file = __DIR__ . "/translations/translation_{$lang}.php";
if (file_exists($translation_file)) {
    $t = require $translation_file;
} else {
    die("Translation file not found: {$translation_file}");
}

include_once './config/database.php';

// 2) Kullanıcı oturum kontrolü
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// 3) Veritabanı bağlantısı
$database = new Database();
$db = $database->getConnection();

try {
    // Kullanıcının profil resmini sil
    $query = "SELECT image_url FROM users WHERE id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$_SESSION['user_id']]);
    $image_url = $stmt->fetchColumn();

    if ($image_url && file_exists($image_url)) {
        unlink($image_url);
    }

    // Kullanıcıyı sil
    $query = "DELETE FROM users WHERE id = ?";
    $stmt  = $db->prepare($query);
    
    if ($stmt->execute([$_SESSION['user_id']])) {
        // Oturumu sonlandır
        session_destroy();
        // İsterseniz "?message=account_deleted" yerine session’da dil destekli mesaj tutabilirsiniz
        header("Location: login.php?message=account_deleted");
    } else {
        $_SESSION['profile_error'] = $t['account_delete_error'];
        header("Location: profile.php");
    }
} catch (PDOException $e) {
    $_SESSION['profile_error'] = $t['account_delete_exception'] . $e->getMessage();
    header("Location: profile.php");
}

exit();
