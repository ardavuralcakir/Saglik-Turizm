<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
include_once '../config/database.php';
include_once '../includes/new-header.php';

// 1) Dil dosyasını yükle
$lang = $_SESSION['lang'] ?? 'tr';
$translation_file = __DIR__ . "/../translations/translation_{$lang}.php"; 
if (file_exists($translation_file)) {
    // $t değişkeni artık çeviri dizisi
    $t = require $translation_file;
} else {
    die("Translation file not found: {$translation_file}");
}

// 2) Admin kontrolü
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: /health_tourism/login.php");
    exit();
}

// 3) Veritabanı bağlantısı
$database = new Database();
$db = $database->getConnection();

// Sayfa numarası al
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$messages_per_page = 10; // Her sayfada gösterilecek mesaj sayısı

// Toplam mesaj sayısını al
$count_query = "SELECT COUNT(*) FROM contact_requests";
$count_stmt = $db->prepare($count_query);
$count_stmt->execute();
$total_messages = $count_stmt->fetchColumn();

// Toplam sayfa sayısını hesapla
$total_pages = ceil($total_messages / $messages_per_page);

// Sayfa numarasının geçerliliğini kontrol et
if ($page < 1) {
    $page = 1;
} elseif ($page > $total_pages && $total_pages > 0) {
    $page = $total_pages;
}

// Offset
$offset = ($page - 1) * $messages_per_page;

// Mesajları al (okunmamışlar önce, sonra yenilere göre DESC)
$query = "
    SELECT *
    FROM contact_requests
    ORDER BY is_read ASC, created_at DESC
    LIMIT :offset, :limit
";
$stmt = $db->prepare($query);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->bindValue(':limit',  $messages_per_page, PDO::PARAM_INT);
$stmt->execute();
$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<style>
/* Sayfalama CSS'leri */
.pagination-container { 
  display: flex;
  justify-content: center; 
  align-items: center; 
  margin-top: 20px; 
  padding-bottom: 20px;
}
.pagination-container a {
  display: inline-block;
  padding: 10px 15px;
  margin: 0 5px;
  border: none; 
  border-radius: 5px; 
  color: #007bff; 
  background-color: transparent; 
  text-decoration: none;
  font-weight: 500; 
  border: 1px solid #007bff;
  transition: all 0.3s ease;
}
.pagination-container a:hover {
  background-color: #007bff; 
  color: #fff; 
  transform: translateY(-2px); 
  box-shadow: 0px 2px 5px rgba(0, 0, 0, 0.2); 
}
.pagination-container a.active {
  background-color: #007bff; 
  color: #fff; 
  cursor: default; 
}
.pagination-container a:first-child {
  border-top-left-radius: 25px; 
  border-bottom-left-radius: 25px; 
}
.pagination-container a:last-child {
  border-top-right-radius: 25px; 
  border-bottom-right-radius: 25px; 
}

/* Kart stilimiz */
.message-card {
  background-color: #fff;
  border-radius: 1rem;
  padding: 1.5rem;
  box-shadow: 0 6px 14px -1px rgba(19, 182, 204, 0.3);
  transition: all 0.3s ease-in-out;
  position: relative;
  margin-bottom: 1.5rem;
}
.message-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 8px 16px rgba(128, 0, 128, 0.3);
}
.message-card.unread {
  background-color: #f0f4ff;
}

/* İçerik düzeneği */
.message-card-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
}

/* Buton ve tarih alanı */
.message-card-actions {
  display: flex;
  align-items: center;
  gap: 1rem;
}
</style>

<div class="luxury-gradient min-h-screen pt-24 pb-12">
    <div class="max-w-7xl mx-auto px-4">
        <div class="flex items-center justify-between mb-12">
            <div>
                <h1 class="text-4xl font-light text-gray-800 mb-2">
                    <?php echo $t['messages']; ?>
                </h1>
                <p class="text-gray-600">
                    <?php echo $t['view_messages']; ?>
                </p>
            </div>
        </div>

        <?php if (!empty($messages)): ?>
            <?php foreach ($messages as $msg): ?>
                <div class="message-card <?php echo !$msg['is_read'] ? 'unread' : ''; ?>">
                    <div class="message-card-header">
                        <div>
                            <h3 class="text-xl font-medium text-gray-800">
                                <?php echo htmlspecialchars($msg['name']); ?>
                            </h3>
                            <p class="text-gray-500 mb-2">
                                <?php echo htmlspecialchars($msg['email']); ?> |
                                <?php echo htmlspecialchars($msg['phone']); ?>
                            </p>
                        </div>
                        <div class="message-card-actions">
                            <span class="text-sm text-gray-500">
                                <?php echo date('d.m.Y H:i', strtotime($msg['created_at'])); ?>
                            </span>
                            <button type="button"
                                    data-message-id="<?php echo $msg['id']; ?>"
                                    data-email="<?php echo htmlspecialchars($msg['email']); ?>"
                                    data-name="<?php echo htmlspecialchars($msg['name']); ?>"
                                    class="reply-button text-green-600 hover:text-green-800 ml-4"
                                    title="<?php echo $t['reply_message']; ?>">
                                <i class="fas fa-reply"></i> <?php echo $t['reply']; ?>
                            </button>
                            <?php if (!$msg['is_read']): ?>
                                <button type="button"
                                        data-message-id="<?php echo $msg['id']; ?>"
                                        class="mark-read text-blue-600 hover:text-blue-800 ml-4"
                                        title="<?php echo $t['mark_as_read_title']; ?>">
                                    <?php echo $t['mark_as_read']; ?>
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <p class="text-gray-600 mt-4">
                        <?php echo nl2br(htmlspecialchars($msg['message'])); ?>
                    </p>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <!-- Hiç mesaj yoksa -->
            <div class="message-card text-center" style="box-shadow: 0 4px 10px rgba(128, 0, 128, 0.2)">
                <p class="text-gray-500">
                    <?php echo $t['no_messages']; ?><br>
                    <?php echo $t['no_messages_info']; ?>
                </p>
            </div>
        <?php endif; ?>

        <!-- Sayfalama -->
        <?php if ($total_pages > 1): ?>
            <div class="pagination-container">
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page - 1; ?>" class="prev-page">
                        <?php echo $t['pagination_previous']; ?>
                    </a>
                <?php endif; ?>

                <?php
                $start = max(1, $page - 2);
                $end   = min($total_pages, $page + 2);
                for ($i = $start; $i <= $end; $i++):
                ?>
                    <a href="?page=<?php echo $i; ?>"
                       <?php if ($page == $i) echo 'class="active"'; ?>>
                       <?php echo $i; ?>
                    </a>
                <?php endfor; ?>

                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page + 1; ?>" class="next-page">
                        <?php echo $t['pagination_next']; ?>
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal -->
<div id="replyModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden items-center justify-center">
    <div class="bg-white rounded-lg p-8 max-w-2xl w-full mx-4">
        <div class="flex justify-between items-center mb-6">
            <h3 class="text-2xl font-semibold"><?php echo $t['send_reply']; ?></h3>
            <button id="closeModal" class="text-gray-600 hover:text-gray-800">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <div class="mb-4">
            <p class="text-gray-600"><?php echo $t['recipient']; ?>: <span id="recipientName"></span> (<span id="recipientEmail"></span>)</p>
        </div>

        <div class="mb-6">
            <label for="replyMessage" class="block text-gray-700 mb-2"><?php echo $t['your_message']; ?></label>
            <textarea id="replyMessage" rows="6" 
                      class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                      placeholder="<?php echo $t['write_message']; ?>"></textarea>
        </div>

        <div class="flex justify-end gap-4">
            <button id="cancelReply" 
                    class="px-6 py-2 border rounded-lg hover:bg-gray-100">
                <?php echo $t['cancel']; ?>
            </button>
            <button id="sendReply" 
                    class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                <?php echo $t['send']; ?>
            </button>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
// JavaScript değişkenlerine PHP çevirilerini aktar
const translations = {
    sending: "<?php echo $t['sending']; ?>",
    warning: "<?php echo $t['warning']; ?>",
    pleaseWriteMessage: "<?php echo $t['please_write_message']; ?>",
    success: "<?php echo $t['success']; ?>",
    replySent: "<?php echo $t['reply_sent']; ?>",
    error: "<?php echo $t['error']; ?>",
    errorOccurred: "<?php echo $t['error_occurred']; ?>",
    ok: "<?php echo $t['ok']; ?>",
    send: "<?php echo $t['send']; ?>"
};

// “Okundu İşaretle” butonları
document.querySelectorAll('.mark-read').forEach(button => {
    button.addEventListener('click', function() {
        const messageId = this.dataset.messageId;

        fetch('mark_message_read.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: `mark_read=1&message_id=${messageId}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Başarılı -> sayfayı yenile
                location.reload();
            } else {
                console.error(data.message || "<?php echo $t['error_mark_read']; ?>");
            }
        })
        .catch(error => {
            console.error("<?php echo $t['error_mark_read']; ?>", error);
        });
    });
});

// Modal işlemleri
const modal = document.getElementById('replyModal');
const recipientName = document.getElementById('recipientName');
const recipientEmail = document.getElementById('recipientEmail');
const replyMessage = document.getElementById('replyMessage');

document.querySelectorAll('.reply-button').forEach(button => {
    button.addEventListener('click', function() {
        const email = this.dataset.email;
        const name = this.dataset.name;
        const messageId = this.dataset.messageId;

        recipientName.textContent = name;
        recipientEmail.textContent = email;
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        
        // Modal'ı açtığımızda message ID'yi saklayalım
        modal.dataset.messageId = messageId;
    });
});

// Modal kapatma işlemleri
[document.getElementById('closeModal'), document.getElementById('cancelReply')].forEach(element => {
    element.addEventListener('click', () => {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        replyMessage.value = '';
    });
});

// Yanıt gönderme işlemi
document.getElementById('sendReply').addEventListener('click', function() {
    const messageId = modal.dataset.messageId;
    const email = recipientEmail.textContent;
    const message = replyMessage.value.trim();

    if (!message) {
        Swal.fire({
            title: translations.warning,
            text: translations.pleaseWriteMessage,
            icon: 'warning',
            confirmButtonText: translations.ok,
            confirmButtonColor: '#4F46E5'
        });
        return;
    }

    // Loading durumu
    this.disabled = true;
    this.innerHTML = `<i class="fas fa-spinner fa-spin"></i> ${translations.sending}`;

    fetch('send_reply.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: `message_id=${messageId}&email=${encodeURIComponent(email)}&reply=${encodeURIComponent(message)}`
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            // Önce modal'ı kapat ve formu temizle
            modal.classList.add('hidden');
            modal.classList.remove('flex');
            replyMessage.value = '';

            // Sonra başarı mesajını göster
            Swal.fire({
                title: translations.success,
                text: translations.replySent,
                icon: 'success',
                confirmButtonText: translations.ok,
                confirmButtonColor: '#4F46E5'
            }).then((result) => {
                location.reload();
            });
        } else {
            Swal.fire({
                title: translations.error,
                text: data.message || translations.errorOccurred,
                icon: 'error',
                confirmButtonText: translations.ok,
                confirmButtonColor: '#4F46E5'
            });
            console.error('Sunucu hatası:', data);
        }
    })
    .catch(error => {
        console.error('Hata detayı:', error);
        Swal.fire({
            title: translations.error,
            text: translations.errorOccurred,
            icon: 'error',
            confirmButtonText: translations.ok,
            confirmButtonColor: '#4F46E5'
        });
    })
    .finally(() => {
        this.disabled = false;
        this.innerHTML = translations.send;
    });
});
</script>

<?php include_once '../includes/new-footer.php'; ?>
