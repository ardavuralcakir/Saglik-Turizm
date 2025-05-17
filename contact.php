<?php
session_start();
include_once './config/database.php';
include_once './includes/new-header.php';

$database = new Database();
$db = $database->getConnection();

// Dil ayarları
$lang = $_SESSION['lang'] ?? 'tr';
$translations_file = __DIR__ . "/translations/translation_{$lang}.php";

if (file_exists($translations_file)) {
    $translations = require $translations_file;
    $pt = array_merge($translations['tr'] ?? [], $translations ?? []);
} else {
    die("Translation file not found: {$translations_file}");
}

// Dil haritalaması
$lang_columns = [
    'tr' => ['name', 'description'],
    'en' => ['name_en', 'description_en'],
    'de' => ['name_de', 'description_de'],
    'fr' => ['name_fr', 'description_fr'],
    'es' => ['name_es', 'description_es'],
    'it' => ['name_it', 'description_it'],
    'ru' => ['name_ru', 'description_ru'],
    'zh' => ['name_zh', 'description_zh']
];

// Varsayılan dil kolonları
$name_column = $lang_columns[$lang][0] ?? 'name';
$description_column = $lang_columns[$lang][1] ?? 'description';

// Aktif servisleri çek
$services_query = "SELECT 
    id, 
    {$name_column} as name,
    {$description_column} as description,
    price,
    image_url,
    category_id 
FROM services 
WHERE status = 'active' 
ORDER BY id ASC";

$stmt = $db->prepare($services_query);
$stmt->execute();
$services = $stmt->fetchAll(PDO::FETCH_ASSOC);

$success = false;
$errors = [];

// Form gönderildiğinde
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $service_id = trim($_POST['service'] ?? '');
    $message = trim($_POST['message'] ?? '');

    // Validasyon
    if (empty($name)) {
        $errors['name'] = $pt['required_field'];
    }
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = $pt['invalid_email'];
    }
    if (empty($phone)) {
        $errors['phone'] = $pt['invalid_phone'];
    }
    if (empty($message)) {
        $errors['message'] = $pt['required_field'];
    }
    if (empty($service_id)) {
        $errors['service'] = $pt['required_field'];
    }

    // Seçilen servisin geçerliliğini kontrol et
    if (!empty($service_id)) {
        $service_check = "SELECT id FROM services WHERE id = :service_id AND status = 'active'";
        $stmt = $db->prepare($service_check);
        $stmt->bindParam(":service_id", $service_id);
        $stmt->execute();
        if (!$stmt->fetch()) {
            $errors['service'] = $pt['invalid_service'];
        }
    }

    // Hata yoksa veritabanına kaydet
    if (empty($errors)) {
        try {
            $query = "INSERT INTO contact_requests (name, email, phone, service_id, message, created_at) 
                     VALUES (:name, :email, :phone, :service_id, :message, NOW())";
            
            $stmt = $db->prepare($query);
            
            $stmt->bindParam(":name", $name);
            $stmt->bindParam(":email", $email);
            $stmt->bindParam(":phone", $phone);
            $stmt->bindParam(":service_id", $service_id);
            $stmt->bindParam(":message", $message);
            
            if ($stmt->execute()) {
                $success = true;
                $_POST = array(); // Formu temizle
            }
        } catch(PDOException $exception) {
            $errors['db'] = $exception->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $t['contact_title']; ?> - HealthTurkey</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/tailwindcss/2.2.19/tailwind.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
    <style>
        .luxury-gradient {
            position: relative;
            overflow: hidden;
            background: linear-gradient(135deg, #f6f8fc 0%, #eef2f7 100%);
        }
        
        .hero-pattern {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-image: 
                radial-gradient(circle at 20% 30%, rgba(79, 70, 229, 0.08) 0%, transparent 50%),
                radial-gradient(circle at 80% 70%, rgba(99, 102, 241, 0.05) 0%, transparent 50%);
            animation: patternMove 20s ease-in-out infinite alternate;
        }

        @keyframes patternMove {
            0% { transform: translateX(-10px) translateY(-10px); }
            100% { transform: translateX(10px) translateY(10px); }
        }
        
        .premium-gradient {
            background: linear-gradient(120deg, #4F46E5, #6366F1);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
    </style>

    <!-- Hero Section -->
    <div class="luxury-gradient pt-32 pb-16">
        <div class="hero-pattern"></div>
        <div class="max-w-7xl mx-auto px-8 relative z-10">
            <div class="text-center">
                <h1 class="text-6xl font-light mb-6 tracking-wide text-gray-800">
                    <?php echo $t['contact_title']; ?>
                    <span class="block text-5xl font-semibold mt-3 premium-gradient">
                        <?php echo $t['contact_subtitle']; ?>
                    </span>
                </h1>
            </div>
        </div>
    </div>

    <!-- Contact Section -->
    <div class="luxury-gradient py-24">
        <div class="hero-pattern"></div>
        <div class="max-w-7xl mx-auto px-8 relative">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-12">
                <!-- Contact Info -->
                <div class="md:col-span-1">
                    <div class="bg-white rounded-2xl p-8 shadow-lg">
                        <h3 class="text-2xl font-semibold mb-6 premium-gradient">
                            <?php echo $t['contact_info']; ?>
                        </h3>
                        <div class="space-y-6">
                            <div>
                                <h4 class="text-lg font-medium text-gray-700 mb-2"><?php echo $t['address']; ?></h4>
                                <p class="text-gray-600"><?php echo $t['office_address']; ?></p>
                            </div>
                            <div>
                                <h4 class="text-lg font-medium text-gray-700 mb-2"><?php echo $t['phone']; ?></h4>
                                <p class="text-gray-600">+90 555 555 5555</p>
                            </div>
                            <div>
                                <h4 class="text-lg font-medium text-gray-700 mb-2"><?php echo $t['email']; ?></h4>
                                <p class="text-gray-600">info@healthturkey.com</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Contact Form -->
                <div class="md:col-span-2">
                    <div class="bg-white rounded-2xl p-8 shadow-lg">
                        <form method="POST" class="space-y-6">
                            <div>
                                <label for="name" class="block text-sm font-medium text-gray-700 mb-1">
                                    <?php echo $t['form_name']; ?>
                                </label>
                                <input type="text" name="name" id="name" 
                                       class="w-full px-4 py-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                       value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>">
                                <?php if (isset($errors['name'])): ?>
                                    <p class="mt-1 text-sm text-red-600"><?php echo $errors['name']; ?></p>
                                <?php endif; ?>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label for="email" class="block text-sm font-medium text-gray-700 mb-1">
                                        <?php echo $t['form_email']; ?>
                                    </label>
                                    <input type="email" name="email" id="email" 
                                           class="w-full px-4 py-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                                    <?php if (isset($errors['email'])): ?>
                                        <p class="mt-1 text-sm text-red-600"><?php echo $errors['email']; ?></p>
                                    <?php endif; ?>
                                </div>

                                <div>
                                    <label for="phone" class="block text-sm font-medium text-gray-700 mb-1">
                                        <?php echo $t['form_phone']; ?>
                                    </label>
                                    <input type="tel" name="phone" id="phone" 
                                           class="w-full px-4 py-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                           value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
                                    <?php if (isset($errors['phone'])): ?>
                                        <p class="mt-1 text-sm text-red-600"><?php echo $errors['phone']; ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div>
                                <label for="service" class="block text-sm font-medium text-gray-700 mb-1">
                                    <?php echo $t['form_service']; ?>
                                </label>
                                <select name="service" id="service" 
                                        class="w-full px-4 py-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                                    <?php foreach ($services as $service): ?>
                                        <option value="<?php echo htmlspecialchars($service['id']); ?>"
                                                <?php echo (isset($_POST['service']) && $_POST['service'] == $service['id']) ? 'selected' : ''; ?>
                                                data-price="<?php echo htmlspecialchars($service['price']); ?>"
                                                data-description="<?php echo htmlspecialchars($service['description']); ?>">
                                            <?php echo htmlspecialchars($service['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div>
                                <label for="message" class="block text-sm font-medium text-gray-700 mb-1">
                                    <?php echo $t['form_message']; ?>
                                </label>
                                <textarea name="message" id="message" rows="4" 
                                          class="w-full px-4 py-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                ><?php echo htmlspecialchars($_POST['message'] ?? ''); ?></textarea>
                                <?php if (isset($errors['message'])): ?>
                                    <p class="mt-1 text-sm text-red-600"><?php echo $errors['message']; ?></p>
                                <?php endif; ?>
                            </div>

                            <button type="submit" 
                                    class="w-full px-8 py-4 bg-gradient-to-r from-indigo-600 to-indigo-700 
                                           text-white rounded-xl transform hover:-translate-y-1 
                                           hover:shadow-xl transition-all duration-300">
                                <?php echo $t['submit_button']; ?>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if ($success): ?>
    <script>
        Swal.fire({
            title: '<?php echo $t['success_title']; ?>',
            text: '<?php echo $t['success_message']; ?>',
            icon: 'success',
            confirmButtonText: '<?php echo $t['ok_button']; ?>',
            confirmButtonColor: '#4F46E5'
        });
    </script>
    <?php endif; ?>

    <?php include_once './includes/new-footer.php'; ?>
</body>
</html>