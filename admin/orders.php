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

// 4) Sayfalama parametreleri
$page             = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$records_per_page = 10;
$offset           = ($page - 1) * $records_per_page;

// 5) Filtre parametreleri
$date       = isset($_GET['date'])    ? $_GET['date']    : '';
$status     = isset($_GET['status'])  ? $_GET['status']  : '';
$service_id = isset($_GET['service']) ? $_GET['service'] : '';

// 6) Sipariş durumu güncelleme işlemi (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $order_id   = $_POST['order_id'];
    $new_status = $_POST['new_status'];

    try {
        $query = "UPDATE orders SET status = ? WHERE id = ?";
        $stmt  = $db->prepare($query);
        if ($stmt->execute([$new_status, $order_id])) {
            // Dilerseniz çeviri: $_SESSION['admin_success'] = $t['order_status_updated'];
            $_SESSION['admin_success'] = "Sipariş durumu başarıyla güncellendi.";
        }
    } catch (PDOException $e) {
        // Dilerseniz çeviri: $_SESSION['admin_error'] = $t['update_error'];
        $_SESSION['admin_error'] = "Güncelleme sırasında bir hata oluştu.";
    }
}

// 7) Siparişleri sorgula (filtre + sayfalama)

// Ana sorgu
$query = "
    SELECT 
        o.*, 
        u.username, 
        u.full_name, 
        s.name as service_name, 
        d.name as doctor_name
    FROM orders o
    JOIN users u      ON o.user_id    = u.id
    JOIN services s   ON o.service_id = s.id
    LEFT JOIN doctors d ON o.doctor_id= d.id
    WHERE 1=1
";

// Sayı sorgusu (toplam kayıt)
// DİKKAT: Burada da JOIN services eklendi ki 's.status' kontrol edebilelim
$count_query = "
    SELECT COUNT(*) 
    FROM orders o
    JOIN services s ON o.service_id = s.id
    WHERE 1=1
";

$params       = [];
$count_params = [];

// Filtre: service
if ($service_id) {
    $query       .= " AND o.service_id = ?";
    $count_query .= " AND o.service_id = ?";
    $params[]     = $service_id;
    $count_params[]= $service_id;
}

// Filtre: date
if ($date) {
    $query       .= " AND DATE(o.created_at) = ?";
    $count_query .= " AND DATE(o.created_at) = ?";
    $params[]     = $date;
    $count_params[]= $date;
}

// Filtre: status
if ($status) {
    $query       .= " AND o.status = ?";
    $count_query .= " AND o.status = ?";
    $params[]     = $status;
    $count_params[]= $status;
}

// == SİLİNMİŞ SERVİSLERİ GÖSTERMEMEK ==
// (services tablosunda status <> 'deleted')
$query       .= " AND s.status <> 'deleted'";
$count_query .= " AND s.status <> 'deleted'";

// 8) Toplam kayıt sayısı
$count_stmt = $db->prepare($count_query);
$count_stmt->execute($count_params);
$total_records = $count_stmt->fetchColumn();
$total_pages   = ceil($total_records / $records_per_page);

// Ana sorgu - sıralama + limit
$query .= " ORDER BY o.created_at DESC LIMIT $offset, $records_per_page";
$stmt   = $db->prepare($query);
$stmt->execute($params);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Siparişler</title>
    <!-- SweetAlert2 CSS ve JS -->
    <link href="https://cdn.jsdelivr.net/npm/@sweetalert2/theme-material-ui/material-ui.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
<style>

.pagination-container {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 0.5rem;
    padding: 2rem 0;
}

.pagination-container a {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 2.5rem;
    height: 2.5rem;
    padding: 0 0.75rem;
    font-size: 0.875rem;
    border-radius: 0.5rem;
    font-weight: 500;
    color: #4B5563;
    background-color: white;
    border: 1px solid #E5E7EB;
    transition: all 0.2s;
}

.pagination-container a:hover {
    background-color: #F3F4F6;
    color: #1F2937;
    transform: translateY(-1px);
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
}

.pagination-container a.active {
    background-color: #3B82F6;
    color: white;
    border-color: #3B82F6;
}

.pagination-container .prev-page,
.pagination-container .next-page {
    padding: 0 1rem;
}

.pagination-container .dots {
    padding: 0 0.5rem;
    color: #6B7280;
}

@media (max-width: 640px) {
    .pagination-container {
        gap: 0.25rem;
    }
    
    .pagination-container a {
        min-width: 2rem;
        height: 2rem;
        padding: 0 0.5rem;
        font-size: 0.75rem;
    }
}

.blur-effect {
    filter: blur(4px);
    opacity: 0.3;
    transition: all 3s ease;
    pointer-events: none;
    background-color: rgba(0, 0, 0, 0.2);
}

.custom-scrollbar::-webkit-scrollbar {
    width: 6px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 4px;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background: #ddd;
    border-radius: 4px;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: #ccc;
}

@keyframes modalFadeIn {
    from {
        opacity: 0;
        transform: scale(0.95);
    }
    to {
        opacity: 1;
        transform: scale(1);
    }
}
#orderModal .relative {
    animation: modalFadeIn 0.5s ease-out;
}

@keyframes noteAdded {
    0% {
        opacity: 0;
        transform: translateY(20px);
    }
    100% {
        opacity: 1;
        transform: translateY(0);
    }
}
.note-added {
    animation: noteAdded 0.3s ease-out forwards;
}
</style>

<div class="luxury-gradient min-h-screen pt-24 pb-12">
    <div class="max-w-7xl mx-auto px-4">
        <!-- Üst başlık ve Export -->
        <div class="flex items-center justify-between mb-12">
            <div>
                <h1 class="text-4xl font-light text-gray-800 mb-2">
                    <?php echo $t['orders_page_title']; ?>
                </h1>
                <p class="text-gray-600">
                    <?php echo $t['orders_page_subtitle']; ?>
                </p>
            </div>
            <button onclick="exportOrders()"
                    class="px-4 py-2 bg-green-500 text-white rounded-lg hover:bg-green-600 transition-colors duration-300 flex items-center">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                          d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                <?php echo isset($t['download_orders']) ? $t['download_orders'] : 'Siparişleri İndir'; ?>
            </button>
        </div>

        <!-- Filtreleme Formu -->
                <!-- Filtreleme Formu -->
        <div class="filter-bar bg-white/5 backdrop-blur-lg p-6 rounded-2xl mb-8 border border-white/10 shadow-xl">
            <form class="grid grid-cols-1 md:grid-cols-4 gap-6">
                <!-- Hizmet -->
                <div>
                    <label for="service" class="block text-sm font-medium text-white-2000 font-semibold mb-2">
                        <?php echo $t['service_label']; ?>
                    </label>
                    <div class="relative">

                        <?php 
                        // Servis tablosundan "deleted" olmayan kayıtları, 
                        // dil seçimine göre CASE WHEN ile çekiyoruz.
                        $services_query = "
                            SELECT 
                                id,
                                CASE WHEN :lang = 'en' THEN name_en ELSE name END AS service_name
                            FROM services
                            WHERE status <> 'deleted'
                            ORDER BY name
                        ";
                        $services_stmt = $db->prepare($services_query);
                        // :lang parametresini bağla
                        $services_stmt->execute([':lang' => $lang]);
                        $services = $services_stmt->fetchAll(PDO::FETCH_ASSOC);
                        ?>

                        <select id="service" name="service" 
                                class="w-full px-4 py-3 bg-gray-800/50 border border-gray-700 rounded-xl 
                                    focus:ring-2 focus:ring-blue-500 focus:border-transparent appearance-none">
                            <option value=""><?php echo $t['all']; ?></option>
                            <?php foreach ($services as $svc): ?>
                                <option value="<?php echo $svc['id']; ?>"
                                    <?php if(isset($_GET['service']) && $_GET['service'] == $svc['id']) echo "selected"; ?>>
                                    <?php echo htmlspecialchars($svc['service_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-4 text-gray-400">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                    d="M19 9l-7 7-7-7" />
                            </svg>
                        </div>
                    </div>
                </div>

                <!-- Tarih -->
                <div>
                    <label for="date" class="block text-sm font-medium text-gray-2000 mb-2">
                        <?php echo $t['date_label']; ?>
                    </label>
                    <input type="date" id="date" name="date" 
                        value="<?php echo isset($_GET['date']) ? $_GET['date'] : ''; ?>" 
                        class="w-full px-4 py-3 bg-gray-800/50 border border-gray-700 rounded-xl 
                                focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>

                <!-- Durum -->
                <div>
                    <label for="status" class="block text-sm font-medium text-gray-2000 mb-2">
                        <?php echo $t['status_label']; ?>
                    </label>
                    <div class="relative">
                        <select id="status" name="status" 
                                class="w-full px-4 py-3 bg-gray-800/50 border border-gray-700 rounded-xl 
                                    focus:ring-2 focus:ring-blue-500 focus:border-transparent appearance-none">
                            <option value=""><?php echo $t['all']; ?></option>
                            <option value="pending"   <?php echo ($status === 'pending')   ? 'selected' : ''; ?>>
                                <?php echo $t['pending']; ?>
                            </option>
                            <option value="completed" <?php echo ($status === 'completed') ? 'selected' : ''; ?>>
                                <?php echo $t['completed']; ?>
                            </option>
                            <option value="cancelled" <?php echo ($status === 'cancelled') ? 'selected' : ''; ?>>
                                <?php echo $t['cancelled']; ?>
                            </option>
                        </select>
                        <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-4 text-gray-400">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                    d="M19 9l-7 7-7-7" />
                            </svg>
                        </div>
                    </div>
                </div>

                <!-- Filtre Butonu -->
                <div class="flex items-end">
                    <button type="submit" 
                            class="w-full px-6 py-3 bg-gradient-to-r from-blue-600 to-blue-800 text-white rounded-xl 
                                hover:from-blue-700 hover:to-blue-900 transition-all duration-300 shadow-lg 
                                hover:shadow-blue-500/25">
                        <div class="flex items-center justify-center space-x-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                    d="M3 4a1 1 0 011-1h16
                                        a1 1 0 011 1v2.586
                                        a1 1 0 01-.293.707
                                        l-6.414 6.414
                                        a1 1 0 00-.293.707V17
                                        l-4 4
                                        v-6.586
                                        a1 1 0 00-.293-.707
                                        L3.293 7.293
                                        A1 1 0 013 6.586V4z" />
                            </svg>
                            <span><?php echo $t['filter']; ?></span>
                        </div>
                    </button>
                </div>
            </form>
        </div>


        <!-- Siparişler Tablosu -->
    <div class="orders-table rounded-2xl overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        <?php echo $t['order_id']; ?>
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        <?php echo $t['customer']; ?>
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        <?php echo $t['service']; ?>
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        <?php echo $t['doctor']; ?>
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        <?php echo $t['date']; ?>
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        <?php echo $t['amount']; ?>
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        <?php echo $t['status']; ?>
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        <?php echo $t['actions']; ?>
                    </th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php foreach ($orders as $order): ?>
                    <tr class="hover:bg-gray-50 transition-colors duration-300">
                        <!-- Order ID -->
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm text-gray-900">#<?php echo $order['id']; ?></div>
                        </td>
                        <!-- Customer -->
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center">
                                <div class="flex-shrink-0 h-10 w-10">
                                    <img class="h-10 w-10 rounded-full" 
                                        src="https://ui-avatars.com/api/?name=<?php echo urlencode($order['full_name']); ?>" 
                                        alt="">
                                </div>
                                <div class="ml-4">
                                    <div class="text-sm font-medium text-gray-900">
                                        <?php echo htmlspecialchars($order['full_name']); ?>
                                    </div>
                                    <div class="text-sm text-gray-500">
                                        <?php echo htmlspecialchars($order['username']); ?>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <!-- Service -->
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm text-gray-900">
                                <?php echo htmlspecialchars($order['service_name']); ?>
                            </div>
                        </td>
                        <!-- Doctor -->
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm text-gray-900">
                                <?php echo htmlspecialchars($order['doctor_name']); ?>
                            </div>
                        </td>
                        <!-- Date -->
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <?php echo date('d.m.Y H:i', strtotime($order['created_at'])); ?>
                        </td>
                        <!-- Amount -->
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            ₺<?php echo number_format($order['total_amount'], 2); ?>
                        </td>
                        <!-- Status -->
                        <td class="px-6 py-4 whitespace-nowrap">
                            <form method="POST" class="inline-block">
                                <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                <input type="hidden" name="update_status" value="1">
                                <select name="new_status"
                                        onchange="this.form.submit()"
                                        class="px-2 py-1 rounded-lg text-sm transition-colors duration-200
                                        <?php
                                        switch($order['status']) {
                                            case 'pending':
                                                echo 'bg-yellow-100 text-yellow-800 hover:bg-yellow-200';
                                                break;
                                            case 'completed':
                                                echo 'bg-green-100 text-green-800 hover:bg-green-200';
                                                break;
                                            case 'cancelled':
                                                echo 'bg-red-100 text-red-800 hover:bg-red-200';
                                                break;
                                        }
                                        ?>">
                                    <option value="pending"   <?php if($order['status'] === 'pending')   echo 'selected'; ?>>
                                        <?php echo $t['pending']; ?>
                                    </option>
                                    <option value="completed" <?php if($order['status'] === 'completed') echo 'selected'; ?>>
                                        <?php echo $t['completed']; ?>
                                    </option>
                                    <option value="cancelled" <?php if($order['status'] === 'cancelled') echo 'selected'; ?>>
                                        <?php echo $t['cancelled']; ?>
                                    </option>
                                </select>
                            </form>
                        </td>
                        <!-- Actions -->
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                            <a href="#" onclick="showOrderDetails(<?php echo $order['id']; ?>)" 
                            class="text-indigo-600 hover:text-indigo-900">
                            <?php echo $t['details']; ?>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

        <!-- Sayfalama -->
        <?php if ($total_pages > 1): ?>
            <div class="pagination-container">
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page - 1; ?>&date=<?php echo $date; ?>&status=<?php echo $status; ?>&service=<?php echo $service_id; ?>" 
                    class="prev-page">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                        </svg>
                    </a>
                <?php endif; ?>

                <?php
                // Sayfa numaralarını akıllıca göster
                $start = max(1, $page - 2);
                $end = min($total_pages, $page + 2);
                
                if ($start > 1) {
                    echo '<a href="?page=1&date=' . $date . '&status=' . $status . '&service=' . $service_id . '">1</a>';
                    if ($start > 2) {
                        echo '<span class="dots">...</span>';
                    }
                }

                for ($i = $start; $i <= $end; $i++): ?>
                    <a href="?page=<?php echo $i; ?>&date=<?php echo $date; ?>&status=<?php echo $status; ?>&service=<?php echo $service_id; ?>"
                    <?php if ($page == $i) echo 'class="active"'; ?>>
                    <?php echo $i; ?>
                    </a>
                <?php endfor;

                if ($end < $total_pages) {
                    if ($end < $total_pages - 1) {
                        echo '<span class="dots">...</span>';
                    }
                    echo '<a href="?page=' . $total_pages . '&date=' . $date . '&status=' . $status . '&service=' . $service_id . '">' . $total_pages . '</a>';
                }
                ?>

                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page + 1; ?>&date=<?php echo $date; ?>&status=<?php echo $status; ?>&service=<?php echo $service_id; ?>" 
                    class="next-page">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Container -->
<div id="orderModal" class="fixed inset-0 z-50 hidden overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen p-4">
        <!-- Modal Backdrop -->
        <div class="fixed inset-0 bg-black/70 backdrop-blur-sm transition-opacity"></div>

        <!-- Modal Content -->
        <div class="relative bg-white rounded-lg w-full max-w-2xl mx-auto shadow-xl transform transition-all">
            <!-- Modal Header -->
            <div class="relative p-4 border-b">
                <div class="flex justify-between items-center">
                    <h3 class="text-lg font-semibold text-gray-900">
                        <?php echo $t['order_details']; ?>
                    </h3>
                    <button onclick="closeOrderModal()" 
                            class="text-gray-400 hover:text-gray-600 transition-colors duration-200">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                  d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Modal Body -->
            <div class="relative">
                <div class="max-h-[calc(100vh-12rem)] overflow-y-auto custom-scrollbar">
                    <div id="orderDetailsContent" class="p-4">
                        <div class="flex justify-center items-center py-8">
                            <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-500"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Başarılı İşlem Toast Bildirimi (örnek) -->
<div id="successToast" class="fixed right-4 top-4 transform translate-x-full transition-transform duration-300 ease-out">
    <div class="bg-white border-l-4 border-green-500 shadow-lg rounded-lg p-4 flex items-center">
        <div class="text-green-500 mr-3">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                      d="M5 13l4 4L19 7" />
            </svg>
        </div>
        <p class="text-gray-600"><?php echo $t['note_added_success']; ?></p>
    </div>
</div>

<script>
// 1) Sipariş Detayları Modal'ını Göster
function showOrderDetails(orderId) {
    const modal       = document.getElementById('orderModal');
    const contentDiv  = document.getElementById('orderDetailsContent');
    const mainContent = document.querySelector('.luxury-gradient');
    
    modal.classList.remove('hidden');
    modal.classList.add('fade-in');
    mainContent.classList.add('blur-effect');
    
    contentDiv.innerHTML = `
        <div class="flex justify-center items-center py-12">
            <div class="animate-spin rounded-full h-12 w-12 border-4 border-gray-600 border-t-blue-500"></div>
        </div>
    `;
    
    fetch(`get_order_details.php?id=${orderId}`)
        .then(response => response.text())
        .then(html => {
            contentDiv.innerHTML = html;
        })
        .catch(error => {
            contentDiv.innerHTML = `
                <div class="text-center py-8">
                    <div class="text-red-500 mb-2">
                        <svg class="w-16 h-16 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M12 8v4m0 4h.01
                                     M21 12a9 9 0 11-18 0
                                     9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <div class="text-gray-400">
                        <?php echo $t['order_details_error']; ?>
                    </div>
                </div>
            `;
            console.error('Error:', error);
        });
}

// 2) Modal'ı Kapat
function closeOrderModal() {
    const modal       = document.getElementById('orderModal');
    const mainContent = document.querySelector('.luxury-gradient');
    modal.classList.add('fade-out');
    setTimeout(() => {
        modal.classList.add('hidden');
        modal.classList.remove('fade-out');
        mainContent.classList.remove('blur-effect');
    }, 200);
}

// 3) Yeni Not Ekleme (get_order_details.php içindeki form)
function addNote(form, orderId) {
    const formData = new FormData(form);
    formData.append('order_id', orderId);

    fetch('add_order_note.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showOrderDetails(orderId);
            form.reset();
        } else {
            alert(data.message || '<?php echo $t['note_adding_error']; ?>');
        }
    })
    .catch(error => {
        console.error('Hata:', error);
        alert('<?php echo $t['note_adding_error']; ?>');
    });

    return false;
}

// 4) Not Silme
function deleteNote(noteId, orderId) {
    fetch('delete_order_note.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ note_id: noteId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showOrderDetails(orderId);
        } else {
            alert(data.message || '<?php echo $t['note_deleting_error']; ?>');
        }
    })
    .catch(error => {
        console.error('Hata:', error);
        alert('<?php echo $t['note_deleting_error']; ?>');
    });
}

// Siparişleri İndir Fonksiyonu
function exportOrders() {
    Swal.fire({
        title: '<?php echo $t['export_title']; ?>',
        html: `
            <div class="space-y-4">
                <p class="text-gray-600 mb-4"><?php echo $t['export_subtitle']; ?></p>
                
                <!-- Dosya Adı Input -->
                <div class="text-left">
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        <?php echo $t['export_filename_label']; ?>
                    </label>
                    <input type="text" id="filename" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm 
                        focus:ring-blue-500 focus:border-blue-500" 
                        value="" 
                        placeholder="<?php echo $t['export_filename_placeholder']; ?>">
                </div>

                <!-- Format Seçimi -->
                <div class="text-left mt-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        <?php echo $t['export_format_label']; ?>
                    </label>
                    <div class="grid grid-cols-3 gap-3">
                        <button type="button" onclick="selectFormat('xlsx')" 
                                class="format-btn px-4 py-2 border border-gray-300 rounded-md hover:bg-blue-50 
                                hover:border-blue-500 transition-colors duration-200">
                            <i class="fas fa-file-excel text-green-600 mb-1"></i>
                            <span class="block text-sm"><?php echo $t['export_excel']; ?></span>
                        </button>
                        <button type="button" onclick="selectFormat('csv')" 
                                class="format-btn px-4 py-2 border border-gray-300 rounded-md hover:bg-blue-50 
                                hover:border-blue-500 transition-colors duration-200">
                            <i class="fas fa-file-csv text-blue-600 mb-1"></i>
                            <span class="block text-sm"><?php echo $t['export_csv']; ?></span>
                        </button>
                        <button type="button" onclick="selectFormat('txt')" 
                                class="format-btn px-4 py-2 border border-gray-300 rounded-md hover:bg-blue-50 
                                hover:border-blue-500 transition-colors duration-200">
                            <i class="fas fa-file-alt text-gray-600 mb-1"></i>
                            <span class="block text-sm"><?php echo $t['export_txt']; ?></span>
                        </button>
                    </div>
                </div>
            </div>
        `,
        showCancelButton: true,
        confirmButtonText: '<?php echo $t['export_download']; ?>',
        cancelButtonText: '<?php echo $t['export_cancel']; ?>',
        customClass: {
            container: 'export-modal',
            popup: 'rounded-xl shadow-2xl',
            header: 'border-b pb-4',
            title: 'text-xl font-semibold text-gray-800',
            content: 'pt-4',
            confirmButton: 'bg-blue-600 hover:bg-blue-700 text-white font-medium px-8 py-3 rounded-lg transition-colors duration-200 shadow-lg hover:shadow-blue-500/50 text-base',
            cancelButton: 'bg-gray-200 hover:bg-gray-300 text-gray-700 font-medium px-8 py-3 rounded-lg transition-colors duration-200 shadow-md hover:shadow-gray-400/50 text-base border border-gray-300'
        },
        didOpen: () => {
            // Font Awesome ikonlarını ekle
            const link = document.createElement('link');
            link.href = 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css';
            link.rel = 'stylesheet';
            document.head.appendChild(link);

            // Seçili format için stil
            const buttons = document.querySelectorAll('.format-btn');
            buttons.forEach(btn => {
                btn.addEventListener('click', function() {
                    buttons.forEach(b => b.classList.remove('selected-format'));
                    this.classList.add('selected-format');
                });
            });
        }
    }).then((result) => {
        if (result.isConfirmed) {
            const filename = document.getElementById('filename').value || '<?php echo $t['export_filename_placeholder']; ?>';
            const selectedBtn = document.querySelector('.format-btn.selected-format');
            const format = selectedBtn ? selectedBtn.getAttribute('data-format') : 'xlsx';

            // Mevcut filtreleri al
            const date = document.getElementById('date').value;
            const status = document.getElementById('status').value;
            const service = document.getElementById('service').value;

            // URL oluştur
            let url = `export_orders.php?format=${format}&filename=${encodeURIComponent(filename)}`;
            if (date) url += `&date=${date}`;
            if (status) url += `&status=${status}`;
            if (service) url += `&service=${service}`;

            // İndirme başlatıldı animasyonu
            Swal.fire({
                title: '<?php echo $t['export_download_started']; ?>',
                text: '<?php echo $t['export_download_started_message']; ?>',
                timer: 2000,
                timerProgressBar: true,
                icon: 'success',
                showConfirmButton: false,
                didOpen: () => {
                    Swal.showLoading();
                    setTimeout(() => {
                        window.location.href = url;
                    }, 1000);
                }
            });
        }
    });
}

// Format seçimi için yardımcı fonksiyon
function selectFormat(format) {
    const buttons = document.querySelectorAll('.format-btn');
    buttons.forEach(btn => {
        btn.classList.remove('selected-format');
        if (btn.querySelector('span').textContent.toLowerCase().includes(format)) {
            btn.classList.add('selected-format');
            btn.setAttribute('data-format', format);
        }
    });
}

// Özel stiller ekle
const style = document.createElement('style');
style.textContent = `
    .selected-format {
        background-color: #EBF5FF !important;
        border-color: #3B82F6 !important;
        position: relative;
    }
    .selected-format::after {
        content: '✓';
        position: absolute;
        top: -8px;
        right: -8px;
        background: #3B82F6;
        color: white;
        width: 20px;
        height: 20px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 12px;
        border: 2px solid white;
    }
    .format-btn {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        transition: all 0.2s;
    }
    .format-btn:hover {
        transform: translateY(-2px);
    }
    .format-btn i {
        font-size: 24px;
    }
    .export-modal .swal2-html-container {
        margin: 1em 0 0 0;
    }
`;
document.head.appendChild(style);

// 6) Modal dışında tıklama -> kapatma
window.onclick = function(event) {
    const modal = document.getElementById('orderModal');
    if (event.target === modal) {
        closeOrderModal();
    }
};

// 7) ESC tuşu ile kapatma
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeOrderModal();
    }
});
</script>

<?php include_once '../includes/new-footer.php'; ?>
