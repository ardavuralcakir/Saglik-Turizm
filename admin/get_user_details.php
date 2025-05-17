<?php
session_start();
include_once '../config/database.php';

// ===========================================
// 1) DİL DESTEĞİ İÇİN GEREKLİ ÇEVİRİ DİZİSİ
// ===========================================
$page_translations = [
    'tr' => [
        'unauthorized_access'    => 'Yetkisiz erişim',
        'user_id_required'       => 'Kullanıcı ID gerekli',
        'user_not_found'         => 'Kullanıcı bulunamadı',
        'delete'                 => 'Sil',

        // Sayfa başlık
        'user_details_title'     => 'Kullanıcı Detayları',

        // Kullanıcı bilgileri
        'no_phone'               => '',
        'total_orders_label'     => 'Toplam Sipariş',
        'total_spent_label'      => 'Toplam Harcama',
        'registration_date_label'=> 'Kayıt Tarihi',

        // Tab başlıkları
        'orders_tab' => 'Siparişler',
        'notes_tab'  => 'Notlar',

        // Siparişler
        'no_orders'              => 'Henüz sipariş bulunmuyor',
        'status_completed'       => 'Tamamlandı',
        'status_pending'         => 'Bekliyor',
        'status_cancelled'       => 'İptal Edildi',
        'doctor_prefix'          => 'Dr.',

        // Notlar
        'order_notes'            => 'Sipariş Notları',
        'placeholder_note_text'  => 'Siparişe not ekleyin...',
        'add_note'               => 'Not Ekle',
        'no_notes'               => 'Henüz not eklenmemiş.',

        // Butonlar / Eylemler
        'close_modal'            => 'Kapat',
        'confirm_delete_note'    => 'Bu notu silmek istediğinize emin misiniz?',

        // Toast / uyarı mesajları
        'please_enter_note'      => 'Lütfen bir not giriniz',
        'note_added_success'     => 'Not başarıyla eklendi',
        'error_adding_note'      => 'Not eklenirken bir hata oluştu',
        'note_deleted_success'   => 'Not başarıyla silindi',
        'error_deleting_note'    => 'Not silinirken bir hata oluştu',
    ],
    'en' => [
        'unauthorized_access'    => 'Unauthorized access',
        'user_id_required'       => 'User ID required',
        'user_not_found'         => 'User not found',
        'delete'                 => 'Delete',

        // Page heading
        'user_details_title'     => 'User Details',

        // User info
        'no_phone'               => '',
        'total_orders_label'     => 'Total Orders',
        'total_spent_label'      => 'Total Spent',
        'registration_date_label'=> 'Registration Date',

        // Tab headings
        'orders_tab' => 'Orders',
        'notes_tab'  => 'Notes',

        // Orders
        'no_orders'              => 'No orders yet',
        'status_completed'       => 'Completed',
        'status_pending'         => 'Pending',
        'status_cancelled'       => 'Cancelled',
        'doctor_prefix'          => 'Dr.',

        // Notes
        'order_notes'            => 'Order Notes',
        'placeholder_note_text'  => 'Add a note to the order...',
        'add_note'               => 'Add Note',
        'no_notes'               => 'No notes yet.',

        // Buttons / actions
        'close_modal'            => 'Close',
        'confirm_delete_note'    => 'Are you sure you want to delete this note?',

        // Toast / alerts
        'please_enter_note'      => 'Please enter a note',
        'note_added_success'     => 'Note added successfully',
        'error_adding_note'      => 'An error occurred while adding the note',
        'note_deleted_success'   => 'Note deleted successfully',
        'error_deleting_note'    => 'An error occurred while deleting the note',
    ],
];

// Oturumdaki dili yakala (varsayılan: 'tr')
$lang = $_SESSION['lang'] ?? 'tr';
// $pt dizisini al
$pt = $page_translations[$lang];

// Admin kontrolü
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    exit($pt['unauthorized_access']);
}

if (!isset($_GET['id'])) {
    http_response_code(400);
    exit($pt['user_id_required']);
}

$userId = (int)$_GET['id'];

$database = new Database();
$db = $database->getConnection();

// Kullanıcı bilgileri + sipariş özeti
$query = "SELECT u.*,
          COUNT(DISTINCT o.id) as total_orders,
          COALESCE(SUM(o.total_amount), 0) as total_spent,
          MAX(o.created_at) as last_order_date
          FROM users u
          LEFT JOIN orders o ON u.id = o.user_id
          WHERE u.id = ?
          GROUP BY u.id";

$stmt = $db->prepare($query);
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    http_response_code(404);
    exit($pt['user_not_found']);
}

// Son siparişler
$query = "SELECT o.*, s.name as service_name, d.name as doctor_name
          FROM orders o
          LEFT JOIN services s ON o.service_id = s.id
          LEFT JOIN doctors d ON o.doctor_id = d.id
          WHERE o.user_id = ?
          ORDER BY o.created_at DESC
          LIMIT 5";

$stmt = $db->prepare($query);
$stmt->execute([$userId]);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Kullanıcı notları
$query = "SELECT n.*, a.username as admin_name 
          FROM user_notes n
          LEFT JOIN users a ON n.admin_id = a.id
          WHERE n.user_id = ?
          ORDER BY n.created_at DESC";

$stmt = $db->prepare($query);
$stmt->execute([$userId]);
$notes = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<style>
    /* Webkit (Chrome, Safari, newer versions of Opera) */
    .overflow-y-auto::-webkit-scrollbar {
        width: 6px;
    }

    .overflow-y-auto::-webkit-scrollbar-track {
        background: transparent;
    }

    .overflow-y-auto::-webkit-scrollbar-thumb {
        background-color: rgba(156, 163, 175, 0.5);
        border-radius: 3px;
    }

    /* Firefox */
    .overflow-y-auto {
        scrollbar-width: thin;
        scrollbar-color: rgba(156, 163, 175, 0.5) transparent;
    }

    .orders-container {
        max-height: 400px;
        overflow-y: auto;
        scrollbar-width: thin;
        scrollbar-color: rgba(156, 163, 175, 0.5) transparent;
    }

    .orders-container::-webkit-scrollbar {
        width: 6px;
    }

    .orders-container::-webkit-scrollbar-track {
        background: transparent;
    }

    .orders-container::-webkit-scrollbar-thumb {
        background-color: rgba(156, 163, 175, 0.5);
        border-radius: 3px;
    }
</style>

<!-- Ana Container -->
<div class="p-6">
    <!-- Tek Başlık -->
    <div class="flex justify-between items-center mb-6 border-b border-gray-200 pb-4">
        <h2 class="text-xl font-semibold"><?php echo $pt['user_details_title']; ?></h2>
        <button onclick="closeModal('userDetailsModal')" class="text-gray-500 hover:text-gray-700" title="<?php echo $pt['close_modal']; ?>">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>
    </div>

    <!-- Kullanıcı Bilgileri -->
    <div class="space-y-2 mb-8">
        <div class="text-lg font-medium text-gray-900">
            <?php echo htmlspecialchars($user['full_name']); ?>
        </div>
        <div class="text-gray-600">
            <?php echo htmlspecialchars($user['email']); ?>
        </div>
        <div class="text-gray-600">
            <?php echo htmlspecialchars($user['phone'] ?? $pt['no_phone']); ?>
        </div>
    </div>

    <!-- İstatistikler -->
    <div class="grid grid-cols-3 gap-4 mb-8">
        <div class="bg-gray-50 p-4 rounded-lg">
            <div class="text-sm text-gray-500"><?php echo $pt['total_orders_label']; ?></div>
            <div class="text-xl font-medium mt-1">
                <?php echo number_format($user['total_orders']); ?>
            </div>
        </div>
        <div class="bg-gray-50 p-4 rounded-lg">
            <div class="text-sm text-gray-500"><?php echo $pt['total_spent_label']; ?></div>
            <div class="text-xl font-medium mt-1">
                ₺<?php echo number_format($user['total_spent'], 2); ?>
            </div>
        </div>
        <div class="bg-gray-50 p-4 rounded-lg">
            <div class="text-sm text-gray-500"><?php echo $pt['registration_date_label']; ?></div>
            <div class="text-xl font-medium mt-1">
                <?php echo date('d.m.Y', strtotime($user['created_at'])); ?>
            </div>
        </div>
    </div>

    <!-- Tabs -->
    <div class="border-b border-gray-200 mb-6">
        <div class="flex -mb-px space-x-8">
            <button id="ordersTab"
                    onclick="switchTab('orders')"
                    class="px-4 py-3 border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 transition-colors">
                <?php echo $pt['orders_tab']; ?>
            </button>
            <button id="notesTab"
                    onclick="switchTab('notes')"
                    class="px-4 py-3 border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 transition-colors">
                <?php echo $pt['notes_tab']; ?>
            </button>
        </div>
    </div>

    <!-- Orders Tab -->
    <div id="ordersContent" class="tab-content hidden">
        <div class="orders-container">
            <div class="space-y-4">
                <?php if (count($orders) > 0): ?>
                    <?php foreach ($orders as $order): ?>
                        <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-100 hover:shadow-md transition-shadow">
                            <div class="flex justify-between items-center py-4 border-b border-gray-100 last:border-0">
                                <div>
                                    <div class="font-medium text-gray-900">
                                        <?php echo htmlspecialchars($order['service_name']); ?>
                                    </div>
                                    <div class="text-sm text-gray-500">
                                        <?php echo $pt['doctor_prefix'] . ' ' . htmlspecialchars($order['doctor_name']); ?>
                                    </div>
                                </div>
                                <div class="text-right flex flex-col items-end gap-2">
                                    <div class="text-gray-900 font-medium">
                                        ₺<?php echo number_format($order['total_amount'], 2); ?>
                                    </div>
                                    <div class="text-sm text-gray-500">
                                        <?php echo date('d.m.Y', strtotime($order['created_at'])); ?>
                                    </div>
                                    <?php
                                    $statusClasses = [
                                        'completed' => 'bg-green-100 text-green-800',
                                        'pending'   => 'bg-yellow-100 text-yellow-800',
                                        'cancelled' => 'bg-red-100 text-red-800'
                                    ];
                                    $statusTexts = [
                                        'completed' => $pt['status_completed'],
                                        'pending'   => $pt['status_pending'],
                                        'cancelled' => $pt['status_cancelled']
                                    ];
                                    $status = $order['status'] ?? 'pending';
                                    $statusClass = $statusClasses[$status] ?? $statusClasses['pending'];
                                    $statusText  = $statusTexts[$status]  ?? $statusTexts['pending'];
                                    ?>
                                    <span class="px-3 py-1 rounded-full text-xs font-medium <?php echo $statusClass; ?>">
                                        <?php echo $statusText; ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-gray-500 text-center py-4"><?php echo $pt['no_orders']; ?></p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Notes Tab -->
    <div id="notesContent" class="tab-content hidden">
        <!-- Not Başlığı -->
        <div class="flex items-center gap-2 mb-4">
            <svg class="w-5 h-5 text-gray-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                      d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5
                         a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414
                         a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
            <h3 class="text-lg font-medium text-gray-900"><?php echo $pt['order_notes']; ?></h3>
        </div>

        <!-- Not Ekleme Alanı -->
        <div class="mb-6">
            <!-- Not Yazma Alanı -->
            <div class="bg-gray-50 border border-gray-200 rounded-xl p-4 mb-4">
                <textarea id="newNoteText" 
                          placeholder="<?php echo $pt['placeholder_note_text']; ?>"
                          class="w-full bg-transparent text-gray-700 placeholder-gray-400 resize-none focus:outline-none"
                          rows="3"></textarea>
            </div>
            
            <!-- Not Ekle Butonu -->
            <button onclick="handleAddNote(<?php echo $userId; ?>)"
                    class="w-full p-4 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl 
                           flex items-center justify-center gap-2 transition-colors duration-300">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                          d="M12 4v16m8-8H4"/>
                </svg>
                <?php echo $pt['add_note']; ?>
            </button>
        </div>

        <!-- Mevcut Notlar -->
        <div class="overflow-y-auto pr-2 space-y-4" style="max-height: 150px; scrollbar-width: thin; scrollbar-color: rgba(156, 163, 175, 0.5) transparent;">
            <?php if (empty($notes)): ?>
                <div class="text-gray-500 text-center py-4"><?php echo $pt['no_notes']; ?></div>
            <?php else: ?>
                <div class="space-y-3">
                    <?php foreach ($notes as $note): ?>
                        <div class="flex items-start justify-between bg-gray-50 rounded-lg p-4 shadow-sm">
                            <div class="flex-1">
                                <p class="text-gray-700 mb-2">
                                    <?php echo htmlspecialchars($note['note']); ?>
                                </p>
                                <div class="flex items-center gap-2">
                                    <span class="text-sm text-gray-600">
                                        <?php echo htmlspecialchars($note['admin_name']); ?>
                                    </span>
                                    <span class="text-sm text-gray-400">
                                        <?php echo date('d.m.Y H:i', strtotime($note['created_at'])); ?>
                                    </span>
                                </div>
                            </div>
                            <button onclick="deleteNote(<?php echo $note['id']; ?>)"
                                    class="ml-4 p-2 text-red-600 hover:text-red-800 transition-colors"
                                    title="<?php echo $pt['delete']; ?>">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                          d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862
                                             a2 2 0 01-1.995-1.858L5 7m5 4v6
                                             m4-6v6m1-10V4a1 1 0 00-1-1h-4
                                             a1 1 0 00-1 1v3M4 7h16"/>
                                </svg>
                            </button>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// Mevcut userId (global)
let currentUserId = <?php echo $userId; ?>;
let activeTab = 'orders'; // Aktif tab'ı tutacak değişken

// Not Ekleme Handler
async function handleAddNote(userId) {
    const noteText = document.getElementById('newNoteText')?.value?.trim();
    if (!noteText) {
        showToast('error', '<?php echo $pt['please_enter_note']; ?>');
        return;
    }

    try {
        const formData = new FormData();
        formData.append('user_id', userId);
        formData.append('note', noteText);

        const response = await fetch('add_note.php', {
            method: 'POST',
            body: formData
        });

        if (!response.ok) {
            throw new Error('Network response was not ok');
        }

        const result = await response.json();
        
        if (result.success) {
            showToast('success', '<?php echo $pt['note_added_success']; ?>');
            document.getElementById('newNoteText').value = '';
            // Kullanıcı detaylarını yeniden yükle ve aktif tab'ı koru
            await reloadUserDetails(userId);
        } else {
            showToast('error', result.message || '<?php echo $pt['error_adding_note']; ?>');
        }
    } catch (error) {
        console.error('Error:', error);
        showToast('error', '<?php echo $pt['error_adding_note']; ?>');
    }
}

// Not Silme Fonksiyonu
async function deleteNote(noteId) {
    if (!confirm('<?php echo $pt['confirm_delete_note']; ?>')) {
        return;
    }

    try {
        const formData = new FormData();
        formData.append('note_id', noteId);

        const response = await fetch('delete_user_note.php', {
            method: 'POST',
            body: formData
        });

        if (!response.ok) {
            throw new Error('Network response was not ok');
        }

        const result = await response.json();
        
        if (result.success) {
            showToast('success', '<?php echo $pt['note_deleted_success']; ?>');
            // Kullanıcı detaylarını yeniden yükle ve aktif tab'ı koru
            await reloadUserDetails(currentUserId);
        } else {
            showToast('error', result.message || '<?php echo $pt['error_deleting_note']; ?>');
        }
    } catch (error) {
        console.error('Error:', error);
        showToast('error', '<?php echo $pt['error_deleting_note']; ?>');
    }
}

// Kullanıcı detaylarını yeniden yükleme fonksiyonu
async function reloadUserDetails(userId) {
    try {
        const response = await fetch(`get_user_details.php?id=${userId}`);
        if (!response.ok) throw new Error(`HTTP error: ${response.status}`);

        const html = await response.text();
        if (!html) throw new Error('Boş yanıt');

        const modalBody = document.getElementById('userDetailsBody');
        if (!modalBody) throw new Error('userDetailsBody yok');

        modalBody.innerHTML = html;
        
        // Aktif tab'ı tekrar ayarla
        switchTab(activeTab);
    } catch (error) {
        console.error('Hata:', error);
        showToast('error', '<?php echo $pt['user_details_not_loaded']; ?>: ' + error.message);
    }
}

// Tab değiştirme fonksiyonu
function switchTab(tabId) {
    // Aktif tab'ı güncelle
    activeTab = tabId;
    
    // Tüm tab içeriklerini gizle
    document.querySelectorAll('.tab-content').forEach(content => {
        content.classList.add('hidden');
    });
    
    // Tüm tab butonlarını pasif yap
    document.querySelectorAll('[id$="Tab"]').forEach(tab => {
        tab.classList.remove('border-blue-500', 'text-blue-600');
        tab.classList.add('text-gray-500', 'border-transparent');
    });
    
    // Seçilen tabı ve içeriğini göster
    const selectedContent = document.getElementById(tabId + 'Content');
    const selectedTab = document.getElementById(tabId + 'Tab');
    
    if (selectedContent && selectedTab) {
        selectedContent.classList.remove('hidden');
        selectedTab.classList.add('border-blue-500', 'text-blue-600');
        selectedTab.classList.remove('text-gray-500', 'border-transparent');
    }
}

// Sayfa yüklendiğinde varsayılan tab'ı göster
document.addEventListener('DOMContentLoaded', () => {
    switchTab('orders');
});
</script>
