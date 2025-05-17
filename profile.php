<?php
session_start();
include_once './config/database.php';
include_once './includes/new-header.php';

// Kullanıcı giriş yapmamışsa login sayfasına yönlendir
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Dil seçimi
$lang = $_SESSION['lang'] ?? 'tr';
$translations_file = __DIR__ . "/translations/translation_{$lang}.php";

if (file_exists($translations_file)) {
    $translations = require $translations_file;
    $pt = array_merge($translations['tr'] ?? [], $translations ?? []);
} else {
    die("Translation file not found: {$translations_file}");
}

// Veritabanı bağlantısı
$database = new Database();
$db = $database->getConnection();

// Kullanıcı bilgilerini al
$query = "SELECT * FROM users WHERE id = ?";
$stmt = $db->prepare($query);
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Hangi sütunu çekeceğiz?
if ($lang === 'en') {
    $service_name_field = "s.name_en AS service_name";
} elseif ($lang === 'de') {
    $service_name_field = "s.name_de AS service_name";
} elseif ($lang === 'ru') {
    $service_name_field = "s.name_ru AS service_name";
} elseif ($lang === 'es') {
    $service_name_field = "s.name_es AS service_name";
} elseif ($lang === 'zh') {
    $service_name_field = "s.name_zh AS service_name";
} elseif ($lang === 'fr') {
    $service_name_field = "s.name_fr AS service_name";
} elseif ($lang === 'it') {
    $service_name_field = "s.name_it AS service_name";
} else {
    $service_name_field = "s.name AS service_name";
}

// Kullanıcının siparişlerini al
$query = "SELECT o.*, 
                 $service_name_field, 
                 d.name as doctor_name
          FROM orders o
          LEFT JOIN services s ON o.service_id = s.id
          LEFT JOIN doctors d ON o.doctor_id = d.id
          WHERE o.user_id = ?
          ORDER BY o.created_at DESC";

$stmt = $db->prepare($query);
$stmt->execute([$_SESSION['user_id']]);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
    
    .elegant-card {
        background: rgba(255, 255, 255, 0.98);
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
    }

    .premium-input {
        background: rgba(255, 255, 255, 0.9);
        border: 1px solid rgba(79, 70, 229, 0.2);
        transition: all 0.3s ease;
    }

    .premium-input:focus {
        border-color: rgba(79, 70, 229, 0.5);
        box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.1);
    }

    .status-badge {
        padding: 0.5rem 1rem;
        border-radius: 9999px;
        font-size: 0.875rem;
        font-weight: 500;
        transition: all 0.3s ease;
    }
</style>

<div class="luxury-gradient min-h-screen pt-20">
    <div class="hero-pattern"></div>
    
    <!-- Success Message -->
    <?php if (isset($_SESSION['profile_success'])): ?>
    <div id="successAlert" class="fixed top-20 right-4 bg-gradient-to-r from-green-500 to-emerald-500 text-white px-6 py-3 rounded-lg shadow-lg transform transition-all duration-500 translate-x-full">
        <div class="flex items-center space-x-2">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
            </svg>
            <span><?php echo $_SESSION['profile_success']; ?></span>
        </div>
    </div>
    <?php unset($_SESSION['profile_success']); endif; ?>

    <div class="max-w-7xl mx-auto px-4 py-12">
        <!-- Profile Header -->
        <div class="elegant-card rounded-2xl p-8 mb-8">
            <div class="flex justify-between items-start">
                <form action="update_profile_image.php" method="POST" enctype="multipart/form-data" class="flex items-center space-x-6">
                    <div class="relative group">
                        <div class="w-24 h-24 rounded-full bg-gradient-to-r from-indigo-500 to-indigo-600 flex items-center justify-center text-3xl font-light text-white shadow-lg overflow-hidden">
                            <?php if($user['image_url'] && file_exists($user['image_url'])): ?>
                                <img src="<?php echo htmlspecialchars($user['image_url']); ?>" alt="Profile" class="w-full h-full object-cover">
                            <?php else: ?>
                                <?php echo strtoupper(substr($user['full_name'], 0, 1)); ?>
                            <?php endif; ?>
                            <div class="absolute inset-0 bg-black bg-opacity-50 opacity-0 group-hover:opacity-100 flex items-center justify-center transition-opacity cursor-pointer">
                                <label for="profile_image" class="text-white text-sm cursor-pointer">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"></path>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                    </svg>
                                </label>
                                <input type="file" id="profile_image" name="profile_image" class="hidden" accept="image/*" onchange="this.form.submit()">
                            </div>
                        </div>
                    </div>
                    <div>
                        <h1 class="text-3xl font-light text-gray-800 mb-2"><?php echo htmlspecialchars($user['full_name']); ?></h1>
                        <p class="text-gray-500"><?php echo htmlspecialchars($user['email']); ?></p>
                    </div>
                </form>
                
                <!-- Hesap Silme Butonu -->
                <button type="button" 
                        onclick="confirmDeleteAccount()"
                        class="text-sm px-4 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600 transition duration-300">
                    <?php echo $pt['delete_account']; ?>
                </button>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Profile Info Card -->
            <div class="lg:col-span-1">
                <div class="elegant-card rounded-2xl p-8">
                    <h2 class="text-2xl font-light text-gray-800 mb-8"><?php echo $pt['profile_title_2']; ?></h2>
                    <form action="update_profile.php" method="POST" class="space-y-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-500 mb-2"><?php echo $pt['username_2']; ?></label>
                            <div class="relative group">
                                <input type="text" 
                                       value="<?php echo htmlspecialchars($user['username']); ?>" 
                                       class="premium-input w-full px-4 py-3 rounded-lg text-gray-700 cursor-not-allowed" 
                                       readonly>
                                <div class="absolute hidden group-hover:block bg-gray-800 text-white text-sm px-3 py-1 rounded mt-1 whitespace-nowrap">
                                    <?php echo $pt['cannot_change']; ?>
                                </div>
                            </div>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-500 mb-2"><?php echo $pt['email']; ?></label>
                            <div class="relative group">
                                <input type="email" 
                                       value="<?php echo htmlspecialchars($user['email']); ?>" 
                                       class="premium-input w-full px-4 py-3 rounded-lg text-gray-700 cursor-not-allowed" 
                                       readonly>
                                <div class="absolute hidden group-hover:block bg-gray-800 text-white text-sm px-3 py-1 rounded mt-1 whitespace-nowrap">
                                    <?php echo $pt['cannot_change']; ?>
                                </div>
                            </div>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-500 mb-2"><?php echo $pt['full_name_2']; ?></label>
                            <input type="text" 
                                   name="full_name" 
                                   value="<?php echo htmlspecialchars($user['full_name']); ?>" 
                                   class="premium-input w-full px-4 py-3 rounded-lg text-gray-700">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-500 mb-2"><?php echo $pt['phone']; ?></label>
                            <input type="tel" 
                                   name="phone" 
                                   value="<?php echo htmlspecialchars($user['phone']); ?>" 
                                   class="premium-input w-full px-4 py-3 rounded-lg text-gray-700">
                        </div>
                        
                        <button type="submit" 
                                class="w-full bg-gradient-to-r from-indigo-500 to-indigo-600 text-white px-6 py-3 rounded-lg 
                                       hover:shadow-lg transform hover:scale-105 transition duration-300">
                            <?php echo $pt['update_info']; ?>
                        </button>
                    </form>
                </div>
            </div>

            <!-- Orders Card -->
            <div class="lg:col-span-2">
                <div class="elegant-card rounded-2xl p-8 relative"> <!-- z-10 ekledim -->
                    <h2 class="text-2xl font-light text-gray-800 mb-8"><?php echo $pt['purchased_services']; ?></h2>
                    
                    <?php if ($orders): ?>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead>
                                <tr class="border-b border-gray-200">
                                    <th class="text-left py-4 px-4 text-gray-500 font-medium"><?php echo $pt['service_2']; ?></th>
                                    <th class="text-left py-4 px-4 text-gray-500 font-medium"><?php echo $pt['doctor']; ?></th>
                                    <th class="text-left py-4 px-4 text-gray-500 font-medium"><?php echo $pt['amount']; ?></th>
                                    <th class="text-left py-4 px-4 text-gray-500 font-medium"><?php echo $pt['status']; ?></th>
                                    <th class="text-left py-4 px-4 text-gray-500 font-medium"><?php echo $pt['date']; ?></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <?php foreach ($orders as $order): ?>
                                <tr class="hover:bg-gray-50 transition-colors duration-200">
                                    <td class="py-4 px-4">
                                        <span class="text-gray-700"><?php echo htmlspecialchars($order['service_name']); ?></span>
                                    </td>
                                    <td class="py-4 px-4">
                                        <span class="text-gray-700"><?php echo htmlspecialchars($order['doctor_name']); ?></span>
                                    </td>
                                    <td class="py-4 px-4">
                                        <span class="text-gray-700">₺<?php echo number_format($order['total_amount'], 2); ?></span>
                                    </td>
                                    <td class="py-4 px-4">
                                        <span class="status-badge 
                                            <?php 
                                            switch($order['status']) {
                                                case 'pending':
                                                    echo 'bg-yellow-100 text-yellow-700';
                                                    break;
                                                case 'completed':
                                                    echo 'bg-green-100 text-green-700';
                                                    break;
                                                case 'cancelled':
                                                    echo 'bg-red-100 text-red-700';
                                                    break;
                                                default:
                                                    echo 'bg-gray-100 text-gray-700';
                                            }
                                            ?>">
                                            <?php 
                                            switch($order['status']) {
                                                case 'pending':
                                                    echo $pt['status_pending'];
                                                    break;
                                                case 'completed':
                                                    echo $pt['status_completed'];
                                                    break;
                                                case 'cancelled':
                                                    echo $pt['status_cancelled'];
                                                    break;
                                                default:
                                                    echo ucfirst($order['status']);
                                            }
                                            ?>
                                        </span>
                                    </td>
                                    <td class="py-4 px-4">
                                        <span class="text-gray-700"><?php echo date('d.m.Y', strtotime($order['created_at'])); ?></span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="text-center py-12 relative z-20">
                        <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <svg class="w-10 h-10 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" 
                                      d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                        </div>
                        <h3 class="text-xl font-light text-gray-600 mb-2"><?php echo $pt['no_services']; ?></h3>
                        <p class="text-gray-500 mb-6"><?php echo $pt['start_exploring']; ?></p>
                        <a href="services.php" 
                           class="inline-flex items-center text-indigo-600 hover:text-indigo-700 transition-colors duration-200">
                            <?php echo $pt['explore_services']; ?>
                            <svg class="w-5 h-5 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/>
                            </svg>
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Delete Account Modal -->
<div id="deleteAccountModal" class="fixed inset-0 bg-black bg-opacity-60 backdrop-blur-sm hidden items-center justify-center z-50 transition-all duration-300 opacity-0">
    <div class="bg-white rounded-2xl p-8 max-w-md mx-4 shadow-2xl transform transition-all duration-300 scale-95 translate-y-4">
        <!-- Modal Header with Icon -->
        <div class="text-center mb-6">
            <div class="mx-auto w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mb-4">
                <svg class="w-8 h-8 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                </svg>
            </div>
            <h3 class="text-2xl font-semibold text-gray-900 mb-2"><?php echo $pt['delete_confirm_title']; ?></h3>
            <p class="text-gray-600"><?php echo $pt['delete_confirm_text']; ?></p>
        </div>

        <!-- Divider -->
        <div class="border-t border-gray-200 my-6"></div>

        <!-- Action Buttons -->
        <div class="flex flex-col sm:flex-row space-y-3 sm:space-y-0 sm:space-x-4">
            <button onclick="hideDeleteModal()" 
                    class="flex-1 px-6 py-3 bg-white border border-gray-300 rounded-xl text-gray-700 font-medium hover:bg-gray-50 hover:border-gray-400 transition-all duration-300 group">
                <span class="flex items-center justify-center">
                    <svg class="w-5 h-5 mr-2 text-gray-400 group-hover:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                    <?php echo $pt['delete_cancel']; ?>
                </span>
            </button>
            <form action="delete_account.php" method="POST" class="flex-1">
                <button type="submit" 
                        class="w-full px-6 py-3 bg-gradient-to-r from-red-500 to-red-600 text-white rounded-xl font-medium hover:from-red-600 hover:to-red-700 transform hover:scale-[1.02] transition-all duration-300 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 shadow-lg shadow-red-500/30">
                    <span class="flex items-center justify-center">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                        <?php echo $pt['delete_confirm']; ?>
                    </span>
                </button>
            </form>
        </div>
    </div>
</div>

<?php include_once './includes/new-footer.php'; ?>

<script>
// Success message animation
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

// Delete Account Modal Functions
function confirmDeleteAccount() {
    const modal = document.getElementById('deleteAccountModal');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    // Smooth fade in
    setTimeout(() => {
        modal.classList.remove('opacity-0');
        modal.querySelector('div').classList.remove('scale-95', 'translate-y-4');
    }, 10);
}

function hideDeleteModal() {
    const modal = document.getElementById('deleteAccountModal');
    modal.classList.add('opacity-0');
    modal.querySelector('div').classList.add('scale-95', 'translate-y-4');
    // Wait for animation to finish
    setTimeout(() => {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }, 300);
}

// GSAP Animations
gsap.registerPlugin(ScrollTrigger);

// Card animations
gsap.utils.toArray('.elegant-card').forEach((card, index) => {
    gsap.from(card, {
        opacity: 0,
        y: 50,
        duration: 0.8,
        delay: index * 0.2,
        scrollTrigger: {
            trigger: card,
            start: "top bottom-=100",
            toggleActions: "play none none reverse"
        }
    });
});

// Form input animations
const formInputs = document.querySelectorAll('input');
formInputs.forEach(input => {
    input.addEventListener('focus', () => {
        gsap.to(input, {
            scale: 1.02,
            duration: 0.2,
            ease: 'power2.out'
        });
    });

    input.addEventListener('blur', () => {
        gsap.to(input, {
            scale: 1,
            duration: 0.2,
            ease: 'power2.in'
        });
    });
});
</script>
