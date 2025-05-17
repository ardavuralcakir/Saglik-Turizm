<?php
session_start();
include_once './config/database.php';

// 1) Dil dosyasını yükle
$lang = $_SESSION['lang'] ?? 'tr';
$translations_file = __DIR__ . "/translations/translation_{$lang}.php";
if (file_exists($translations_file)) {
    // Eğer alt dizi yaklaşımı kullanıyorsanız (örn. $translations['tr']), buna göre uyarlayın:
    // $translations = require $translations_file;
    // $pt = array_merge($translations['tr'] ?? [], $translations ?? []);
    // Aksi halde doğrudan:
    $pt = require $translations_file;
} else {
    // Dil dosyası yoksa sonlandır
    die("Translation file not found: {$translations_file}");
}

// Kullanıcı oturumu kontrol
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Dosya yüklendi mi kontrol et
if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] == 0) {
    $target_dir = "uploads/profiles/";
    
    // Klasör yoksa oluştur
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0777, true);
    }

    // (İsteğe bağlı) Hata ayarları
    error_reporting(E_ALL);
    ini_set('display_errors', 1);

    $file_extension = strtolower(pathinfo($_FILES["profile_image"]["name"], PATHINFO_EXTENSION));
    $new_filename   = uniqid() . '.' . $file_extension;
    $target_file    = $target_dir . $new_filename;

    // Dosya türü kontrolü
    $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
    if (!in_array($file_extension, $allowed_types)) {
        $_SESSION['profile_error'] = $pt['upload_only_jpg_jpeg_png_gif'];
        header("Location: profile.php");
        exit();
    }

    // Dosya boyutu kontrolü (5MB)
    if ($_FILES["profile_image"]["size"] > 5000000) {
        $_SESSION['profile_error'] = $pt['upload_file_too_large'];
        header("Location: profile.php");
        exit();
    }

    try {
        if (move_uploaded_file($_FILES["profile_image"]["tmp_name"], $target_file)) {
            // Veritabanı bağlantısı
            $database = new Database();
            $db       = $database->getConnection();

            // Eski resmi bulup sil
            $query = "SELECT image_url FROM users WHERE id = ?";
            $stmt  = $db->prepare($query);
            $stmt->execute([$_SESSION['user_id']]);
            $old_image = $stmt->fetchColumn();

            if ($old_image && file_exists($old_image)) {
                unlink($old_image);
            }

            // Yeni resmi kaydet
            $query = "UPDATE users SET image_url = ? WHERE id = ?";
            $stmt  = $db->prepare($query);
            
            if ($stmt->execute([$target_file, $_SESSION['user_id']])) {
                $_SESSION['profile_success'] = $pt['upload_success'];
            } else {
                $_SESSION['profile_error'] = $pt['upload_db_error'];
                error_log("DB Error: " . print_r($stmt->errorInfo(), true));
            }
        } else {
            // move_uploaded_file başarısız
            $_SESSION['profile_error'] = $pt['upload_file_error'] 
                                         . (error_get_last()['message'] ?? '');
            error_log("Upload Error: " . print_r($_FILES['profile_image']['error'], true));
        }
    } catch (Exception $e) {
        $_SESSION['profile_error'] = $pt['upload_exception'] . $e->getMessage();
        error_log("Exception: " . $e->getMessage());
    }
} else {
    // Hiç dosya seçilmemiş veya yükleme hatası
    $error_num = $_FILES['profile_image']['error'] ?? 'NO_FILE';
    $_SESSION['profile_error'] = $pt['upload_no_file'] . $error_num;
    error_log("No file or error: " . print_r($_FILES, true));
}

// En son profile sayfasına geri dön
header("Location: profile.php");
exit();
