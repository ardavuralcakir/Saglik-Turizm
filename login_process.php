<?php
include_once './config/database.php';
session_start();

// 1) Dil dosyasını yükle
$lang = $_SESSION['lang'] ?? 'tr';
$translations_file = __DIR__ . "/translations/translation_{$lang}.php";
if (file_exists($translations_file)) {
    $t = require $translations_file;
} else {
    die("Translation file not found: {$translations_file}");
}

// 2) Başlangıçta POST isteği olup olmadığını kontrol edelim
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $database = new Database();
    $db = $database->getConnection();
    
    // 3) Form verilerini al
    $login    = trim($_POST['email']); // email veya kullanıcı adı
    $password = trim($_POST['password']);
    
    // 4) Temel validasyon
    if (empty($login) || empty($password)) {
        $_SESSION['login_error'] = $t['fill_all_fields'];
        header("Location: login.php");
        exit();
    }
    
    // 5) SQL injection koruması için prepared statement
    $query = "SELECT * FROM users WHERE email = :login OR username = :login";
    $stmt = $db->prepare($query);
    $stmt->execute([':login' => $login]);
    
    if ($stmt->rowCount() > 0) {
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // E-posta doğrulama kontrolü
        if (!$user['is_verified']) {
            // Doğrulama kodu yeniden gönderme seçeneği için session'a email kaydedelim
            $_SESSION['unverified_email'] = $user['email'];
            $_SESSION['login_error']      = $t['email_not_verified'];
            header("Location: login.php");
            exit();
        }
        
        // 6) Şifre kontrolü
        if (password_verify($password, $user['password'])) {
            // Başarılı giriş - Session bilgilerini ayarla
            $_SESSION['user_id']   = $user['id'];
            $_SESSION['username']  = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['email']     = $user['email'];
            $_SESSION['role']      = $user['role'];
            
            // Son giriş zamanını güncelle
            $updateQuery = "UPDATE users SET last_login = NOW() WHERE id = :id";
            $updateStmt  = $db->prepare($updateQuery);
            $updateStmt->execute([':id' => $user['id']]);
            
            // Başarılı giriş logunu kaydet
            $logQuery = "INSERT INTO login_logs (user_id, login_time, ip_address, success) 
                         VALUES (:user_id, NOW(), :ip, 1)";
            $logStmt = $db->prepare($logQuery);
            $logStmt->execute([
                ':user_id' => $user['id'],
                ':ip'      => $_SERVER['REMOTE_ADDR']
            ]);
            
            // Kullanıcı rolüne göre yönlendirme
            if ($user['role'] === 'admin') {
                header("Location: admin/dashboard.php");
            } else {
                header("Location: profile.php");
            }
            exit();
        } else {
            // Başarısız giriş logunu kaydet
            $logQuery = "INSERT INTO login_logs (user_id, login_time, ip_address, success) 
                         VALUES (:user_id, NOW(), :ip, 0)";
            $logStmt = $db->prepare($logQuery);
            $logStmt->execute([
                ':user_id' => $user['id'],
                ':ip'      => $_SERVER['REMOTE_ADDR']
            ]);
            
            // 7) Başarısız giriş denemesi sayısını kontrol et
            $failedAttemptsQuery = "SELECT COUNT(*) as attempts 
                                    FROM login_logs 
                                    WHERE user_id = :user_id 
                                      AND success = 0 
                                      AND login_time > DATE_SUB(NOW(), INTERVAL 15 MINUTE)";
            $failedStmt = $db->prepare($failedAttemptsQuery);
            $failedStmt->execute([':user_id' => $user['id']]);
            $failedAttempts = $failedStmt->fetch(PDO::FETCH_ASSOC)['attempts'];
            
            if ($failedAttempts >= 5) {
                // Hesabı geçici olarak kilitle
                $lockQuery = "UPDATE users 
                              SET account_locked_until = DATE_ADD(NOW(), INTERVAL 30 MINUTE) 
                              WHERE id = :id";
                $lockStmt = $db->prepare($lockQuery);
                $lockStmt->execute([':id' => $user['id']]);
                
                $_SESSION['login_error'] = $t['too_many_attempts'];
            } else {
                $_SESSION['login_error'] = $t['wrong_credentials'];
            }
        }
    } else {
        $_SESSION['login_error'] = $t['user_not_found'];
    }
    
    header("Location: login.php");
    exit();
}

// 8) POST request değilse ana sayfaya yönlendir
header("Location: index.php");
exit();
