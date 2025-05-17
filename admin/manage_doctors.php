<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
include_once '../config/database.php';
include_once '../includes/new-header.php';


$lang = $_SESSION['lang'] ?? 'tr';
$translations_file = dirname(__DIR__) . "/translations/translation_{$lang}.php";

if (file_exists($translations_file)) {
    $t = require $translations_file;
} else {
    die("Translation file not found: {$translations_file}");
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: /health_tourism/login.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

$showInactive = isset($_GET['show_inactive']) && $_GET['show_inactive'] === 'true';
$service_name_field = ($lang === 'en') ? 's.name_en' : 's.name';

$query = "SELECT 
    d.*, 
    GROUP_CONCAT($service_name_field SEPARATOR ', ') AS service_names
  FROM doctors d
  LEFT JOIN doctor_services ds ON d.id = ds.doctor_id
  LEFT JOIN services s ON ds.service_id = s.id";

if ($showInactive) {
    $query .= " WHERE d.status = 'inactive'";
} else {
    $query .= " WHERE d.status = 'active' OR d.status IS NULL";
}
$query .= " GROUP BY d.id ORDER BY d.name ASC";

$stmt = $db->prepare($query);
$stmt->execute();
$doctors = $stmt->fetchAll(PDO::FETCH_ASSOC);

$service_name_select = ($lang === 'en') ? 'name_en' : 'name';
$servicesQuery = "SELECT id, $service_name_select AS name
                  FROM services
                  WHERE (status = 'active' OR status IS NULL)
                  ORDER BY name ASC";
$stmtSrv = $db->prepare($servicesQuery);
$stmtSrv->execute();
$allServices = $stmtSrv->fetchAll(PDO::FETCH_ASSOC);
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
    .doctor-card {
        display: flex;
        flex-direction: column;
        height: 100%;
        background: rgba(255, 255, 255, 0.95);
        border: 1px solid rgba(255, 255, 255, 0.8);
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
        transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .doctor-card:hover {
        transform: translateY(-5px) scale(1.01);
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.12);
    }
    .biography-text {
        display: -webkit-box;
        -webkit-line-clamp: 4;
        -webkit-box-orient: vertical;
        overflow: hidden;
        min-height: 6rem;
        max-height: 6rem;
    }
    .line-clamp-4 {
        display: -webkit-box;
        -webkit-line-clamp: 4;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
    .notification {
        position: fixed;
        top: 20px;
        right: 20px;
        background-color: #fff;
        padding: 15px 20px;
        border-radius: 8px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        display: flex;
        align-items: center;
        gap: 10px;
        z-index: 1000;
    }
    .service-list li {
        background: rgba(255, 255, 255, 0.95);
        border: 1px solid rgba(229, 231, 235, 0.8);
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.02);
        margin-bottom: 8px;
        padding: 12px 16px;
        border-radius: 12px;
        cursor: pointer;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        display: flex;
        align-items: center;
        gap: 8px;
        position: relative;
        overflow: hidden;
    }
    .service-list li:hover {
        background: rgba(249, 250, 251, 0.95);
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
    }
    .service-list li::before {
        content: "";
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: linear-gradient(45deg, #4F46E5, #6366F1);
        opacity: 0;
        transition: opacity 0.3s ease;
        z-index: 1;
    }
    .service-list li:hover::before {
        opacity: 0.05;
    }
    .service-list li .service-icon {
        color: #6366F1;
        transition: transform 0.3s ease;
    }
    .service-list li:hover .service-icon {
        transform: scale(1.1);
    }
    #leftServiceList li::after,
    #rightServiceList li::after {
        content: "→";
        position: absolute;
        right: 16px;
        opacity: 0;
        transform: translateX(-10px);
        transition: all 0.3s ease;
    }
    #leftServiceList li:hover::after {
        opacity: 1;
        transform: translateX(0);
    }
    #rightServiceList li::after {
        content: "←";
    }
    #rightServiceList li:hover::after {
        opacity: 1;
        transform: translateX(0);
    }
    .service-container {
        background: rgba(255, 255, 255, 0.5);
        backdrop-filter: blur(12px);
        border: 1px solid rgba(229, 231, 235, 0.8);
        border-radius: 16px;
        padding: 20px;
        height: 300px;
        overflow-y: auto;
        scrollbar-width: thin;
        scrollbar-color: #6366F1 #F3F4F6;
    }
    .service-container::-webkit-scrollbar {
        width: 6px;
    }
    .service-container::-webkit-scrollbar-track {
        background: #F3F4F6;
        border-radius: 3px;
    }
    .service-container::-webkit-scrollbar-thumb {
        background: #6366F1;
        border-radius: 3px;
    }
</style>

<script>
    const translations = <?php echo json_encode($t); ?>;
</script>

<div class="min-h-screen luxury-gradient pt-20">
    <div class="max-w-7xl mx-auto px-4 py-8">
        <div class="flex flex-col md:flex-row justify-between items-center mb-12">
            <div>
                <h1 class="text-4xl font-light mb-4 text-gray-800">
                    <?php echo $t['doctor_management']; ?>
                    <span class="block text-3xl font-semibold mt-3 premium-gradient">
                        <?php echo $showInactive ? $t['inactive_doctors'] : $t['active_doctors']; ?>
                    </span>
                </h1>
                <p class="text-gray-600"><?php echo $t['manage_doctors_info']; ?></p>
            </div>
            <div class="flex gap-4 mt-4 md:mt-0">
                <a href="?show_inactive=<?php echo $showInactive ? 'false' : 'true'; ?>" 
                   class="px-8 py-4 bg-white border border-gray-300 text-gray-700 rounded-full 
                          hover:bg-gray-50 transition-all duration-300 flex items-center gap-2">
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
                        <?php echo $t['show_active_doctors']; ?>
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
                        <?php echo $t['show_inactive_doctors']; ?>
                    <?php endif; ?>
                </a>
                <?php if (!$showInactive): ?>
                <button onclick="showAddDoctorModal()" 
                        class="px-8 py-4 bg-gradient-to-r from-indigo-600 to-indigo-700 
                               text-white rounded-full transform hover:-translate-y-1 hover:shadow-xl 
                               transition-all duration-300 flex items-center">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                              d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                    </svg>
                    <?php echo $t['add_new_doctor']; ?>
                </button>
                <?php endif; ?>
            </div>
        </div>

        <div id="deleteConfirmModal" class="fixed inset-0 z-50 hidden overflow-y-auto">
            <div class="flex items-center justify-center min-h-screen p-4">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"></div>
                <div class="relative bg-white rounded-2xl max-w-md w-full p-8 transform transition-all">
                    <div class="text-center">
                        <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-red-100 mb-6">
                            <svg class="w-10 h-10 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                      d="M19 7l-.867 12.142A2 2 0 0116.138 21
                                         H7.862a2 2 0 01-1.995-1.858
                                         L5 7m5 4v6m4-6v6
                                         m1-10V4
                                         a1 1 0 00-1-1h-4
                                         a1 1 0 00-1 1v3
                                         M4 7h16" />
                            </svg>
                        </div>
                        <h3 class="text-2xl font-bold text-gray-900 mb-4"><?php echo $t['confirm_delete']; ?></h3>
                        <p class="text-gray-600 mb-8" id="deleteConfirmText"></p>
                        <div class="flex justify-center space-x-4">
                            <button onclick="hideDeleteConfirmModal()" 
                                    class="px-6 py-3 bg-white border border-gray-300 text-gray-700 rounded-xl hover:bg-gray-50 transition-all duration-300">
                                <?php echo $t['cancel']; ?>
                            </button>
                            <button id="confirmDeleteButton"
                                    class="px-6 py-3 bg-gradient-to-r from-red-500 to-red-600 text-white rounded-xl hover:shadow-lg transform hover:scale-105 transition-all duration-300">
                                <?php echo $t['delete']; ?>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div id="doctorModal" class="fixed inset-0 z-50 hidden overflow-y-auto">
            <div class="min-h-screen px-4 flex items-center justify-center">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"></div>
                <div class="relative bg-white rounded-2xl max-w-2xl w-full p-8 transform transition-all">
                    <div class="absolute top-4 right-4">
                        <button onclick="hideDoctorModal()" class="text-gray-400 hover:text-gray-500 transition-colors duration-300">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                    <div class="p-8">
                        <h3 class="text-2xl font-bold mb-6 text-gray-900" id="modalTitle"><?php echo $t['add_new_doctor']; ?></h3>
                        <form id="doctorForm" action="doctor_process.php" method="POST" enctype="multipart/form-data" class="space-y-6">
                            <input type="hidden" name="action" value="add">
                            <input type="hidden" name="doctor_id" id="doctorId">
                            <input type="hidden" name="service_ids_string" id="serviceIdsString" value="">

                            <div class="space-y-6">
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2"><?php echo $t['doctor_name']; ?></label>
                                        <input type="text" name="name" id="doctorName" required class="w-full px-4 py-3 bg-white border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all duration-300">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2"><?php echo $t['specialty']; ?></label>
                                        <input type="text" name="specialty" id="doctorSpecialty" required class="w-full px-4 py-3 bg-white border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all duration-300">
                                    </div>
                                </div>
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2"><?php echo $t['biography_tr']; ?></label>
                                        <textarea name="bio_tr" id="doctorBioTr" required maxlength="500" rows="4" placeholder="Maksimum 500 karakter" class="w-full px-4 py-3 bg-white border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all duration-300 resize-none line-clamp-4"></textarea>
                                        <small class="text-gray-500 mt-1 block" id="bioTrCount">0/500</small>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2"><?php echo $t['biography_en']; ?></label>
                                        <textarea name="bio_en" id="doctorBioEn" required maxlength="500" rows="4" placeholder="Maximum 500 characters" class="w-full px-4 py-3 bg-white border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all duration-300 resize-none line-clamp-4"></textarea>
                                        <small class="text-gray-500 mt-1 block" id="bioEnCount">0/500</small>
                                    </div>
                                </div>
                                <div class="grid grid-cols-2 gap-6">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2"><?php echo $t['service_provided'].' ('.$t['not_assigned'].')'; ?></label>
                                        <div class="service-container">
                                            <ul id="leftServiceList" class="service-list"></ul>
                                        </div>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2"><?php echo $t['assigned_service']; ?></label>
                                        <div class="service-container">
                                            <ul id="rightServiceList" class="service-list"></ul>
                                        </div>
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2"><?php echo $t['photo']; ?></label>
                                    <div class="grid grid-cols-2 gap-4">
                                        <div>
                                            <label id="dropZone" class="relative flex flex-col items-center justify-center w-full h-40 border-2 border-dashed border-gray-300 rounded-xl hover:border-indigo-500 transition-colors duration-300 cursor-pointer bg-white">
                                                <div class="flex flex-col items-center justify-center pt-5 pb-6">
                                                    <svg class="w-10 h-10 text-gray-400 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                                              d="M7 16a4 4 0 01-.88-7.903
                                                                 A5 5 0 1115.9 6L16 6
                                                                 a5 5 0 011 9.9
                                                                 M15 13l-3-3m0 0l-3 3m3-3v12" />
                                                    </svg>
                                                    <p class="mb-2 text-sm text-gray-500">
                                                        <span class="font-semibold">Fotoğraf yüklemek için tıklayın</span>
                                                    </p>
                                                    <p class="text-xs text-gray-500">PNG, JPG veya GIF (MAX. 2MB)</p>
                                                </div>
                                                <input type="file" name="image" id="doctorImage" accept="image/*" class="hidden">
                                            </label>
                                        </div>
                                        <div>
                                            <div class="w-full h-40 bg-gray-50 rounded-xl overflow-hidden border border-gray-200">
                                                <img id="currentImage" src="" alt="<?php echo $t['photo']; ?>" class="w-full h-full object-cover hidden">
                                                <div id="previewPlaceholder" class="w-full h-full flex items-center justify-center text-gray-400">
                                                    <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
                            </div>
                            <div class="flex justify-end space-x-4 mt-8">
                                <button type="button" onclick="hideDoctorModal()" class="px-6 py-3 bg-white border border-gray-300 text-gray-700 rounded-xl hover:bg-gray-50 transition-all duration-300">
                                    <?php echo $t['cancel']; ?>
                                </button>
                                <button type="submit" class="px-6 py-3 bg-gradient-to-r from-indigo-600 to-indigo-700 text-white rounded-xl hover:shadow-lg transform hover:scale-105 transition-all duration-300">
                                    <span id="submitText"><?php echo $t['add_doctor']; ?></span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <?php if (empty($doctors)): ?>
            <div class="flex flex-col items-center justify-center min-h-[400px] bg-white/50 backdrop-blur-sm rounded-2xl p-8 text-center">
                <div class="mb-6">
                    <svg class="w-20 h-20 text-gray-400 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                              d="M13 10V3L4 14h7v7l9-11h-7z" />
                    </svg>
                </div>
                <h3 class="text-2xl font-semibold text-gray-800 mb-2">
                    <?php echo $showInactive ? $t['inactive_doctors'] : $t['no_doctors_added']; ?>
                </h3>
                <p class="text-gray-600">
                    <?php echo $showInactive ? '' : $t['add_doctor_info']; ?>
                </p>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-8">
                <?php foreach ($doctors as $doctor): ?>
                    <div class="relative group" data-doctor-id="<?php echo $doctor['id']; ?>">
                        <div class="doctor-card rounded-2xl overflow-hidden h-full flex flex-col">
                            <div class="relative h-48">
                                <?php if ($doctor['image_url']): ?>
                                    <img src="/health_tourism/assets/images/<?php echo htmlspecialchars($doctor['image_url']); ?>" 
                                         alt="<?php echo htmlspecialchars($doctor['name']); ?>" 
                                         class="doctor-image w-full h-full object-cover">
                                <?php else: ?>
                                    <div class="doctor-image-placeholder w-full h-full bg-gradient-to-br from-gray-100 to-gray-200 flex items-center justify-center">
                                        <svg class="w-16 h-16 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                                  d="M16 7a4 4 0 11-8 0 4 4 0 018 0z
                                                     M12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                        </svg>
                                    </div>
                                <?php endif; ?>
                                <div class="absolute top-3 left-3 bg-white/90 backdrop-blur-md px-3 py-1 rounded-full">
                                    <span class="doctor-specialty text-indigo-600 font-medium text-sm">
                                        <?php echo htmlspecialchars($doctor['specialty']); ?>
                                    </span>
                                </div>
                                <div class="absolute top-3 right-3 flex space-x-2 opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                                    <button onclick="editDoctor(<?php echo $doctor['id']; ?>)" class="p-2 bg-white/90 hover:bg-white rounded-lg transition-colors duration-300">
                                        <svg class="w-4 h-4 text-gray-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                                  d="M11 5H6a2 2 0 00-2 2v11 a2 2 0 002 2h11a2 2 0 002-2v-5 m-1.414-9.414 a2 2 0 112.828 2.828 L11.828 15H9v-2.828 l8.586-8.586z" />
                                        </svg>
                                    </button>
                                    <button onclick="confirmDelete(<?php echo $doctor['id']; ?>, '<?php echo addslashes($doctor['name']); ?>')" 
                                            class="p-2 bg-white/90 hover:bg-white rounded-lg transition-colors duration-300">
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
                                </div>
                            </div>
                            <div class="p-6 flex flex-col justify-between flex-1">
                                <div>
                                    <h3 class="text-xl font-semibold text-gray-900 mb-2">
                                        <?php echo htmlspecialchars($doctor['name']); ?>
                                    </h3>
                                    <p class="text-gray-600 text-sm mb-4 biography-text">
                                        <?php
                                        if ($lang === 'en') {
                                            echo nl2br(htmlspecialchars($doctor['bio_en'] ?? '', ENT_QUOTES, 'UTF-8'));
                                        } else {
                                            echo nl2br(htmlspecialchars($doctor['bio_tr'] ?? '', ENT_QUOTES, 'UTF-8'));
                                        }
                                        ?>
                                    </p>
                                    <div class="flex items-center justify-between mt-4 pt-4 border-t border-gray-100">
                                        <?php if (!empty($doctor['service_names'])): ?>
                                            <span class="text-sm text-green-600 font-medium">
                                                <?php echo htmlspecialchars($doctor['service_names']); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-sm text-red-600 font-medium">
                                                <?php echo $t['not_assigned']; ?>
                                            </span>
                                        <?php endif; ?>
                                        <?php $doctorStatus = $doctor['status'] ?? 'active'; ?>
                                        <button onclick="toggleDoctorStatus(<?php echo $doctor['id']; ?>, '<?php echo $doctorStatus; ?>')" 
                                                class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium cursor-pointer transition-all duration-300 
                                                <?php echo ($doctorStatus === 'active') ? 'bg-green-100 text-green-800 hover:bg-green-200' : 'bg-red-100 text-red-800 hover:bg-red-200'; ?>">
                                            <?php echo ($doctorStatus === 'active') ? $t['active'] : $t['inactive']; ?>
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

<script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
<?php include_once '../includes/new-footer.php'; ?>

<script>
function toggleDoctorStatus(id, currentStatus) {
    const newStatus = (currentStatus === 'active') ? 'inactive' : 'active';
    fetch('doctor_process.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=toggle_status&doctor_id=${encodeURIComponent(id)}&status=${encodeURIComponent(newStatus)}`
    })
    .then(resp => resp.json())
    .then(data => {
        if (data.success) {
            showNotification('✅', data.message, 'text-green-500');
            const doctorCard = document.querySelector(`[data-doctor-id="${id}"]`);
            if (doctorCard) {
                gsap.to(doctorCard, {
                    scale: 0.9,
                    opacity: 0,
                    duration: 0.3,
                    ease: "power2.in",
                    onComplete: () => {
                        doctorCard.remove();
                        const remaining = document.querySelectorAll('[data-doctor-id]');
                        if (remaining.length === 0) {
                            setTimeout(() => location.reload(), 1000);
                        }
                    }
                });
            }
        } else {
            showNotification('❌', data.message || 'Durum güncellenemedi', 'text-red-500');
        }
    })
    .catch(err => {
        showNotification('❌', 'Bir hata oluştu', 'text-red-500');
    });
}

function showNotification(icon, message, colorClass) {
    const existingNotification = document.querySelector('.notification');
    if (existingNotification) existingNotification.remove();
    const notification = document.createElement('div');
    notification.className = `notification bg-white px-6 py-4 rounded-xl shadow-2xl flex items-center space-x-3`;
    notification.innerHTML = `<span class="${colorClass} text-2xl">${icon}</span><span class="text-gray-800 font-medium">${message}</span>`;
    document.body.appendChild(notification);
    gsap.fromTo(notification, { x: '120%' }, { x: 0, duration: 0.5, ease: "back.out(1.7)" });
    setTimeout(() => {
        gsap.to(notification, { x: '120%', duration: 0.3, ease: "power2.in", onComplete: () => notification.remove() });
    }, 3000);
}

function hideDeleteConfirmModal() {
    const modal = document.getElementById('deleteConfirmModal');
    modal.classList.add('hidden');
}

function confirmDelete(id, name) {
    const modal = document.getElementById('deleteConfirmModal');
    const confirmText = document.getElementById('deleteConfirmText');
    const confirmButton = document.getElementById('confirmDeleteButton');
    const message = translations.delete_confirmation.replace('{name}', name);
    confirmText.innerHTML = message;
    confirmButton.onclick = () => deleteDoctor(id);
    modal.classList.remove('hidden');
}

function deleteDoctor(id) {
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
       ${translations.processing}...
   `;
    fetch('doctor_process.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=delete&doctor_id=${id}`
    })
    .then(response => response.json())
    .then(data => {
        confirmButton.disabled = false;
        confirmButton.textContent = translations.delete;
        if (data.success) {
            const doctorCard = document.querySelector(`[data-doctor-id="${id}"]`);
            if (doctorCard) {
                gsap.to(doctorCard, {
                    scale: 0.9,
                    opacity: 0,
                    duration: 0.3,
                    ease: "power2.in",
                    onComplete: () => {
                        doctorCard.remove();
                        hideDeleteConfirmModal();
                        showNotification('✅', data.message, 'text-green-500');
                        const remainingDoctors = document.querySelectorAll('[data-doctor-id]');
                        if (remainingDoctors.length === 0) {
                            setTimeout(() => location.reload(), 1500);
                        }
                    }
                });
            }
        } else {
            hideDeleteConfirmModal();
            showNotification('❌', data.message || '<?php echo $t["delete_error"]; ?>', 'text-red-500');
        }
    })
    .catch(error => {
        confirmButton.disabled = false;
        confirmButton.textContent = '<?php echo $t["delete"]; ?>';
        hideDeleteConfirmModal();
        showNotification('❌', '<?php echo $t["error_occurred"]; ?>', 'text-red-500');
    });
}

function showAddDoctorModal() {
    const modal = document.getElementById('doctorModal');
    const form = document.getElementById('doctorForm');
    const modalTitle = document.getElementById('modalTitle');
    const submitText = document.getElementById('submitText');
    modalTitle.textContent = translations.add_new_doctor;
    submitText.textContent = translations.add_doctor;
    form.reset();
    form.elements.action.value = 'add';
    form.elements.doctor_id.value = '';
    document.getElementById('doctorBioTr').value = '';
    document.getElementById('doctorBioEn').value = '';
    document.getElementById('bioTrCount').textContent = '0/500';
    document.getElementById('bioEnCount').textContent = '0/500';
    const currentImage = document.getElementById('currentImage');
    const previewPlaceholder = document.getElementById('previewPlaceholder');
    currentImage.classList.add('hidden');
    previewPlaceholder.classList.remove('hidden');
    assignedServiceIds = [];
    renderLists();
    modal.classList.remove('hidden');
}

function hideDoctorModal() {
    document.getElementById('doctorModal').classList.add('hidden');
}

document.getElementById('doctorImage').addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(evt) {
            const currentImage = document.getElementById('currentImage');
            const previewPlaceholder = document.getElementById('previewPlaceholder');
            currentImage.src = evt.target.result;
            currentImage.classList.remove('hidden');
            previewPlaceholder.classList.add('hidden');
        }
        reader.readAsDataURL(file);
    }
});

document.getElementById('doctorBioTr').addEventListener('input', function() {
    document.getElementById('bioTrCount').textContent = this.value.length + '/500';
});
document.getElementById('doctorBioEn').addEventListener('input', function() {
    document.getElementById('bioEnCount').textContent = this.value.length + '/500';
});

let allServicesData = <?php echo json_encode($allServices, JSON_UNESCAPED_UNICODE); ?>;
let assignedServiceIds = [];

function moveLeftToRight(serviceId) {
   const element = document.querySelector(`#leftServiceList li[data-service-id="${serviceId}"]`);
   const targetList = document.getElementById('rightServiceList');
   
   if (element && targetList) {
       const start = element.getBoundingClientRect();
       const end = targetList.getBoundingClientRect();
       
       // Slide effect
       gsap.fromTo(element, 
           { opacity: 1 },
           {
               x: end.left - start.left + 20,
               scale: 0.8,
               opacity: 0,
               duration: 0.8,
               ease: "power2.inOut",
               onComplete: () => {
                   const sid = parseInt(serviceId);
                   if (!assignedServiceIds.includes(sid)) {
                       assignedServiceIds.push(sid);
                   }
                   renderLists();
                   
                   const newElement = document.querySelector(`#rightServiceList li[data-service-id="${serviceId}"]`);
                   if (newElement) {
                       gsap.from(newElement, {
                           scale: 0.8,
                           y: -20,
                           opacity: 0,
                           duration: 0.6,
                           ease: "back.out(1.4)"
                       });
                   }
               }
           }
       );
   }
}

function moveRightToLeft(serviceId) {
   const element = document.querySelector(`#rightServiceList li[data-service-id="${serviceId}"]`);
   const targetList = document.getElementById('leftServiceList');
   
   if (element && targetList) {
       const start = element.getBoundingClientRect();
       const end = targetList.getBoundingClientRect();
       
       gsap.fromTo(element,
           { opacity: 1 },
           {
               x: end.left - start.left - 20,
               scale: 0.8,
               opacity: 0,
               duration: 0.8,
               ease: "power2.inOut",
               onComplete: () => {
                   const sid = parseInt(serviceId);
                   assignedServiceIds = assignedServiceIds.filter(x => x !== sid);
                   renderLists();
                   
                   const newElement = document.querySelector(`#leftServiceList li[data-service-id="${serviceId}"]`);
                   if (newElement) {
                       gsap.from(newElement, {
                           scale: 0.8,
                           y: -20,
                           opacity: 0,
                           duration: 0.6,
                           ease: "back.out(1.4)"
                       });
                   }
               }
           }
       );
   }
}

function renderLists() {
    const leftServiceList = document.getElementById('leftServiceList');
    const rightServiceList = document.getElementById('rightServiceList');
    leftServiceList.innerHTML = '';
    rightServiceList.innerHTML = '';
    allServicesData.forEach(srv => {
        if (!assignedServiceIds.includes(srv.id)) {
            const li = document.createElement('li');
            li.setAttribute('data-service-id', srv.id);
            li.innerHTML = `
                <span class="service-icon">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                    </svg>
                </span>
                <span>${srv.name}</span>
            `;
            leftServiceList.appendChild(li);
        }
    });
    assignedServiceIds.forEach(id => {
        const found = allServicesData.find(x => x.id === id);
        if (found) {
            const li = document.createElement('li');
            li.setAttribute('data-service-id', found.id);
            li.innerHTML = `
                <span class="service-icon">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                </span>
                <span>${found.name}</span>
            `;
            rightServiceList.appendChild(li);
        }
    });
    if (leftServiceList.children.length === 0) {
        const emptyState = document.createElement('div');
        emptyState.className = 'text-center py-8 text-gray-500';
        emptyState.innerHTML = translations.all_services_assigned || 'Tüm hizmetler atandı';
        leftServiceList.appendChild(emptyState);
    }
    if (rightServiceList.children.length === 0) {
        const emptyState = document.createElement('div');
        emptyState.className = 'text-center py-8 text-gray-500';
        emptyState.innerHTML = 'Henüz hizmet atanmadı';
        rightServiceList.appendChild(emptyState);
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const leftList = document.getElementById('leftServiceList');
    const rightList = document.getElementById('rightServiceList');
    leftList.addEventListener('click', function(e) {
        if (e.target.closest('li')) {
            const li = e.target.closest('li');
            moveLeftToRight(li.getAttribute('data-service-id'));
        }
    });
    rightList.addEventListener('click', function(e) {
        if (e.target.closest('li')) {
            const li = e.target.closest('li');
            moveRightToLeft(li.getAttribute('data-service-id'));
        }
    });
});

document.getElementById('doctorForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const serviceIdsStringInput = document.getElementById('serviceIdsString');
    serviceIdsStringInput.value = assignedServiceIds.join(',');
    const formData = new FormData(this);
    fetch('doctor_process.php', {
        method: 'POST',
        body: formData
    })
    .then(resp => resp.text())
    .then(text => {
        let data;
        try {
            data = JSON.parse(text);
        } catch (error) {
            showNotification('❌', 'Sunucudan gelen cevap hatalı', 'text-red-500');
            return;
        }
        if (data.success) {
            hideDoctorModal();
            showNotification('✅', data.message, 'text-green-500');
            setTimeout(() => {
                location.reload();
            }, 1500);
        } else {
            showNotification('❌', data.message || 'Bir hata oluştu', 'text-red-500');
        }
    })
    .catch(err => {
        showNotification('❌', 'Bir hata oluştu: ' + err.message, 'text-red-500');
    });
});

function editDoctor(id) {
    fetch(`get_doctor.php?id=${id}`)
    .then(response => response.json())
    .then(res => {
        if (!res.success) {
            showNotification('❌', res.message || translations.doctor_info_error || 'Doktor bilgisi alınamadı', 'text-red-500');
            return;
        }
        const data = res.data;
        const modal = document.getElementById('doctorModal');
        const form = document.getElementById('doctorForm');
        const modalTitle = document.getElementById('modalTitle');
        const submitText = document.getElementById('submitText');
        const currentImage = document.getElementById('currentImage');
        const previewPlaceholder = document.getElementById('previewPlaceholder');

        assignedServiceIds = data.service_ids || [];
        document.getElementById('doctorId').value = data.id;
        form.elements.action.value = 'edit';
        modalTitle.textContent = '<?php echo $t["update_doctor"]; ?>';
        submitText.textContent = '<?php echo $t["update_doctor"]; ?>';
        form.elements.name.value = data.name;
        form.elements.specialty.value = data.specialty;
        form.elements.bio_tr.value = data.bio_tr;
        document.getElementById('bioTrCount').textContent = data.bio_tr.length + '/500';
        form.elements.bio_en.value = data.bio_en;
        document.getElementById('bioEnCount').textContent = data.bio_en.length + '/500';

        renderLists(); // Doktorun mevcut servislerini sağ tarafa, diğerlerini sol tarafa yerleştir

        if (data.image_url) {
            currentImage.src = `/health_tourism/assets/images/${data.image_url}`;
            currentImage.classList.remove('hidden');
            previewPlaceholder.classList.add('hidden');
        } else {
            currentImage.classList.add('hidden');
            previewPlaceholder.classList.remove('hidden');
        }
        modal.classList.remove('hidden');
    })
    .catch(error => {
        console.error('editDoctor error:', error);
        showNotification('❌', 'Doktor bilgisi alınırken hata oluştu', 'text-red-500');
    });
}
</script>
