<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('HTTP/1.1 403 Forbidden');
    exit('Unauthorized');
}

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no'); // Nginx için buffering'i devre dışı bırak

while (true) {
    try {
        // fetch_responses.php'yi include etmek yerine direkt çalıştırıp sonucunu alalım
        ob_start();
        include 'fetch_responses.php';
        $result = ob_get_clean();
        
        // JSON formatında veriyi gönder
        if ($result) {
            $data = json_decode($result, true);
            if ($data) {
                echo "data: " . json_encode($data) . "\n\n";
            } else {
                echo "data: " . json_encode(['error' => true, 'message' => 'Invalid JSON response']) . "\n\n";
            }
        } else {
            echo "data: " . json_encode(['error' => true, 'message' => 'No response']) . "\n\n";
        }
    } catch (Exception $e) {
        echo "data: " . json_encode([
            'error' => true,
            'message' => $e->getMessage()
        ]) . "\n\n";
    }
    
    ob_flush();
    flush();
    
    sleep(10); // Her 10 saniyede bir kontrol et
}