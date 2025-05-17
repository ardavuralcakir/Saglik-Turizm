<?php
session_start();
include_once '../config/database.php';

// 1) Translation file
$lang = $_SESSION['lang'] ?? 'tr'; 
$translation_file = __DIR__ . "/../translations/translation_{$lang}.php";
if (file_exists($translation_file)) {
   $t = require $translation_file; 
} else {
   die("Translation file not found: {$translation_file}");
}

// 2) Admin check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
   exit($t['unauthorized'] ?? 'Unauthorized access');
}

// 3) Order ID check
if (!isset($_GET['id'])) {
   exit($t['order_id_required'] ?? 'Order ID is required');
}

// 4) Database connection
$database = new Database();
$db = $database->getConnection();

// 5) Multi-language fields with CASE WHEN
$caseServiceName = "
   CASE WHEN ? = 'en' THEN s.name_en 
        ELSE s.name
   END AS service_name
";
$caseServiceDesc = "
   CASE WHEN ? = 'en' THEN s.description_en
        ELSE s.description
   END AS service_description
";

// Doctor bio based on language
$caseDoctorBio = "
   CASE 
       WHEN ? = 'en' THEN d.bio_en
       WHEN ? = 'de' THEN d.bio_de
       WHEN ? = 'fr' THEN d.bio_fr
       WHEN ? = 'es' THEN d.bio_es
       WHEN ? = 'zh' THEN d.bio_zh
       WHEN ? = 'it' THEN d.bio_it
       WHEN ? = 'ru' THEN d.bio_ru
       ELSE d.bio_tr
   END AS doctor_bio
";

// 6) Order query with joins
$query = "
   SELECT 
       o.*,
       COALESCE(u.username, '') as customer_username,
       COALESCE(u.full_name, '') as customer_name,
       COALESCE(u.email, '') as customer_email,
       COALESCE(u.phone, '') as customer_phone,
       {$caseServiceName},
       {$caseServiceDesc},
       COALESCE(d.name, '') as doctor_name,
       COALESCE(d.specialty, '') as doctor_specialty,
       {$caseDoctorBio}
   FROM orders o
   LEFT JOIN users u ON o.user_id = u.id
   LEFT JOIN services s ON o.service_id = s.id
   LEFT JOIN doctors d ON o.doctor_id = d.id
   WHERE o.id = ?
";

// Prepare and execute main query
$stmt = $db->prepare($query);
$params = [
   $lang,  // for service name
   $lang,  // for service description
   $lang,  // for doctor bio
   $lang,  // for doctor bio
   $lang,  // for doctor bio
   $lang,  // for doctor bio
   $lang,  // for doctor bio
   $lang,  // for doctor bio
   $lang,  // for doctor bio
   $_GET['id'] // for order id
];
$stmt->execute($params);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
   exit($t['order_not_found'] ?? 'Order not found');
}

// 7) Order notes
$notes_query = "
   SELECT 
       n.*,
       COALESCE(us.full_name, '') as user_name
   FROM order_notes n
   LEFT JOIN users us ON n.user_id = us.id
   WHERE n.order_id = ?
   ORDER BY n.created_at DESC
";
$notes_stmt = $db->prepare($notes_query);
$notes_stmt->execute([$_GET['id']]);
$notes = $notes_stmt->fetchAll(PDO::FETCH_ASSOC);

// 8) Continue with HTML output...
?>
<style>
/* Örnek scrollbar */
.custom-scrollbar::-webkit-scrollbar {
    width: 6px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: rgba(0, 0, 0, 0.05);
    border-radius: 8px;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background: rgba(0, 0, 0, 0.2);
    border-radius: 8px;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: rgba(0, 0, 0, 0.3);
}
</style>

<?php
// ... (Önceki PHP kodu aynı kalacak)

// HTML çıktısı için güncelleme yapıyoruz ?>

<div class="max-w-xl mx-auto space-y-5 p-4 text-sm">
    <!-- Başlık (Sipariş ID, Durum, Tutar vs.) -->
    <div class="border-b border-gray-300 pb-3">
        <div class="flex justify-between items-start">
            <div>
                <h3 class="text-xl font-bold mb-1 text-black">
                    <?php echo $t['order_label'] ?? 'Sipariş'; ?> #<?php echo htmlspecialchars($order['id']); ?>
                </h3>
                <span class="px-2 py-1 rounded-full text-xs
                    <?php 
                    switch($order['status']) {
                        case 'pending':   echo 'bg-yellow-100 text-yellow-600'; break;
                        case 'completed': echo 'bg-green-100 text-green-600';  break;
                        case 'cancelled': echo 'bg-red-100 text-red-600';     break;
                        default:          echo 'bg-gray-200 text-gray-800';   break;
                    }
                    ?>">
                    <?php 
                    echo $t[$order['status']] ?? $order['status']; 
                    ?>
                </span>
            </div>
            <div class="text-right">
                <p class="text-xl font-bold text-black">
                    ₺<?php echo number_format($order['total_amount'], 2); ?>
                </p>
                <p class="text-xs text-gray-700">
                    <?php echo date('d.m.Y H:i', strtotime($order['created_at'])); ?>
                </p>
            </div>
        </div>
    </div>

    <!-- Müşteri Detayları + Hizmet Detayları -->
    <div class="flex flex-col lg:flex-row gap-4">
        <!-- Müşteri Bilgileri -->
        <div class="bg-gray-100 rounded-xl p-3 flex-1">
            <h4 class="text-base font-semibold mb-3 flex items-center text-black">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                          d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                </svg>
                <?php echo $t['customer_info'] ?? 'Müşteri Bilgileri'; ?>
            </h4>
            <div class="space-y-1 text-gray-700">
                <p>
                    <span class="font-medium"><?php echo $t['full_name'] ?? 'Ad Soyad'; ?>:</span> 
                    <?php echo htmlspecialchars($order['customer_name']); ?>
                </p>
                <p>
                    <span class="font-medium"><?php echo $t['username'] ?? 'Kullanıcı Adı'; ?>:</span> 
                    <?php echo htmlspecialchars($order['customer_username']); ?>
                </p>
                <p>
                    <span class="font-medium"><?php echo $t['email'] ?? 'E-posta'; ?>:</span> 
                    <?php echo htmlspecialchars($order['customer_email']); ?>
                </p>
                <p>
                    <span class="font-medium"><?php echo $t['phone'] ?? 'Telefon'; ?>:</span> 
                    <?php echo htmlspecialchars($order['customer_phone']); ?>
                </p>
            </div>
        </div>

        <!-- Hizmet Bilgileri -->
        <div class="bg-gray-100 rounded-xl p-3 flex-1">
            <h4 class="text-base font-semibold mb-3 flex items-center text-black">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                          d="M13 10V3L4 14h7v7l9-11h-7z"/>
                </svg>
                <?php echo $t['service_info'] ?? 'Hizmet Bilgileri'; ?>
            </h4>
            <div class="space-y-1 text-gray-700">
                <p>
                    <span class="font-medium"><?php echo $t['service'] ?? 'Servis'; ?>:</span> 
                    <?php echo htmlspecialchars($order['service_name']); ?>
                </p>
                <p>
                    <span class="font-medium"><?php echo $t['doctor'] ?? 'Doktor'; ?>:</span> 
                    <?php echo htmlspecialchars($order['doctor_name']); ?>
                </p>
                <p>
                    <span class="font-medium"><?php echo $t['doctor_bio'] ?? 'Doktor Bilgisi'; ?>:</span> 
                    <?php echo htmlspecialchars($order['doctor_bio'] ?? ''); ?>
                </p>
            </div>
        </div>
    </div>

    <!-- Notlar -->
    <div>
        <h4 class="text-base font-semibold mb-3 flex items-center text-black">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                      d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
            <?php echo $t['order_notes'] ?? 'Sipariş Notları'; ?>
        </h4>

        <!-- Not listesi -->
<!-- Notlar Listesi -->
        <div class="space-y-3 overflow-y-auto custom-scrollbar pr-2" style="max-height: 300px;">
            <?php if (empty($notes)): ?>
                <p class="text-gray-500 text-center py-4">
                    <?php echo $t['no_notes'] ?? 'Henüz not eklenmemiş.'; ?>
                </p>
            <?php else: ?>
                <?php foreach ($notes as $note): ?>
                    <div class="bg-white rounded-lg p-3 shadow-sm hover:shadow-md transition-shadow">
                        <div class="flex items-start justify-between gap-4">
                            <!-- Not İçeriği -->
                            <div class="flex-grow">
                                <p class="text-gray-800 whitespace-pre-line">
                                    <?php echo nl2br(htmlspecialchars($note['note'])); ?>
                                </p>
                                <div class="mt-2 flex items-center justify-between text-xs text-gray-500">
                                    <span class="font-medium"><?php echo htmlspecialchars($note['user_name']); ?></span>
                                    <span><?php echo date('d.m.Y H:i', strtotime($note['created_at'])); ?></span>
                                </div>
                            </div>
                            
                            <!-- Silme Butonu -->
                            <button onclick="deleteNote(<?php echo $note['id']; ?>, <?php echo $order['id']; ?>)"
                                    class="group p-1.5 rounded-full hover:bg-red-50 transition-colors duration-200"
                                    title="Notu Sil">
                                <svg class="w-4 h-4 text-gray-400 group-hover:text-red-500 transition-colors duration-200" 
                                    fill="none" 
                                    stroke="currentColor" 
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" 
                                        stroke-linejoin="round" 
                                        stroke-width="2" 
                                        d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                </svg>
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Not ekleme formu -->
        <form onsubmit="return addNote(this, <?php echo $order['id']; ?>)" class="mt-4">
            <textarea name="note" rows="3" 
                      class="w-full p-3 border rounded-lg resize-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                      placeholder="<?php echo $t['add_note_placeholder'] ?? 'Not ekleyin...'; ?>"></textarea>
            <button type="submit" 
                    class="mt-2 w-full px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                <?php echo $t['add_note_button'] ?? 'Not Ekle'; ?>
            </button>
        </form>
    </div>
</div>


<script>

// Bu JavaScript kodunu get_order_details.php dosyasının en altına ekleyin
function deleteNote(noteId, orderId) {
    if (confirm('Bu notu silmek istediğinize emin misiniz?')) {
        fetch('delete_order_note.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ note_id: noteId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Not başarıyla silindiyse modal'ı yenile
                showOrderDetails(orderId);
                
                // Başarılı silme bildirimi göster
                const toast = document.createElement('div');
                toast.className = 'fixed right-4 top-4 bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded shadow-lg transform transition-transform duration-300';
                toast.innerHTML = `
                    <div class="flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                        <p>${data.message}</p>
                    </div>
                `;
                document.body.appendChild(toast);

                // 3 saniye sonra bildirimi kaldır
                setTimeout(() => {
                    toast.style.transform = 'translateX(150%)';
                    setTimeout(() => toast.remove(), 300);
                }, 3000);
            } else {
                // Hata durumunda kullanıcıya bildir
                alert(data.message || 'Not silinirken bir hata oluştu.');
            }
        })
        .catch(error => {
            console.error('Hata:', error);
            alert('Not silinirken bir hata oluştu.');
        });
    }
}

</script>