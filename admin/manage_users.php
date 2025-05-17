<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

include_once '../config/database.php';
include_once '../includes/new-header.php';

// 1) Dil dosyasını yükle
$lang = $_SESSION['lang'] ?? 'tr';
$translation_file = __DIR__ . "/../translations/translation_{$lang}.php";
if (file_exists($translation_file)) {
    $t = require $translation_file;  // $t değişkeni artık çeviri dizisi
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

// 4) İstatistik sorgusu (toplam kullanıcı, aktif kullanıcı, toplam gelir vs.)
$stats_query = "
    SELECT 
        COUNT(DISTINCT CASE WHEN role != 'admin' THEN id END) as total_users,
        COUNT(DISTINCT CASE WHEN role = 'admin' THEN id END) as total_admins,
        (SELECT COUNT(DISTINCT user_id) 
         FROM orders 
         WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ) as active_users,
        COALESCE((SELECT SUM(total_amount) FROM orders), 0) as total_revenue,
        COALESCE((SELECT SUM(total_amount)
                  FROM orders
                  WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                 ), 0) as monthly_revenue
    FROM users
    WHERE id != ?
";
$stats_stmt = $db->prepare($stats_query);
$stats_stmt->execute([$_SESSION['user_id']]);
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

// 5) Sayfalama parametreleri
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$view_mode = isset($_GET['view']) ? $_GET['view'] : 'list';
$records_per_page = ($view_mode === 'grid') ? 12 : 10; // Grid görünümde 12, liste görünümünde 10 kayıt
$offset = ($page - 1) * $records_per_page;

// 6) Arama & filtre parametreleri
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status = isset($_GET['status']) ? $_GET['status'] : '';
$sort   = isset($_GET['sort'])   ? $_GET['sort']   : 'newest';

// 7) Kullanıcıları listeleme sorgusu (orders join)
$query = "
    SELECT u.*,
           COUNT(DISTINCT o.id) as total_orders,
           COALESCE(SUM(o.total_amount), 0) as total_spent,
           MAX(o.created_at) as last_order_date
    FROM users u
    LEFT JOIN orders o ON u.id = o.user_id
    WHERE u.id != ?
";
$count_query  = "SELECT COUNT(*) FROM users WHERE id != ?";
$params       = [$_SESSION['user_id']];
$count_params = [$_SESSION['user_id']];

// 8) Arama (username, email, full_name, phone)
if ($search) {
    $search_term = "%$search%";
    $query .= " AND (u.username LIKE ? OR u.email LIKE ? OR u.full_name LIKE ? OR u.phone LIKE ?)";
    $count_query .= " AND (username LIKE ? OR email LIKE ? OR full_name LIKE ? OR phone LIKE ?)";
    $params       = array_merge($params, [$search_term, $search_term, $search_term, $search_term]);
    $count_params = array_merge($count_params, [$search_term, $search_term, $search_term, $search_term]);
}

// 9) Aktif / Pasif / Admin filtresi
if ($status === 'active') {
    // Aktif kullanıcı => son 30 günde siparişi olanlar
    $query .= " AND EXISTS (
        SELECT 1 FROM orders
        WHERE user_id = u.id
          AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    )";
    $count_query .= " AND id IN (
        SELECT user_id FROM orders
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    )";
} elseif ($status === 'inactive') {
    // Pasif kullanıcı => son 30 günde siparişi olmayanlar
    $query .= " AND NOT EXISTS (
        SELECT 1 FROM orders
        WHERE user_id = u.id
          AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    )";
    $count_query .= " AND id NOT IN (
        SELECT user_id FROM orders
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    )";
} elseif ($status === 'admin') {
    // Admin kullanıcılar
    $query .= " AND u.role = 'admin'";
    $count_query .= " AND role = 'admin'";
}

// 10) Group by ve Sıralama
$query .= " GROUP BY u.id ";
switch ($sort) {
    case 'oldest':
        $query .= "ORDER BY u.created_at ASC";
        break;
    case 'orders':
        $query .= "ORDER BY total_orders DESC";
        break;
    case 'spent':
        $query .= "ORDER BY total_spent DESC";
        break;
    default: // newest
        $query .= "ORDER BY u.created_at DESC";
        break;
}
$query .= " LIMIT $offset, $records_per_page";

// 11) Toplam kayıt sayısı ve sayfa hesaplamaları
$count_stmt = $db->prepare($count_query);
$count_stmt->execute($count_params);
$total_records = $count_stmt->fetchColumn();
$total_pages   = ceil($total_records / $records_per_page);

// 12) Sorguyu çalıştır
$stmt = $db->prepare($query);
$stmt->execute($params);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($lang); ?>">
<head>
    <meta charset="UTF-8">
    <title><?php echo $t['manage_users_title']; ?> - HealthTurkey</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Tailwind -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/tailwindcss/2.2.19/tailwind.min.css" rel="stylesheet">
    <style>
        .luxury-gradient {
            background: linear-gradient(135deg, #f6f8fc 0%, #eef2f7 100%);
        }
        .premium-gradient {
            background: linear-gradient(120deg, #4F46E5, #6366F1);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .user-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.8);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .user-card:hover {
            transform: translateY(-5px) scale(1.01);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.12);
        }
        .modal-overlay {
            z-index: 50;
            background-color: rgba(0, 0, 0, 0.5);
            transition: opacity 0.3s ease;
        }
        .modal-content {
            z-index: 51;
            transition: all 0.3s ease;
        }
        .modal-active {
            opacity: 1 !important;
            transform: translateY(0) !important;
        }
        /* Notlar bölümü için scroll özelliği */
        .notes-container {
            min-height: 100px;
            max-height: 200px;
            overflow-y: auto;
            scrollbar-width: thin;
            scrollbar-color: rgba(156, 163, 175, 0.5) transparent;
        }
        .notes-container::-webkit-scrollbar {
            width: 6px;
        }
        .notes-container::-webkit-scrollbar-track {
            background: transparent;
        }
        .notes-container::-webkit-scrollbar-thumb {
            background-color: rgba(156, 163, 175, 0.5);
            border-radius: 3px;
        }
    </style>
</head>
<body>
<!-- Ana Container -->
<div class="min-h-screen luxury-gradient pt-20">
    <div class="max-w-7xl mx-auto px-4 py-8">

        <!-- Başlık ve İstatistik -->
        <div class="flex flex-col md:flex-row justify-between items-center mb-12">
            <div>
                <h1 class="text-4xl font-light mb-4 text-gray-800">
                    <?php echo $t['manage_users_title']; ?>
                    <span class="block text-3xl font-semibold mt-3 premium-gradient">
                        <?php echo $t['manage_users_subtitle']; ?>
                    </span>
                </h1>
                <p class="text-gray-600"><?php echo $t['manage_users_desc']; ?></p>
            </div>
            <!-- List/Grid Toggle Buttons -->
            <div class="mt-4 md:mt-0">
                <div class="bg-white rounded-xl p-1 shadow-sm inline-flex">
                    <button onclick="setViewMode('grid')" 
                            id="gridViewBtn"
                            class="p-2 rounded-lg transition-all duration-300 bg-gray-100 text-gray-600 hover:bg-gray-200">
                        <!-- Grid icon -->
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2
                                     a2 2 0 01-2 2H6a2 2 0 01-2-2V6z
                                     M14 6a2 2 0 012-2h2a2 2 0 012 2v2
                                     a2 2 0 01-2 2h-2a2 2 0 01-2-2V6z
                                     M4 16a2 2 0 012-2h2a2 2 0 012 2v2
                                     a2 2 0 01-2 2H6a2 2 0 01-2-2v-2z
                                     M14 16a2 2 0 012-2h2a2 2 0 012 2v2
                                     a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/>
                        </svg>
                    </button>
                    <button onclick="setViewMode('list')"
                            id="listViewBtn"
                            class="p-2 rounded-lg transition-all duration-300 bg-gray-100 text-gray-600 hover:bg-gray-200">
                        <!-- List icon -->
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round"
                                  stroke-linejoin="round"
                                  stroke-width="2"
                                  d="M4 6h16M4 10h16M4 14h16M4 18h16"/>
                        </svg>
                    </button>
                </div>
            </div>
        </div>

        <!-- 4'lü Stats Kartları -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <!-- Kart 1 -->
            <div class="bg-white rounded-2xl p-6 shadow-xl user-card">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm mb-1"><?php echo $t['total_customers']; ?></p>
                        <h3 class="text-2xl font-bold text-gray-900">
                            <?php echo number_format($stats['total_users']); ?>
                        </h3>
                        <p class="text-sm text-indigo-600 mt-1">
                            <?php echo number_format($stats['total_admins']); ?> <?php echo $t['admin']; ?>
                        </p>
                    </div>
                    <div class="w-12 h-12 bg-indigo-100 rounded-xl flex items-center justify-center">
                        <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M12 8c-1.657 0-3 .895-3 2
                                     s1.343 2 3 2 3 .895 3 2-1.343 2-3 2
                                     m0-8c1.11 0 2.08.402 2.599 1
                                     M12 8V7m0 1v8m0 0v1m0-1
                                     c-1.11 0-2.08-.402-2.599-1
                                     M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                </div>
            </div>
            <!-- Kart 2 -->
            <div class="bg-white rounded-2xl p-6 shadow-xl user-card">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm mb-1"><?php echo $t['active_customers']; ?></p>
                        <h3 class="text-2xl font-bold text-gray-900">
                            <?php echo number_format($stats['active_users']); ?>
                        </h3>
                        <p class="text-sm text-green-600 mt-1">
                            <?php echo $t['last_30_days']; ?>
                        </p>
                    </div>
                    <div class="w-12 h-12 bg-green-100 rounded-xl flex items-center justify-center">
                        <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M9 12l2 2 4-4
                                     m5.618-4.016A11.955 11.955 0 0112 2.944
                                     a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9
                                     c0 5.591 3.824 10.29 9 11.622
                                     5.176-1.332 9-6.03 9-11.622
                                     0-1.042-.133-2.052-.382-3.016z"/>
                        </svg>
                    </div>
                </div>
            </div>
            <!-- Kart 3 -->
            <div class="bg-white rounded-2xl p-6 shadow-xl user-card">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm mb-1"><?php echo $t['total_revenue']; ?></p>
                        <h3 class="text-2xl font-bold text-gray-900">
                            ₺<?php echo number_format($stats['total_revenue'], 2); ?>
                        </h3>
                        <p class="text-sm text-purple-600 mt-1"><?php echo $t['all_time']; ?></p>
                    </div>
                    <div class="w-12 h-12 bg-purple-100 rounded-xl flex items-center justify-center">
                        <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M16 8v8m-4-5v5m-4-2v2
                                     m-2 4h12a2 2 0 002-2V6
                                     a2 2 0 00-2-2H6
                                     a2 2 0 00-2 2v12
                                     a2 2 0 002 2z"/>
                        </svg>
                    </div>
                </div>
            </div>
            <!-- Kart 4 -->
            <div class="bg-white rounded-2xl p-6 shadow-xl user-card">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm mb-1"><?php echo $t['last_30_days_revenue']; ?></p>
                        <h3 class="text-2xl font-bold text-gray-900">
                            ₺<?php echo number_format($stats['monthly_revenue'], 2); ?>
                        </h3>
                        <p class="text-sm text-blue-600 mt-1"><?php echo $t['monthly_average']; ?></p>
                    </div>
                    <div class="w-12 h-12 bg-blue-100 rounded-xl flex items-center justify-center">
                        <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M16 8v8m-4-5v5m-4-2v2
                                     m-2 4h12a2 2 0 002-2V6
                                     a2 2 0 00-2-2H6
                                     a2 2 0 00-2 2v12
                                     a2 2 0 002 2z"/>
                        </svg>
                    </div>
                </div>
            </div>
        </div>

        <!-- Arama & Filtre Formu -->
        <div class="bg-white rounded-2xl p-6 shadow-xl mb-8">
            <form class="grid grid-cols-1 md:grid-cols-4 gap-6">
                <!-- Arama -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        <?php echo $t['search']; ?>
                    </label>
                    <div class="relative">
                        <input type="text"
                               name="search"
                               value="<?php echo htmlspecialchars($search); ?>"
                               placeholder="<?php echo $t['search_placeholder']; ?>"
                               class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl
                                      focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500
                                      transition-all duration-300">
                        <div class="absolute inset-y-0 right-0 flex items-center pr-3">
                            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round"
                                      stroke-linejoin="round"
                                      stroke-width="2"
                                      d="M21 21l-6-6m2-5a7 7 0 11-14 0
                                         7 7 0 0114 0z"/>
                            </svg>
                        </div>
                    </div>
                </div>

                <!-- Durum -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        <?php echo $t['status']; ?>
                    </label>
                    <select name="status"
                            class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl
                                   focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500
                                   transition-all duration-300">
                        <option value=""><?php echo $t['all']; ?></option>
                        <option value="active"   <?php echo ($status === 'active')   ? 'selected' : ''; ?>><?php echo $t['active']; ?></option>
                        <option value="inactive" <?php echo ($status === 'inactive') ? 'selected' : ''; ?>><?php echo $t['inactive']; ?></option>
                        <option value="admin"    <?php echo ($status === 'admin')    ? 'selected' : ''; ?>><?php echo $t['admin']; ?></option>
                    </select>
                </div>

                <!-- Sıralama -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        <?php echo $t['sort']; ?>
                    </label>
                    <select name="sort"
                            class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl
                                   focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500
                                   transition-all duration-300">
                        <option value="newest" <?php echo ($sort === 'newest') ? 'selected' : ''; ?>><?php echo $t['newest']; ?></option>
                        <option value="oldest" <?php echo ($sort === 'oldest') ? 'selected' : ''; ?>><?php echo $t['oldest']; ?></option>
                        <option value="orders" <?php echo ($sort === 'orders') ? 'selected' : ''; ?>><?php echo $t['orders_count']; ?></option>
                        <option value="spent"  <?php echo ($sort === 'spent')  ? 'selected' : ''; ?>><?php echo $t['spent_amount']; ?></option>
                    </select>
                </div>

                <!-- Filtre Butonu -->
                <div class="flex items-end">
                    <button type="submit"
                            class="w-full px-4 py-3 bg-gradient-to-r from-indigo-600 to-indigo-700 text-white rounded-xl
                                   hover:shadow-lg transform hover:scale-105 transition-all duration-300
                                   flex items-center justify-center">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round"
                                  stroke-linejoin="round"
                                  stroke-width="2"
                                  d="M3 4a1 1 0 011-1h16
                                     a1 1 0 011 1v2.586
                                     a1 1 0 01-.293.707
                                     l-6.414 6.414
                                     a1 1 0 00-.293.707V17l-4 4
                                     v-6.586
                                     a1 1 0 00-.293-.707L3.293 7.293
                                     A1 1 0 013 6.586V4z"/>
                        </svg>
                        <?php echo $t['filter']; ?>
                    </button>
                </div>
            </form>
        </div>

        <!-- Tablo (List) Görünümü -->
        <div id="listView" class="bg-white rounded-2xl shadow-xl mb-6 hidden">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="text-left border-b border-gray-100">
                            <th class="px-6 py-4 font-medium text-gray-600"><?php echo $t['customer']; ?></th>
                            <th class="px-6 py-4 font-medium text-gray-600"><?php echo $t['contact']; ?></th>
                            <th class="px-6 py-4 font-medium text-gray-600"><?php echo $t['orders']; ?></th>
                            <th class="px-6 py-4 font-medium text-gray-600"><?php echo $t['total_spent']; ?></th>
                            <th class="px-6 py-4 font-medium text-gray-600"><?php echo $t['status_col']; ?></th>
                            <th class="px-6 py-4 font-medium text-gray-600"><?php echo $t['actions']; ?></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                    <?php foreach ($users as $user):
                        $isActive = ($user['last_order_date'] && strtotime($user['last_order_date']) > strtotime('-30 days'));
                    ?>
                        <tr class="hover:bg-gray-50 transition-colors duration-200">
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <div>
                                        <div class="font-medium text-gray-900">
                                            <?php echo htmlspecialchars($user['full_name']); ?>
                                            <?php if ($user['role'] === 'admin'): ?>
                                                <span class="ml-2 px-2 py-1 text-xs bg-yellow-100 text-yellow-800 rounded-full">
                                                    <?php echo $t['admin']; ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="text-sm text-gray-500">
                                            @<?php echo htmlspecialchars($user['username']); ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm">
                                    <div class="text-gray-900"><?php echo htmlspecialchars($user['email']); ?></div>
                                    <div class="text-gray-500"><?php echo htmlspecialchars($user['phone'] ?? '-'); ?></div>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <span class="text-gray-900 font-medium">
                                    <?php echo number_format($user['total_orders']); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <span class="text-gray-900 font-medium">
                                    ₺<?php echo number_format($user['total_spent']); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <span class="px-3 py-1 rounded-full text-sm font-medium inline-flex items-center
                                    <?php echo $isActive ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'; ?>">
                                    <?php echo $isActive ? $t['active_label'] : $t['inactive_label']; ?>
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex space-x-2">
                                    <!-- Kullanıcı Detayları Butonu -->
                                    <button onclick="showUserDetails(<?php echo $user['id']; ?>)"
                                            class="p-2 bg-indigo-100 text-indigo-600 rounded-lg hover:bg-indigo-200 transition-colors duration-300">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M15 12a3 3 0 11-6 0 3 3 0 016 0z
                                                     M2.458 12C3.732 7.943 7.523 5, 12 5
                                                     c4.478 0 8.268 2.943 9.542 7
                                                     -1.274 4.057-5.064 7
                                                     -9.542 7-4.477 0
                                                     -8.268-2.943
                                                     -9.542-7z"/>
                                        </svg>
                                    </button>
                                    <?php if ($_SESSION['user_id'] != $user['id']): ?>
                                        <!-- Admin Rolü -->
                                        <button onclick="toggleAdminRole(<?php echo $user['id']; ?>, '<?php echo $user['role']; ?>')"
                                                class="p-2 rounded-lg transition-colors duration-300
                                                    <?php echo ($user['role'] === 'admin')
                                                        ? 'bg-yellow-100 text-yellow-600'
                                                        : 'bg-indigo-100 text-indigo-600'; ?>
                                                    hover:bg-opacity-80">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                      d="M12 4.354a4 4 0 110 5.292
                                                         M15 21H3v-1a6 6 0 0112 0v1
                                                         M15 21h6v-1
                                                         a6 6 0 00-9-5.197
                                                         M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                                            </svg>
                                        </button>

                                        <!-- Kullanıcı Silme -->
                                        <button onclick="confirmDeleteUser(<?php echo $user['id']; ?>, '<?php echo addslashes($user['full_name']); ?>')"
                                                class="p-2 bg-red-100 text-red-600 rounded-lg hover:bg-red-200 transition-colors duration-300">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                      d="M19 7l-.867 12.142A2 2 0 0116.138 21
                                                         H7.862a2 2 0 01-1.995-1.858
                                                         L5 7m5 4v6m4-6v6
                                                         m1-10V4
                                                         a1 1 0 00-1-1h-4
                                                         a1 1 0 00-1 1v3
                                                         M4 7h16"/>
                                            </svg>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Grid Görünümü -->
        <div id="gridView" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 hidden">
            <?php foreach ($users as $user):
                $isActive = ($user['last_order_date'] && strtotime($user['last_order_date']) > strtotime('-30 days'));
            ?>
            <div class="bg-white rounded-2xl shadow-xl user-card overflow-hidden">
                <div class="p-6">
                    <div class="flex items-start justify-between mb-4">
                        <div>
                            <h3 class="text-xl font-semibold text-gray-900 mb-1">
                                <?php echo htmlspecialchars($user['full_name']); ?>
                            </h3>
                            <p class="text-gray-500">
                                @<?php echo htmlspecialchars($user['username']); ?>
                            </p>
                        </div>
                        <?php if ($user['role'] === 'admin'): ?>
                            <span class="px-3 py-1 bg-yellow-100 text-yellow-800 text-sm font-medium rounded-full">
                                <?php echo $t['admin']; ?>
                            </span>
                        <?php endif; ?>
                    </div>

                    <!-- İletişim -->
                    <div class="space-y-2 mb-6">
                        <div class="flex items-center text-gray-600">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round"
                                      stroke-linejoin="round"
                                      stroke-width="2"
                                      d="M3 8l7.89 5.26a2 2 0 002.22 0
                                         L21 8
                                         M5 19h14a2 2 0 002-2V7
                                         a2 2 0 00-2-2H5
                                         a2 2 0 00-2 2v10
                                         a2 2 0 002 2z"/>
                            </svg>
                            <span class="text-sm">
                                <?php echo htmlspecialchars($user['email']); ?>
                            </span>
                        </div>
                        <?php if ($user['phone']): ?>
                        <div class="flex items-center text-gray-600">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round"
                                      stroke-linejoin="round"
                                      stroke-width="2"
                                      d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684
                                         l1.498 4.493a1 1 0 01-.502 1.21
                                         l-2.257 1.13
                                         a11.042 11.042 0 005.516 5.516
                                         l1.13-2.257a1 1 0 011.21-.502
                                         l4.493 1.498
                                         a1 1 0 01.684.949V19
                                         a2 2 0 01-2 2h-1
                                         C9.716 21 3 14.284 3 6V5z"/>
                            </svg>
                            <span class="text-sm">
                                <?php echo htmlspecialchars($user['phone']); ?>
                            </span>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Stats -->
                    <div class="grid grid-cols-2 gap-4 mb-6">
                        <div class="bg-gray-50 rounded-xl p-4 text-center">
                            <p class="text-gray-500 text-sm mb-1"><?php echo $t['orders']; ?></p>
                            <p class="text-xl font-bold text-gray-900">
                                <?php echo number_format($user['total_orders']); ?>
                            </p>
                        </div>
                        <div class="bg-gray-50 rounded-xl p-4 text-center">
                            <p class="text-gray-500 text-sm mb-1"><?php echo $t['total_spent']; ?></p>
                            <p class="text-xl font-bold text-gray-900">
                                ₺<?php echo number_format($user['total_spent']); ?>
                            </p>
                        </div>
                    </div>

                    <!-- Durum + İşlemler -->
                    <div class="flex items-center justify-between">
                        <span class="px-3 py-1 rounded-full text-sm font-medium
                            <?php echo $isActive ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'; ?>">
                            <?php echo $isActive ? $t['active_label'] : $t['inactive_label']; ?>
                        </span>
                        <div class="flex space-x-2">
                            <button onclick="showUserDetails(<?php echo $user['id']; ?>)"
                                    class="p-2 bg-indigo-100 text-indigo-600 rounded-lg hover:bg-indigo-200 transition-colors duration-300">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round"
                                          stroke-linejoin="round"
                                          stroke-width="2"
                                          d="M15 12a3 3 0 11-6 0 3 3 0 016 0z
                                             M2.458 12C3.732 7.943 7.523 5, 12 5
                                             c4.478 0 8.268 2.943 9.542 7
                                             -1.274 4.057-5.064 7
                                             -9.542 7-4.477 0
                                             -8.268-2.943
                                             -9.542-7z"/>
                                </svg>
                            </button>
                            <?php if ($_SESSION['user_id'] != $user['id']): ?>
                                <button onclick="toggleAdminRole(<?php echo $user['id']; ?>, '<?php echo $user['role']; ?>')"
                                        class="p-2 rounded-lg transition-colors duration-300
                                            <?php echo ($user['role'] === 'admin')
                                                ? 'bg-yellow-100 text-yellow-600'
                                                : 'bg-indigo-100 text-indigo-600'; ?>
                                            hover:bg-opacity-80">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M12 4.354a4 4 0 110 5.292
                                                 M15 21H3v-1a6 6 0 0112 0v1
                                                 M15 21h6v-1
                                                 a6 6 0 00-9-5.197
                                                 M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                                    </svg>
                                </button>
                                <button onclick="confirmDeleteUser(<?php echo $user['id']; ?>, '<?php echo addslashes($user['full_name']); ?>')"
                                        class="p-2 bg-red-100 text-red-600 rounded-lg hover:bg-red-200 transition-colors duration-300">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M19 7l-.867 12.142A2 2 0 0116.138 21
                                                 H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6
                                                         m1-10V4
                                                         a1 1 0 00-1-1h-4
                                                         a1 1 0 00-1 1v3
                                                         M4 7h16"/>
                                    </svg>
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Sayfalama -->
        <?php if ($total_pages > 1): ?>
        <div class="mt-8 flex justify-center">
            <nav class="relative z-0 inline-flex rounded-xl shadow-sm -space-x-px overflow-hidden" aria-label="Pagination">
                <!-- Önceki Sayfa -->
                <?php if ($page > 1): ?>
                    <button onclick="goToPage(<?php echo $page - 1; ?>)"
                            class="relative inline-flex items-center px-4 py-3 bg-white text-sm font-medium text-gray-500 
                                   hover:bg-gray-50 transition-colors duration-300 rounded-l-xl">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                        </svg>
                    </button>
                <?php endif; ?>

                <!-- Sayfa Numaraları -->
                <?php
                // Gösterilecek sayfa aralığını hesapla
                $start_page = max(1, $page - 1);
                $end_page = min($total_pages, $page + 1);
                
                // İlk sayfa
                if ($start_page > 1) {
                    echo '<button onclick="goToPage(1)" 
                                 class="relative inline-flex items-center px-4 py-3 bg-white text-sm font-medium 
                                        text-gray-500 hover:bg-gray-50 transition-colors duration-300">
                            1
                          </button>';
                    if ($start_page > 2) {
                        echo '<span class="relative inline-flex items-center px-4 py-3 bg-white text-sm font-medium text-gray-500">
                                ...
                              </span>';
                    }
                }
                
                // Sayfa numaraları
                for ($i = $start_page; $i <= $end_page; $i++) {
                    echo '<button onclick="goToPage(' . $i . ')" 
                                 class="relative inline-flex items-center px-4 py-3 text-sm font-medium transition-colors duration-300 ' . 
                                 ($page === $i 
                                    ? 'bg-indigo-600 text-white hover:bg-indigo-700' 
                                    : 'bg-white text-gray-500 hover:bg-gray-50') . '">
                            ' . $i . '
                          </button>';
                }
                
                // Son sayfa
                if ($end_page < $total_pages) {
                    if ($end_page < $total_pages - 1) {
                        echo '<span class="relative inline-flex items-center px-4 py-3 bg-white text-sm font-medium text-gray-500">
                                ...
                              </span>';
                    }
                    echo '<button onclick="goToPage(' . $total_pages . ')" 
                                 class="relative inline-flex items-center px-4 py-3 bg-white text-sm font-medium 
                                        text-gray-500 hover:bg-gray-50 transition-colors duration-300">
                            ' . $total_pages . '
                          </button>';
                }
                ?>

                <!-- Sonraki Sayfa -->
                <?php if ($page < $total_pages): ?>
                    <button onclick="goToPage(<?php echo $page + 1; ?>)"
                            class="relative inline-flex items-center px-4 py-3 bg-white text-sm font-medium text-gray-500 
                                   hover:bg-gray-50 transition-colors duration-300 rounded-r-xl">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                    </button>
                <?php endif; ?>
            </nav>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Note Modal -->
<div id="noteModal" class="fixed inset-0 z-50 hidden">
    <div class="fixed inset-0 bg-black/50"></div>
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-xl w-full max-w-md p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold"><?php echo $t['add_note']; ?></h3>
                <button type="button" onclick="closeNoteModal()">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            <form id="noteForm">
                <input type="hidden" id="noteUserId" name="user_id">
                <textarea id="noteText" name="note" rows="4" class="w-full border rounded-lg p-2 mb-4"></textarea>
                <button type="submit" class="w-full bg-blue-500 text-white rounded-lg py-2">
                    <?php echo $t['add_note']; ?>
                </button>
            </form>
        </div>
    </div>
</div>

<!-- Admin Toggle Modal -->
<div id="toggleAdminModal" class="fixed inset-0 z-50 hidden">
    <div class="fixed inset-0 bg-black/50 backdrop-blur-sm transition-opacity duration-300 opacity-0" id="toggleAdminOverlay"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-md transform transition-all duration-300 opacity-0 translate-y-8" id="toggleAdminContent">
            <div class="p-6">
                <div class="flex justify-between items-start mb-4">
                    <div class="flex items-center">
                        <div class="w-12 h-12 bg-yellow-100 rounded-xl flex items-center justify-center mr-4">
                            <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                            </svg>
                        </div>
                        <h3 class="text-2xl font-semibold text-gray-900"><?php echo $t['admin_privilege']; ?></h3>
                    </div>
                    <button onclick="closeModal('toggleAdminModal')" class="p-2 hover:bg-gray-100 rounded-lg transition-colors">
                        <svg class="w-6 h-6 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
                <div class="mb-6">
                    <p class="text-gray-700" id="toggleAdminMessage"></p>
                </div>
                <div class="flex space-x-3">
                    <button onclick="closeModal('toggleAdminModal')"
                            class="flex-1 px-4 py-3 bg-gray-100 text-gray-700 rounded-xl hover:bg-gray-200 transition-colors">
                        <?php echo $t['cancel']; ?>
                    </button>
                    <button onclick="executeToggleAdmin()"
                            class="flex-1 px-4 py-3 bg-yellow-500 text-white rounded-xl hover:bg-yellow-600 transition-colors
                                   flex items-center justify-center">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <?php echo $t['confirm']; ?>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Toast -->
<div id="toast" class="fixed bottom-4 right-4 z-50 transform transition-all duration-300 translate-y-full opacity-0"></div>

<!-- User Details Modal -->
<div id="userDetailsModal" class="fixed inset-0 z-50 hidden">
    <div id="userDetailsOverlay" class="fixed inset-0 bg-black/50 backdrop-blur-sm opacity-0 transition-opacity"></div>
    <div id="userDetailsContent" class="fixed inset-0 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-2xl">
            <div class="p-6">
                <div class="flex justify-between items-start mb-4"></div>
                <div id="userDetailsBody"></div>
            </div>
        </div>
    </div>
</div>

<!-- Delete User Modal -->
<div id="deleteUserModal" class="fixed inset-0 z-50 hidden">
    <div class="fixed inset-0 bg-black/50 backdrop-blur-sm transition-opacity duration-300 opacity-0" id="deleteUserOverlay"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-md transform transition-all duration-300 opacity-0 translate-y-8" id="deleteUserContent">
            <div class="p-6">
                <div class="flex justify-between items-start mb-4">
                    <div class="flex items-center">
                        <div class="w-12 h-12 bg-red-100 rounded-xl flex items-center justify-center mr-4">
                            <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                      d="M19 7l-.867 12.142A2 2 0 0116.138 21
                                                         H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6
                                                         m1-10V4
                                                         a1 1 0 00-1-1h-4
                                                         a1 1 0 00-1 1v3
                                                         M4 7h16"/>
                            </svg>
                        </div>
                        <h3 class="text-2xl font-semibold text-gray-900"><?php echo $t['delete_user']; ?></h3>
                    </div>
                    <button onclick="closeModal('deleteUserModal')" class="p-2 hover:bg-gray-100 rounded-lg transition-colors">
                        <svg class="w-6 h-6 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
                <div class="mb-6">
                    <p class="text-gray-700" id="deleteUserMessage"></p>
                    <p class="text-red-600 text-sm mt-2"><?php echo $t['cannot_undo']; ?></p>
                </div>
                <div class="flex space-x-3">
                    <button onclick="closeModal('deleteUserModal')"
                            class="flex-1 px-4 py-3 bg-gray-100 text-gray-700 rounded-xl hover:bg-gray-200 transition-colors">
                        <?php echo $t['cancel']; ?>
                    </button>
                    <button onclick="executeDeleteUser()"
                            class="flex-1 px-4 py-3 bg-red-500 text-white rounded-xl hover:bg-red-600 transition-colors
                                   flex items-center justify-center">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                  d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6
                                                         m1-10V4
                                                         a1 1 0 00-1-1h-4
                                                         a1 1 0 00-1 1v3
                                                         M4 7h16"/>
                        </svg>
                        <?php echo $t['delete']; ?>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once '../includes/new-footer.php'; ?>

<!-- GSAP -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
<script>
//////////////////////////////
// Global Variables
//////////////////////////////
let currentUserId   = null;
let currentUserRole = null;
let currentUserName = null;

//////////////////////////////
// NOT EKLE / SİL
//////////////////////////////
async function handleAddNote(userId) {
    const noteText = document.getElementById('newNoteText')?.value?.trim();
    if (!noteText) {
        showToast('error', '<?php echo $t['please_enter_note']; ?>');
        return;
    }
    try {
        const formData = new FormData();
        formData.append('user_id', userId);
        formData.append('note', noteText);

        const response = await fetch('add_note.php', { method: 'POST', body: formData });
        if (!response.ok) throw new Error('Network response was not ok');
        const result = await response.json();

        if (result.success) {
            showToast('success', '<?php echo $t['note_added_success']; ?>');
            document.getElementById('newNoteText').value = '';
            showUserDetails(userId); // Kullanıcı detaylarını yeniden yükle
        } else {
            showToast('error', result.message || '<?php echo $t['error_adding_note']; ?>');
        }
    } catch (error) {
        console.error('Error:', error);
        showToast('error', '<?php echo $t['error_adding_note']; ?>');
    }
}

async function deleteNote(noteId) {
    try {
        const formData = new FormData();
        formData.append('note_id', noteId);

        const response = await fetch('delete_user_note.php', { method: 'POST', body: formData });
        if (!response.ok) throw new Error('Network response was not ok');
        const result = await response.json();

        if (result.success) {
            showToast('success', '<?php echo $t['note_deleted_success']; ?>');
            if (currentUserId) showUserDetails(currentUserId);
        } else {
            showToast('error', result.message || '<?php echo $t['error_deleting_note']; ?>');
        }
    } catch (error) {
        console.error('Error:', error);
        showToast('error', '<?php echo $t['error_deleting_note']; ?>');
    }
}

//////////////////////////////
// Kullanıcı Detayları
//////////////////////////////
async function showUserDetails(userId) {
    currentUserId = userId;
    try {
        const response = await fetch(`get_user_details.php?id=${userId}`);
        if (!response.ok) throw new Error(`HTTP error: ${response.status}`);

        const html = await response.text();
        if (!html) throw new Error('Boş yanıt');

        const modalBody = document.getElementById('userDetailsBody');
        if (!modalBody) throw new Error('userDetailsBody yok');

        modalBody.innerHTML = html;
        openModal('userDetailsModal');
        switchTab('orders'); // Varsayılan tab
    } catch (error) {
        console.error('Hata:', error);
        showToast('error', '<?php echo $t['user_details_not_loaded']; ?>: ' + error.message);
    }
}

function switchTab(tabId) {
    document.querySelectorAll('.tab-content').forEach(content => {
        content.classList.add('hidden');
    });
    document.querySelectorAll('[id$="Tab"]').forEach(tab => {
        tab.classList.remove('border-blue-500', 'text-blue-600');
        tab.classList.add('text-gray-500', 'border-transparent');
    });
    const contentEl = document.getElementById(tabId + 'Content');
    const tabEl     = document.getElementById(tabId + 'Tab');
    if (contentEl && tabEl) {
        contentEl.classList.remove('hidden');
        tabEl.classList.remove('text-gray-500', 'border-transparent');
        tabEl.classList.add('border-blue-500', 'text-blue-600');
    }
}

//////////////////////////////
// Admin Yetkisi
//////////////////////////////
function toggleAdminRole(userId, currentRole) {
    currentUserId = userId;
    currentUserRole = currentRole;
    
    const modal = document.getElementById('toggleAdminModal');
    const overlay = document.getElementById('toggleAdminOverlay');
    const content = document.getElementById('toggleAdminContent');
    const messageEl = document.getElementById('toggleAdminMessage');
    
    if (!modal || !overlay || !content || !messageEl) return;
    
    const isAdmin = (currentRole === 'admin');
    messageEl.textContent = isAdmin 
        ? '<?php echo $t['confirm_remove_admin']; ?>'
        : '<?php echo $t['confirm_make_admin']; ?>';
    
    modal.classList.remove('hidden');
    
    // GSAP animasyonu
    gsap.to(overlay, {
        opacity: 1,
        duration: 0.3,
        ease: 'power2.out'
    });
    
    gsap.to(content, {
        opacity: 1,
        y: 0,
        duration: 0.4,
        ease: 'back.out(1.7)'
    });
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    const overlay = document.getElementById(modalId.replace('Modal', 'Overlay'));
    const content = document.getElementById(modalId.replace('Modal', 'Content'));
    
    if (!modal || !overlay || !content) return;
    
    // GSAP ile kapanış animasyonu
    gsap.to(overlay, {
        opacity: 0,
        duration: 0.3,
        ease: 'power2.in'
    });
    
    gsap.to(content, {
        opacity: 0,
        y: 40,
        duration: 0.3,
        ease: 'power2.in',
        onComplete: () => {
            modal.classList.add('hidden');
            content.style.transform = 'translateY(0)';
        }
    });
}

async function executeToggleAdmin() {
    if (!currentUserId) return;
    
    const formData = new FormData();
    formData.append('user_id', currentUserId);
    
    closeModal('toggleAdminModal');
    
    try {
        const response = await fetch('toggle_admin.php', {
            method: 'POST',
            body: formData
        });
        
        if (!response.ok) throw new Error('Network response was not ok');
        
        const data = await response.json();
        
        if (data.success) {
            showToast('success', data.message);
            // Sayfayı 1.5 saniye sonra yenile
            setTimeout(() => window.location.reload(), 1500);
        } else {
            showToast('error', data.message || '<?php echo $t['error_during_process']; ?>');
        }
    } catch (error) {
        console.error('Error:', error);
        showToast('error', '<?php echo $t['error_during_process']; ?>');
    }
}

//////////////////////////////
// Kullanıcı Silme
//////////////////////////////
function confirmDeleteUser(userId, userName) {
    currentUserId = userId;
    currentUserName = userName;
    
    const modal = document.getElementById('deleteUserModal');
    const overlay = document.getElementById('deleteUserOverlay');
    const content = document.getElementById('deleteUserContent');
    const messageEl = document.getElementById('deleteUserMessage');
    
    if (!modal || !overlay || !content || !messageEl) return;
    
    messageEl.textContent = `"${userName}" <?php echo $t['delete_user_confirm']; ?>`;
    
    modal.classList.remove('hidden');
    
    // GSAP animasyonu
    gsap.to(overlay, {
        opacity: 1,
        duration: 0.3,
        ease: 'power2.out'
    });
    
    gsap.to(content, {
        opacity: 1,
        y: 0,
        duration: 0.4,
        ease: 'back.out(1.7)'
    });
}

async function executeDeleteUser() {
    if (!currentUserId) return;
    
    const formData = new FormData();
    formData.append('user_id', currentUserId);
    
    closeModal('deleteUserModal');
    
    try {
        const response = await fetch('delete_user.php', {
            method: 'POST',
            body: formData
        });
        
        if (!response.ok) throw new Error('Network response was not ok');
        
        const data = await response.json();
        
        if (data.success) {
            showToast('success', data.message);
            // Sayfayı 1.5 saniye sonra yenile
            setTimeout(() => window.location.reload(), 1500);
        } else {
            showToast('error', data.message || '<?php echo $t['user_not_deleted']; ?>');
        }
    } catch (error) {
        console.error('Error:', error);
        showToast('error', '<?php echo $t['error_during_process']; ?>');
    }
}

//////////////////////////////
// Modal Aç / Kapat
//////////////////////////////
function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (!modal) return;
    modal.classList.remove('hidden');
    
    // Özel animasyon
    if (modalId === 'userDetailsModal') {
        const overlay = document.getElementById('userDetailsOverlay');
        const content = document.getElementById('userDetailsContent');
        if (overlay && content) {
            overlay.style.opacity = '1';
            content.classList.add('modal-active');
        }
    }
}

function closeNoteModal() {
    document.getElementById('noteModal').classList.add('hidden');
}

//////////////////////////////
// Toast Notifications
//////////////////////////////
function showToast(type, message) {
    const toast = document.createElement('div');
    toast.className = `toast-notification fixed bottom-4 right-4 p-4 rounded-lg shadow-lg z-50 
                       transition-all duration-300 transform
                       ${type === 'success' ? 'bg-green-500' : 'bg-red-500'} text-white`;
    toast.style.opacity = '0';
    toast.style.transform = 'translateY(100%)';
    toast.textContent = message;

    document.body.appendChild(toast);

    requestAnimationFrame(() => {
        toast.style.transform = 'translateY(0)';
        toast.style.opacity = '1';
    });

    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transform = 'translateY(100%)';
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

//////////////////////////////
// List / Grid Görünümü
//////////////////////////////
function setViewMode(mode, shouldReload = true) {
    const gridView = document.getElementById('gridView');
    const listView = document.getElementById('listView');
    const gridBtn = document.getElementById('gridViewBtn');
    const listBtn = document.getElementById('listViewBtn');

    if (!gridView || !listView || !gridBtn || !listBtn) return;

    if (mode === 'grid') {
        gridView.classList.remove('hidden');
        listView.classList.add('hidden');
        gridBtn.classList.add('bg-indigo-600', 'text-white');
        gridBtn.classList.remove('bg-gray-100', 'text-gray-600');
        listBtn.classList.add('bg-gray-100', 'text-gray-600');
        listBtn.classList.remove('bg-indigo-600', 'text-white');
    } else {
        // "list"
        gridView.classList.add('hidden');
        listView.classList.remove('hidden');
        listBtn.classList.add('bg-indigo-600', 'text-white');
        listBtn.classList.remove('bg-gray-100', 'text-gray-600');
        gridBtn.classList.add('bg-gray-100', 'text-gray-600');
        gridBtn.classList.remove('bg-indigo-600', 'text-white');
    }
    localStorage.setItem('manageUsersView', mode);
    
    // Sadece buton tıklamasında sayfayı yenile
    if (shouldReload) {
        const params = new URLSearchParams(window.location.search);
        const currentView = params.get('view') || 'list';
        
        // Sadece görünüm değiştiyse sayfayı yenile
        if (currentView !== mode) {
            params.set('view', mode);
            params.set('page', '1'); // İlk sayfaya dön
            window.location.search = params.toString();
        }
    }
}

function goToPage(page) {
    const params = new URLSearchParams(window.location.search);
    params.set('page', page);
    window.location.search = params.toString();
}

document.addEventListener('DOMContentLoaded', () => {
    const params = new URLSearchParams(window.location.search);
    const currentView = params.get('view') || 'list';
    const savedMode = localStorage.getItem('manageUsersView') || 'list';
    
    // Sayfa ilk yüklendiğinde yenileme yapmadan görünümü ayarla
    setViewMode(currentView, false);
    
    // View mode butonları için event listener'lar
    const gridViewBtn = document.getElementById('gridViewBtn');
    const listViewBtn = document.getElementById('listViewBtn');
    
    if (gridViewBtn) {
        gridViewBtn.addEventListener('click', () => setViewMode('grid', true));
    }
    if (listViewBtn) {
        listViewBtn.addEventListener('click', () => setViewMode('list', true));
    }

    // Mevcut modal kapatma logic
    document.querySelectorAll('[id$="Overlay"]').forEach(overlay => {
        overlay.addEventListener('click', (e) => {
            if (e.target === overlay) {
                const modalId = overlay.id.replace('Overlay', 'Modal');
                closeModal(modalId);
            }
        });
    });
    
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            ['noteModal','toggleAdminModal','userDetailsModal','deleteUserModal'].forEach(id => {
                const el = document.getElementById(id);
                if (el && !el.classList.contains('hidden')) {
                    closeModal(id);
                }
            });
        }
    });
});
</script>
</body>
</html>
