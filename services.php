<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Dil dosyası yükleme
$lang = $_SESSION['lang'] ?? 'en';
$translations_file = __DIR__ . "/translations/translation_{$lang}.php";

if (file_exists($translations_file)) {
   $translations = require $translations_file;
   $t = $translations;
} else {
   die("Translation file not found: {$translations_file}");
}

require_once './config/database.php';
require_once './includes/new-header.php';

$database = new Database();
$db = $database->getConnection();

try {
    // Kategoriler için sorgu (tüm diller)
    $categories_query = "SELECT 
        id,
        CASE 
            WHEN :lang = 'en' THEN COALESCE(NULLIF(TRIM(name_en), ''), name)
            WHEN :lang = 'de' THEN COALESCE(NULLIF(TRIM(name_de), ''), name)
            WHEN :lang = 'fr' THEN COALESCE(NULLIF(TRIM(name_fr), ''), name)
            WHEN :lang = 'es' THEN COALESCE(NULLIF(TRIM(name_es), ''), name)
            WHEN :lang = 'it' THEN COALESCE(NULLIF(TRIM(name_it), ''), name)
            WHEN :lang = 'ru' THEN COALESCE(NULLIF(TRIM(name_ru), ''), name)
            WHEN :lang = 'zh' THEN COALESCE(NULLIF(TRIM(name_zh), ''), name)
            ELSE name
        END as name
    FROM service_categories 
    ORDER BY name";
 
    $stmt = $db->prepare($categories_query);
    $stmt->bindParam(':lang', $lang);
    
    if (!$stmt->execute()) {
        error_log("Kategori sorgusu hatası: " . print_r($stmt->errorInfo(), true));
        $categories = [];
    } else {
        $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
 
    // Hizmetler için sorgu (tüm diller)
    $query = "SELECT 
    s.id,
    CASE 
        WHEN :lang = 'en' THEN COALESCE(NULLIF(TRIM(s.name_en), ''), s.name)
        WHEN :lang = 'de' THEN COALESCE(NULLIF(TRIM(s.name_de), ''), s.name)
        WHEN :lang = 'fr' THEN COALESCE(NULLIF(TRIM(s.name_fr), ''), s.name)
        WHEN :lang = 'es' THEN COALESCE(NULLIF(TRIM(s.name_es), ''), s.name)
        WHEN :lang = 'it' THEN COALESCE(NULLIF(TRIM(s.name_it), ''), s.name)
        WHEN :lang = 'ru' THEN COALESCE(NULLIF(TRIM(s.name_ru), ''), s.name)
        WHEN :lang = 'zh' THEN COALESCE(NULLIF(TRIM(s.name_zh), ''), s.name)
        ELSE s.name
    END as name,
    CASE 
        WHEN :lang = 'en' THEN COALESCE(NULLIF(TRIM(s.description_en), ''), s.description)
        WHEN :lang = 'de' THEN COALESCE(NULLIF(TRIM(s.description_de), ''), s.description)
        WHEN :lang = 'fr' THEN COALESCE(NULLIF(TRIM(s.description_fr), ''), s.description)
        WHEN :lang = 'es' THEN COALESCE(NULLIF(TRIM(s.description_es), ''), s.description)
        WHEN :lang = 'it' THEN COALESCE(NULLIF(TRIM(s.description_it), ''), s.description)
        WHEN :lang = 'ru' THEN COALESCE(NULLIF(TRIM(s.description_ru), ''), s.description)
        WHEN :lang = 'zh' THEN COALESCE(NULLIF(TRIM(s.description_zh), ''), s.description)
        ELSE s.description
    END as description,
    s.price,
    s.category_id,
    CASE 
        WHEN :lang = 'en' THEN COALESCE(NULLIF(TRIM(sc.name_en), ''), sc.name)
        ELSE sc.name
    END as category_name,
    s.image_url,
    (SELECT GROUP_CONCAT(d.name SEPARATOR ', ') 
    FROM doctors d
    JOIN doctor_services ds ON d.id = ds.doctor_id
    WHERE ds.service_id = s.id
    AND d.status = 'active') as doctors
    FROM services s
    LEFT JOIN service_categories sc ON s.category_id = sc.id
    WHERE s.status = 'active'";
 
    $stmt = $db->prepare($query);
    $stmt->bindParam(':lang', $lang);
    
    if (!$stmt->execute()) {
        error_log("Hizmetler sorgusu hatası: " . print_r($stmt->errorInfo(), true));
        $services = [];
    } else {
        $services = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
 } catch (PDOException $e) {
    error_log("Veritabanı hatası: " . $e->getMessage());
    $categories = [];
    $services = [];
 }
?>

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
    
    .elegant-card {
        background: rgba(255, 255, 255, 0.95);
        border: 1px solid rgba(255, 255, 255, 0.8);
        box-shadow: 
            0 10px 30px rgba(0, 0, 0, 0.08),
            0 0 1px rgba(0, 0, 0, 0.1);
        transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    }
    
    .elegant-card:hover {
        transform: translateY(-5px) scale(1.01);
        box-shadow: 
            0 20px 40px rgba(0, 0, 0, 0.12),
            0 0 1px rgba(0, 0, 0, 0.1);
        border: 1px solid rgba(255, 255, 255, 0.9);
    }

    .filter-btn {
        background: rgba(255, 255, 255, 0.9);
        backdrop-filter: blur(20px);
        border: 2px solid #E5E7EB;
        transition: all 0.3s ease;
        white-space: nowrap;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }

    .filter-btn:hover, .filter-btn.active {
        background: linear-gradient(120deg, #4F46E5, #6366F1);
        border-color: transparent;
        color: white;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(79, 70, 229, 0.2);
    }

    .filter-btn:not(.active):hover {
        border-color: #4F46E5;
        color: #4F46E5;
        background: white;
    }
</style>

<!-- Hero Section -->
<div class="luxury-gradient pt-32 pb-16">
    <div class="hero-pattern"></div>
    <div class="max-w-7xl mx-auto px-8 relative z-10">
        <div class="text-center">
            <h1 class="text-6xl font-light mb-6 tracking-wide text-gray-800">
                <?php echo $t['hero_title']; ?>
                <span class="block text-5xl font-semibold mt-3 premium-gradient">
                    <?php echo $t['hero_subtitle']; ?>
                </span>
            </h1>
            <p class="text-xl text-gray-600 max-w-3xl mx-auto leading-relaxed">
                <?php echo $t['hero_description']; ?>
            </p>
        </div>
    </div>
</div>

<!-- Filters Section -->
<div class="luxury-gradient py-4">
    <div class="max-w-7xl mx-auto px-8">
        <div class="flex flex-wrap gap-6 justify-center items-center">
            <!-- Tümü butonu -->
            <button class="filter-btn active px-8 py-4 min-w-[120px] text-lg rounded-full text-gray-700 font-medium" data-filter="all">
                <?php echo $t['all_services']; ?>
            </button>
            
            <?php foreach($categories as $category): ?>
                <?php if(!empty($category['name'])): 
                            error_log("Kategori ID: " . $category['id'] . ", İsim: " . $category['name']); 
                            ?>  <!-- Boş kategori isimlerini filtrele -->
                    <button class="filter-btn px-8 py-4 min-w-[120px] text-lg rounded-full text-gray-700 font-medium" 
                            data-filter="category-<?php echo htmlspecialchars($category['id']); ?>">
                        <?php echo htmlspecialchars($category['name']); ?>
                    </button>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Services Grid -->
<div class="luxury-gradient py-12">
   <div class="hero-pattern"></div>
   <div class="max-w-7xl mx-auto px-8 relative">
       <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
           <?php if (empty($services)): ?>
               <div class="col-span-3 text-center py-12">
                   <p class="text-gray-600 text-lg">
                       <?php echo $lang == 'tr' ? 'Henüz hizmet bulunmamaktadır.' : 'No services available yet.'; ?>
                   </p>
               </div>
           <?php else: ?>
               <?php foreach ($services as $service): ?>
                   <div class="elegant-card rounded-2xl overflow-hidden h-full flex flex-col transform transition-all duration-300 mb-12"
                        data-category="category-<?php echo htmlspecialchars($service['category_id']); ?>">
                       <!-- Resim Alanı -->
                       <div class="relative h-56 overflow-hidden flex-shrink-0">
                           <?php if ($service['image_url']): ?>
                               <img src="assets/images/services/<?php echo htmlspecialchars($service['image_url']); ?>" 
                                   alt="<?php echo htmlspecialchars($service['name']); ?>"
                                   class="w-full h-full object-cover transform hover:scale-110 transition duration-500">
                           <?php else: ?>
                               <img src="/api/placeholder/400/300" 
                                    alt="<?php echo htmlspecialchars($service['name']); ?>"
                                    class="w-full h-full object-cover transform hover:scale-110 transition duration-500">
                           <?php endif; ?>
                           <div class="absolute inset-0 bg-gradient-to-t from-gray-900 via-gray-900/40 to-transparent"></div>
                       </div>
                       
                       <!-- İçerik Alanı -->
                       <div class="p-8 flex flex-col flex-grow">
                           <h3 class="text-2xl font-light mb-4 premium-gradient">
                               <?php echo htmlspecialchars($service['name']); ?>
                           </h3>
                           
                           <p class="text-gray-600 mb-6 leading-relaxed flex-grow">
                               <?php echo htmlspecialchars($service['description']); ?>
                           </p>

                           <div class="mt-auto space-y-6">                                
                                <!-- Fiyat Bilgisi -->
                                <div class="flex items-baseline gap-2">
                                    <span class="text-sm text-gray-500"><?php echo $t['starting_price']; ?></span>
                                    <span class="text-2xl font-light premium-gradient">
                                        ₺<?php echo number_format($service['price'], 0, ',', '.'); ?>
                                    </span>
                                </div>
                                
                                <!-- Doktor Bilgisi -->
                                <div>
                                    <span class="text-sm text-gray-500 block mb-1"><?php echo $t['expert_doctors']; ?>:</span>
                                    <?php if ($service['doctors']): ?>
                                        <p class="text-indigo-600"><?php echo htmlspecialchars($service['doctors']); ?></p>
                                    <?php else: ?>
                                        <p class="text-indigo-600"><?php echo $t['no_doctor_yet']; ?></p>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Detay Butonu -->
                                <a href="service_detail.php?id=<?php echo $service['id']; ?>" 
                                    class="inline-block w-full text-center px-8 py-4 bg-gradient-to-r from-indigo-600 
                                        to-indigo-700 text-white rounded-xl transform hover:-translate-y-1 
                                        hover:shadow-xl transition-all duration-300">
                                    <?php echo $t['details_button']; ?>
                                </a>
                            </div>
                       </div>
                   </div>
               <?php endforeach; ?>
           <?php endif; ?>
       </div>
   </div>
</div>

<!-- Call to Action -->
<div class="luxury-gradient py-24 border-t border-gray-200">
    <div class="hero-pattern"></div>
    <div class="max-w-4xl mx-auto px-8 text-center relative">
        <h2 class="text-4xl font-light mb-6 text-gray-800"><?php echo $t['cta_title']; ?></h2>
        <p class="text-xl text-gray-600 mb-12 leading-relaxed">
            <?php echo $t['cta_description_2']; ?>
        </p>
        <a href="contact.php" 
           class="inline-block px-12 py-4 bg-gradient-to-r from-indigo-600 to-indigo-700 
                  text-white rounded-full transform hover:-translate-y-1 hover:shadow-xl 
                  transition-all duration-300">
            <?php echo $t['contact_button_2']; ?>
        </a>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const filterBtns = document.querySelectorAll('.filter-btn');
    const serviceCards = document.querySelectorAll('.elegant-card');

    filterBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            // Aktif buton sınıfını güncelle
            filterBtns.forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            
            const filter = btn.dataset.filter;
            
            // Kartları filtrele
            serviceCards.forEach(card => {
                if (filter === 'all' || card.dataset.category === filter) {
                    card.style.opacity = '1';
                    card.style.transform = 'scale(1)';
                    card.style.display = 'block';
                } else {
                    card.style.opacity = '0';
                    card.style.transform = 'scale(0.95)';
                    setTimeout(() => {
                        card.style.display = 'none';
                    }, 300);
                }
            });
        });
    });

    // Parallax effect for hero pattern
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
    gsap.utils.toArray('.elegant-card').forEach(card => {
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
});
</script>

<?php include_once './includes/new-footer.php'; ?>