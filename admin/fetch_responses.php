<?php
// CLI veya web kontrolü
if (php_sapi_name() === 'cli') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../vendor/autoload.php';

function logError($message) {
    $logFile = __DIR__ . '/email_fetch.log';
    if (!file_exists($logFile)) {
        touch($logFile);
        chmod($logFile, 0666);
    }
    error_log(date('Y-m-d H:i:s') . " - " . $message . "\n", 3, $logFile);
}

// Kilit mekanizması
$lockFile = __DIR__ . '/fetch_responses.lock';
if (file_exists($lockFile)) {
    $lockTime = filectime($lockFile);
    if (time() - $lockTime > 60) { // 1 dakikadan eski kilidi sil
        unlink($lockFile);
    } else {
        echo json_encode(['error' => false, 'message' => 'Email kontrolü zaten çalışıyor...']);
        return;
    }
}
touch($lockFile);

try {
    $database = new Database();
    $db = $database->getConnection();

    // Son kontrol tarihini al
    $stmt = $db->prepare("SELECT last_check FROM email_check_status ORDER BY id DESC LIMIT 1");
    $stmt->execute();
    $lastCheck = $stmt->fetch(PDO::FETCH_COLUMN);

    $mailbox = imap_open(
        '{imap.gmail.com:993/imap/ssl}INBOX',
        'agaskmag@gmail.com',
        'bmpt jcdt eiqv mmwq'
    );

    if (!$mailbox) {
        logError("IMAP bağlantı hatası: " . imap_last_error());
        throw new Exception('IMAP bağlantı hatası');
    }

    // Son kontrolden sonraki mailleri al
    $search_criteria = $lastCheck 
        ? 'SINCE "' . date('d-M-Y', strtotime($lastCheck)) . '"'
        : 'ALL';
    $emails = imap_search($mailbox, $search_criteria);

    if ($emails) {
        logError("Bulunan email sayısı: " . count($emails));
        
        foreach ($emails as $email_number) {
            $overview = imap_fetch_overview($mailbox, $email_number, 0);
            $message_id = $overview[0]->message_id;

            // Mail daha önce işlenmiş mi kontrol et
            $stmt = $db->prepare("SELECT id FROM support_responses WHERE email_id = ?");
            $stmt->execute([$message_id]);
            
            if ($stmt->rowCount() > 0) {
                continue; // Bu mail daha önce işlenmişse atla
            }
            
            // Mail konusunu decode et
            $subject = imap_mime_header_decode(imap_utf8($overview[0]->subject));
            $decodedSubject = '';
            foreach ($subject as $element) {
                $decodedSubject .= $element->text;
            }
            
            // Ticket ID'yi bul
            preg_match('/\[Ticket #(\d+)\]/', $decodedSubject, $matches);
            
            if (isset($matches[1])) {
                $ticketId = $matches[1];

                // Mail içeriğini al
                $structure = imap_fetchstructure($mailbox, $email_number);
                $message = '';
                
                if (isset($structure->parts) && is_array($structure->parts)) {
                    for($i = 0; $i < count($structure->parts); $i++) {
                        $part = $structure->parts[$i];
                        if ($part->subtype == 'PLAIN') {
                            $message = imap_fetchbody($mailbox, $email_number, $i+1);
                            if ($part->encoding == 3) { // BASE64
                                $message = base64_decode($message);
                            } elseif ($part->encoding == 4) { // QUOTED-PRINTABLE
                                $message = quoted_printable_decode($message);
                            }
                            break;
                        }
                    }
                } else {
                    $message = imap_body($mailbox, $email_number);
                }

                $message = trim(strip_tags($message));

                try {
                    // Yanıtı veritabanına kaydet
                    $stmt = $db->prepare("
                        INSERT INTO support_responses (ticket_id, message, email_id, created_at)
                        VALUES (?, ?, ?, NOW())
                    ");
                    $stmt->execute([$ticketId, $message, $message_id]);

                    // Ticket durumunu güncelle
                    $stmt = $db->prepare("
                        UPDATE support_tickets 
                        SET status = 'in_progress', updated_at = NOW()
                        WHERE id = ?
                    ");
                    $stmt->execute([$ticketId]);

                    // Maili okundu olarak işaretle
                    imap_setflag_full($mailbox, $email_number, "\\Seen");
                } catch (Exception $e) {
                    logError("Veritabanı hatası: " . $e->getMessage());
                }
            }
        }
    }

    imap_close($mailbox);
    
    // Son kontrol tarihini güncelle
    $stmt = $db->prepare("INSERT INTO email_check_status (last_check) VALUES (NOW())");
    $stmt->execute();
    
    // Güncel ticket verilerini al
    $stmt = $db->prepare("
        SELECT 
            st.id,
            st.subject,
            st.created_at,
            st.is_read,
            CASE WHEN sr.id IS NOT NULL THEN true ELSE false END as has_response
        FROM support_tickets st
        LEFT JOIN support_responses sr ON st.id = sr.ticket_id
        GROUP BY st.id
        ORDER BY 
            CASE WHEN sr.id IS NOT NULL THEN 1 ELSE 0 END DESC,
            st.is_read ASC,
            st.created_at DESC
        LIMIT 99999");

    $stmt->execute();
    $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Tarihleri formatla
    foreach ($tickets as &$ticket) {
        $ticket['created_at'] = date('d.m.Y H:i', strtotime($ticket['created_at']));
        $ticket['subject'] = htmlspecialchars($ticket['subject']);
    }

    // Sonuçları döndür
    echo json_encode([
        'error' => false,
        'tickets' => $tickets
    ]);

} catch (Exception $e) {
    logError("Hata oluştu: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    echo json_encode([
        'error' => true,
        'message' => $e->getMessage()
    ]);
} finally {
    if (file_exists($lockFile)) {
        unlink($lockFile);
    }
}