<?php
session_start();
include_once '../config/database.php';
include_once '../includes/new-header.php';

// Dil değişkenini tanımla
$lang = $_SESSION['lang'] ?? 'tr';

// Translations dosyasının yolunu tanımla
$translations_file = "../translations/translation_{$lang}.php";

if (file_exists($translations_file)) {
    $translations = require $translations_file;
    $pt = $translations; // array_merge kaldırıldı, direkt atama yapılıyor
} else {
    die("Translation file not found: {$translations_file}");
}

// tStatus değişkenini tanımla
$tStatus = true; // varsayılan olarak aktif

// -----------------------------------------------------------
//   Admin kontrolü (kodunuzun orijinal kısmı başlıyor)
// -----------------------------------------------------------

// Admin kontrolü
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: /health_tourism/login.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

// 1) Okunmamış mesaj sayısı
$query = "SELECT COUNT(*) as unread_messages FROM contact_requests WHERE is_read = 0";
$stmt = $db->prepare($query);
$stmt->execute();
$unread_messages = $stmt->fetch(PDO::FETCH_ASSOC)['unread_messages'] ?? 0;

// 2) Genel istatistikler
$query = "SELECT SUM(total_amount) as total_revenue FROM orders WHERE status = 'completed'";
$stmt = $db->prepare($query);
$stmt->execute();
$revenue = $stmt->fetch(PDO::FETCH_ASSOC)['total_revenue'] ?? 0;

$query = "SELECT COUNT(*) as total_orders FROM orders";
$stmt = $db->prepare($query);
$stmt->execute();
$total_orders = $stmt->fetch(PDO::FETCH_ASSOC)['total_orders'] ?? 0;

$query = "SELECT COUNT(*) as total_users FROM users WHERE role != 'admin'";
$stmt = $db->prepare($query);
$stmt->execute();
$total_users = $stmt->fetch(PDO::FETCH_ASSOC)['total_users'] ?? 0;

// 3) Son 3 sipariş
$query = "SELECT o.*, u.username, u.full_name, s.name as service_name 
          FROM orders o 
          JOIN users u ON o.user_id = u.id 
          JOIN services s ON o.service_id = s.id 
          ORDER BY o.created_at DESC 
          LIMIT 3";
$stmt = $db->prepare($query);
$stmt->execute();
$recent_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 4) Varsayılan son 30 gün
$defaultEnd   = new DateTime();
$defaultStart = clone $defaultEnd;
$defaultStart->modify('-30 days');
$startDate = $defaultStart->format('Y-m-d');
$endDate   = $defaultEnd->format('Y-m-d');

// 5) GET parametresinden date_range alınırsa
if (!empty($_GET['date_range'])) {
    $dateRange = $_GET['date_range']; // "YYYY-MM-DD to YYYY-MM-DD"
    $parts = explode(" to ", $dateRange);
    if (count($parts) === 2) {
        $startDate = $parts[0];
        $endDate   = $parts[1];
    }
}

// DateTime nesneleri
$start = new DateTime($startDate);
$end   = new DateTime($endDate);

// Bitiş < başlangıç ise varsayılan 30 güne dön
if ($end < $start) {
    $start = $defaultStart;
    $end   = $defaultEnd;
}

// 6) Seçilen tarih aralığındaki tüm günler
$dayArray = [];
$temp = clone $start;
while ($temp <= $end) {
    $dayArray[] = $temp->format('Y-m-d');
    $temp->modify('+1 day');
}

// 7) Veritabanından bu aralıktaki sipariş verileri
$query = "
    SELECT DATE_FORMAT(created_at, '%Y-%m-%d') AS day,
           COUNT(*) AS total_orders,
           SUM(total_amount) AS revenue
    FROM orders
    WHERE status = 'completed'
      AND created_at >= :start_date
      AND created_at <= :end_date
    GROUP BY DATE_FORMAT(created_at, '%Y-%m-%d')
    ORDER BY day ASC
";



$stmt = $db->prepare($query);
$stmt->bindParam(':start_date', $startDate);
$stmt->bindParam(':end_date',   $endDate);
$stmt->execute();
$result = $stmt->fetchAll(PDO::FETCH_ASSOC);



// 8) Boş günlere 0 değer atama
$completeData = [];
foreach ($dayArray as $singleDay) {
    $ordersCount = 0;
    $revenueSum  = 0.0;

    foreach ($result as $row) {
        if ($row['day'] === $singleDay) {
            $ordersCount = intval($row['total_orders']);
            $revenueSum  = floatval($row['revenue'] ?? 0);
            break;
        }
    }
    $completeData[] = [
        'day'          => $singleDay,
        'total_orders' => $ordersCount,
        'revenue'      => $revenueSum
    ];
}

// 9) Grafikte gösterilecek diziler
$days      = [];
$revenues  = [];
$ordersArr = [];

foreach ($completeData as $data) {
    $dateObj = new DateTime($data['day']);
    $days[]     = $dateObj->format('d M'); // Ekranda "03 Jan" vb. göster
    $revenues[] = $data['revenue'];
    $ordersArr[] = $data['total_orders'];
}

// Toplam geliri hesapla
$totalRevenue = 0;
foreach ($completeData as $data) {
    $totalRevenue += $data['revenue'];
}

?>

<!-- Tailwind / Flatpickr / Chart.js / SweetAlert2 (CDN) -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/airbnb.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
/* Arkaplan */
.luxury-gradient {
    background: linear-gradient(135deg, #f6f8fc 0%, #eef2f7 100%);
}

/* Stat kartı stilleri */
.stat-card {
    background: rgba(255, 255, 255, 0.9);
    backdrop-filter: blur(20px);
    border: 1px solid rgba(255, 255, 255, 0.8);
    box-shadow: 0 4px 40px rgba(0, 0, 0, 0.06), 0 0 1px rgba(0, 0, 0, 0.1);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}
.stat-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 40px rgba(0, 0, 0, 0.1), 0 0 1px rgba(0, 0, 0, 0.1);
}

/* Grafik kartı */
.chart-card {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(20px);
    border: 1px solid rgba(255, 255, 255, 0.8);
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.08), 0 0 1px rgba(0, 0, 0, 0.1);
}

/* Son Siparişler kartı */
.orders-card {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(20px);
    border: 4px solid rgba(255, 255, 255, 0.85);
    min-height: 455px; /* Burada sabit */
    overflow-y: auto; 
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.08), 0 0 1px rgba(0, 0, 0, 0.1);
}

/* Buton hover animasyonu (Üst menü + diğer butonlar) */
.admin-nav-button {
    box-shadow: 0 4px 10px -2px rgba(19, 182, 204, 0.3); 
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}
.admin-nav-button:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 14px -2px rgba(19, 182, 204, 0.3);
}

/* Modal (Popup) - Animasyon vb. basit kalsın, fade in/out */
.modal {
    position: fixed;
    inset: 0;
    z-index: 50;
    display: none; /* Başlangıçta gizli */
    align-items: center;
    justify-content: center;
}
.modal.show {
    display: flex; /* .show eklenince görünür olsun */
}

.modal .modal-bg {
    position: absolute;
    inset: 0;
    background: rgba(0,0,0,0.5);
    cursor: pointer;
}

/* Modal içerik */
.modal .modal-content {
    position: relative;
    margin: auto;
    background: white;
    border-radius: 0.75rem;
    max-width: 450px;
    width: 90%;
    padding: 2rem;
    z-index: 999;
}

.support-button {
    position: fixed;
    right: 0;
    top: 50%;
    transform: translateY(-50%);
    z-index: 1000;
    transition: all 0.3s ease;
}

.support-button-content {
    position: relative;
    display: flex;
    align-items: center;
}

.support-button-text {
    position: absolute;
    right: 100%;
    background-color: #4F46E5;
    color: white;
    padding: 0.75rem 1.5rem;
    border-radius: 9999px 0 0 9999px;
    white-space: nowrap;
    transform: translateX(100%);
    opacity: 0;
    transition: all 0.5s ease;
    pointer-events: none;
}

.support-button:hover .support-button-text {
    transform: translateX(0);
    opacity: 1;
}

.support-button-icon {
    background-color: #4F46E5;
    color: white;
    padding: 1rem;
    border-radius: 9999px 0 0 9999px;
    cursor: pointer;
    transition: all 0.3s ease;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
}

.support-button:hover .support-button-icon {
    background-color: #4338CA;
    box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
}

/* Ticket kartları için stil */
.ticket-item {
    border: 1px solid rgba(229, 231, 235, 0.5);
    transition: all 0.2s ease;
}

.ticket-item:hover {
    border-color: rgba(79, 70, 229, 0.2);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
}

/* Modal için ek stiller */
.modal-content.max-w-2xl {
    max-width: 42rem;
    margin: 2rem;
}

.custom-scrollbar {
    scrollbar-width: thin;
    scrollbar-color: #E5E7EB transparent;
    scroll-behavior: smooth;
}

.custom-scrollbar::-webkit-scrollbar {
    width: 3px;
    height: 0;
}

.custom-scrollbar::-webkit-scrollbar-track {
    background: #F3F4F6;
    border-radius: 1.5px;
}

.custom-scrollbar::-webkit-scrollbar-thumb {
    background: #E5E7EB;
    border-radius: 1.5px;
    transition: background-color 0.3s ease;
}

.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: #D1D5DB;
}

.custom-scrollbar {
    scroll-behavior: smooth;
    overflow-y: auto;
    scrollbar-gutter: stable;
}

@keyframes pulse-green {
    0% {
        background-color: #10B981;
        box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7);
    }
    70% {
        background-color: #10B981;
        box-shadow: 0 0 0 6px rgba(16, 185, 129, 0);
    }
    100% {
        background-color: #10B981;
        box-shadow: 0 0 0 0 rgba(16, 185, 129, 0);
    }
}

@keyframes pulse-red {
    0% {
        background-color: #EF4444;
        box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.7);
    }
    70% {
        background-color: #EF4444;
        box-shadow: 0 0 0 6px rgba(239, 68, 68, 0);
    }
    100% {
        background-color: #EF4444;
        box-shadow: 0 0 0 0 rgba(239, 68, 68, 0);
    }
}

.status-active {
    animation: pulse-green 2s infinite;
    width: 12px !important;
    height: 12px !important;
}

.status-error {
    animation: pulse-red 2s infinite;
    width: 12px !important;
    height: 12px !important;
}

@keyframes shake {
    0%, 100% { transform: translateX(0); }
    25% { transform: translateX(-5px); }
    75% { transform: translateX(5px); }
}

.shake-animation {
    animation: shake 0.5s ease-in-out;
}

/* Modal Animations */
.modal {
    opacity: 0;
    visibility: hidden;
    transition: all 0.3s ease-in-out;
}

.modal.show {
    opacity: 1;
    visibility: visible;
}

.modal.show .modal-content {
    transform: translateY(0) scale(1);
    opacity: 1;
}

.modal .modal-content {
    transform: translateY(-20px) scale(0.95);
    opacity: 0;
    transition: all 0.3s ease-in-out;
}

/* Status Colors */
#ticketStatus.status-new {
    @apply bg-blue-100 text-blue-800;
}

#ticketStatus.status-pending {
    @apply bg-yellow-100 text-yellow-800;
}

#ticketStatus.status-resolved {
    @apply bg-green-100 text-green-800;
}

#ticketStatus.status-closed {
    @apply bg-gray-100 text-gray-800;
}

/* Priority Colors */
.priority-high {
    @apply text-red-600;
}

.priority-medium {
    @apply text-yellow-600;
}

.priority-low {
    @apply text-green-600;
}

/* Custom Scrollbar */
.modal-content {
    max-height: 90vh;
    overflow-y: auto;
}

.modal-content::-webkit-scrollbar {
    width: 8px;
}

.modal-content::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 4px;
}

.modal-content::-webkit-scrollbar-thumb {
    background: #cbd5e0;
    border-radius: 4px;
}

.modal-content::-webkit-scrollbar-thumb:hover {
    background: #a0aec0;
}
</style>

<div class="luxury-gradient min-h-screen pt-24 pb-12">
    <div class="max-w-7xl mx-auto px-4">
        <!-- Dashboard Header -->
        <div class="flex items-center justify-between mb-12">
            <div>
                <!-- "Yönetim Paneli" => $pt['admin_panel'] -->
                <h1 class="text-4xl font-light text-gray-800 mb-2">
                    <?php echo $pt['admin_panel']; ?>
                </h1>
                <!-- "Sistemin genel durumunu ve istatistiklerini görüntüleyin" => $pt['admin_subtitle'] -->
                <p class="text-gray-600">
                    <?php echo $pt['admin_subtitle']; ?>
                </p>
            </div>
            
            <!-- Üst Menüler -->
            <div class="flex space-x-4">
                <!-- Mesajlar Butonu -->
                <a href="messages.php"
                   class="admin-nav-button flex items-center px-6 py-3
                          bg-amber-200 text-black 
                          border border-purple-500 
                          rounded-xl hover:shadow-lg relative">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                    </svg>
                    <!-- "Mesajlar" => $pt['messages'] -->
                    <?php echo $pt['messages']; ?>
                    <?php if ($unread_messages > 0): ?>
                        <span class="absolute -top-2 -right-2 bg-red-500 text-white text-xs font-bold px-2 py-1 rounded-full">
                            <?php echo $unread_messages; ?>
                        </span>
                    <?php endif; ?>
                </a>

                <a href="manage_services.php"
                   class="admin-nav-button flex items-center px-6 py-3
                          bg-amber-200 text-black
                          border border-purple-500
                          rounded-xl hover:shadow-lg">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                    </svg>
                    <!-- "Hizmetleri Yönet" => $pt['manage_services'] -->
                    <?php echo $pt['manage_services']; ?>
                </a>

                <a href="manage_doctors.php"
                   class="admin-nav-button flex items-center px-6 py-3
                          bg-amber-200 text-black
                          border border-purple-500
                          rounded-xl hover:shadow-lg">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                    </svg>
                    <!-- "Doktorları Yönet" => $pt['manage_doctors'] -->
                    <?php echo $pt['manage_doctors']; ?>
                </a>

                <a href="manage_users.php"
                   class="admin-nav-button flex items-center px-6 py-3
                          bg-amber-200 text-black
                          border border-purple-500
                          rounded-xl hover:shadow-lg">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                    </svg>
                    <!-- "Müşterileri Yönet" => $pt['manage_users'] -->
                    <?php echo $pt['manage_users']; ?>
                </a>
            </div>
        </div>
    

        <!-- Stats Grid -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8 mb-12">
            <!-- Toplam Kazanç -->
            <div class="stat-card rounded-2xl p-6">
                <div class="flex items-center space-x-4">
                    <div class="w-10 h-10 bg-green-100 rounded-xl flex items-center justify-center">
                        <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <div>
                        <!-- "Toplam Kazanç" => $pt['total_revenue'] -->
                        <p class="text-sm font-medium text-gray-500"><?php echo $pt['total_revenue']; ?></p>
                        <h3 class="text-2xl font-light text-gray-800">
                            <!-- Örneğin: ₺123.45 -->
                            <?php echo $pt['currency_symbol'] . number_format($revenue, 2); ?>
                        </h3>
                    </div>
                </div>
            </div>

            <!-- Toplam Sipariş -->
            <div class="stat-card rounded-2xl p-6">
                <div class="flex items-center space-x-4">
                    <div class="w-10 h-10 bg-blue-100 rounded-xl flex items-center justify-center">
                        <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
                        </svg>
                    </div>
                    <div>
                        <!-- "Toplam Sipariş" => $pt['total_orders'] -->
                        <p class="text-sm font-medium text-gray-500"><?php echo $pt['total_orders']; ?></p>
                        <h3 class="text-2xl font-light text-gray-800">
                            <?php echo number_format($total_orders); ?>
                        </h3>
                    </div>
                </div>
            </div>

            <!-- Toplam Kullanıcı -->
            <div class="stat-card rounded-2xl p-6">
                <div class="flex items-center space-x-4">
                    <div class="w-10 h-10 bg-purple-100 rounded-xl flex items-center justify-center">
                        <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                        </svg>
                    </div>
                    <div>
                        <!-- "Toplam Kullanıcı" => $pt['total_users'] -->
                        <p class="text-sm font-medium text-gray-500"><?php echo $pt['total_users']; ?></p>
                        <h3 class="text-2xl font-light text-gray-800">
                            <?php echo number_format($total_users); ?>
                        </h3>
                    </div>
                </div>
            </div>
        </div>

        <!-- Ana İçerik Grid -->
        <div class="grid grid-cols-12 gap-8">
            <!-- Grafik Kartı -->
            <div class="col-span-12 lg:col-span-8">
                <div class="chart-card rounded-2xl p-8">
                    <div class="flex justify-between items-center mb-8">
                        <!-- "Seçili Tarih Aralığı Analiz" => $pt['analysis_for_selected_period'] -->
                        <h3 class="text-xl font-light text-gray-800">
                            <?php 
                            echo $pt['analysis_for_selected_period']; 
                            echo ' - ' . $pt['currency_symbol'] . number_format($totalRevenue, 2);
                            ?>
                        </h3>
                        <div class="flex space-x-2 items-center">
                            <!-- Gelir / Sipariş Butonları -->
                            <!-- "Gelir" => $pt['revenue'] -->
                            <button class="chart-type-btn active px-4 py-2 rounded-lg bg-indigo-600 text-white text-sm" 
                                    data-type="revenue">
                                <?php echo $pt['revenue']; ?>
                            </button>
                            <!-- "Sipariş" => $pt['orders'] -->
                            <button class="chart-type-btn px-4 py-2 rounded-lg bg-gray-200 text-gray-600 text-sm" 
                                    data-type="orders">
                                <?php echo $pt['orders']; ?>
                            </button>

                            <!-- Tarih Popup Butonu -->
                            <button id="openDateModal" class="px-3 py-2 rounded-md bg-gray-100 text-gray-600 hover:bg-gray-200 transition-colors relative">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                          d="M8 7V3m8 4V3M3 11h18M5 19h14a2 2 0 002-2v-5H3v5a2 2 0 002 2z"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                    <div class="h-80">
                        <canvas id="revenueChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Son Siparişler -->
            <div class="col-span-12 lg:col-span-4">
                <div class="orders-card rounded-2xl p-8 overflow-y-auto">
                    <div class="flex justify-between items-center mb-8">
                        <!-- "Son Siparişler" => $pt['recent_orders'] -->
                        <h3 class="text-xl font-light text-gray-800"><?php echo $pt['recent_orders']; ?></h3>
                        <!-- "Tümünü Gör" => $pt['see_all'] -->
                        <a href="orders.php" 
                           class="px-4 py-2 bg-gradient-to-r from-indigo-600 to-indigo-700 
                                  text-white rounded-lg hover:shadow-lg transition-all duration-300">
                            <?php echo $pt['see_all']; ?>
                        </a>
                    </div>
                    <div class="space-y-4">
                        <?php foreach ($recent_orders as $order): ?>
                        <div class="p-3 rounded-xl bg-gray-50 hover:bg-gray-100 transition-all duration-300">
                            <div class="flex justify-between items-start w-full">
                                <div class="flex flex-col">
                                    <p class="font-medium text-gray-800 mb-1">
                                        <?php echo htmlspecialchars($order['full_name']); ?>
                                    </p>
                                    <p class="text-sm text-gray-500">
                                        <?php echo htmlspecialchars($order['service_name']); ?>
                                    </p>
                                </div>
                                <div class="flex flex-col items-end">
                                    <p class="font-medium text-indigo-600 mb-1">
                                        <?php 
                                        echo $pt['currency_symbol'] . number_format($order['total_amount'], 2); 
                                        ?>
                                    </p>
                                    <p class="text-sm text-gray-500">
                                        <?php echo date('d.m.Y', strtotime($order['created_at'])); ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        
        <div class="col-span-12 lg:col-span-8">
        <div class="bg-white rounded-2xl p-4 shadow-lg border border-gray-100 h-28">
            <!-- Kart Başlığı -->
            <!-- Kart Başlığı kısmında -->
            <div class="flex items-center justify-between mb-2">
                <h3 class="text-sm font-medium text-gray-700"><?php echo $pt['ticket_responses']; ?></h3>
                <div class="flex items-center space-x-3"> <!-- space-x-2'yi space-x-3 yaptık -->
                    <span class="text-sm text-gray-500">tStatus</span> <!-- text-xs'i text-sm yaptık -->
                    <span id="tStatusIndicator" class="w-3 h-3 rounded-full"></span> <!-- w-2 h-2'yi w-3 h-3 yaptık -->
                </div>
            </div>
            
            <?php
            $stmt = $db->prepare("
                SELECT 
                    st.id,
                    st.subject,
                    st.created_at,
                    st.is_read,
                    CASE WHEN sr.id IS NOT NULL THEN true ELSE false END as has_response
                FROM support_tickets st
                LEFT JOIN support_responses sr ON st.id = sr.ticket_id
                WHERE st.user_id = ?
                ORDER BY 
                    CASE WHEN sr.id IS NOT NULL THEN 1 ELSE 0 END DESC,
                    st.is_read ASC,
                    st.created_at DESC
                LIMIT 99999");
            $stmt->execute([$_SESSION['user_id']]);
            $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);
            ?>
            
            <div class="grid grid-cols-3 gap-1_5 overflow-y-auto overflow-x-hidden h-14 custom-scrollbar" 
                style="scroll-behavior: smooth;">
                <?php if ($tickets && count($tickets) > 0): ?>
                    <?php foreach ($tickets as $ticket): ?>
                        <div 
                            role="button"
                            tabindex="0"
                            onclick="showTicketDetails(<?php echo $ticket['id']; ?>)"
                            onkeypress="if(event.key === 'Enter') showTicketDetails(<?php echo $ticket['id']; ?>)"
                            class="flex items-center justify-between p-1.5 rounded-lg select-none 
                                transition-all duration-300 hover:bg-gray-50 hover:shadow-sm 
                                active:scale-98 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500
                                border cursor-pointer
                                <?php 
                                if ($ticket['has_response']) {
                                    echo $ticket['is_read'] ? 'bg-red-50 border-red-100' : 'bg-green-50 border-green-100';
                                } else {
                                    echo 'bg-amber-50 border-amber-100';
                                }
                                ?>"
                        >
                            <div class="flex items-center space-x-2 min-w-0">
                                <span class="flex-shrink-0 w-1.5 h-1.5 rounded-full
                                    <?php 
                                    if ($ticket['has_response']) {
                                        echo $ticket['is_read'] ? 'bg-red-400' : 'bg-green-400';
                                    } else {
                                        echo 'bg-amber-400';
                                    }
                                    ?>">
                                </span>
                                <div class="flex flex-col min-w-0">
                                    <span class="text-xs font-medium truncate
                                        <?php 
                                        if ($ticket['has_response']) {
                                            echo $ticket['is_read'] ? 'text-red-600' : 'text-green-600';
                                        } else {
                                            echo 'text-amber-600';
                                        }
                                        ?>">
                                        <?php echo htmlspecialchars($ticket['subject']); ?>
                                    </span>
                                    <span class="text-[10px] text-gray-400">
                                        <?php echo date('d.m.Y H:i', strtotime($ticket['created_at'])); ?>
                                    </span>
                                </div>
                            </div>
                            
                            <?php if ($ticket['has_response'] && $ticket['is_read']): ?>
                                <svg class="w-3 h-3 text-red-400 ml-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                            <?php elseif ($ticket['has_response']): ?>
                                <svg class="w-3 h-3 text-green-400 ml-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                </svg>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-xs text-gray-500 text-center"><?php echo $pt['no_tickets_yet']; ?></div>
                <?php endif; ?>
            </div>
        </div>
        </div>


        <!-- Support Butonu -->
        <div class="col-span-12 lg:col-span-4">
        <div class="bg-white rounded-2xl p-4 shadow-lg border border-gray-100 h-28"> <!-- h-32 eklendi -->
            <div id="openSupportModal" class="flex items-center justify-between cursor-pointer hover:bg-gray-50 p-3 rounded-xl transition-all duration-300">
                <div class="flex items-center space-x-4">
                    <div class="w-10 h-10 bg-indigo-100 rounded-xl flex items-center justify-center">
                        <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-base font-medium text-gray-800 mb-0.5"><?php echo $pt['need_support']; ?></p>
                        <p class="text-sm text-gray-500"><?php echo $pt['always_with_you']; ?></p>
                    </div>
                </div>
                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
            </div>
        </div>
    </div>

    
</div>



<!-- Support Modal -->
<div id="supportModal" class="modal">
    <div class="modal-bg"></div>
    <div class="modal-content">
        <!-- Normal Form -->
        <div id="supportForm">
            <button id="closeSupportModal" class="absolute top-3 right-3 text-gray-400 hover:text-gray-600 transition-colors">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
            
            <h2 class="text-xl font-semibold text-gray-800 mb-6"><?php echo $pt['create_support_ticket_ticket']; ?></h2>
            
            <form id="supportTicketForm" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1"><?php echo $pt['subject_ticket']; ?></label>
                    <input type="text" id="ticketSubject" name="subject" required
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1"><?php echo $pt['priority_ticket']; ?></label>
                    <select id="ticketPriority" name="priority"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                        <option value="low"><?php echo $pt['priority_low_ticket']; ?></option>
                        <option value="medium"><?php echo $pt['priority_medium_ticket']; ?></option>
                        <option value="high"><?php echo $pt['priority_high_ticket']; ?></option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1"><?php echo $pt['message_ticket']; ?></label>
                    <textarea id="ticketMessage" name="message" required
                              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent h-32 resize-none"></textarea>
                </div>

                <div class="flex justify-end space-x-3 pt-4">
                    <button type="button" id="cancelSupport"
                            class="px-4 py-2 text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors">
                        <?php echo $pt['cancel_ticket']; ?>
                    </button>
                    <button type="submit"
                            class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors flex items-center"
                            id="submitSupport">
                        <span><?php echo $pt['send_ticket']; ?></span>
                    </button>
                </div>
            </form>
        </div>

        <!-- Success Message -->
        <div id="successMessage" class="hidden">
            <div class="flex flex-col items-center justify-center py-8">
                <svg class="w-16 h-16 text-green-500 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <h3 class="text-xl font-medium text-gray-800 mb-2"><?php echo $pt['message_sent_ticket']; ?></h3>
                <p class="text-gray-600"><?php echo $pt['will_reply_soon_ticket']; ?></p>
            </div>
        </div>
    </div>
</div>


<!-- Ticket Details Modal -->
<div id="ticketDetailsModal" class="modal">
    <div class="modal-bg"></div>
    <div class="modal-content max-w-3xl w-full bg-white rounded-2xl shadow-2xl transform transition-all">
        <button id="closeTicketModal" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600 transition-colors p-2 rounded-full hover:bg-gray-100">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
        
        <div class="p-8 space-y-6">
            <!-- Header -->
            <div class="flex items-center justify-between border-b pb-4">
                <div class="space-y-1">
                    <h2 id="ticketSubject" class="text-2xl font-bold text-gray-800"></h2>
                    <div class="flex items-center space-x-4 text-sm text-gray-500">
                        <div class="flex items-center">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <span id="ticketDate"></span>
                        </div>
                        <div class="flex items-center">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <span id="ticketPriority"></span>
                        </div>
                    </div>
                </div>
                <span id="ticketStatus" class="px-4 py-2 rounded-full text-sm font-semibold"></span>
            </div>

            <!-- Ticket Content -->
            <div class="space-y-8">
                <!-- Customer Message -->
                <div class="bg-indigo-50 rounded-xl p-6 space-y-4">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-semibold text-indigo-900">Müşteri Mesajı</h3>
                        <span class="text-sm text-indigo-600 font-medium px-3 py-1 bg-indigo-100 rounded-full">Gönderen</span>
                    </div>
                    <p id="customerMessage" class="text-gray-700 leading-relaxed"></p>
                </div>

                <!-- Response Section -->
                <div id="responseSection" class="bg-green-50 rounded-xl p-6 space-y-4 hidden">
                    <div class="flex items-center justify-between">
                        <div class="space-y-1">
                            <h3 class="text-lg font-semibold text-green-900">Destek Yanıtı</h3>
                            <p id="responseAuthor" class="text-sm text-green-700"></p>
                        </div>
                        <div class="flex flex-col items-end">
                            <span id="responseTime" class="text-sm text-green-600"></span>
                            <span id="responseDevice" class="text-xs text-green-500 mt-1"></span>
                        </div>
                    </div>
                    <div class="bg-white/50 rounded-lg p-4">
                        <p id="responseMessage" class="text-gray-700 leading-relaxed"></p>
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <div class="flex justify-end space-x-4 pt-4 border-t">
                <button id="markAsReadBtn" class="px-6 py-2.5 rounded-xl font-medium transition-all duration-300 flex items-center space-x-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    <span>Okundu Olarak İşaretle</span>
                </button>
                <button onclick="closeTicketModal()" class="px-6 py-2.5 bg-gray-100 text-gray-700 rounded-xl font-medium hover:bg-gray-200 transition-all duration-300">
                    Kapat
                </button>
            </div>
        </div>
    </div>
</div>




<!-- Modal (Popup) -->
<div class="modal" id="dateModal">
    <div class="modal-bg"></div>
    <div class="modal-content">
        <!-- Kapat Butonu -->
        <button id="closeDateModal" class="absolute top-3 right-3 text-gray-400 hover:text-gray-600 transition-colors">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
        
        <!-- "Tarih Aralığı Seçin" => $pt['choose_date_range'] -->
        <h2 class="text-xl font-semibold text-gray-800 mb-4"><?php echo $pt['choose_date_range']; ?></h2>
        <form method="GET">
            <div class="mb-6">
                <!-- "Tarih Aralığı" => $pt['date_range'] -->
                <label class="block text-sm font-medium text-gray-700 mb-1" for="daterange">
                    <?php echo $pt['date_range']; ?>
                </label>
                <input type="text" 
                       id="daterange" 
                       name="date_range"
                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-1 focus:ring-indigo-500"
                       placeholder="<?php echo $pt['date_range']; ?>..."
                       readonly />
            </div>
            <!-- "Filtre Uygula" => $pt['apply_filter'] -->
            <button type="submit"
                    id="applyFilter"
                    class="px-6 py-2 rounded-lg bg-indigo-600 text-white hover:bg-indigo-700 transition-all">
                <?php echo $pt['apply_filter']; ?>
            </button>
        </form>
    </div>
</div>


<!-- Success/Error Messages -->
<?php if (isset($_SESSION['admin_success'])): ?>
    <div id="successAlert" class="fixed top-24 right-4 bg-green-500 text-white px-6 py-3 rounded-xl shadow-lg transform transition-all duration-500 translate-x-full">
        <?php 
        echo $_SESSION['admin_success'];
        unset($_SESSION['admin_success']);
        ?>
    </div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    let eventSource = new EventSource('check_emails.php');
    const tStatusIndicator = document.getElementById('tStatusIndicator');
    
    eventSource.onopen = function() {
        tStatusIndicator.classList.add('status-active');
        tStatusIndicator.classList.remove('status-error');
    };

    eventSource.onerror = function() {
        tStatusIndicator.classList.remove('status-active');
        tStatusIndicator.classList.add('status-error');
    };
    
    eventSource.onmessage = function(event) {
        const data = JSON.parse(event.data);
        
        if (data.error) {
            tStatusIndicator.classList.remove('status-active');
            tStatusIndicator.classList.add('status-error');
        } else {
            tStatusIndicator.classList.add('status-active');
            tStatusIndicator.classList.remove('status-error');

            // Açık olan ticket modalını güncelle
            const ticketModal = document.getElementById('ticketDetailsModal');
            if (ticketModal && ticketModal.classList.contains('show')) {
                const ticketId = ticketModal.dataset.ticketId;
                if (ticketId) {
                    // AJAX ile ticket detaylarını güncelle
                    fetch(`get_ticket_details.php?id=${ticketId}`)
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                updateTicketModal(data);
                            }
                        })
                        .catch(error => console.error('Error:', error));
                }
            }

            // Ticket listesini güncelle
            if (data.tickets) {
                updateTicketList(data.tickets);
            }
        }
    };

    // Sayfa kapanırken bağlantıyı kapat
    window.addEventListener('beforeunload', function() {
        eventSource.close();
    });
});

// Ticket modalını güncelleyen yeni fonksiyon
function updateTicketModal(data) {
    const subjectEl = document.getElementById('ticketSubject');
    const dateEl = document.getElementById('ticketDate');
    const statusEl = document.getElementById('ticketStatus');
    const priorityEl = document.getElementById('ticketPriority');
    const customerMessageEl = document.getElementById('customerMessage');
    const responseMessage = document.getElementById('responseMessage');
    const responseAuthor = document.getElementById('responseAuthor');
    const responseTime = document.getElementById('responseTime');
    const responseDevice = document.getElementById('responseDevice');
    const responseSection = document.getElementById('responseSection');
    const markAsReadBtn = document.getElementById('markAsReadBtn');

    // Ana bilgiler
    subjectEl.textContent = data.ticket.subject;
    dateEl.textContent = new Date(data.ticket.created_at).toLocaleString('tr-TR');
    customerMessageEl.textContent = data.ticket.message;
    priorityEl.textContent = getPriorityText(data.ticket.priority);

    // Durum göstergesi
    if (data.response) {
        if (data.ticket.is_read) {
            statusEl.textContent = pt.read;
            statusEl.className = 'px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-800';
        } else {
            statusEl.textContent = pt.new_response;
            statusEl.className = 'px-3 py-1 rounded-full text-sm font-medium bg-yellow-100 text-yellow-800';
        }

        // Ticket başlığını oluştur
        const ticketHeader = `[Ticket #${data.ticket.id}] [${getPriorityText(data.ticket.priority)}] ${data.ticket.subject}`;
        responseAuthor.textContent = ticketHeader;
        
        // Parse email response
        const parsedResponse = parseEmailResponse(data.response.message);
        responseMessage.textContent = parsedResponse.message;
        responseTime.textContent = parsedResponse.time;
        responseDevice.textContent = parsedResponse.device;
        responseSection.classList.remove('hidden');

        // Yeni yanıt geldiğinde bildirim göster
        if (!data.ticket.is_read) {
            Swal.fire({
                title: pt.new_response,
                text: `${data.ticket.subject} ${pt.received_new_response}`,
                icon: 'info',
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 3000,
                timerProgressBar: true
            });
        }

        // Okundu/Okunmadı butonu
        markAsReadBtn.classList.remove('hidden');
        updateMarkAsReadButton(data.ticket.id, data.ticket.is_read);
    } else {
        statusEl.textContent = pt.waiting;
        statusEl.className = 'px-3 py-1 rounded-full text-sm font-medium bg-gray-100 text-gray-800';
        responseSection.classList.add('hidden');
        markAsReadBtn.classList.add('hidden');
    }
}

// Okundu/Okunmadı butonunu güncelleyen yardımcı fonksiyon
function updateMarkAsReadButton(ticketId, isRead) {
    const markAsReadBtn = document.getElementById('markAsReadBtn');
    if (isRead) {
        markAsReadBtn.innerHTML = `
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                      d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8"/>
            </svg>
            <span>${pt.mark_as_unread}</span>
        `;
        markAsReadBtn.className = 'px-4 py-2 bg-amber-100 text-amber-700 rounded-lg hover:bg-amber-200 transition-colors flex items-center space-x-2';
        markAsReadBtn.onclick = () => markAsRead(ticketId, 'unread');
    } else {
        markAsReadBtn.innerHTML = `
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                      d="M5 13l4 4L19 7"/>
            </svg>
            <span>${pt.mark_as_read}</span>
        `;
        markAsReadBtn.className = 'px-4 py-2 bg-green-100 text-green-700 rounded-lg hover:bg-green-200 transition-colors flex items-center space-x-2';
        markAsReadBtn.onclick = () => markAsRead(ticketId, 'read');
    }
}

// Ticket listesini güncellemek için yeni fonksiyon
function updateTicketList(tickets) {
    // Seçiciyi düzeltelim - tam class string'i kullanarak
    const ticketContainer = document.querySelector('.grid.grid-cols-3.gap-1_5.overflow-y-auto.overflow-x-hidden.h-14.custom-scrollbar');
    
    if (!ticketContainer) {
        console.error('Ticket container bulunamadı');
        return;
    }
    
    ticketContainer.innerHTML = tickets.map(ticket => `
        <div 
            data-ticket-id="${ticket.id}"
            role="button"
            tabindex="0"
            onclick="showTicketDetails(${ticket.id})"
            onkeypress="if(event.key === 'Enter') showTicketDetails(${ticket.id})"
            class="ticket-item flex items-center justify-between p-1.5 rounded-lg select-none 
                transition-all duration-300 hover:bg-gray-50 hover:shadow-sm 
                active:scale-98 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500
                border cursor-pointer
                ${ticket.has_response ? 
                    (ticket.is_read ? 'bg-red-50 border-red-100' : 'bg-green-50 border-green-100') 
                    : 'bg-amber-50 border-amber-100'}"
        >
            <div class="flex items-center space-x-2 min-w-0">
                <span class="flex-shrink-0 w-1.5 h-1.5 rounded-full
                    ${ticket.has_response ? 
                        (ticket.is_read ? 'bg-red-400' : 'bg-green-400') 
                        : 'bg-amber-400'}">
                </span>
                <div class="flex flex-col min-w-0">
                    <span class="text-xs font-medium truncate
                        ${ticket.has_response ? 
                            (ticket.is_read ? 'text-red-600' : 'text-green-600') 
                            : 'text-amber-600'}">
                        ${ticket.subject}
                    </span>
                    <span class="text-[10px] text-gray-400">
                        ${ticket.created_at}
                    </span>
                </div>
            </div>
            
            ${ticket.has_response ? 
                (ticket.is_read ? 
                    `<svg class="w-3 h-3 text-red-400 ml-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>`
                    : 
                    `<svg class="w-3 h-3 text-green-400 ml-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                    </svg>`
                ) 
                : ''}
        </div>
    `).join('');
}

// showTicketDetails fonksiyonunu güncelleyelim
async function showTicketDetails(ticketId) {
    try {
        const response = await fetch(`get_ticket_details.php?id=${ticketId}`);
        const data = await response.json();

        if (data.success) {
            const modal = document.getElementById('ticketDetailsModal');
            modal.dataset.ticketId = ticketId;

            // Modal içeriğini güncelle
            updateTicketModal(data);

            // Modalı göster
            modal.classList.add('show');
        } else {
            Swal.fire({
                icon: 'error',
                title: pt.error_ticket,
                text: data.message || pt.something_went_wrong_ticket,
                confirmButtonColor: '#4F46E5'
            });
        }
    } catch (error) {
        console.error('Error:', error);
        Swal.fire({
            icon: 'error',
            title: pt.error_ticket,
            text: pt.something_went_wrong_ticket,
            confirmButtonColor: '#4F46E5'
        });
    }
}

const pt = <?php echo json_encode([
    'read' => $pt['read'] ?? 'Read',
    'new_response' => $pt['new_response'] ?? 'New Response',
    'waiting' => $pt['waiting'] ?? 'Waiting',
    'mark_as_read' => $pt['mark_as_read'] ?? 'Mark as Read',
    'mark_as_unread' => $pt['mark_as_unread'] ?? 'Mark as Unread',
    'staff_response' => $pt['staff_response'] ?? 'Staff Response',
    'staff_response_prefix' => $pt['staff_response_prefix'] ?? 'Response: ',
    'customer_message' => $pt['customer_message'] ?? 'Customer Message',
    'error_ticket' => $pt['error_ticket'] ?? 'Error',
    'success_ticket' => $pt['success_ticket'] ?? 'Success',
    'something_went_wrong_ticket' => $pt['something_went_wrong_ticket'] ?? 'Something went wrong',
    'priority_low' => $pt['priority_low'] ?? 'Low Priority',
    'priority_medium' => $pt['priority_medium'] ?? 'Medium Priority',
    'priority_high' => $pt['priority_high'] ?? 'High Priority',
    'received_new_response' => $pt['received_new_response'] ?? 'received a new response',
    'success' => $pt['success'] ?? 'Success',
    'date_range_saved' => $pt['date_range_saved'] ?? 'Date range saved',
    'sending_ticket' => $pt['sending_ticket'] ?? 'Sending'
]); ?>;

// Priority text converter with language support
function getPriorityText(priority) {
    const priorities = {
        'low': pt.priority_low,
        'medium': pt.priority_medium,
        'high': pt.priority_high
    };
    return priorities[priority] || priority;
}



function parseEmailResponse(responseText) {
    // E-posta satırlarını bölelim ve boş olmayan satırları alalım
    const lines = responseText.split('\n').map(line => line.trim()).filter(line => line);
    
    // Yetkilinin mesajını bulmak için ilk satırı alalım (genelde asıl yanıt buradadır)
    let message = '';
    let deviceInfo = '';
    
    // Satırları dolaş
    for (let i = 0; i < lines.length; i++) {
        const line = lines[i];
        
        // iPhone bilgisini bul
        if (line.includes("iPhone'umdan gönderildi")) {
            deviceInfo = line;
            continue;
        }
        
        // Eğer bu satır bir yanıtsa ve önceki mesaj detayları değilse
        if (!line.includes('HealthTurkey Support') && 
            !line.includes('Yeni Destek Talebi') &&
            !line.includes('Gönderen:') &&
            !line.includes('Aciliyet:') &&
            !line.includes('Konu:') &&
            !line.includes('Mesaj:') &&
            !line.includes('[Ticket #') &&
            !line.includes('agaskmag@gmail.com') &&
            !line.includes('şunları yazdı')) {
            
            // İlk anlamlı mesaj satırını al
            if (!message) {
                message = line;
                break; // İlk mesajı bulduktan sonra döngüden çık
            }
        }
    }

    return {
        author: '[Ticket #] Yetkili Yanıtı',
        time: lines.find(line => /\d{1,2}\.\d{1,2}\.\d{4}/.test(line)) || '',
        device: deviceInfo,
        message: message || 'Yanıt bulunamadı'
    };
}



// Okundu olarak işaretleme
async function markAsRead(ticketId, status = 'read') {
    try {
        // İşlem sırasında butonu devre dışı bırak
        const markAsReadBtn = document.getElementById('markAsReadBtn');
        if (markAsReadBtn) {
            markAsReadBtn.disabled = true;
        }

        const response = await fetch('mark_ticket_read.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ 
                ticket_id: ticketId,
                status: status 
            })
        });

        const data = await response.json();

        if (data.success) {
            // Mevcut ticket kartını bul ve güncelle
            const ticketCard = document.querySelector(`[onclick*="showTicketDetails(${ticketId})"]`);
            if (ticketCard) {
                if (status === 'read') {
                    ticketCard.classList.remove('bg-green-50');
                    ticketCard.classList.add('bg-red-50');
                    ticketCard.querySelector('.rounded-full').classList.remove('bg-green-400');
                    ticketCard.querySelector('.rounded-full').classList.add('bg-red-400');
                    ticketCard.querySelector('.text-xs.font-medium').classList.remove('text-green-600');
                    ticketCard.querySelector('.text-xs.font-medium').classList.add('text-red-600');
                } else {
                    ticketCard.classList.remove('bg-red-50');
                    ticketCard.classList.add('bg-green-50');
                    ticketCard.querySelector('.rounded-full').classList.remove('bg-red-400');
                    ticketCard.querySelector('.rounded-full').classList.add('bg-green-400');
                    ticketCard.querySelector('.text-xs.font-medium').classList.remove('text-red-600');
                    ticketCard.querySelector('.text-xs.font-medium').classList.add('text-green-600');
                }
            }

            // Modal içindeki durumu güncelle
            const statusEl = document.getElementById('ticketStatus');
            if (statusEl) {
                if (status === 'read') {
                    statusEl.textContent = pt.read;
                    statusEl.className = 'px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-800';
                    if (markAsReadBtn) {
                        markAsReadBtn.innerHTML = `
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                      d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8"/>
                            </svg>
                            <span>${pt.mark_as_unread}</span>
                        `;
                        markAsReadBtn.className = 'px-4 py-2 bg-amber-100 text-amber-700 rounded-lg hover:bg-amber-200 transition-colors flex items-center space-x-2';
                        markAsReadBtn.onclick = () => markAsRead(ticketId, 'unread');
                    }
                } else {
                    statusEl.textContent = pt.new_response;
                    statusEl.className = 'px-3 py-1 rounded-full text-sm font-medium bg-yellow-100 text-yellow-800';
                    if (markAsReadBtn) {
                        markAsReadBtn.innerHTML = `
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                      d="M5 13l4 4L19 7"/>
                            </svg>
                            <span>${pt.mark_as_read}</span>
                        `;
                        markAsReadBtn.className = 'px-6 py-4 bg-green-100 text-green-700 rounded-lg hover:bg-green-200 transition-colors flex items-center space-x-2';
                        markAsReadBtn.onclick = () => markAsRead(ticketId, 'read');
                    }
                }
            }
        }
    } catch (error) {
        console.error('Error:', error);
    } finally {
        // İşlem bittiğinde butonu tekrar aktif et
        const markAsReadBtn = document.getElementById('markAsReadBtn');
        if (markAsReadBtn) {
            markAsReadBtn.disabled = false;
        }
    }
}

// Modal kapatma
document.getElementById('closeTicketModal').addEventListener('click', () => {
    document.getElementById('ticketDetailsModal').classList.remove('show');
});



const supportModal = document.getElementById('supportModal');
const openSupportModal = document.getElementById('openSupportModal');
const closeSupportModal = document.getElementById('closeSupportModal');
const supportForm = document.getElementById('supportForm');
const successMessage = document.getElementById('successMessage');
const cancelSupport = document.getElementById('cancelSupport');
const supportTicketForm = document.getElementById('supportTicketForm');
const submitSupport = document.getElementById('submitSupport');

function openModal() {
    supportModal.classList.add('show');
    // Form ve success message'ı sıfırla
    supportForm.classList.remove('hidden');
    successMessage.classList.add('hidden');
    supportTicketForm.reset();
}

function closeModal() {
    supportModal.classList.remove('show');
}

openSupportModal.addEventListener('click', openModal);
closeSupportModal.addEventListener('click', closeModal);
cancelSupport.addEventListener('click', closeModal);
supportModal.querySelector('.modal-bg').addEventListener('click', closeModal);

supportTicketForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    
    // Submit butonunu loading durumuna getir
    const originalButtonText = submitSupport.innerHTML;
    submitSupport.innerHTML = `
        <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
        <?php echo $pt['sending_ticket']; ?>...
    `;
    submitSupport.disabled = true;

    const formData = {
        subject: document.getElementById('ticketSubject').value,
        priority: document.getElementById('ticketPriority').value,
        message: document.getElementById('ticketMessage').value
    };

    try {
        const response = await fetch('send_ticket.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(formData)
        });

        const data = await response.json();

        if (data.success) {
            // Form'u gizle ve success message'ı göster
            supportForm.classList.add('hidden');
            successMessage.classList.remove('hidden');

            // 2 saniye sonra modalı kapat
            setTimeout(() => {
                closeModal();
                window.location.reload();
            }, 2000);
        } else {
            // Hata durumunda SweetAlert2 ile göster
            Swal.fire({
                icon: 'error',
                title: '<?php echo $pt['error_ticket']; ?>',
                text: data.message || '<?php echo $pt['something_went_wrong_ticket']; ?>',
                confirmButtonColor: '#4F46E5'
            });
        }
    } catch (error) {
        console.error('Error:', error);
        Swal.fire({
            icon: 'error',
            title: '<?php echo $pt['error_ticket']; ?>',
            text: '<?php echo $pt['something_went_wrong_ticket']; ?>',
            confirmButtonColor: '#4F46E5'
        });
    } finally {
        // Submit butonunu normal haline getir
        submitSupport.innerHTML = originalButtonText;
        submitSupport.disabled = false;
    }
});





// ========================= Modal Aç/Kapa =========================
const dateModal      = document.getElementById('dateModal');
const openDateModal  = document.getElementById('openDateModal');
const closeDateModal = document.getElementById('closeDateModal');
const modalBg        = dateModal.querySelector('.modal-bg');

openDateModal.addEventListener('click', () => {
    dateModal.classList.add('show');
});

closeDateModal.addEventListener('click', () => {
    dateModal.classList.remove('show');
});

modalBg.addEventListener('click', () => {
    dateModal.classList.remove('show');
});

// ========================= Flatpickr Range =========================
const dateRangeInput = document.getElementById('daterange');
flatpickr("#daterange", {
    mode: "range",
    dateFormat: "Y-m-d",
    defaultDate: [
        "<?php echo htmlspecialchars($start->format('Y-m-d')); ?>",
        "<?php echo htmlspecialchars($end->format('Y-m-d')); ?>"
    ]
});

// ========================= localStorage ile Tarih Kaybetmeme =========================
document.addEventListener('DOMContentLoaded', () => {
    const localRange = localStorage.getItem('adminDateRange');
    const urlParams  = new URLSearchParams(window.location.search);
    const paramRange = urlParams.get('date_range');
    
    if (!paramRange && localRange) {
        window.location.search = `date_range=${encodeURIComponent(localRange)}`;
    } else {
        if (paramRange) {
            localStorage.setItem('adminDateRange', paramRange);
            dateRangeInput.value = paramRange;
        }
    }
});

const applyFilterBtn = document.getElementById('applyFilter');
applyFilterBtn.addEventListener('click', function(e) {
    e.preventDefault(); // Submit'i durdur
    localStorage.setItem('adminDateRange', dateRangeInput.value);
    dateModal.classList.remove('show');

    // SweetAlert2 ile animasyonlu "tik" popup'ı:
    Swal.fire({
        title: '<?php echo $pt['success']; ?>', // "Başarılı!" veya "Success!"
        text: '<?php echo $pt['date_range_saved']; ?>', // "Tarih aralığı kaydedildi." veya "Date range saved."
        icon: 'success',
        confirmButtonColor: '#4F46E5',
        confirmButtonText: 'Tamam'
    }).then(() => {
        document.querySelector('#dateModal form').submit();
    });
});

// ========================= Chart.js =========================
const ctx = document.getElementById('revenueChart').getContext('2d');
const days        = <?php echo json_encode($days); ?>;
const revenueData = <?php echo json_encode($revenues); ?>;
const ordersData  = <?php echo json_encode($ordersArr); ?>;

let currentChart = null;

function createChart(type = 'revenue') {
    if (currentChart) {
        currentChart.destroy();
    }

    let data, label, color, gradient;
    gradient = ctx.createLinearGradient(0, 0, 0, 400);

    switch(type) {
        case 'revenue':
            data = revenueData;
            // "Günlük Gelir (₺)" => Sabit değilse, isterseniz $pt ile çevirebilirsiniz.
            label = '<?php echo ($lang === "tr") ? "Günlük Gelir (₺)" : "Daily Revenue (₺)"; ?>';
            gradient.addColorStop(0, 'rgba(99, 102, 241, 0.2)');
            gradient.addColorStop(1, 'rgba(99, 102, 241, 0)');
            color = '#6366f1';
            break;
        case 'orders':
            data = ordersData;
            // "Günlük Sipariş Sayısı" => Sabit değilse, isterseniz $pt ile çevirebilirsiniz.
            label = '<?php echo ($lang === "tr") ? "Günlük Sipariş Sayısı" : "Daily Order Count"; ?>';
            gradient.addColorStop(0, 'rgba(16, 185, 129, 0.2)');
            gradient.addColorStop(1, 'rgba(16, 185, 129, 0)');
            color = '#10b981';
            break;
    }

    currentChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: days,
            datasets: [{
                label: label,
                data: data,
                fill: true,
                backgroundColor: gradient,
                borderColor: color,
                tension: 0.4,
                pointBackgroundColor: color,
                pointBorderColor: '#fff',
                pointHoverBackgroundColor: '#fff',
                pointHoverBorderColor: color,
                pointRadius: 4,
                pointHoverRadius: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                mode: 'index',
                intersect: false,
            },
            animation: {
                duration: 1000,
                easing: 'easeInOutQuart'
            },
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: 'rgba(255, 255, 255, 0.95)',
                    titleColor: '#1f2937',
                    bodyColor: '#4b5563',
                    borderColor: 'rgba(0,0,0,0.1)',
                    borderWidth: 1,
                    padding: 12,
                    cornerRadius: 8,
                    mode: 'index',
                    intersect: false,
                    callbacks: {
                        label: function(context) {
                            let value = context.raw;
                            if (type === 'revenue') {
                                // currency_symbol => $pt['currency_symbol']
                                return '<?php echo $pt['currency_symbol']; ?>' + value.toLocaleString('tr-TR', {
                                    minimumFractionDigits: 2,
                                    maximumFractionDigits: 2
                                });
                            }
                            return value.toLocaleString('tr-TR');
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        color: 'rgba(0, 0, 0, 0.05)',
                        drawBorder: false
                    },
                    ticks: {
                        color: '#6b7280',
                        padding: 10,
                        callback: function(value) {
                            if (type === 'revenue') {
                                return '<?php echo $pt['currency_symbol']; ?>' + value.toLocaleString('tr-TR');
                            }
                            return value;
                        }
                    }
                },
                x: {
                    grid: { display: false },
                    ticks: {
                        color: '#6b7280',
                        padding: 10
                    }
                }
            }
        }
    });
}

// İlk grafik
createChart();

// Gelir / Sipariş butonları arasında geçiş
document.querySelectorAll('.chart-type-btn').forEach(button => {
    button.addEventListener('click', () => {
        document.querySelectorAll('.chart-type-btn').forEach(btn => {
            btn.classList.remove('active', 'bg-indigo-600', 'text-white');
            btn.classList.add('bg-gray-200', 'text-gray-600');
        });
        button.classList.remove('bg-gray-200', 'text-gray-600');
        button.classList.add('active', 'bg-indigo-600', 'text-white');
        
        createChart(button.dataset.type);
    });
});

// Success mesaj animasyonu (örnek)
const successAlert = document.getElementById('successAlert');
if (successAlert) {
    setTimeout(() => {
        successAlert.classList.remove('translate-x-full');
    }, 100);
    setTimeout(() => {
        successAlert.classList.add('translate-x-full');
        setTimeout(() => {
            successAlert.remove();
        }, 500);
    }, 5000);
}

// Scroll animasyonları (stat-card, chart-card, orders-card)
const animateOnScroll = () => {
    const elements = document.querySelectorAll('.stat-card, .chart-card, .orders-card');
    elements.forEach((element, index) => {
        const rect = element.getBoundingClientRect();
        if (rect.top <= window.innerHeight * 0.85) {
            setTimeout(() => {
                element.style.opacity = '1';
                element.style.transform = 'translateY(0)';
            }, index * 100);
        }
    });
};

document.addEventListener('DOMContentLoaded', () => {
    const elements = document.querySelectorAll('.stat-card, .chart-card, .orders-card');
    elements.forEach(element => {
        element.style.opacity = '0';
        element.style.transform = 'translateY(20px)';
        element.style.transition = 'all 0.6s cubic-bezier(0.4, 0, 0.2, 1)';
    });
    animateOnScroll();
});

window.addEventListener('scroll', animateOnScroll);

function closeTicketModal() {
    const modal = document.getElementById('ticketDetailsModal');
    modal.classList.remove('show');
    setTimeout(() => {
        modal.style.display = 'none';
    }, 300);
}

document.getElementById('closeTicketModal').addEventListener('click', closeTicketModal);

// Modal dışına tıklandığında kapatma
document.getElementById('ticketDetailsModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeTicketModal();
    }
});

// ESC tuşu ile kapatma
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && document.getElementById('ticketDetailsModal').classList.contains('show')) {
        closeTicketModal();
    }
});
</script>

<?php include_once '../includes/new-footer.php'; ?>
