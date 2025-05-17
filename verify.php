<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include_once './config/database.php';
session_start();

// 1) Dil çeviri dosyasını yükle
$lang = $_SESSION['lang'] ?? 'tr';
$translations_file = __DIR__ . "/translations/translation_{$lang}.php";
if (file_exists($translations_file)) {
    // Eğer alt dizi yapınız "['tr'=> [...], 'en'=> [...]]" şeklinde ise bunları birleştirmeniz gerekebilir
    $translations = require $translations_file;
    // Örneğin:
    // $pt = array_merge($translations[$lang] ?? [], $translations ?? []);
    // Eğer tek düzeyde tutuyorsanız doğrudan $pt = $translations;
    $pt = $translations; 
} else {
    // Çeviri dosyası yoksa çık
    die("Translation file not found: {$translations_file}");
}

// DEBUG: gelen ham veriyi logla
$input = file_get_contents('php://input');
error_log('verify.php >> Raw input: ' . $input);

// JSON decode
$_POST = json_decode($input, true);

// Session & POST kontrol logları
error_log('verify.php >> Session ID: ' . session_id());
error_log('verify.php >> $_SESSION[temp_user_email]: ' . ($_SESSION['temp_user_email'] ?? 'YOK'));
error_log('verify.php >> POST: ' . print_r($_POST, true));

// POST mu?
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $database = new Database();
    $db = $database->getConnection();
    
    // POST’tan gelen email ve code
    $email = $_POST['email'] ?? '';
    $code  = $_POST['verification_code'] ?? '';

    // Sorguda 30 dk kontrolü (verification_expires > NOW())
    $query = "SELECT * FROM users 
              WHERE email = ? 
                AND verification_code = ? 
                AND verification_expires > NOW()";
    $stmt = $db->prepare($query);
    $stmt->execute([$email, $code]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    error_log('verify.php >> User data: ' . print_r($user, true));
    
    if ($user) {
        // Kod doğru ve süresi geçmemiş, kullanıcıyı doğrula
        $updateQuery = "UPDATE users SET is_verified = TRUE WHERE email = ?";
        $updateStmt  = $db->prepare($updateQuery);
        $updateStmt->execute([$email]);

        // İsterseniz verification_code’u sıfırlayabilirsiniz:
        // $updateCode = "UPDATE users SET verification_code='' WHERE email=?";
        // $db->prepare($updateCode)->execute([$email]);

        // JSON: success = true + mesaj
        echo json_encode([
            'success' => true,
            // Dil dosyasından çekelim
            'message' => $pt['verify_success'] ?? 'Account verified successfully!',
        ]);
    } else {
        // Hata: Kod geçersiz veya süresi dolmuş
        echo json_encode([
            'error' => $pt['verify_invalid_or_expired'] ?? 'Invalid or expired verification code',
        ]);
    }
    exit();
}

// POST gelmediyse
echo json_encode([
    'error' => $pt['verify_invalid_request'] ?? 'Invalid request',
]);
exit();
