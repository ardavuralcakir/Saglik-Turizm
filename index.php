<?php
session_start();

$lang = $_SESSION['lang'] ?? 'en';
$translations_file = __DIR__ . "/translations/translation_{$lang}.php";

if (file_exists($translations_file)) {
    $translations = require $translations_file;
    $pt = $translations; // array_merge kaldırıldı, direkt atama yapılıyor
} else {
    die("Translation file not found: {$translations_file}");
}

// Config ve Header
require_once 'config/database.php';
require_once 'includes/new-header.php';

// Veritabanı bağlantısı
$database = new Database();
$db = $database->getConnection();

// Son 3 hizmeti çek - dile göre dinamik sorgu
$stmt = $db->prepare("SELECT 
    id,
    CASE 
        WHEN ? = 'tr' THEN name
        WHEN ? = 'en' THEN COALESCE(name_en, name)
        WHEN ? = 'de' THEN COALESCE(name_de, name_en, name)
        WHEN ? = 'ru' THEN COALESCE(name_ru, name_en, name)
        WHEN ? = 'es' THEN COALESCE(name_es, name_en, name)
        WHEN ? = 'zh' THEN COALESCE(name_zh, name_en, name)
        WHEN ? = 'fr' THEN COALESCE(name_fr, name_en, name)
        WHEN ? = 'it' THEN COALESCE(name_it, name_en, name)
        ELSE COALESCE(name_en, name)
    END AS service_name,
    CASE 
        WHEN ? = 'tr' THEN description
        WHEN ? = 'en' THEN COALESCE(description_en, description)
        WHEN ? = 'de' THEN COALESCE(description_de, description_en, description)
        WHEN ? = 'ru' THEN COALESCE(description_ru, description_en, description)
        WHEN ? = 'es' THEN COALESCE(description_es, description_en, description)
        WHEN ? = 'zh' THEN COALESCE(description_zh, description_en, description)
        WHEN ? = 'fr' THEN COALESCE(description_fr, description_en, description)
        WHEN ? = 'it' THEN COALESCE(description_it, description_en, description)
        ELSE COALESCE(description_en, description)
    END AS service_description,
    price,
    image_url
FROM services 
ORDER BY id DESC 
LIMIT 3");

$stmt->execute([$lang, $lang, $lang, $lang, $lang, $lang, $lang, $lang, $lang, $lang, $lang, $lang, $lang, $lang, $lang, $lang]);
$services = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>HealthTurkey - <?php echo $pt['hero_title'] ?? 'Home'; ?></title>
  <!-- Örnek stil ve scriptler -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/tailwindcss/2.2.19/tailwind.min.css" />
  <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.11.4/gsap.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.11.4/ScrollTrigger.min.js"></script>
  <style>
    .luxury-gradient {
      position: relative;
      overflow: hidden;
      background: linear-gradient(135deg, #f6f8fc 0%, #eef2f7 100%);
    }
    .hero-pattern {
      position: absolute; top: 0; left: 0; right: 0; bottom: 0;
      background-image:
        radial-gradient(circle at 20% 30%, rgba(79,70,229,0.08) 0%, transparent 50%),
        radial-gradient(circle at 80% 70%, rgba(99,102,241,0.05) 0%, transparent 50%);
      animation: patternMove 20s ease-in-out infinite alternate;
    }
    @keyframes patternMove {
      0%   { transform: translateX(-10px) translateY(-10px); }
      100% { transform: translateX(10px)  translateY(10px); }
    }
    .premium-gradient {
      background: linear-gradient(120deg, #4F46E5, #6366F1);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
    }
    .elegant-card {
      background: rgba(255,255,255,0.95);
      border: 1px solid rgba(255,255,255,0.8);
      box-shadow:
        0 10px 30px rgba(0,0,0,0.08),
        0 0 1px rgba(0,0,0,0.1);
      transition: all 0.4s cubic-bezier(0.4,0,0.2,1);
    }
    .elegant-card:hover {
      transform: translateY(-5px) scale(1.01);
      box-shadow:
        0 20px 40px rgba(0,0,0,0.12),
        0 0 1px rgba(0,0,0,0.1);
      border: 1px solid rgba(255,255,255,0.9);
    }
    .stat-card {
      background: rgba(255,255,255,0.9);
      backdrop-filter: blur(20px);
      border: 1px solid rgba(255,255,255,0.8);
      border-radius: 15px;
      box-shadow:
        0 4px 20px rgba(0,0,0,0.06),
        0 0 1px rgba(0,0,0,0.1);
      transition: all 0.3s cubic-bezier(0.4,0,0.2,1);
    }
    .stat-card:hover {
      transform: translateY(-3px);
      box-shadow:
        0 8px 30px rgba(0,0,0,0.1),
        0 0 1px rgba(0,0,0,0.1);
      background: rgba(255,255,255,0.95);
    }
  </style>
</head>
<body>

<!-- Hero Section -->
<div class="luxury-gradient min-h-screen">
  <div class="hero-pattern"></div>
  <div class="max-w-7xl mx-auto px-8 pt-32 pb-20 relative">
    <div class="text-center mb-16">
      <!-- hero_title -->
      <h1 class="text-6xl font-light mb-6 tracking-wide text-gray-800">
        <?php echo $pt['hero_title'] ?? 'For Your Health'; ?>
        <!-- hero_subtitle -->
        <span class="block text-7xl font-semibold mt-3 premium-gradient">
          <?php echo $pt['hero_subtitle'] ?? 'Premium Solutions'; ?>
        </span>
      </h1>

      <!-- hero_description -->
      <p class="text-xl text-gray-600 max-w-3xl mx-auto leading-relaxed">
        <?php echo $pt['hero_description'] ?? '...'; ?>
      </p>

      <div class="mt-12">
        <!-- discover_services -->
        <a href="services.php"
           class="inline-block px-12 py-4 text-lg bg-gradient-to-r from-indigo-600 to-indigo-700
                  text-white rounded-full transform hover:-translate-y-1 hover:shadow-xl
                  transition-all duration-300 hover:from-indigo-700 hover:to-indigo-800">
          <?php echo $pt['discover_services'] ?? 'Discover Services'; ?>
        </a>
      </div>
    </div>

    <!-- İstatistikler -->
    <div class="grid grid-cols-4 gap-8 mt-24">
      <div class="stat-card p-8 text-center">
        <span class="block text-5xl font-light text-indigo-600 mb-2">15K+</span>
        <!-- happy_patients -->
        <span class="text-gray-500 text-sm tracking-wider uppercase">
          <?php echo $pt['happy_patients'] ?? 'HAPPY PATIENTS'; ?>
        </span>
      </div>
      <div class="stat-card p-8 text-center">
        <span class="block text-5xl font-light text-indigo-600 mb-2">50+</span>
        <!-- expert_doctors -->
        <span class="text-gray-500 text-sm tracking-wider uppercase">
          <?php echo $pt['expert_doctors'] ?? 'EXPERT DOCTORS'; ?>
        </span>
      </div>
      <div class="stat-card p-8 text-center">
        <span class="block text-5xl font-light text-indigo-600 mb-2">10+</span>
        <!-- years_experience -->
        <span class="text-gray-500 text-sm tracking-wider uppercase">
          <?php echo $pt['years_experience'] ?? 'YEARS EXPERIENCE'; ?>
        </span>
      </div>
      <div class="stat-card p-8 text-center">
        <span class="block text-5xl font-light text-indigo-600 mb-2">98%</span>
        <!-- satisfaction -->
        <span class="text-gray-500 text-sm tracking-wider uppercase">
          <?php echo $pt['satisfaction'] ?? 'SATISFACTION'; ?>
        </span>
      </div>
    </div>
  </div>
</div>

<!-- Hizmetler -->
<div class="py-32">
  <div class="max-w-7xl mx-auto px-8">
    <div class="text-center mb-20">
      <!-- our_services -->
      <h2 class="text-4xl font-light mb-4 text-gray-800">
        <?php echo $pt['our_services'] ?? 'Our Premium Services'; ?>
      </h2>
      <div class="w-24 h-1 bg-gradient-to-r from-indigo-500 to-indigo-600 mx-auto rounded-full"></div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
      <?php foreach ($services as $service): ?>
        <div class="elegant-card rounded-2xl overflow-hidden h-full flex flex-col">
          <!-- Resim Alanı -->
          <div class="relative h-56 overflow-hidden">
            <?php if (!empty($service['image_url'])): ?>
              <img src="assets/images/services/<?php echo htmlspecialchars($service['image_url']); ?>"
                   alt="<?php echo htmlspecialchars($service['service_name']); ?>"
                   class="w-full h-full object-cover transform hover:scale-110 transition duration-500">
            <?php else: ?>
              <div class="w-full h-full bg-gradient-to-br from-indigo-100 to-indigo-50 flex items-center justify-center">
                <svg class="w-16 h-16 text-indigo-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                        d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10
                           a2 2 0 002-2V7a2 2 0 00-2-2h-2
                           M9 5a2 2 0 002 2h2a2 2 0 002-2
                           M9 5a2 2 0 012-2h2a2 2 0 012 2
                           m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                </svg>
              </div>
            <?php endif; ?>
            <div class="absolute inset-0 bg-gradient-to-t from-gray-900/60 to-transparent"></div>
          </div>

          <!-- İçerik Alanı -->
          <div class="p-8 flex flex-col flex-grow">
            <h3 class="text-xl font-light text-gray-800 mb-4">
              <?php echo htmlspecialchars($service['service_name']); ?>
            </h3>
            <p class="text-gray-600 mb-8 leading-relaxed line-clamp-3 flex-grow">
              <?php echo htmlspecialchars($service['service_description']); ?>
            </p>
            <div class="flex justify-between items-center mt-auto">
              <span class="text-2xl font-light text-indigo-600">
                <?php echo ($pt['price_currency'] ?? '₺') . number_format($service['price'], 0, ',', '.'); ?>
              </span>
              <a href="service_detail.php?id=<?php echo $service['id']; ?>"
                 class="px-6 py-2 bg-gradient-to-r from-indigo-500 to-indigo-600 text-white rounded-full
                        text-sm tracking-wider hover:shadow-lg transition-all duration-300 transform hover:-translate-y-0.5">
                <?php echo $pt['more_info'] ?? 'MORE INFO'; ?>
              </a>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<!-- Neden Biz -->
<div class="luxury-gradient py-32 relative">
  <div class="hero-pattern"></div>
  <div class="max-w-7xl mx-auto px-8 relative">
    <div class="text-center mb-20">
      <!-- why_us -->
      <h2 class="text-4xl font-light mb-4 text-gray-800">
        <?php echo $pt['why_us'] ?? 'Why Choose Us?'; ?>
      </h2>
      <div class="w-24 h-1 bg-gradient-to-r from-indigo-500 to-indigo-600 mx-auto rounded-full"></div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-16">
      <!-- Card 1 -->
      <div class="elegant-card p-12 rounded-2xl text-center">
        <div class="w-20 h-20 rounded-full bg-gray-100 flex items-center justify-center mx-auto mb-8">
          <!-- Ikon Örnek -->
          <svg class="w-10 h-10 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                  d="M9 12l2 2 4-4m5.618-4.016
                     A11.955 11.955 0 0112 2.944
                     a11.955 11.955 0 01-8.618 3.04
                     A12.02 12.02 0 003 9
                     c0 5.591 3.824 10.29 9 11.622
                     5.176-1.332 9-6.03 9-11.622
                     0-1.042-.133-2.052-.382-3.016z" />
          </svg>
        </div>
        <!-- premium_service / premium_desc -->
        <h3 class="text-2xl font-light mb-4 text-gray-800">
          <?php echo $pt['premium_service'] ?? 'Premium Service'; ?>
        </h3>
        <p class="text-gray-600 leading-relaxed">
          <?php echo $pt['premium_desc'] ?? 'Exclusive healthcare services at the highest quality standards'; ?>
        </p>
      </div>

      <!-- Card 2 -->
      <div class="elegant-card p-12 rounded-2xl text-center">
        <div class="w-20 h-20 rounded-full bg-gray-100 flex items-center justify-center mx-auto mb-8">
          <!-- Ikon Örnek -->
          <svg class="w-10 h-10 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                  d="M12 8v4l3 3m6-3
                     a9 9 0 11-18 0
                     a9 9 0 0118 0z" />
          </svg>
        </div>
        <!-- fast_result / fast_desc -->
        <h3 class="text-2xl font-light mb-4 text-gray-800">
          <?php echo $pt['fast_result'] ?? 'Fast Results'; ?>
        </h3>
        <p class="text-gray-600 leading-relaxed">
          <?php echo $pt['fast_desc'] ?? 'Quick and effective treatment with state-of-the-art technology'; ?>
        </p>
      </div>

      <!-- Card 3 -->
      <div class="elegant-card p-12 rounded-2xl text-center">
        <div class="w-20 h-20 rounded-full bg-gray-100 flex items-center justify-center mx-auto mb-8">
          <!-- Ikon Örnek -->
          <svg class="w-10 h-10 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                  d="M17 20h5v-2
                     a3 3 0 00-5.356-1.857
                     M17 20H7
                     m10 0v-2
                     c0-.656-.126-1.283-.356-1.857
                     M7 20H2v-2
                     a3 3 0 015.356-1.857
                     M7 20v-2
                     c0-.656.126-1.283.356-1.857
                     m0 0a5.002 5.002 0 019.288 0
                     M15 7a3 3 0 11-6 0
                     3 3 0 016 0
                     zm6 3
                     a2 2 0 11-4 0
                     2 2 0 014 0
                     zM7 10
                     a2 2 0 11-4 0
                     2 2 0 014 0z" />
          </svg>
        </div>
        <!-- expert_staff / expert_desc -->
        <h3 class="text-2xl font-light mb-4 text-gray-800">
          <?php echo $pt['expert_staff'] ?? 'Expert Staff'; ?>
        </h3>
        <p class="text-gray-600 leading-relaxed">
          <?php echo $pt['expert_desc'] ?? 'Experienced and professional healthcare experts in their fields'; ?>
        </p>
      </div>
    </div>
  </div>
</div>

<!-- CTA Section -->
<div class="py-32">
  <div class="max-w-5xl mx-auto px-8 text-center relative">
    <!-- cta_title -->
    <h2 class="text-4xl font-light mb-8 text-gray-800">
      <?php echo $pt['cta_title'] ?? 'The Best Choice for Your Health'; ?>
    </h2>
    <!-- cta_desc -->
    <p class="text-xl text-gray-600 mb-12 leading-relaxed">
      <?php echo $pt['cta_desc'] ?? 'We offer customized solutions with our premium healthcare services and expert staff'; ?>
    </p>
    <?php if (!isset($_SESSION['user_id'])): ?>
      <a href="login.php"
         class="inline-block px-12 py-4 text-lg bg-gradient-to-r from-indigo-600 to-indigo-700
                text-white rounded-full transform hover:-translate-y-1 hover:shadow-xl
                transition-all duration-300 hover:from-indigo-700 hover:to-indigo-800">
        <!-- start_now -->
        <?php echo $pt['start_now'] ?? 'START NOW'; ?>
      </a>
    <?php endif; ?>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  gsap.registerPlugin(ScrollTrigger);

  // Hero pattern parallax
  gsap.to(".hero-pattern", {
    yPercent: 50,
    ease: "none",
    scrollTrigger: {
      trigger: ".luxury-gradient",
      start: "top top",
      end: "bottom top",
      scrub: true
    }
  });

  // Card animations
  gsap.utils.toArray('.elegant-card').forEach(function(card) {
    gsap.from(card, {
      y: 60,
      opacity: 0,
      duration: 1,
      scrollTrigger: {
        trigger: card,
        start: "top bottom-=100",
        toggleActions: "play none none reverse"
      }
    });
  });

  // Stat card animations
  gsap.utils.toArray('.stat-card').forEach(function(card, i) {
    gsap.from(card, {
      y: 40,
      opacity: 0,
      duration: 0.8,
      delay: i * 0.2,
      scrollTrigger: {
        trigger: card,
        start: "top bottom-=50",
        toggleActions: "play none none reverse"
      }
    });
  });
});
</script>

<!-- Footer -->
<?php include_once 'includes/new-footer.php'; ?>

</body>
</html>
