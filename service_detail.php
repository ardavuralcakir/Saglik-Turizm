<?php
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 1) Ortak dosyalar (header + session + veritabanı vb.)
include_once './includes/new-header.php';
include_once './config/database.php';

// Kullanıcı dilini session veya new-header’dan alıyoruz
$lang = $_SESSION['lang'] ?? 'tr';

// 2) Çeviri dosyasını yükle
$translations_file = __DIR__ . "/translations/translation_{$lang}.php";
if (file_exists($translations_file)) {
    // Bu aşamada doğrudan $t değişkenine atayabilirsiniz:
    $t = require $translations_file;
} else {
    die("Translation file not found: {$translations_file}");
}

// ID kontrolü ve session'da saklama
if (!isset($_GET['id']) && !isset($_SESSION['last_service_id'])) {
    header("Location: services.php");
    exit();
} else {
    $serviceId = isset($_GET['id']) ? (int)$_GET['id'] : $_SESSION['last_service_id'];
    $_SESSION['last_service_id'] = $serviceId;
}

// Veritabanı
$database = new Database();
$db = $database->getConnection();

// Servis sorgusu (CASE WHEN :lang = 'en' THEN name_en ELSE name END)
$query = "
SELECT
   s.id,
   s.price,
   s.image_url,
   CASE 
      WHEN :lang = 'en' THEN COALESCE(s.name_en, s.name)
      WHEN :lang = 'de' THEN COALESCE(s.name_de, s.name_en, s.name)
      WHEN :lang = 'fr' THEN COALESCE(s.name_fr, s.name_en, s.name)
      WHEN :lang = 'es' THEN COALESCE(s.name_es, s.name_en, s.name)
      WHEN :lang = 'it' THEN COALESCE(s.name_it, s.name_en, s.name)
      WHEN :lang = 'ru' THEN COALESCE(s.name_ru, s.name_en, s.name)
      WHEN :lang = 'zh' THEN COALESCE(s.name_zh, s.name_en, s.name)
      ELSE s.name 
   END as name,
   CASE 
      WHEN :lang = 'en' THEN COALESCE(s.description_en, s.description)
      WHEN :lang = 'de' THEN COALESCE(s.description_de, s.description_en, s.description)
      WHEN :lang = 'fr' THEN COALESCE(s.description_fr, s.description_en, s.description)
      WHEN :lang = 'es' THEN COALESCE(s.description_es, s.description_en, s.description)
      WHEN :lang = 'it' THEN COALESCE(s.description_it, s.description_en, s.description)
      WHEN :lang = 'ru' THEN COALESCE(s.description_ru, s.description_en, s.description)
      WHEN :lang = 'zh' THEN COALESCE(s.description_zh, s.description_en, s.description)
      ELSE s.description
   END as description
FROM services s
WHERE s.id = :sid
LIMIT 1";
$stmt = $db->prepare($query);
$stmt->bindParam(':lang', $lang);
$stmt->bindParam(':sid', $serviceId, PDO::PARAM_INT);
$stmt->execute();
$service = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$service) {
    header("Location: services.php");
    exit();
}

// Doktor sorgusu
$query = "
SELECT
    d.id,
    d.name,
    d.specialty,
    d.image_url,
    CASE 
        WHEN :lang = 'tr' THEN d.bio_tr
        WHEN :lang = 'en' THEN COALESCE(d.bio_en, d.bio_tr)
        WHEN :lang = 'de' THEN COALESCE(d.bio_de, d.bio_en, d.bio_tr)
        WHEN :lang = 'fr' THEN COALESCE(d.bio_fr, d.bio_en, d.bio_tr)
        WHEN :lang = 'es' THEN COALESCE(d.bio_es, d.bio_en, d.bio_tr)
        WHEN :lang = 'it' THEN COALESCE(d.bio_it, d.bio_en, d.bio_tr)
        WHEN :lang = 'ru' THEN COALESCE(d.bio_ru, d.bio_en, d.bio_tr)
        WHEN :lang = 'zh' THEN COALESCE(d.bio_zh, d.bio_en, d.bio_tr)
        ELSE d.bio_tr
    END AS bio
FROM doctors d
INNER JOIN doctor_services ds ON ds.doctor_id = d.id
WHERE ds.service_id = :sid
AND d.status = 'active'
";
$stmt = $db->prepare($query);
$stmt->bindParam(':lang', $lang, PDO::PARAM_STR);
$stmt->bindParam(':sid', $serviceId, PDO::PARAM_INT);
$stmt->execute();
$doctors = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($lang); ?>">
<head>
    <meta charset="UTF-8">
    <title>Service Detail</title>
    <style>
        /* -- Önceki stil blokları -- */
        .premium-gradient {
            background: linear-gradient(135deg, #6366F1, #818CF8);
        }
        .premium-text {
            background: linear-gradient(135deg, #6366F1, #818CF8);
            -webkit-background-clip: text;
            color: transparent;
            -webkit-text-fill-color: transparent;
        }
        .soft-bg { background-color: #F8FAFC; }
        .custom-shadow {
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        }
        .custom-shadow:hover {
            box-shadow: 0 30px 60px -15px rgba(0, 0, 0, 0.3);
            transform: translateY(-2px);
            transition: all 0.3s ease;
        }
        .card-hover {
            transition: all 0.3s ease;
            background-color: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(99, 102, 241, 0.1);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1),
                        0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }
        .card-hover:hover {
            transform: translateY(-5px);
            border-color: rgba(99, 102, 241, 0.2);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1),
                        0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }
        .hero-pattern {
            background-image:
                linear-gradient(135deg, rgba(99, 102, 241, 0.05) 25%, transparent 25%),
                linear-gradient(225deg, rgba(99, 102, 241, 0.05) 25%, transparent 25%),
                linear-gradient(45deg, rgba(99, 102, 241, 0.05) 25%, transparent 25%),
                linear-gradient(315deg, rgba(99, 102, 241, 0.05) 25%, #F8FAFC 25%);
            background-position: 25px 0, 25px 0, 0 0, 0 0;
            background-size: 50px 50px;
            background-repeat: repeat;
        }
        .feature-icon {
            filter: drop-shadow(0 4px 6px rgba(99, 102, 241, 0.1));
        }

        /* -- Biyografinin tamamını okuyabilmek için scroll ekledik -- */
        .doctor-bio-text {
            min-height: 100px;
            max-height: 100px;
            overflow-y: auto;
        }
        .service-doctor-card {
            display: flex;
            flex-direction: column;
            height: 100%;
        }
    </style>
</head>
<body>

<!-- Hero Section -->
<div class="relative pt-32 pb-20 overflow-hidden soft-bg">
    <div class="hero-pattern absolute inset-0 opacity-40"></div>
    
    <!-- Main Content -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative">
        <div class="flex flex-col lg:flex-row gap-12 items-start">
            
            <!-- Main Image -->
            <div class="lg:w-1/2 relative">
                <div class="aspect-w-16 aspect-h-12 rounded-3xl overflow-hidden custom-shadow group">
                    <?php if (!empty($service['image_url'])): ?>
                        <img src="/health_tourism/assets/images/services/<?php echo htmlspecialchars($service['image_url']); ?>" 
                             alt="<?php echo htmlspecialchars($service['name']); ?>"
                             class="w-full h-full object-cover transform transition-transform duration-700 group-hover:scale-105">
                    <?php else: ?>
                        <img src="/api/placeholder/800/600"
                             alt="Service"
                             class="w-full h-full object-cover transform transition-transform duration-700 group-hover:scale-105">
                    <?php endif; ?>
                    <div class="absolute inset-0 bg-gradient-to-t from-indigo-900/30 via-transparent to-transparent"></div>
                </div>
            </div>

            <!-- Service Info -->
            <div class="lg:w-1/2 space-y-8">
                <!-- Card 1: Name & Description -->
                <div class="card-hover rounded-2xl p-8">
                    <h1 class="text-4xl font-bold mb-6 premium-text">
                        <?php echo htmlspecialchars($service['name'] ?? ''); ?>
                    </h1>
                    <p class="text-gray-600 text-lg leading-relaxed">
                        <?php echo htmlspecialchars($service['description'] ?? ''); ?>
                    </p>
                </div>

                <!-- Price Card -->
                <div class="card-hover rounded-2xl p-8 border border-indigo-50">
                    <div class="flex items-baseline gap-2 mb-6">
                        <span class="text-4xl font-bold premium-text">
                            ₺<?php echo number_format($service['price'] ?? 0, 0, ',', '.'); ?>
                        </span>
                        <span class="text-gray-500">
                            <?php echo $t['starting_from']; ?>
                        </span>
                    </div>

                    <?php if (isset($_SESSION['user_id'])): ?>
                        <!-- Giriş yapıldıysa -->
                        <button type="button"
                                id="openPurchaseModal"
                                class="w-full premium-gradient text-white py-4 px-6 rounded-xl font-medium
                                       transform transition duration-300 hover:scale-[1.02] hover:shadow-lg">
                            <?php echo $t['book_appointment']; ?>
                        </button>
                    <?php else: ?>
                        <!-- Giriş yoksa -->
                        <a href="login.php"
                           class="block text-center w-full premium-gradient text-white py-4 px-6 rounded-xl
                                  font-medium transform transition duration-300 hover:scale-[1.02] hover:shadow-lg">
                            <?php echo $t['login_for_appointment']; ?>
                        </a>
                    <?php endif; ?>

                    <!-- Features List -->
                    <div class="mt-8 space-y-4">
                        <div class="flex items-center gap-3 p-3 rounded-lg bg-indigo-50/50 hover:bg-indigo-50 transition-colors">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-indigo-600" 
                                 fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                      d="M5 13l4 4L19 7" />
                            </svg>
                            <span class="text-gray-600"><?php echo $t['free_consultation']; ?></span>
                        </div>
                        <div class="flex items-center gap-3 p-3 rounded-lg bg-indigo-50/50 hover:bg-indigo-50 transition-colors">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-indigo-600" 
                                 fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                      d="M5 13l4 4L19 7" />
                            </svg>
                            <span class="text-gray-600"><?php echo $t['support_service']; ?></span>
                        </div>
                        <div class="flex items-center gap-3 p-3 rounded-lg bg-indigo-50/50 hover:bg-indigo-50 transition-colors">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-indigo-600"
                                 fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M5 13l4 4L19 7" />
                            </svg>
                            <span class="text-gray-600"><?php echo $t['warranty']; ?></span>
                        </div>
                    </div>
                </div> <!-- end price card -->
            </div> <!-- end col -->
        </div> <!-- end row -->
    </div> <!-- end container -->
</div> <!-- end hero section -->

<!-- Doctors Section -->
<div class="bg-white py-24">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-16">
            <h2 class="text-3xl font-bold premium-text"><?php echo $t['our_expert_doctors']; ?></h2>
            <p class="mt-4 text-gray-600"><?php echo $t['doctors_subtitle_2']; ?></p>
        </div>

        <?php if (!empty($doctors)): ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                <?php foreach ($doctors as $doctor): ?>
                <div class="card-hover rounded-2xl overflow-hidden service-doctor-card">
                    <div class="aspect-w-4 aspect-h-3">
                        <?php if (!empty($doctor['image_url'])): ?>
                            <img src="assets/images/<?php echo htmlspecialchars($doctor['image_url']); ?>" 
                                 alt="<?php echo htmlspecialchars($doctor['name']); ?>"
                                 class="w-full h-full object-cover">
                        <?php else: ?>
                            <img src="assets/images/doctor-avatar.png"
                                 alt="Doctor Avatar"
                                 class="w-full h-full object-cover">
                        <?php endif; ?>
                    </div>
                    <div class="p-6">
                        <h3 class="text-xl font-bold premium-text mb-2">
                            <?php echo htmlspecialchars($doctor['name']); ?>
                        </h3>
                        <p class="text-gray-600 mb-4">
                            <?php echo htmlspecialchars($doctor['specialty']); ?>
                        </p>
                        <p class="doctor-bio-text text-gray-500 text-sm leading-relaxed">
                            <?php echo htmlspecialchars($doctor['bio']); ?>
                        </p>

                        <!-- Experience & Rating (örnek sabit veri) -->
                        <div class="mt-6 flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-indigo-600"
                                     fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" 
                                          stroke-linejoin="round" 
                                          stroke-width="2"
                                          d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <span class="text-gray-600">15+ <?php echo $t['years_experience']; ?></span>
                            </div>
                            <div class="flex items-center gap-1">
                                <svg xmlns="http://www.w3.org/2000/svg"
                                     class="h-5 w-5 text-yellow-400"
                                     viewBox="0 0 20 20" fill="currentColor">
                                    <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                                </svg>
                                <span class="text-gray-600">4.9</span>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <!-- Doktor yoksa -->
            <p class="text-center text-gray-500">
                <?php echo $t['no_doctors']; ?>
            </p>
        <?php endif; ?>
    </div>
</div>

<!-- Features Section -->
<div class="soft-bg py-24">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8">
            <!-- 1) Quick Healing -->
            <div class="card-hover p-6 text-center rounded-2xl">
                <div class="w-16 h-16 premium-gradient rounded-2xl flex items-center justify-center mx-auto mb-6">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-white feature-icon"
                         fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round"
                              stroke-linejoin="round"
                              stroke-width="2"
                              d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <h3 class="text-xl font-bold mb-3 premium-text">
                    <?php echo $t['features']['quick_healing']['title']; ?>
                </h3>
                <p class="text-gray-600">
                    <?php echo $t['features']['quick_healing']['desc']; ?>
                </p>
            </div>

            <!-- 2) Safe Treatment -->
            <div class="card-hover p-6 text-center rounded-2xl">
                <div class="w-16 h-16 premium-gradient rounded-2xl flex items-center justify-center mx-auto mb-6">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-white feature-icon"
                         fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round"
                              stroke-linejoin="round"
                              stroke-width="2"
                              d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                    </svg>
                </div>
                <h3 class="text-xl font-bold mb-3 premium-text">
                    <?php echo $t['features']['safe_treatment']['title']; ?>
                </h3>
                <p class="text-gray-600">
                    <?php echo $t['features']['safe_treatment']['desc']; ?>
                </p>
            </div>

            <!-- 3) Modern Tech -->
            <div class="card-hover p-6 text-center rounded-2xl">
                <div class="w-16 h-16 premium-gradient rounded-2xl flex items-center justify-center mx-auto mb-6">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-white feature-icon"
                         fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round"
                              stroke-linejoin="round"
                              stroke-width="2"
                              d="M13 10V3L4 14h7v7l9-11h-7z" />
                    </svg>
                </div>
                <h3 class="text-xl font-bold mb-3 premium-text">
                    <?php echo $t['features']['modern_tech']['title']; ?>
                </h3>
                <p class="text-gray-600">
                    <?php echo $t['features']['modern_tech']['desc']; ?>
                </p>
            </div>

            <!-- 4) Patient Satisfaction -->
            <div class="card-hover p-6 text-center rounded-2xl">
                <div class="w-16 h-16 premium-gradient rounded-2xl flex items-center justify-center mx-auto mb-6">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-white feature-icon"
                         fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round"
                              stroke-linejoin="round"
                              stroke-width="2"
                              d="M14 10h4.764a2 2 0 011.789 2.894l-3.5 7A2 2 0 0115.263 21h-4.017c-.163 0-.326-.02-.485-.06L7 20m7-10V5a2 2 0 00-2-2h-.095c-.5 0-.905.405-.905.905 0 .714-.211 1.412-.608 2.006L7 11v9m7-10h-2M7 20H5a2 2 0 01-2-2v-6a2 2 0 012-2h2.5" />
                    </svg>
                </div>
                <h3 class="text-xl font-bold mb-3 premium-text">
                    <?php echo $t['features']['patient_satisfaction']['title']; ?>
                </h3>
                <p class="text-gray-600">
                    <?php echo $t['features']['patient_satisfaction']['desc']; ?>
                </p>
            </div>
        </div>
    </div>
</div>


<!-- Purchase Modal (Sadece giriş yapmış kullanıcılar) -->
<?php if (isset($_SESSION['user_id'])): ?>
<div id="purchaseModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden">
    <div class="min-h-screen flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl p-8 w-full max-w-md custom-shadow">
            <h3 class="text-2xl font-bold mb-6">
                <?php echo htmlspecialchars($service['name'] ?? ''); ?>
                <span class="block text-lg font-normal mt-1 text-gray-600">
                    <?php echo $t['payment_form']['title']; ?>
                </span>
            </h3>
            
            <form id="purchaseForm" action="purchase_service.php" method="POST">
                <input type="hidden" name="service_id" value="<?php echo htmlspecialchars($service['id']); ?>">

                <div class="space-y-6">
                    <!-- Doktor Seçimi -->
                    <div>
                        <label class="block text-gray-700 mb-2 font-medium">
                            <?php echo $t['payment_form']['doctor_selection']; ?>
                        </label>
                        <select name="doctor_id"
                                class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:border-indigo-500
                                       focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                                required>
                            <?php foreach($doctors as $doctor): ?>
                              <option value="<?php echo $doctor['id']; ?>">
                                <?php echo htmlspecialchars($doctor['name']); ?> - <?php echo htmlspecialchars($doctor['specialty']); ?>
                              </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Kart Bilgileri -->
                    <div>
                        <label class="block text-gray-700 mb-2 font-medium">
                            <?php echo $t['payment_form']['card_name']; ?>
                        </label>
                        <input type="text"
                               name="card_name"
                               required
                               class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:border-indigo-500
                                      focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                               placeholder="<?php echo $t['payment_form']['card_name_placeholder']; ?>" />
                    </div>

                    <div>
                        <label class="block text-gray-700 mb-2 font-medium">
                            <?php echo $t['payment_form']['card_number']; ?>
                        </label>
                        <input type="text"
                               name="card_number"
                               required
                               maxlength="19"
                               class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:border-indigo-500
                                      focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                               placeholder="<?php echo $t['payment_form']['card_number_placeholder']; ?>"
                               oninput="this.value = this.value.replace(/[^\d]/g, '').replace(/(.{4})/g, '$1 ').trim()">
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-gray-700 mb-2 font-medium">
                                <?php echo $t['payment_form']['expiry_date']; ?>
                            </label>
                            <input type="text"
                                   name="card_expiry"
                                   required
                                   maxlength="5"
                                   class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:border-indigo-500
                                          focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                                   placeholder="<?php echo $t['payment_form']['card_expiry_placeholder']; ?>"
                                   oninput="this.value = this.value.replace(/[^\d]/g, '').replace(/^(\d{2})(\d{0,2})/, '$1/$2')">
                        </div>
                        <div>
                            <label class="block text-gray-700 mb-2 font-medium">
                                <?php echo $t['payment_form']['cvv']; ?>
                            </label>
                            <input type="text"
                                   name="card_cvv"
                                   required
                                   maxlength="3"
                                   class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:border-indigo-500
                                          focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                                   placeholder="<?php echo $t['payment_form']['card_cvv_placeholder']; ?>"
                                   oninput="this.value = this.value.replace(/[^\d]/g, '').replace(/^(\d{3})/, '$1')">
                        </div>
                    </div>
                </div>

                <!-- Toplam Tutar -->
                <div class="mt-8 p-4 bg-indigo-50/50 rounded-xl">
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600">
                            <?php echo $t['payment_form']['total_amount']; ?>:
                        </span>
                        <span class="text-2xl font-bold premium-text">
                            ₺<?php echo number_format($service['price'] ?? 0, 0, ',', '.'); ?>
                        </span>
                    </div>
                </div>

                <!-- Butonlar -->
                <div class="flex gap-4 mt-8">
                    <button type="button"
                            onclick="document.getElementById('purchaseModal').classList.add('hidden')"
                            class="flex-1 bg-gray-100 text-gray-700 px-6 py-3 rounded-xl hover:bg-gray-200
                                   transition duration-300 border border-gray-300 hover:border-gray-400">
                        <?php echo $t['payment_form']['cancel']; ?>
                    </button>
                    <button type="submit"
                            class="flex-1 premium-gradient text-white px-6 py-3 rounded-xl hover:opacity-90
                                   transition duration-300">
                        <?php echo $t['payment_form']['make_payment']; ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>


<!-- Success Popup (isteğe bağlı) -->
<div id="successPopup"
     class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm hidden">
    <div id="successPopupContent"
         class="bg-white rounded-2xl p-8 w-full max-w-md transform transition-all duration-500 scale-95 opacity-0 shadow-xl">
        <div class="text-center">
            <!-- Success Icon -->
            <div class="mb-4 inline-flex">
                <svg class="w-20 h-20 text-green-600 animate-[bounce_1s_ease-in-out]"
                     fill="none"
                     stroke="currentColor"
                     viewBox="0 0 24 24"
                     stroke-width="2">
                    <path stroke-linecap="round"
                          stroke-linejoin="round"
                          d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>

            <!-- Title -->
            <h3 class="text-3xl font-bold mb-4 text-emerald-600">
                <?php echo ($lang === 'en') ? 'Purchase Successful!' : 'Satın Alma İşlemi Başarılı!'; ?>
            </h3>

            <!-- Message -->
            <p class="text-gray-700 text-lg mb-8">
                <?php echo ($lang === 'en')
                    ? 'We will contact you within 24 hours to discuss the details.'
                    : 'Detayları görüşmek için 24 saat içinde sizinle iletişime geçeceğiz.'; ?>
            </p>

            <!-- Button -->
            <button onclick="closeSuccessPopup()"
                    class="bg-gradient-to-r from-green-600 to-emerald-600 text-white px-12 py-4
                           rounded-xl text-lg font-semibold shadow-lg transform transition duration-300
                           hover:scale-105 hover:shadow-xl active:scale-95 w-full sm:w-auto">
                <?php echo ($lang === 'en') ? 'OK' : 'Tamam'; ?>
            </button>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const purchaseForm  = document.getElementById('purchaseForm');
    const purchaseModal = document.getElementById('purchaseModal');
    const openModalBtn  = document.getElementById('openPurchaseModal');

    // Modal aç
    if (openModalBtn) {
        openModalBtn.addEventListener('click', () => {
            purchaseModal.classList.remove('hidden');
        });
    }

    // Modal dışına tıklayınca kapat
    if (purchaseModal) {
        purchaseModal.addEventListener('click', function(e) {
            if (e.target === purchaseModal) {
                purchaseModal.classList.add('hidden');
            }
        });
    }

    // ESC tuşu ile kapat
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && !purchaseModal.classList.contains('hidden')) {
            purchaseModal.classList.add('hidden');
        }
    });

    // Form submit
    if (purchaseForm) {
        purchaseForm.addEventListener('submit', function(e) {
            e.preventDefault();

            if (!validateForm()) return;

            const formData = new FormData(this);
            fetch('purchase_service.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    purchaseModal.classList.add('hidden');
                    showSuccessPopup();
                } else {
                    showNotification(data.message || 'Bir hata oluştu', 'error');
                }
            })
            .catch(err => {
                console.error('Hata:', err);
                showNotification('Bir hata oluştu, lütfen tekrar deneyin', 'error');
            });
        });
    }
});

// Form Validasyonu
function validateForm() {
    const cardName   = document.querySelector('input[name="card_name"]');
    const cardNumber = document.querySelector('input[name="card_number"]');
    const cardExpiry = document.querySelector('input[name="card_expiry"]');
    const cardCvv    = document.querySelector('input[name="card_cvv"]');

    // Kart sahibi adı
    if (!/^[a-zA-ZğüşıöçĞÜŞİÖÇ\s]+$/.test(cardName.value.trim())) {
        showNotification('Kart sahibinin adı sadece harflerden oluşmalıdır', 'error');
        cardName.focus();
        return false;
    }

    // Kart numarası
    const cleanNum = cardNumber.value.replace(/\s/g, '');
    if (cleanNum.length !== 16 || !/^\d+$/.test(cleanNum)) {
        showNotification('Geçerli bir kart numarası giriniz', 'error');
        cardNumber.focus();
        return false;
    }

    // Son kullanma
    const [month, year] = cardExpiry.value.split('/');
    const currentDate  = new Date();
    const currentYear  = currentDate.getFullYear() % 100;
    const currentMonth = currentDate.getMonth() + 1;

    if (!month || !year ||
        parseInt(month) < 1 || parseInt(month) > 12 ||
        (parseInt(year) < currentYear ||
         (parseInt(year) === currentYear && parseInt(month) < currentMonth))) {
        showNotification('Geçerli bir son kullanma tarihi giriniz', 'error');
        cardExpiry.focus();
        return false;
    }

    // CVV
    if (!/^\d{3}$/.test(cardCvv.value)) {
        showNotification('Geçerli bir CVV kodu giriniz', 'error');
        cardCvv.focus();
        return false;
    }

    return true;
}

// SweetAlert Başarı Popup
function showSuccessPopup() {
    Swal.fire({
        title: '<?php echo $t['payment_form']['success_title']; ?>',
        text: '<?php echo $t['payment_form']['success_message']; ?>',
        icon: 'success',
        confirmButtonText: '<?php echo $t['payment_form']['success_button']; ?>',
        confirmButtonColor: '#4F46E5'
    });
}

// Basit notification
function showNotification(message, type = 'success') {
    const note = document.createElement('div');
    note.className = `fixed top-4 right-4 p-4 rounded-xl text-white transform transition-all duration-300
                      shadow-lg ${type === 'success' ? 'bg-green-600' : 'bg-red-600'} z-50`;
    note.style.opacity = '0';
    note.style.transform = 'translateY(-20px)';

    note.innerHTML = `
        <div class="flex items-center gap-3">
            ${
                type === 'success'
                ? '<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>'
                : '<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/></svg>'
            }
            <span class="font-medium">${message}</span>
        </div>
    `;

    document.body.appendChild(note);

    setTimeout(() => {
        note.style.opacity = '1';
        note.style.transform = 'translateY(0)';
    }, 10);

    setTimeout(() => {
        note.style.opacity = '0';
        note.style.transform = 'translateY(-20px)';
        setTimeout(() => note.remove(), 300);
    }, 3000);
}

// successPopup'ı kapatmak istersen
function closeSuccessPopup() {
    document.getElementById('successPopup').classList.add('hidden');
}
</script>

</body>
</html>

<?php
// Alt kısım (Footer)
include_once './includes/new-footer.php';
?>
