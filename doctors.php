<?php
session_start();

// Dil dosyası yükleme
$lang = $_SESSION['lang'] ?? 'tr';
$translations_file = __DIR__ . "/translations/translation_{$lang}.php";

if (file_exists($translations_file)) {
   $translations = require $translations_file;
   $t = $translations;
} else {
   die("Translation file not found: {$translations_file}");
}

include_once './config/database.php';
include_once './includes/new-header.php';

$database = new Database();
$db = $database->getConnection();

try {
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
    $stmt->execute();
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $query = "SELECT DISTINCT d.id, 
        d.name,
        CASE 
            WHEN :lang = 'en' THEN d.bio_en
            WHEN :lang = 'de' THEN COALESCE(d.bio_de, d.bio_en, d.bio_tr)
            WHEN :lang = 'fr' THEN COALESCE(d.bio_fr, d.bio_en, d.bio_tr)
            WHEN :lang = 'es' THEN COALESCE(d.bio_es, d.bio_en, d.bio_tr)
            WHEN :lang = 'it' THEN COALESCE(d.bio_it, d.bio_en, d.bio_tr)
            WHEN :lang = 'ru' THEN COALESCE(d.bio_ru, d.bio_en, d.bio_tr)
            WHEN :lang = 'zh' THEN COALESCE(d.bio_zh, d.bio_en, d.bio_tr)
            ELSE d.bio_tr
        END as bio,
        d.specialty, 
        d.image_url,
        GROUP_CONCAT(
            CASE 
                WHEN :lang = 'en' THEN COALESCE(s.name_en, s.name)
                WHEN :lang = 'de' THEN COALESCE(s.name_de, s.name_en, s.name)
                WHEN :lang = 'fr' THEN COALESCE(s.name_fr, s.name_en, s.name)
                WHEN :lang = 'es' THEN COALESCE(s.name_es, s.name_en, s.name)
                WHEN :lang = 'it' THEN COALESCE(s.name_it, s.name_en, s.name)
                WHEN :lang = 'ru' THEN COALESCE(s.name_ru, s.name_en, s.name)
                WHEN :lang = 'zh' THEN COALESCE(s.name_zh, s.name_en, s.name)
                ELSE s.name
            END
        ) as service_name,
        GROUP_CONCAT(s.id) as service_id,
        GROUP_CONCAT(DISTINCT sc.id) as category_id,
        GROUP_CONCAT(DISTINCT 
            CASE 
                WHEN :lang = 'en' THEN COALESCE(sc.name_en, sc.name)
                WHEN :lang = 'de' THEN COALESCE(sc.name_de, sc.name_en, sc.name)
                WHEN :lang = 'fr' THEN COALESCE(sc.name_fr, sc.name_en, sc.name)
                WHEN :lang = 'es' THEN COALESCE(sc.name_es, sc.name_en, sc.name)
                WHEN :lang = 'it' THEN COALESCE(sc.name_it, sc.name_en, sc.name)
                WHEN :lang = 'ru' THEN COALESCE(sc.name_ru, sc.name_en, sc.name)
                WHEN :lang = 'zh' THEN COALESCE(sc.name_zh, sc.name_en, sc.name)
                ELSE sc.name
            END
        ) as category_name
        FROM doctors d 
        LEFT JOIN doctor_services ds ON d.id = ds.doctor_id
        LEFT JOIN services s ON ds.service_id = s.id
        LEFT JOIN service_categories sc ON s.category_id = sc.id
        WHERE d.status = 'active'
        GROUP BY d.id, d.name, d.bio_tr, d.bio_en, d.specialty, d.image_url
        ORDER BY d.name ASC";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':lang', $lang);
    $stmt->execute();
    $doctors = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Veritabanı hatası: " . $e->getMessage());
    $categories = [];
    $doctors = [];
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
                <?php echo $t['hero_title_3']; ?>
                <span class="block text-5xl font-semibold mt-3 premium-gradient">
                    <?php echo $t['hero_subtitle_3']; ?>
                </span>
            </h1>
            <p class="text-xl text-gray-600 max-w-3xl mx-auto leading-relaxed">
                <?php echo $t['hero_description_3']; ?>
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
                <?php echo $t['all_doctors']; ?>
            </button>
            
            <?php foreach($categories as $category): ?>
                <button class="filter-btn px-8 py-4 min-w-[120px] text-lg rounded-full text-gray-700 font-medium" 
                        data-filter="category-<?php echo htmlspecialchars($category['id']); ?>">
                    <?php echo htmlspecialchars($category['name']); ?>
                </button>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Doctors Grid -->
<div class="luxury-gradient py-24">
   <div class="hero-pattern"></div>
   <div class="max-w-7xl mx-auto px-8 relative">
       <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
           <?php if (empty($doctors)): ?>
               <div class="col-span-3 text-center py-12">
                   <p class="text-gray-600 text-lg">
                       <?php echo $lang == 'tr' ? 'Henüz doktor bilgisi bulunmamaktadır.' : 'No doctor information available yet.'; ?>
                   </p>
               </div>
           <?php else: ?>
               <?php foreach ($doctors as $doctor): ?>
                   <div class="elegant-card rounded-2xl overflow-hidden h-full flex flex-col transform transition-all duration-300"
                        data-category="category-<?php echo htmlspecialchars($doctor['category_id'] ?? ''); ?>">
                       <!-- Resim Alanı -->
                       <div class="relative h-64 overflow-hidden flex-shrink-0">
                           <?php if ($doctor['image_url']): ?>
                               <img src="assets/images/<?php echo htmlspecialchars($doctor['image_url']); ?>" 
                                    alt="<?php echo htmlspecialchars($doctor['name']); ?>"
                                    class="w-full h-full object-cover transform hover:scale-110 transition duration-500">
                           <?php else: ?>
                               <div class="w-full h-full bg-gradient-to-br from-indigo-50 to-indigo-100 flex items-center justify-center">
                                   <svg class="w-24 h-24 text-indigo-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                       <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" 
                                             d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                   </svg>
                               </div>
                           <?php endif; ?>
                           <div class="absolute inset-0 bg-gradient-to-t from-gray-900 via-gray-900/40 to-transparent"></div>
                       </div>

                       <!-- İçerik Alanı -->
                       <div class="p-8 flex flex-col flex-grow">
                           <h3 class="text-2xl font-light mb-4 premium-gradient">
                               <?php echo htmlspecialchars($doctor['name']); ?>
                           </h3>
                           
                           <p class="text-indigo-600 mb-4 font-medium">
                               <?php echo htmlspecialchars($doctor['specialty']); ?>
                           </p>
                           
                           <p class="text-gray-600 mb-6 leading-relaxed line-clamp-3 flex-grow">
                               <?php echo htmlspecialchars($doctor['bio']); ?>
                           </p>

                           <div class="mt-auto space-y-6">                                
                               <!-- Verdiği Hizmet -->
                               <?php if ($doctor['service_name']): ?>
                                <div>
                                    <span class="text-sm text-gray-500 block mb-1">
                                        <?php echo $t['service_provided']; ?>:
                                    </span>
                                    <p class="text-indigo-600"><?php echo htmlspecialchars($doctor['service_name']); ?></p>
                                </div>
                                <?php else: ?>
                                <div>
                                    <span class="text-sm text-gray-500 block mb-1">
                                        <?php echo $t['service_provided']; ?>:
                                    </span>
                                    <p class="text-indigo-600"><?php echo $t['no_service_yet']; ?></p>
                                </div>
                                <?php endif; ?>
                               
                               <!-- Detay Butonu -->
                               <button onclick="openModal('doctorModal<?php echo $doctor['id']; ?>')"
                                       class="inline-block w-full text-center px-8 py-4 bg-gradient-to-r from-indigo-600 
                                              to-indigo-700 text-white rounded-xl transform hover:-translate-y-1 
                                              hover:shadow-xl transition-all duration-300">
                                   <?php echo $t['detailed_info']; ?>
                               </button>
                           </div>
                       </div>
                   </div>

                   <!-- Doctor Modal -->
                   <div id="doctorModal<?php echo $doctor['id']; ?>" 
                        class="fixed inset-0 z-50 hidden overflow-y-auto"
                        aria-labelledby="modal-title" 
                        role="dialog" 
                        aria-modal="true">
                       <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                           <div class="fixed inset-0 bg-gray-900 bg-opacity-75 transition-opacity" 
                                aria-hidden="true"></div>

                           <span class="hidden sm:inline-block sm:align-middle sm:h-screen" 
                                 aria-hidden="true">&#8203;</span>

                           <div class="modal-content inline-block align-bottom bg-white rounded-2xl 
                                     text-left overflow-hidden shadow-xl transform transition-all 
                                     sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                               <div class="relative">
                                   <?php if ($doctor['image_url']): ?>
                                       <img src="assets/images/<?php echo htmlspecialchars($doctor['image_url']); ?>" 
                                            alt="<?php echo htmlspecialchars($doctor['name']); ?>"
                                            class="w-full h-64 object-cover">
                                   <?php else: ?>
                                       <div class="w-full h-64 bg-gradient-to-br from-indigo-50 to-indigo-100 flex items-center justify-center">
                                           <svg class="w-24 h-24 text-indigo-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                               <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" 
                                                     d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                           </svg>
                                       </div>
                                   <?php endif; ?>
                                   <div class="absolute inset-0 bg-gradient-to-t from-gray-900 via-gray-900/40 to-transparent"></div>
                               </div>
                               
                               <div class="p-8">
                                   <h3 class="text-2xl font-light mb-2 premium-gradient">
                                       <?php echo htmlspecialchars($doctor['name']); ?>
                                   </h3>
                                   <p class="text-indigo-600 mb-4 font-medium">
                                       <?php echo htmlspecialchars($doctor['specialty']); ?>
                                   </p>
                                   <p class="text-gray-600 mb-6">
                                       <?php echo htmlspecialchars($doctor['bio']); ?>
                                   </p>
                                   
                                   <div class="bg-indigo-50 rounded-xl p-4 mb-6">
                                       <h4 class="font-medium text-indigo-900 mb-2"><?php echo $t['modal_service']; ?></h4>
                                       <p class="text-indigo-600">
                                           <?php echo htmlspecialchars($doctor['service_name']); ?>
                                       </p>
                                   </div>

                                   <div class="flex justify-end space-x-4">
                                       <button onclick="closeModal('doctorModal<?php echo $doctor['id']; ?>')"
                                               class="px-6 py-2 bg-gray-200 text-gray-700 rounded-full hover:bg-gray-300 
                                                      transition-colors duration-300">
                                           <?php echo $t['close']; ?>
                                       </button>
                                       <a href="service_detail.php?id=<?php echo $doctor['service_id']; ?>" 
                                          class="px-6 py-2 bg-gradient-to-r from-indigo-600 to-indigo-700 text-white 
                                                 rounded-full hover:shadow-lg transform hover:-translate-y-0.5 
                                                 transition duration-300">
                                           <?php echo $t['view_service']; ?>
                                       </a>
                                   </div>
                               </div>
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
            <?php echo $t['cta_description_3']; ?>
        </p>
        <a href="contact.php" 
           class="inline-block px-12 py-4 bg-gradient-to-r from-indigo-600 to-indigo-700 
                  text-white rounded-full transform hover:-translate-y-1 hover:shadow-xl 
                  transition-all duration-300">
            <?php echo $t['contact_button_3']; ?>
        </a>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const filterBtns = document.querySelectorAll('.filter-btn');
    const doctorCards = document.querySelectorAll('.elegant-card');

    filterBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            // Aktif buton sınıfını güncelle
            filterBtns.forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            
            const filter = btn.dataset.filter;
            console.log('Selected filter:', filter); // Debug için

            // Kartları filtrele
            doctorCards.forEach(card => {
                console.log('Card category:', card.dataset.category); // Debug için
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

// Modal functions
function openModal(modalId) {
    const modal = document.getElementById(modalId);
    modal.classList.remove('hidden');
    gsap.from(modal.querySelector('.sm\\:max-w-lg'), {
        y: 50,
        opacity: 0,
        duration: 0.4,
        ease: 'power3.out'
    });
}

// Modal functions
function closeModal(modalId) {
    document.getElementById(modalId).classList.add('hidden');
}

// Close modal when clicking overlay
window.onclick = function(event) {
    const modalOverlay = event.target.closest('.fixed.inset-0.z-50');
    if (modalOverlay && event.target === modalOverlay) {
        const modalId = modalOverlay.id;
        closeModal(modalId);
    }
}
</script>

<?php include_once './includes/new-footer.php'; ?>