<?php
// Tüm çıktı bufferını temizle ve hataları engelle
ob_start();
error_reporting(0);
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);

// Önceki çıktıları temizle
if (ob_get_length()) ob_clean();

session_start();
header('Content-Type: application/json; charset=utf-8');

try {
    include_once '../config/database.php';

    // 1) Dil dosyasını yükle
    $lang = $_SESSION['lang'] ?? 'tr';
    $translation_file = __DIR__ . "/../translations/translation_{$lang}.php";

    if (file_exists($translation_file)) {
        $t = require $translation_file;
    } else {
        throw new Exception("Translation file not found: {$translation_file}");
    }

    // Admin kontrolü
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
        throw new Exception($t['unauthorized'] ?? 'Unauthorized access');
    }

    $database = new Database();
    $db = $database->getConnection();

    $user_id = $_POST['user_id'] ?? null;

    if (!$user_id) {
        throw new Exception($t['user_id_required'] ?? 'User ID is required');
    }

    // Kendini değiştirmeye çalışıyorsa engelle
    if ($user_id == $_SESSION['user_id']) {
        throw new Exception($t['cannot_change_own'] ?? 'You cannot change your own role');
    }

    // Mevcut rolü kontrol et
    $query = "SELECT role, full_name FROM users WHERE id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        throw new Exception($t['user_not_found'] ?? 'User not found');
    }
    
    $currentRole = $user['role'];
    $fullName = $user['full_name'];
    
    // Yeni rolü belirle (admin <-> user)
    $newRole = ($currentRole === 'admin') ? 'user' : 'admin';
    
    // Rolü güncelle
    $query = "UPDATE users SET role = ? WHERE id = ?";
    $stmt = $db->prepare($query);
    
    if (!$stmt->execute([$newRole, $user_id])) {
        throw new Exception($t['role_update_error'] ?? 'Error updating role');
    }

    $message = ($newRole === 'admin')
        ? sprintf($t['admin_granted_with_name'] ?? '%s is now an admin', $fullName)
        : sprintf($t['admin_revoked_with_name'] ?? 'Admin privileges removed from %s', $fullName);
    
    // Önceki çıktıları temizle
    if (ob_get_length()) ob_clean();
        
    echo json_encode([
        'success' => true, 
        'message' => $message,
        'newRole' => $newRole
    ]);

} catch (PDOException $e) {
    // Önceki çıktıları temizle
    if (ob_get_length()) ob_clean();
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    // Önceki çıktıları temizle
    if (ob_get_length()) ob_clean();
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

// Çıktı bufferını temizle ve sonlandır
ob_end_flush();
