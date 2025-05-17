<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

include_once '../config/database.php';
include_once '../includes/new-header.php';

// İstediğiniz varsayılan dil (ör. 'tr'):
$defaultLang = 'tr';

// Gelen dil yoksa varsayılan 'tr'
$lang = $_SESSION['lang'] ?? $defaultLang;

// İlk deneme: "translation_{$lang}.php"
$translations_file = dirname(__DIR__) . "/translations/translation_{$lang}.php";

// Eğer bu dosya yoksa fallback yapıp tekrar dene
if (!file_exists($translations_file)) {
    // Hata vermek yerine fallback dil dosyasını açıyoruz
    // (örneğin 'tr')
    $fallback_file = dirname(__DIR__) . "/translations/translation_{$defaultLang}.php";
    if (file_exists($fallback_file)) {
        $t = require $fallback_file;
    } else {
        // Bu da yoksa mecburen betiği durduruyoruz.
        die("Fallback translation file not found: {$fallback_file}");
    }
} else {
    // Dil dosyası var => dahil et
    $t = require $translations_file;
}

// Admin kontrolü
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: /health_tourism/login.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Aktif/Pasif hizmet kontrolü
$showInactive = isset($_GET['show_inactive']) && $_GET['show_inactive'] == 'true';

// Hizmetleri al
$query = "SELECT s.*, c.name as category_name, COUNT(DISTINCT ds.doctor_id) as doctor_count 
          FROM services s 
          LEFT JOIN service_categories c ON s.category_id = c.id
          LEFT JOIN doctor_services ds ON s.id = ds.service_id";

if ($showInactive) {
    $query .= " WHERE s.status = 'inactive'";
} else {
    $query .= " WHERE s.status = 'active' OR s.status IS NULL";
}

$query .= " GROUP BY s.id ORDER BY s.name ASC";

try {
    $stmt = $db->prepare($query);
    $stmt->execute();
    $services = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    echo "Veritabanı hatası: " . $e->getMessage();
    exit;
}
?>


<style>
    .luxury-gradient {
        background: linear-gradient(135deg, #f6f8fc 0%, #eef2f7 100%);
    }
    .premium-gradient {
        background: linear-gradient(120deg, #4F46E5, #6366F1);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }
    .service-card {
        background: rgba(255, 255, 255, 0.95);
        border: 1px solid rgba(255, 255, 255, 0.8);
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
        transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .service-card:hover {
        transform: translateY(-5px) scale(1.01);
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.12);
    }

    /* Masaüstünde hover'la butonların görünmesi, mobilde hep görünür (sağ üstte) */
    .hover-buttons {
        position: absolute;
        top: 0.75rem;
        right: 0.75rem;
        display: flex;
        flex-direction: row;
        gap: 0.5rem;
        transition: opacity 0.3s;
    }
    @media (min-width: 768px) {
        .hover-buttons {
            opacity: 0;
        }
        .group:hover .hover-buttons {
            opacity: 1;
        }
    }
</style>

<script>
const translations = <?php echo json_encode($t); ?>;
</script>

<!-- Extra Languages Modal -->
<div id="extraLanguagesModal" class="fixed inset-0 z-50 hidden overflow-y-auto" onclick="modalBackdropClick(event, hideExtraLanguagesModal)">
    <div class="min-h-screen px-4 flex items-center justify-center">
        <div class="relative bg-white rounded-2xl max-w-3xl w-full p-8" onclick="event.stopPropagation()">
            <!-- Modal Header -->
            <div class="absolute top-4 right-4">
                <button onclick="hideExtraLanguagesModal()" class="text-gray-400 hover:text-gray-500 transition-colors duration-300">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                              d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            
            <h3 class="text-2xl font-bold mb-6"><?php echo $t['additional_languages']; ?></h3>

            <!-- Language Tabs -->
            <div class="flex space-x-2 mb-6 overflow-x-auto pb-2">
                <button onclick="switchLanguage('de')" data-lang="de" class="lang-tab px-4 py-2 rounded-lg text-sm font-medium">Deutsch</button>
                <button onclick="switchLanguage('ru')" data-lang="ru" class="lang-tab px-4 py-2 rounded-lg text-sm font-medium">Русский</button>
                <button onclick="switchLanguage('es')" data-lang="es" class="lang-tab px-4 py-2 rounded-lg text-sm font-medium">Español</button>
                <button onclick="switchLanguage('zh')" data-lang="zh" class="lang-tab px-4 py-2 rounded-lg text-sm font-medium">中文</button>
                <button onclick="switchLanguage('fr')" data-lang="fr" class="lang-tab px-4 py-2 rounded-lg text-sm font-medium">Français</button>
                <button onclick="switchLanguage('it')" data-lang="it" class="lang-tab px-4 py-2 rounded-lg text-sm font-medium">Italiano</button>
            </div>

            <!-- Language Forms -->
            <div class="language-forms">
                <!-- Almanca Form -->
                <div id="lang-form-de" class="lang-form hidden">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Name (Deutsch)</label>
                            <input type="text" name="name_de" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Beschreibung (Deutsch)</label>
                            <textarea name="description_de" rows="3" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 resize-none"></textarea>
                        </div>
                    </div>
                </div>

                <!-- Rusça Form -->
                <div id="lang-form-ru" class="lang-form hidden">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Название (Русский)</label>
                            <input type="text" name="name_ru" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Описание (Русский)</label>
                            <textarea name="description_ru" rows="3" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 resize-none"></textarea>
                        </div>
                    </div>
                </div>

                <!-- İspanyolca Form -->
                <div id="lang-form-es" class="lang-form hidden">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Nombre (Español)</label>
                            <input type="text" name="name_es" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Descripción (Español)</label>
                            <textarea name="description_es" rows="3" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 resize-none"></textarea>
                        </div>
                    </div>
                </div>

                <!-- Çince Form -->
                <div id="lang-form-zh" class="lang-form hidden">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">名称 (中文)</label>
                            <input type="text" name="name_zh" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">描述 (中文)</label>
                            <textarea name="description_zh" rows="3" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 resize-none"></textarea>
                        </div>
                    </div>
                </div>

                <!-- Fransızca Form -->
                <div id="lang-form-fr" class="lang-form hidden">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Nom (Français)</label>
                            <input type="text" name="name_fr" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Description (Français)</label>
                            <textarea name="description_fr" rows="3" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 resize-none"></textarea>
                        </div>
                    </div>
                </div>

                <!-- İtalyanca Form -->
                <div id="lang-form-it" class="lang-form hidden">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Nome (Italiano)</label>
                            <input type="text" name="name_it" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Descrizione (Italiano)</label>
                            <textarea name="description_it" rows="3" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 resize-none"></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer Buttons -->
            <div class="flex justify-end space-x-4 mt-8">
                <button onclick="hideExtraLanguagesModal()" 
                        class="px-4 py-2 bg-white border border-gray-300 text-gray-700 rounded-xl hover:bg-gray-50 transition-all duration-300">
                    <?php echo $t['cancel']; ?>
                </button>
                <button onclick="saveExtraLanguages()" 
                        class="px-4 py-2 bg-gradient-to-r from-indigo-600 to-indigo-700 text-white rounded-xl hover:shadow-lg transition-all duration-300">
                    <?php echo $t['save']; ?>
                </button>
            </div>
        </div>
    </div>
</div>

<div class="min-h-screen luxury-gradient pt-16 md:pt-20">
    <div class="max-w-7xl mx-auto px-4 py-8">
        <!-- Başlık ve Butonlar -->
        <div class="flex flex-col md:flex-row justify-between items-center mb-8">
            <div>
                <h1 class="text-3xl md:text-4xl font-light mb-2 text-gray-800">
                    <?php echo $t['service_management']; ?>
                    <span class="block text-2xl md:text-3xl font-semibold mt-2 premium-gradient">
                        <?php echo $showInactive ? $t['inactive_services'] : $t['active_services']; ?>
                    </span>
                </h1>
                <p class="text-gray-600 text-sm md:text-base">
                    <?php echo $t['manage_services']; ?>
                </p>
            </div>
            <div class="flex flex-col md:flex-row gap-4 mt-4 md:mt-0">
                <!-- Toggle Buton (Aktif / Pasif) -->
                <a href="?show_inactive=<?php echo $showInactive ? 'false' : 'true'; ?>"
                   class="px-4 py-2 md:px-6 md:py-3 bg-white border border-gray-300 text-gray-700 rounded-full 
                          hover:bg-gray-50 transition-all duration-300 flex items-center gap-2 text-sm md:text-base">
                    <?php if ($showInactive): ?>
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                  d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M2.458 12C3.732 7.943 7.523 5 12 5
                                     c4.478 0 8.268 2.943 9.542 7
                                     -1.274 4.057-5.064 7-9.542 7
                                     -4.477 0-8.268-2.943-9.542-7z" />
                        </svg>
                        <span><?php echo $t['show_active']; ?></span>
                    <?php else: ?>
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                  d="M13.875 18.825A10.05 10.05 0 0112 19
                                     c-4.478 0-8.268-2.943-9.543-7
                                     a9.97 9.97 0 011.563-3.029
                                     m5.858.908
                                     a3 3 0 114.243 4.243
                                     M9.878 9.878l4.242 4.242
                                     M9.88 9.88l-3.29-3.29
                                     m7.532 7.532l3.29 3.29
                                     M3 3l3.59 3.59
                                     m0 0A9.953 9.953 0 0112 5
                                     c4.478 0 8.268 2.943 9.543 7
                                     a10.025 10.025 0 01-4.132 5.411
                                     m0 0L21 21" />
                        </svg>
                        <span><?php echo $t['show_inactive']; ?></span>
                    <?php endif; ?>
                </a>
                
                <?php if (!$showInactive): ?>
                <!-- Kategori Yönet Butonu -->
                <button onclick="showCategoryModal()" 
                        class="px-4 py-2 md:px-6 md:py-3 bg-white border-2 border-indigo-600 
                               text-indigo-600 rounded-full transform hover:-translate-y-1 hover:shadow-xl 
                               transition-all duration-300 flex items-center text-sm md:text-base">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                              d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586
                                 l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0
                                 l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                    </svg>
                    <?php echo $t['manage_category']; ?>
                </button>

                <!-- Yeni Hizmet Ekle Butonu -->
                <button onclick="showAddServiceModal()" 
                        class="px-4 py-2 md:px-6 md:py-3 bg-gradient-to-r from-indigo-600 to-indigo-700 
                               text-white rounded-full transform hover:-translate-y-1 hover:shadow-xl 
                               transition-all duration-300 flex items-center text-sm md:text-base">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                              d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                    </svg>
                    <?php echo $t['add_new_service']; ?>
                </button>
                <?php endif; ?>
            </div>
        </div>

        <!-- Hizmet Kartları Grid -->
        <?php if (empty($services)): ?>
            <div class="flex flex-col items-center justify-center min-h-[300px] bg-white/50 backdrop-blur-sm rounded-2xl p-8 text-center">
                <div class="mb-6">
                    <svg class="w-20 h-20 text-gray-400 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" 
                              d="M13 10V3L4 14h7v7l9-11h-7z" />
                    </svg>
                </div>
                <h3 class="text-xl md:text-2xl font-semibold text-gray-800 mb-2">
                    <?php echo $showInactive ? $t['no_inactive_services'] : $t['no_services']; ?>
                </h3>
                <p class="text-gray-600 text-sm md:text-base">
                    <?php echo $showInactive ? $t['no_inactive_services_message'] : $t['no_services_message']; ?>
                </p>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-8 auto-rows-fr">
                <?php foreach($services as $service): ?>
                    <div class="relative group" data-service-id="<?php echo $service['id']; ?>">
                        <div class="service-card rounded-2xl overflow-hidden h-full flex flex-col">
                            <!-- Hizmet Görseli -->
                            <div class="relative h-48 flex-shrink-0">
                                <?php if ($service['image_url'] && file_exists("../assets/images/services/" . $service['image_url'])): ?>
                                    <img src="/health_tourism/assets/images/services/<?php echo htmlspecialchars($service['image_url']); ?>" 
                                        alt="<?php 
                                            $name_field = "name_" . $lang;
                                            echo htmlspecialchars($lang === 'tr' ? $service['name'] : 
                                                ($service[$name_field] ?: $service['name_en'])); 
                                        ?>"
                                        class="w-full h-full object-cover">
                                <?php else: ?>
                                    <div class="w-full h-full bg-gradient-to-br from-gray-100 to-gray-200 flex items-center justify-center">
                                        <svg class="w-16 h-16 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" 
                                                d="M13 10V3L4 14h7v7l9-11h-7z" />
                                        </svg>
                                    </div>
                                <?php endif; ?>
                                <!-- Fiyat Badge -->
                                <div class="absolute top-3 left-3 bg-white/90 backdrop-blur-md px-3 py-1 rounded-full">
                                    <span class="text-indigo-600 font-medium text-sm">
                                        ₺<?php echo number_format($service['price'], 0, ',', '.'); ?>
                                    </span>
                                </div>

                                <!-- Sağ Üstte İşlem Butonları (Edit / Delete) -->
                                <div class="hover-buttons">
                                    <!-- Edit Butonu -->
                                    <button onclick="editService(<?php echo $service['id']; ?>)" 
                                            class="p-2 bg-white/90 hover:bg-white rounded-lg transition-colors duration-300" 
                                            title="<?php echo $t['edit']; ?>">
                                        <svg class="w-4 h-4 text-gray-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2
                                                     h11a2 2 0 002-2v-5
                                                     m-1.414-9.414
                                                     a2 2 0 112.828 2.828
                                                     L11.828 15H9
                                                     v-2.828
                                                     l8.586-8.586z" />
                                        </svg>
                                    </button>

                                    <!-- Delete Butonu (Sadece aktif hizmetler için) -->
                                    <?php if ($service['status'] === 'active'): ?>
                                    <button onclick="confirmDelete(<?php echo $service['id']; ?>, '<?php echo addslashes($service['name']); ?>', <?php echo $service['doctor_count']; ?>)"
                                            class="p-2 bg-white/90 hover:bg-white rounded-lg transition-colors duration-300"
                                            title="<?php echo $t['delete']; ?>">
                                        <svg class="w-4 h-4 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                                  d="M19 7l-.867 12.142A2 2 0 0116.138 21
                                                     H7.862a2 2 0 01-1.995-1.858
                                                     L5 7m5 4v6m4-6v6
                                                     m1-10V4
                                                     a1 1 0 00-1-1h-4
                                                     a1 1 0 00-1 1v3
                                                     M4 7h16" />
                                        </svg>
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Hizmet Bilgileri -->
                            <div class="p-6 flex flex-col flex-grow">
                                <div class="mb-4">
                                    <h3 class="text-base md:text-lg font-semibold text-gray-900 mb-1">
                                        <?php echo htmlspecialchars($lang === 'tr' ? $service['name'] : $service['name_en']); ?>
                                    </h3>
                                    <h4 class="text-xs md:text-sm text-gray-500">
                                        <?php echo htmlspecialchars($lang === 'tr' ? $service['name_en'] : $service['name']); ?>
                                    </h4>
                                    <?php if ($service['category_name']): ?>
                                    <div class="mt-2">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800">
                                            <?php echo htmlspecialchars($service['category_name']); ?>
                                        </span>
                                    </div>
                                    <?php endif; ?>
                                </div>

                                <div class="flex-grow">
                                    <p class="text-gray-600 text-sm overflow-hidden line-clamp-2 max-h-14">
                                        <?php echo htmlspecialchars($lang === 'tr' ? $service['description'] : $service['description_en']); ?>
                                    </p>
                                </div>

                                <!-- Doktor sayısı / durum butonu -->
                                <div class="flex items-center justify-between pt-4 border-t border-gray-100 mt-4">
                                    <div class="flex items-center">
                                        <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" 
                                                  d="M17 20h5v-2a3 3 0 00-5.356-1.857
                                                     M17 20H7
                                                     m10 0v-2c0-.656-.126-1.283-.356-1.857
                                                     M7 20H2v-2
                                                     a3 3 0 015.356-1.857
                                                     M7 20v-2c0-.656.126-1.283.356-1.857
                                                     m0 0
                                                     a5.002 5.002 0 019.288 0
                                                     M15 7a3 3 0 11-6 0 3 3 0 016 0
                                                     zm6 3a2 2 0 11-4 0 2 2 0 014 0
                                                     zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                                        </svg>
                                        <span class="text-sm text-gray-500 ml-2 w-20">
                                            <?php echo $service['doctor_count']; ?> 
                                            <?php echo $service['doctor_count'] > 1 ? $t['doctors'] : $t['doctor']; ?>
                                        </span>
                                    </div>
                                    <div>
                                        <button onclick="toggleServiceStatus(<?php echo $service['id']; ?>, '<?php echo $service['status']; ?>')"
                                                class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium cursor-pointer transition-all duration-300 
                                                <?php echo $service['status'] === 'active' 
                                                        ? 'bg-green-100 text-green-800 hover:bg-green-200' 
                                                        : 'bg-red-100 text-red-800 hover:bg-red-200'; ?>">
                                            <?php echo $service['status'] === 'active' ? $t['active'] : $t['inactive']; ?>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- ====================================== -->
<!-- ============= MODALLAR =============== -->
<!-- ====================================== -->

<!-- Yeni/Düzenle Hizmet Modal -->
<div id="serviceModal" class="fixed inset-0 z-50 hidden overflow-y-auto" onclick="modalBackdropClick(event, hideServiceModal)">
    <div class="min-h-screen px-4 flex items-center justify-center">
        <div class="relative bg-white rounded-2xl max-w-2xl w-full p-8 transform transition-all" onclick="event.stopPropagation()">
            <div class="absolute top-4 right-4">
                <button onclick="hideServiceModal()" class="text-gray-400 hover:text-gray-500 transition-colors duration-300">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                              d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <div class="p-8">
                <h3 class="text-2xl font-bold mb-6 text-gray-900" id="modalTitle"><?php echo $t['add_new_service']; ?></h3>
                
                <!-- Form içeriği -->
                <form id="serviceForm" action="service_process.php" method="POST" enctype="multipart/form-data" class="space-y-6">
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="service_id" id="serviceId">
                    <input type="hidden" name="extra_languages" id="extraLanguagesData">
                    
                    <div class="space-y-6">
                        <!-- Extra Languages Butonu -->
                        <div class="flex justify-end">
                            <button type="button" 
                                    onclick="showExtraLanguagesModal()"
                                    class="inline-flex items-center px-4 py-2 border border-indigo-500 text-indigo-500 rounded-lg hover:bg-indigo-50 transition-all duration-300">
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                        d="M3 5h12M9 3v2m1.048 9.5A18.022 18.022 0 016.412 9m6.088 9h7M11 21l5-10 5 10M12.751 5C11.783 10.77 8.07 15.61 3 18.129" />
                                </svg>
                                <?php echo $t['add_other_languages']; ?>
                            </button>
                        </div>

                        <!-- İsim Alanı - TR & EN -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2"><?php echo $t['service_name_tr']; ?></label>
                                <input type="text" 
                                    name="name" 
                                    id="serviceName"
                                    required
                                    class="w-full px-4 py-2 md:py-3 bg-white border border-gray-300 rounded-xl 
                                            focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all duration-300">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2"><?php echo $t['service_name_en']; ?></label>
                                <input type="text" 
                                    name="name_en" 
                                    id="serviceNameEn"
                                    required
                                    class="w-full px-4 py-2 md:py-3 bg-white border border-gray-300 rounded-xl 
                                            focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all duration-300">
                            </div>
                        </div>

                        <!-- Açıklama Alanı - TR & EN -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2"><?php echo $t['description_tr']; ?></label>
                                <textarea name="description" 
                                        id="serviceDescription"
                                        required
                                        rows="3"
                                        class="w-full px-4 py-2 md:py-3 bg-white border border-gray-300 rounded-xl 
                                                focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all duration-300 resize-none"></textarea>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2"><?php echo $t['description_en']; ?></label>
                                <textarea name="description_en" 
                                        id="serviceDescriptionEn"
                                        required
                                        rows="3"
                                        class="w-full px-4 py-2 md:py-3 bg-white border border-gray-300 rounded-xl 
                                                focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all duration-300 resize-none"></textarea>
                            </div>
                        </div>

                        <!-- Fiyat ve Kategori -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2"><?php echo $t['price']; ?> (₺)</label>
                                <input type="number" 
                                    name="price" 
                                    id="servicePrice"
                                    required
                                    min="0"
                                    class="w-full px-4 py-2 md:py-3 bg-white border border-gray-300 rounded-xl 
                                            focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all duration-300">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2"><?php echo $t['category']; ?></label>
                                <select name="category_id" 
                                        id="serviceCategory"
                                        required
                                        class="w-full px-4 py-2 md:py-3 bg-white border border-gray-300 rounded-xl 
                                            focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all duration-300">
                                    <option value=""><?php echo $t['select_category']; ?></option>
                                </select>
                            </div>
                        </div>

                        <!-- Fotoğraf ve Önizleme -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <!-- Sol taraf: Yükleme alanı -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2"><?php echo $t['upload_photo']; ?></label>
                                <label id="dropZone" 
                                    class="relative flex flex-col items-center justify-center w-full h-36 border-2 border-dashed border-gray-300 rounded-xl
                                            hover:border-indigo-500 transition-colors duration-300 cursor-pointer bg-white">
                                    <div class="flex flex-col items-center justify-center pt-3 pb-4">
                                        <svg class="w-8 h-8 text-gray-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                                d="M7 16a4 4 0 01-.88-7.903
                                                    A5 5 0 1115.9 6L16 6
                                                    a5 5 0 011 9.9
                                                    M15 13l-3-3m0 0l-3 3m3-3v12" />
                                        </svg>
                                        <p class="mb-1 text-xs text-gray-500">
                                            <span class="font-semibold"><?php echo $t['click_to_upload']; ?></span>
                                        </p>
                                        <p class="text-xs text-gray-400"><?php echo $t['supported_formats']; ?></p>
                                    </div>
                                    <input type="file" 
                                        name="image" 
                                        id="serviceImage"
                                        accept="image/*"
                                        class="hidden">
                                </label>
                            </div>
                            
                            <!-- Sağ taraf: Önizleme -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2"><?php echo $t['photo_preview']; ?></label>
                                <div class="w-full h-36 bg-gray-50 rounded-xl overflow-hidden border border-gray-200 flex items-center justify-center">
                                    <img id="currentImage" src="" alt="<?php echo $t['service_image_preview']; ?>" 
                                        class="w-full h-full object-cover hidden">
                                    <div id="previewPlaceholder" class="w-full h-full flex items-center justify-center text-gray-400">
                                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" 
                                                d="M4 16l4.586-4.586a2 2 0 012.828 0
                                                    L16 16m-2-2l1.586-1.586
                                                    a2 2 0 012.828 0L20 14
                                                    m-6-6h.01M6 20h12
                                                    a2 2 0 002-2V6
                                                    a2 2 0 00-2-2H6
                                                    a2 2 0 00-2 2v12
                                                    a2 2 0 002 2z" />
                                        </svg>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Form Butonları -->
                    <div class="flex justify-end space-x-4 mt-8">
                        <button type="button"
                                onclick="hideServiceModal()"
                                class="px-4 py-2 md:px-6 md:py-3 bg-white border border-gray-300 text-gray-700 rounded-xl hover:bg-gray-50 transition-all duration-300 text-sm md:text-base">
                            <?php echo $t['cancel']; ?>
                        </button>
                        <button type="submit"
                                class="px-4 py-2 md:px-6 md:py-3 bg-gradient-to-r from-indigo-600 to-indigo-700 text-white rounded-xl hover:shadow-lg transform hover:scale-105 transition-all duration-300 text-sm md:text-base">
                            <span id="submitText"><?php echo $t['save']; ?></span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Kategori Modal -->
<div id="categoryModal" class="fixed inset-0 z-50 hidden overflow-y-auto" onclick="modalBackdropClick(event, hideCategoryModal)">
    <div class="min-h-screen px-4 flex items-center justify-center">
        <!-- İç kısım -->
        <div class="relative bg-white rounded-2xl max-w-lg w-full p-8" onclick="event.stopPropagation()">
            <div class="absolute top-4 right-4">
                <button onclick="hideCategoryModal()" class="text-gray-400 hover:text-gray-500">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                              d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <h3 class="text-2xl font-bold mb-6 text-gray-900"><?php echo $t['category_management']; ?></h3>
            

            <div class="flex justify-end mb-6">
            <button type="button" 
                    onclick="showCategoryExtraLangsModal()"
                    class="inline-flex items-center px-4 py-2 border border-indigo-500 text-indigo-500 rounded-lg hover:bg-indigo-50">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                          d="M3 5h12M9 3v2m1.048 9.5A18.022 18.022 0 016.412 9m6.088 9h7M11 21l5-10 5 10M12.751 5C11.783 10.77 8.07 15.61 3 18.129" />
                </svg>
                <?php echo $t['add_other_languages']; ?>
            </button>
            </div>
            <!-- Yeni Kategori Ekleme Formu -->
            <form id="categoryForm" class="mb-8">
                <input type="hidden" name="extra_languages" id="categoryExtraLangsData">
                <div class="space-y-4">
                    <div>
                        <input type="text" 
                               id="newCategoryName" 
                               placeholder="<?php echo $t['category_name_tr']; ?>"
                               class="w-full px-4 py-2 md:py-3 bg-white border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                    </div>
                    <div>
                        <input type="text" 
                               id="newCategoryNameEn" 
                               placeholder="<?php echo $t['category_name_en']; ?>"
                               class="w-full px-4 py-2 md:py-3 bg-white border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                    </div>
                    <button type="submit"
                            class="w-full px-4 py-2 md:px-6 md:py-3 bg-gradient-to-r from-indigo-600 to-indigo-700 text-white rounded-xl hover:shadow-lg transition-all duration-300 text-sm md:text-base">
                        <?php echo $t['add']; ?>
                    </button>
                </div>
            </form>

            <!-- Kategori Listesi -->
            <div class="space-y-4 max-h-96 overflow-y-auto" id="categoryList">
                <!-- Kategoriler JavaScript ile doldurulacak -->
            </div>
        </div>
    </div>
</div>

<!-- Category Extra Languages Modal -->
<div id="categoryExtraLangsModal" class="fixed inset-0 z-50 hidden overflow-y-auto" onclick="modalBackdropClick(event, hideCategoryExtraLangsModal)">
    <div class="min-h-screen px-4 flex items-center justify-center">
        <div class="relative bg-white rounded-2xl max-w-3xl w-full p-8" onclick="event.stopPropagation()">
            <!-- Close Button -->
            <div class="absolute top-4 right-4">
                <button onclick="hideCategoryExtraLangsModal()" class="text-gray-400 hover:text-gray-500">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            
            <h3 class="text-2xl font-bold mb-6"><?php echo $t['additional_languages']; ?></h3>

            <!-- Language Tabs -->
            <div class="flex space-x-2 mb-6 overflow-x-auto pb-2">
                <button onclick="switchCategoryLanguage('de')" data-lang="de" class="category-lang-tab px-4 py-2 rounded-lg text-sm font-medium">Deutsch</button>
                <button onclick="switchCategoryLanguage('ru')" data-lang="ru" class="category-lang-tab px-4 py-2 rounded-lg text-sm font-medium">Русский</button>
                <button onclick="switchCategoryLanguage('es')" data-lang="es" class="category-lang-tab px-4 py-2 rounded-lg text-sm font-medium">Español</button>
                <button onclick="switchCategoryLanguage('zh')" data-lang="zh" class="category-lang-tab px-4 py-2 rounded-lg text-sm font-medium">中文</button>
                <button onclick="switchCategoryLanguage('fr')" data-lang="fr" class="category-lang-tab px-4 py-2 rounded-lg text-sm font-medium">Français</button>
                <button onclick="switchCategoryLanguage('it')" data-lang="it" class="category-lang-tab px-4 py-2 rounded-lg text-sm font-medium">Italiano</button>
            </div>

            <!-- Language Forms -->
            <div class="category-language-forms">
                <!-- Forms for each language -->
                <div id="category-lang-form-de" class="category-lang-form hidden">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Name (Deutsch)</label>
                        <input type="text" name="category_name_de" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500">
                    </div>
                </div>
                <!-- Repeat for other languages -->
            </div>

            <!-- Footer Buttons -->
            <div class="flex justify-end space-x-4 mt-8">
                <button onclick="hideCategoryExtraLangsModal()" 
                        class="px-4 py-2 bg-white border border-gray-300 text-gray-700 rounded-xl hover:bg-gray-50">
                    <?php echo $t['cancel']; ?>
                </button>
                <button onclick="saveCategoryExtraLanguages()" 
                        class="px-4 py-2 bg-gradient-to-r from-indigo-600 to-indigo-700 text-white rounded-xl hover:shadow-lg">
                    <?php echo $t['save']; ?>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Category Delete Confirm Modal -->
<div id="categoryDeleteConfirmModal" class="fixed inset-0 z-50 hidden" onclick="modalBackdropClick(event, hideCategoryDeleteConfirmModal)">
    <div class="min-h-screen px-4 flex items-center justify-center">
        <div class="relative bg-white rounded-2xl max-w-md w-full p-8" onclick="event.stopPropagation()">
            <button onclick="hideCategoryDeleteConfirmModal()" class="absolute top-4 right-4 text-gray-400 hover:text-gray-500">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                          d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
            
            <div class="text-center">
                <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-red-100 mb-6">
                    <svg class="h-10 w-10 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                              d="M12 9v2m0 4h.01m-6.938 4
                                 h13.856c1.54 0 2.502-1.667 1.732-3
                                 L13.732 4c-.77-1.333-2.694-1.333-3.464 0
                                 L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                </div>
                
                <h3 class="text-2xl font-bold text-gray-900 mb-4"><?php echo $t['delete_category']; ?></h3>
                <p class="text-gray-600 mb-8" id="categoryDeleteConfirmText"><?php echo $t['delete_category_confirm']; ?></p>
                
                <div class="flex justify-center space-x-4">
                    <button onclick="hideCategoryDeleteConfirmModal()" 
                            class="px-4 py-2 md:px-6 md:py-3 bg-white border border-gray-300 text-gray-700 rounded-xl hover:bg-gray-50 transition-all duration-300 text-sm md:text-base">
                        <?php echo $t['cancel']; ?>
                    </button>
                    <button id="confirmCategoryDeleteButton"
                            class="px-4 py-2 md:px-6 md:py-3 bg-gradient-to-r from-red-500 to-red-600 text-white rounded-xl hover:shadow-lg transition-all duration-300 text-sm md:text-base">
                        <?php echo $t['delete']; ?>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirm Modal -->
<div id="deleteConfirmModal" class="fixed inset-0 z-50 hidden" onclick="modalBackdropClick(event, hideDeleteConfirmModal)">
    <div class="min-h-screen px-4 flex items-center justify-center">
        <div class="relative bg-white rounded-2xl max-w-md w-full p-8" onclick="event.stopPropagation()">
            <button onclick="hideDeleteConfirmModal()" class="absolute top-4 right-4 text-gray-400 hover:text-gray-500">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                          d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
            
            <div class="text-center">
                <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-red-100 mb-6">
                    <svg class="h-10 w-10 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                              d="M12 9v2m0 4h.01m-6.938 4
                                 h13.856c1.54 0 2.502-1.667 1.732-3
                                 L13.732 4c-.77-1.333-2.694-1.333-3.464 0
                                 L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                </div>
                
                <h3 class="text-2xl font-bold text-gray-900 mb-4"><?php echo $t['confirm_delete']; ?></h3>
                <p class="text-gray-600 mb-8" id="deleteConfirmText"></p>
                
                <div class="flex justify-center space-x-4">
                    <button onclick="hideDeleteConfirmModal()" 
                            class="px-4 py-2 md:px-6 md:py-3 bg-white border border-gray-300 text-gray-700 rounded-xl hover:bg-gray-50 transition-all duration-300 text-sm md:text-base">
                        <?php echo $t['cancel']; ?>
                    </button>
                    <button id="confirmDeleteButton"
                            class="px-4 py-2 md:px-6 md:py-3 bg-gradient-to-r from-red-500 to-red-600 text-white rounded-xl hover:shadow-lg transition-all duration-300 text-sm md:text-base">
                        <?php echo $t['delete']; ?>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- GSAP -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
<script>
let categoryIdToDelete = null;
let serviceIdToDelete = null;
let extraLanguagesData = {};
let categoryExtraLangsData = {};

function createLanguageForms() {
    const languages = ['de', 'ru', 'es', 'zh', 'fr', 'it'];
    const languageNames = {
        de: 'Deutsch',
        ru: 'Русский',
        es: 'Español',
        zh: '中文',
        fr: 'Français',
        it: 'Italiano'
    };
    
    const formsContainer = document.querySelector('.category-language-forms');
    formsContainer.innerHTML = '';
    
    languages.forEach(lang => {
        const form = document.createElement('div');
        form.id = `category-lang-form-${lang}`;
        form.className = 'category-lang-form hidden';
        form.innerHTML = `
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Name (${languageNames[lang]})</label>
                <input type="text" name="category_name_${lang}" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500">
            </div>
        `;
        formsContainer.appendChild(form);
    });
}

document.addEventListener('DOMContentLoaded', createLanguageForms);

function showCategoryExtraLangsModal() {
    const categoryModal = document.getElementById('categoryModal');
    const extraModal = document.getElementById('categoryExtraLangsModal');
    
    animateModal(categoryModal, false, () => {
        extraModal.style.display = 'block';
        animateModal(extraModal, true);
        switchCategoryLanguage('de');
    });
}

function hideCategoryExtraLangsModal() {
    const categoryModal = document.getElementById('categoryModal');
    const extraModal = document.getElementById('categoryExtraLangsModal');
    
    animateModal(extraModal, false, () => {
        categoryModal.style.display = 'block';
        animateModal(categoryModal, true);
    });
}

function switchCategoryLanguage(lang) {
    document.querySelectorAll('.category-lang-form').forEach(form => {
        form.classList.add('hidden');
    });
    
    document.getElementById(`category-lang-form-${lang}`).classList.remove('hidden');
    
    document.querySelectorAll('.category-lang-tab').forEach(tab => {
        if (tab.dataset.lang === lang) {
            tab.classList.add('bg-indigo-100', 'text-indigo-700');
            tab.classList.remove('text-gray-500');
        } else {
            tab.classList.remove('bg-indigo-100', 'text-indigo-700');
            tab.classList.add('text-gray-500');
        }
    });
}

function saveCategoryExtraLanguages() {
    const languages = ['de', 'ru', 'es', 'zh', 'fr', 'it'];
    const data = {};
    
    languages.forEach(lang => {
        const nameInput = document.querySelector(`[name="category_name_${lang}"]`);
        if (nameInput.value.trim()) {
            data[lang] = {
                name: nameInput.value.trim()
            };
        }
    });
    
    categoryExtraLangsData = data;
    document.getElementById('categoryExtraLangsData').value = JSON.stringify(data);
    
    hideCategoryExtraLangsModal();
    showNotification('✅', translations.extra_languages_saved);
}


// Extra Languages Modal'ını göster
function showExtraLanguagesModal() {
    const serviceModal = document.getElementById('serviceModal');
    const extraModal = document.getElementById('extraLanguagesModal');
    
    // Önce service modal'ını kapat
    animateModal(serviceModal, false, () => {
        // Service modal kapandıktan sonra extra languages modal'ı aç
        extraModal.style.display = 'block';
        animateModal(extraModal, true);
        // İlk dil tab'ını aktif et
        switchLanguage('de');
    });
}

// Extra Languages Modal'ını kapat
function hideExtraLanguagesModal() {
    const serviceModal = document.getElementById('serviceModal');
    const extraModal = document.getElementById('extraLanguagesModal');
    
    // Extra languages modal'ını kapat
    animateModal(extraModal, false, () => {
        // Extra modal kapandıktan sonra service modal'ı aç
        serviceModal.style.display = 'block';
        animateModal(serviceModal, true);
    });
}
function switchLanguage(lang) {
    // Tüm formları gizle
    document.querySelectorAll('.lang-form').forEach(form => {
        form.classList.add('hidden');
    });
    
    // Seçilen dil formunu göster
    document.getElementById(`lang-form-${lang}`).classList.remove('hidden');
    
    // Tab'ları güncelle
    document.querySelectorAll('.lang-tab').forEach(tab => {
        if (tab.dataset.lang === lang) {
            tab.classList.add('bg-indigo-100', 'text-indigo-700');
            tab.classList.remove('text-gray-500');
        } else {
            tab.classList.remove('bg-indigo-100', 'text-indigo-700');
            tab.classList.add('text-gray-500');
        }
    });
}

function saveExtraLanguages() {
    const languages = ['de', 'ru', 'es', 'zh', 'fr', 'it'];
    const data = {};
    
    languages.forEach(lang => {
        const nameInput = document.querySelector(`[name="name_${lang}"]`);
        const descInput = document.querySelector(`[name="description_${lang}"]`);
        
        if (nameInput.value.trim() || descInput.value.trim()) {
            data[lang] = {
                name: nameInput.value.trim(),
                description: descInput.value.trim()
            };
        }
    });
    
    extraLanguagesData = data;
    document.getElementById('extraLanguagesData').value = JSON.stringify(data);
    
    // Extra languages modal'ını kapat ve service modal'a geri dön
    hideExtraLanguagesModal();
    showNotification('✅', translations.extra_languages_saved);
}



/**  Modal backdrop tıklayınca kapatma **/
function modalBackdropClick(event, hideFn) {
  if (event.target === event.currentTarget) {
    hideFn();
  }
}

function hideCategoryModal() {
    const modal = document.getElementById('categoryModal');
    animateModal(modal, false);
}
function showCategoryModal() {
    const modal = document.getElementById('categoryModal');
    modal.style.display = 'block';
    animateModal(modal, true);
    loadCategories(); // Kategori listesini çeker
}

function hideServiceModal() {
    const modal = document.getElementById('serviceModal');
    animateModal(modal, false);
}

function hideDeleteConfirmModal() {
    const modal = document.getElementById('deleteConfirmModal');
    animateModal(modal, false);
    serviceIdToDelete = null;
}

function hideCategoryDeleteConfirmModal() {
    const modal = document.getElementById('categoryDeleteConfirmModal');
    animateModal(modal, false);
    categoryIdToDelete = null;
}

/** Animasyon **/
// Modal animasyonu için callback desteği ekleyelim
function animateModal(modal, show, callback = null) {
    const modalContent = modal.querySelector('.relative.bg-white');
    if (!modalContent) return;
    
    if (show) {
        modal.style.display = 'block';
        gsap.fromTo(modalContent, 
            { opacity: 0, y: 20 },
            { 
                opacity: 1, 
                y: 0, 
                duration: 0.3, 
                ease: "power2.out",
                onComplete: () => {
                    if (callback) callback();
                }
            }
        );
    } else {
        gsap.to(modalContent, {
            opacity: 0,
            y: 20,
            duration: 0.2,
            ease: "power2.in",
            onComplete: () => {
                modal.style.display = 'none';
                if (callback) callback();
            }
        });
    }
}

/** Kategori yükleme **/
function loadCategories() {
    fetch('get_categories.php')
        .then(response => {
            if (!response.ok) {
                return response.text().then(text => { throw new Error(text); });
            }
            return response.json();
        })
        .then(data => {
            if (!data.success) {
                showNotification('❌', data.message || translations.error_loading_categories);
                return;
            }
            const categoryList = document.getElementById('categoryList');
            categoryList.innerHTML = '';
            data.data.forEach(category => {
                const categoryItem = document.createElement('div');
                categoryItem.className = 'flex items-center justify-between p-4 bg-gray-50 rounded-xl';
                categoryItem.innerHTML = `
                    <div class="flex flex-col">
                        <span class="font-medium text-gray-700">${category.name}</span>
                    </div>
                    <button onclick="deleteCategory(${category.id}, '${category.name.replace(/'/g, "\\'")}')"
                            class="text-red-500 hover:text-red-700 transition-colors duration-300">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M19 7l-.867 12.142A2 2 0 0116.138 21
                                     H7.862a2 2 0 01-1.995-1.858L5 7
                                     m5 4v6m4-6v6
                                     m1-10V4a1 1 0 00-1-1h-4
                                     a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                    </button>
                `;
                categoryList.appendChild(categoryItem);
            });
        })
        .catch(error => {
            console.error('Load Categories Error:', error);
            showNotification('❌', error.message || translations.error_loading_categories);
        });
}

function deleteCategory(id, name) {
    showCategoryDeleteConfirmModal(id, name);
}

function showCategoryDeleteConfirmModal(id, categoryName) {
    const modal = document.getElementById('categoryDeleteConfirmModal');
    const confirmText = document.getElementById('categoryDeleteConfirmText');

    let textTemplate = "<?php echo $t['category_delete_confirm_text']; ?>";
    let irreversibleText = "<?php echo $t['this_action_irreversible']; ?>";
    let finalText = textTemplate.replace('{name}', categoryName);
    
    confirmText.innerHTML = `<strong>${finalText}</strong><br><br>${irreversibleText}`;
    categoryIdToDelete = id;

    modal.style.display = 'block';
    animateModal(modal, true);
}

function confirmDeleteCategory() {
   if (!categoryIdToDelete) return;
   
   const modal = document.getElementById('categoryDeleteConfirmModal'); 
   const confirmButton = document.getElementById('confirmCategoryDeleteButton');
   
   confirmButton.disabled = true;
   confirmButton.innerHTML = `
       <svg class="animate-spin h-5 w-5 text-white inline mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
           <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
           <path class="opacity-75" fill="currentColor"
               d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4
                   zm2 5.291A7.962 7.962 0 014 12H0
                   c0 3.042 1.135 5.824 3 7.938
                   l3-2.647z"></path>
       </svg>
       ${translations.deleting}...
   `;

   fetch('category_process.php', {
       method: 'POST',
       headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
       body: `action=delete&category_id=${categoryIdToDelete}`
   })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            modal.style.display = 'none';
            categoryIdToDelete = null;
            confirmButton.disabled = false;
            confirmButton.innerHTML = '<?php echo $t["delete"]; ?>';
            loadCategories();
            showNotification('✅', data.message);
        } else {
            showNotification('❌', data.message);
            confirmButton.disabled = false;
            confirmButton.innerHTML = '<?php echo $t["delete"]; ?>';
        }
    })
    .catch(error => {
        console.error('Silme hatası:', error);
        showNotification('❌', 'Kategori silinirken bir hata oluştu');
        confirmButton.disabled = false;
        confirmButton.innerHTML = '<?php echo $t["delete"]; ?>';
    });
}

document.addEventListener('DOMContentLoaded', function() {
    const confirmCatDelBtn = document.getElementById('confirmCategoryDeleteButton');
    if (confirmCatDelBtn) {
        confirmCatDelBtn.addEventListener('click', confirmDeleteCategory);
    }

    /* --- BURASI: Kategori Formu Submit (Ekle) Dinleyici --- */
    const categoryForm = document.getElementById('categoryForm');
    if (categoryForm) {
        categoryForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData();
            
            formData.append('action', 'add');
            formData.append('name_tr', document.getElementById('newCategoryName').value.trim());
            formData.append('name_en', document.getElementById('newCategoryNameEn').value.trim());
            
            if (Object.keys(categoryExtraLangsData).length > 0) {
                formData.append('extra_languages', JSON.stringify(categoryExtraLangsData));
            }

            fetch('category_process.php', {
                method: 'POST',
                body: formData
            })
            .then(resp => resp.json())
            .then(data => {
                if (data.success) {
                    showNotification('✅', data.message);
                    e.target.reset();
                    categoryExtraLangsData = {};
                    loadCategories();
                } else {
                    showNotification('❌', data.message);
                }
            })
            .catch(error => {
                showNotification('❌', 'Bir hata oluştu');
            });
        });
    }
});

// Service
function showAddServiceModal() {
    const modal = document.getElementById('serviceModal');
    const form = document.getElementById('serviceForm');
    const modalTitle = document.getElementById('modalTitle');
    const submitText = document.getElementById('submitText');
    const currentImage = document.getElementById('currentImage');
    const previewPlaceholder = document.getElementById('previewPlaceholder');
    
    modalTitle.textContent = translations.add_new_service;
    submitText.textContent = translations.save;
    form.reset();
    form.elements.action.value = 'add';
    currentImage.classList.add('hidden');
    previewPlaceholder.classList.remove('hidden');
    
    // Extra languages verilerini sıfırla
    extraLanguagesData = {};
    document.getElementById('extraLanguagesData').value = '';
    
    loadCategoryOptions();
    
    modal.style.display = 'block';
    animateModal(modal, true);
}

function loadCategoryOptions() {
    return fetch('get_categories.php')
        .then(response => response.json())
        .then(res => {
            if (!res.success) {
                showNotification('❌', translations.error_loading_categories);
                return;
            }
            const select = document.getElementById('serviceCategory');
            select.innerHTML = '<option value="">' + translations.select_category + '</option>';
            res.data.forEach(category => {
                const option = document.createElement('option');
                option.value = category.id;
                option.textContent = category.name;
                select.appendChild(option);
            });
        });
}

// Edit Service (form doldurma)
function editService(id) {
   fetch(`get_service.php?id=${id}`)
       .then(response => response.json())
       .then(data => {
           if (data.success) {
               const modal = document.getElementById('serviceModal');
               const form = document.getElementById('serviceForm');
               const modalTitle = document.getElementById('modalTitle');
               const submitText = document.getElementById('submitText');
               const currentImage = document.getElementById('currentImage');
               const previewPlaceholder = document.getElementById('previewPlaceholder');
               
               // Extra languages verilerini yükle
               if (data.data.extra_languages) {
                   extraLanguagesData = JSON.parse(data.data.extra_languages);
                   // Form alanlarını doldur
                   Object.keys(extraLanguagesData).forEach(lang => {
                       const nameInput = document.querySelector(`[name="name_${lang}"]`);
                       const descInput = document.querySelector(`[name="description_${lang}"]`);
                       if (nameInput) nameInput.value = extraLanguagesData[lang].name || '';
                       if (descInput) descInput.value = extraLanguagesData[lang].description || '';
                   });
               }

               modalTitle.textContent = translations.edit_service;
               submitText.textContent = translations.update;
               
               loadCategoryOptions().then(() => {
                   form.elements.category_id.value = data.data.category_id || '';
               });
               
               form.elements.action.value = 'edit';
               form.elements.service_id.value = data.data.id;
               form.elements.name.value = data.data.name;
               form.elements.name_en.value = data.data.name_en;
               form.elements.description.value = data.data.description;
               form.elements.description_en.value = data.data.description_en;
               form.elements.price.value = data.data.price;
               
               if (data.data.image_url) {
                   currentImage.src = `/health_tourism/assets/images/services/${data.data.image_url}`;
                   currentImage.classList.remove('hidden');
                   previewPlaceholder.classList.add('hidden');
               } else {
                   currentImage.classList.add('hidden');
                   previewPlaceholder.classList.remove('hidden');
               }
               
               modal.style.display = 'block';
               animateModal(modal, true);
           } else {
               showNotification('❌', translations.error_loading_service);
           }
       })
       .catch(error => {
           console.error('Error:', error);
           showNotification('❌', translations.error_loading_service);
       });
}

function confirmDelete(id, name, doctorCount) {
    const modal = document.getElementById('deleteConfirmModal');
    const confirmText = document.getElementById('deleteConfirmText');
    const confirmButton = document.getElementById('confirmDeleteButton');
    
    serviceIdToDelete = id;
    
    if (doctorCount > 0) {
        // Doktor varsa
        const message = '<?php echo $t["delete_service_with_doctors"]; ?>';
        confirmText.innerHTML = `<strong>${name}</strong> - ${message.replace('{count}', doctorCount)}`;
        confirmButton.style.display = 'none';
    } else {
        // Doktor yoksa normal sil
        const message = '<?php echo $t["delete_service_confirm"]; ?>';
        confirmText.innerHTML = `<strong>${name}</strong> - ${message}`;
        confirmButton.style.display = 'inline-flex';
        confirmButton.disabled = false;
        confirmButton.textContent = '<?php echo $t["delete"]; ?>';
    }
    modal.style.display = 'block';
    animateModal(modal, true);
}

document.addEventListener('DOMContentLoaded', function() {
    const delBtn = document.getElementById('confirmDeleteButton');
    if (delBtn) {
        delBtn.addEventListener('click', function() {
            if (serviceIdToDelete) {
                deleteService(serviceIdToDelete);
            }
        });
    }
});

function deleteService(id) {
    const confirmButton = document.getElementById('confirmDeleteButton');
    confirmButton.disabled = true;
    confirmButton.innerHTML = `
        <svg class="animate-spin h-5 w-5 text-white inline mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor"
                  d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4
                     zm2 5.291A7.962 7.962 0 014 12H0
                     c0 3.042 1.135 5.824 3 7.938
                     l3-2.647z"></path>
        </svg>
        ${translations.deleting}...
    `;
    fetch('service_process.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=delete&service_id=${id}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const serviceCard = document.querySelector(`[data-service-id="${id}"]`);
            if (serviceCard) {
                gsap.to(serviceCard, {
                    scale: 0.9,
                    opacity: 0,
                    duration: 0.3,
                    ease: "power2.in",
                    onComplete: () => {
                        serviceCard.remove();
                        hideDeleteConfirmModal();
                        showNotification('✅', data.message);
                        
                        const remainingServices = document.querySelectorAll('[data-service-id]');
                        if (remainingServices.length === 0) {
                            setTimeout(() => {
                                location.reload();
                            }, 1500);
                        }
                    }
                });
            }
        } else {
            showNotification('❌', data.message);
            confirmButton.disabled = false;
            confirmButton.textContent = '<?php echo $t["delete"]; ?>';
        }
    })
    .catch(error => {
        showNotification('❌', 'Bir hata oluştu');
        confirmButton.disabled = false;
        confirmButton.textContent = '<?php echo $t["delete"]; ?>';
    });
}

// Form submit (Service)
document.getElementById('serviceForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const submitButton = this.querySelector('[type="submit"]');
    const originalText = submitButton.innerHTML;
    submitButton.disabled = true;
    submitButton.innerHTML = `
        <svg class="animate-spin h-5 w-5 text-white inline mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor"
                  d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4
                     zm2 5.291A7.962 7.962 0 014 12H0
                     c0 3.042 1.135 5.824 3 7.938
                     l3-2.647z"></path>
        </svg>
        <?php echo $t["processing"]; ?>...
    `;

    fetch('service_process.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            hideServiceModal();
            showNotification('✅', data.message);
            setTimeout(() => {
                location.reload();
            }, 1500);
        } else {
            showNotification('❌', data.message);
        }
        submitButton.disabled = false;
        submitButton.innerHTML = originalText;
    })
    .catch(error => {
        showNotification('❌', 'Bir hata oluştu');
        submitButton.disabled = false;
        submitButton.innerHTML = originalText;
    });
});

function toggleServiceStatus(id, currentStatus) {
    const newStatus = currentStatus === 'active' ? 'inactive' : 'active';
    fetch('service_process.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=toggle_status&service_id=${id}&status=${newStatus}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const serviceCard = document.querySelector(`[data-service-id="${id}"]`);
            showNotification('✅', data.message);
            if (serviceCard) {
                gsap.to(serviceCard, {
                    scale: 0.9,
                    opacity: 0,
                    duration: 0.3,
                    ease: "power2.in",
                    onComplete: () => {
                        serviceCard.remove();
                        const remainingServices = document.querySelectorAll('[data-service-id]');
                        if (remainingServices.length === 0) {
                            setTimeout(() => {
                                const currentUrl = new URL(window.location.href);
                                const showInactive = currentUrl.searchParams.get('show_inactive');
                                if ((newStatus === 'inactive' && showInactive !== 'true') 
                                    || (newStatus === 'active' && showInactive === 'true')) {
                                    window.location.href = `?show_inactive=${newStatus === 'inactive'}`;
                                } else {
                                    location.reload();
                                }
                            }, 1000);
                        }
                    }
                });
            }
        } else {
            showNotification('❌', data.message);
        }
    })
    .catch(error => {
        showNotification('❌', 'Bir hata oluştu');
    });
}

// Notification
function showNotification(icon, message) {
    const existingNotification = document.querySelector('.notification');
    if (existingNotification) {
        existingNotification.remove();
    }
    const notification = document.createElement('div');
    notification.className = `fixed top-24 right-6 bg-white px-6 py-4 rounded-xl shadow-2xl flex items-center space-x-3 z-[9999] notification transform translate-x-full`;
    notification.innerHTML = `
        <span class="text-2xl">${icon}</span>
        <span class="text-gray-800 font-medium">${message}</span>
    `;
    document.body.appendChild(notification);
    gsap.to(notification, {
        x: 0,
        duration: 0.5,
        ease: "back.out(1.7)"
    });
    setTimeout(() => {
        gsap.to(notification, {
            x: '120%',
            duration: 0.3,
            ease: "power2.in",
            onComplete: () => notification.remove()
        });
    }, 3000);
}

// Fotoğraf önizleme
document.getElementById('serviceImage').addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const currentImage = document.getElementById('currentImage');
            const previewPlaceholder = document.getElementById('previewPlaceholder');
            currentImage.src = e.target.result;
            currentImage.classList.remove('hidden');
            previewPlaceholder.classList.add('hidden');
        }
        reader.readAsDataURL(file);
    }
});
</script>

<?php include_once '../includes/new-footer.php'; ?>
