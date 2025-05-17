<!-- /includes/new-footer.php -->
<?php
// Footer çevirileri (tüm diller)
$footer_translations = [
    // Türkçe
    'tr' => [
        'company_description' => 'Sağlık turizmi için güvenilir adresiniz. Profesyonel kadromuz ve modern teknolojimizle yanınızdayız.',
        'services' => 'Hizmetler',
        'services_list' => [
            'hair_transplant' => 'Saç Ekimi',
            'dental'          => 'Diş Tedavileri',
            'aesthetic'       => 'Estetik Cerrahi',
            'eye'             => 'Göz Tedavileri'
        ],
        'contact'         => 'İletişim',
        'address'         => 'Adres: İstanbul, Türkiye',
        'follow_us'       => 'Takip Edin',
        'rights_reserved' => 'Tüm hakları saklıdır.'
    ],

    // İngilizce
    'en' => [
        'company_description' => 'Your trusted destination for health tourism. We are at your service with our professional staff and modern technology.',
        'services' => 'Services',
        'services_list' => [
            'hair_transplant' => 'Hair Transplant',
            'dental'          => 'Dental Treatments',
            'aesthetic'       => 'Aesthetic Surgery',
            'eye'             => 'Eye Treatments'
        ],
        'contact'         => 'Contact',
        'address'         => 'Address: Istanbul, Turkey',
        'follow_us'       => 'Follow Us',
        'rights_reserved' => 'All rights reserved.'
    ],

    // Almanca (Deutsch)
    'de' => [
        'company_description' => 'Ihre vertrauenswürdige Adresse für Gesundheitstourismus. Wir stehen Ihnen mit unserem professionellen Team und modernster Technologie zur Seite.',
        'services' => 'Dienstleistungen',
        'services_list' => [
            'hair_transplant' => 'Haartransplantation',
            'dental'          => 'Zahnbehandlungen',
            'aesthetic'       => 'Ästhetische Chirurgie',
            'eye'             => 'Augenbehandlungen'
        ],
        'contact'         => 'Kontakt',
        'address'         => 'Adresse: Istanbul, Türkei',
        'follow_us'       => 'Folgen Sie uns',
        'rights_reserved' => 'Alle Rechte vorbehalten.'
    ],

    // Rusça (Русский)
    'ru' => [
        'company_description' => 'Надежное место для медицинского туризма. К вашим услугам наша профессиональная команда и современная технология.',
        'services' => 'Услуги',
        'services_list' => [
            'hair_transplant' => 'Пересадка волос',
            'dental'          => 'Стоматологические услуги',
            'aesthetic'       => 'Эстетическая хирургия',
            'eye'             => 'Лечение глаз'
        ],
        'contact'         => 'Контакты',
        'address'         => 'Адрес: Стамбул, Турция',
        'follow_us'       => 'Подписывайтесь на нас',
        'rights_reserved' => 'Все права защищены.'
    ],

    // İspanyolca (Español)
    'es' => [
        'company_description' => 'Su destino de confianza para el turismo de salud. Estamos a su servicio con nuestro personal profesional y tecnología moderna.',
        'services' => 'Servicios',
        'services_list' => [
            'hair_transplant' => 'Trasplante Capilar',
            'dental'          => 'Tratamientos Dentales',
            'aesthetic'       => 'Cirugía Estética',
            'eye'             => 'Tratamientos Oculares'
        ],
        'contact'         => 'Contacto',
        'address'         => 'Dirección: Estambul, Turquía',
        'follow_us'       => 'Síguenos',
        'rights_reserved' => 'Todos los derechos reservados.'
    ],

    // Çince (中文)
    'zh' => [
        'company_description' => '您值得信赖的医疗旅游目的地。我们拥有专业团队和现代技术，为您提供服务。',
        'services' => '服务',
        'services_list' => [
            'hair_transplant' => '植发',
            'dental'          => '牙科治疗',
            'aesthetic'       => '整形手术',
            'eye'             => '眼科治疗'
        ],
        'contact'         => '联系方式',
        'address'         => '地址：土耳其伊斯坦布尔',
        'follow_us'       => '关注我们',
        'rights_reserved' => '版权所有。'
    ],

    // Fransızca (Français)
    'fr' => [
        'company_description' => 'Votre destination de confiance pour le tourisme de santé. Nous sommes à votre service avec notre équipe professionnelle et notre technologie moderne.',
        'services' => 'Services',
        'services_list' => [
            'hair_transplant' => 'Greffe de Cheveux',
            'dental'          => 'Soins Dentaires',
            'aesthetic'       => 'Chirurgie Esthétique',
            'eye'             => 'Traitements Oculaires'
        ],
        'contact'         => 'Contact',
        'address'         => 'Adresse : Istanbul, Turquie',
        'follow_us'       => 'Suivez-nous',
        'rights_reserved' => 'Tous droits réservés.'
    ],

    // İtalyanca (Italiano)
    'it' => [
        'company_description' => 'La tua destinazione affidabile per il turismo sanitario. Siamo al tuo servizio con il nostro staff professionale e tecnologia moderna.',
        'services' => 'Servizi',
        'services_list' => [
            'hair_transplant' => 'Trapianto di Capelli',
            'dental'          => 'Trattamenti Dentali',
            'aesthetic'       => 'Chirurgia Estetica',
            'eye'             => 'Trattamenti Oculistici'
        ],
        'contact'         => 'Contatti',
        'address'         => 'Indirizzo: Istanbul, Turchia',
        'follow_us'       => 'Seguici',
        'rights_reserved' => 'Tutti i diritti riservati.'
    ],
];

// Dil seçimine göre çevirileri al
$lang = $_SESSION['lang'] ?? 'tr';
$ft = $footer_translations[$lang];
?>

<footer class="bg-gray-50 py-16 mt-12 border-t border-gray-100">
    <div class="max-w-7xl mx-auto px-4">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-12">
            <!-- Şirket Tanıtım Alanı -->
            <div>
                <h4 class="text-xl font-bold text-gray-900 mb-4">HealthTurkey</h4>
                <p class="text-gray-600">
                    <?php echo $ft['company_description']; ?>
                </p>
            </div>

            <!-- Hizmetler / Services -->
            <div>
                <h4 class="text-lg font-semibold text-gray-900 mb-4"><?php echo $ft['services']; ?></h4>
                <ul class="space-y-2">
                    <li>
                        <a href="#" class="text-gray-600 hover:text-blue-600 transition-colors duration-300">
                            <?php echo $ft['services_list']['hair_transplant']; ?>
                        </a>
                    </li>
                    <li>
                        <a href="#" class="text-gray-600 hover:text-blue-600 transition-colors duration-300">
                            <?php echo $ft['services_list']['dental']; ?>
                        </a>
                    </li>
                    <li>
                        <a href="#" class="text-gray-600 hover:text-blue-600 transition-colors duration-300">
                            <?php echo $ft['services_list']['aesthetic']; ?>
                        </a>
                    </li>
                    <li>
                        <a href="#" class="text-gray-600 hover:text-blue-600 transition-colors duration-300">
                            <?php echo $ft['services_list']['eye']; ?>
                        </a>
                    </li>
                </ul>
            </div>

            <!-- İletişim / Contact -->
            <div>
                <h4 class="text-lg font-semibold text-gray-900 mb-4"><?php echo $ft['contact']; ?></h4>
                <ul class="space-y-2 text-gray-600">
                    <li>Email: info@healthturkey.com</li>
                    <li>Tel: +90 555 555 5555</li>
                    <li><?php echo $ft['address']; ?></li>
                </ul>
            </div>

            <!-- Sosyal Medya / Follow Us -->
            <div>
                <h4 class="text-lg font-semibold text-gray-900 mb-4"><?php echo $ft['follow_us']; ?></h4>
                <div class="flex space-x-4">
                    <!-- Facebook -->
                    <a href="#" class="w-10 h-10 rounded-full bg-blue-600 hover:bg-blue-700 text-white flex items-center justify-center transition-colors duration-300">
                        <span class="sr-only">Facebook</span>
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M22 12c0-5.523-4.477-10-10-10S2 6.477 2 12c0 4.991 3.657 9.128 8.438 9.878v-6.987h-2.54V12h2.54V9.797c0-2.506 1.492-3.89 3.777-3.89 1.094 0 2.238.195 2.238.195v2.46h-1.26c-1.243 0-1.63.771-1.63 1.562V12h2.773l-.443 2.89h-2.33v6.988C18.343 21.128 22 16.991 22 12z"/>
                        </svg>
                    </a>
                    <!-- Instagram -->
                    <a href="#" class="w-10 h-10 rounded-full bg-pink-600 hover:bg-pink-700 text-white flex items-center justify-center transition-colors duration-300">
                        <span class="sr-only">Instagram</span>
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path fill-rule="evenodd" d="M12.315 2c2.43 0 2.784.013 3.808.06 1.064.049 1.791.218 2.427.465a4.902 4.902 0 011.772 1.153 4.902 4.902 0 011.153 1.772c.247.636.416 1.363.465 2.427.048 1.067.06 1.407.06 4.123v.08c0 2.643-.012 2.987-.06 4.043-.049 1.064-.218 1.791-.465 2.427a4.902 4.902 0 01-1.153 1.772 4.902 4.902 0 01-1.772 1.153c-.636.247-1.363.416-2.427.465-1.067.048-1.407.06-4.123.06h-.08c-2.643 0-2.987-.012-4.043-.06-1.064-.049-1.791-.218-2.427-.465a4.902 4.902 0 01-1.772-1.153 4.902 4.902 0 01-1.153-1.772c-.247-.636-.416-1.363-.465-2.427-.047-1.024-.06-1.379-.06-3.808v-.63c0-2.43.013-2.784.06-3.808.049-1.064.218-1.791.465-2.427a4.902 4.902 0 011.153-1.772A4.902 4.902 0 015.45 2.525c.636-.247 1.363-.416 2.427-.465C8.901 2.013 9.256 2 11.685 2h.63zm-.081 1.802h-.468c-2.456 0-2.784.011-3.807.058-.975.045-1.504.207-1.857.344-.467.182-.8.398-1.15.748-.35.35-.566.683-.748 1.15-.137.353-.3.882-.344 1.857-.047 1.023-.058 1.351-.058 3.807v.468c0 2.456.011 2.784.058 3.807.045.975.207 1.504.344 1.857.182.466.399.8.748 1.15.35.35.683.566 1.15.748.353.137.882.3 1.857.344 1.054.048 1.37.058 4.041.058h.08c2.597 0 2.917-.01 3.96-.058.976-.045 1.505-.207 1.858-.344.466-.182.8-.398 1.15-.748.35-.35.566-.683.748-1.15.137-.353.3-.882.344-1.857.048-1.055.058-1.37.058-4.041v-.08c0-2.597-.01-2.917-.058-3.96-.045-.976-.207-1.505-.344-1.858a3.097 3.097 0 00-.748-1.15 3.098 3.098 0 00-1.15-.748c-.353-.137-.882-.3-1.857-.344-1.023-.047-1.351-.058-3.807-.058zM12 6.865a5.135 5.135 0 110 10.27 5.135 5.135 0 010-10.27zm0 1.802a3.333 3.333 0 100 6.666 3.333 3.333 0 000-6.666zm5.338-3.205a1.2 1.2 0 110 2.4 1.2 1.2 0 010-2.4z"/>
                        </svg>
                    </a>
                    <!-- Twitter -->
                    <a href="#" id="twitter-easter-egg" onclick="toggleEasterEgg(); return false;" class="w-10 h-10 rounded-full bg-blue-400 hover:bg-blue-500 text-white flex items-center justify-center transition-colors duration-300">
                        <span class="sr-only">Twitter</span>
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M8.29 20.251c7.547 0 11.675-6.253 11.675-11.675 0-.178 0-.355-.012-.53A8.348 8.348 0 0022 5.92a8.19 8.19 0 01-2.357.646 4.118 4.118 0 001.804-2.27 8.224 8.224 0 01-2.605.996 4.107 4.107 0 00-6.993 3.743 11.65 11.65 0 01-8.457-4.287 4.106 4.106 0 001.27 5.477A4.072 4.072 0 012.8 9.713v.052a4.105 4.105 0 003.292 4.022 4.095 4.095 0 01-1.853.07 4.108 4.108 0 003.834 2.85A8.233 8.233 0 012 18.407a11.616 11.616 0 006.29 1.84"/>
                        </svg>
                    </a>
                </div>
            </div>
        </div>

        <div class="mt-12 pt-8 border-t border-gray-100 text-center">
            <p class="text-gray-600">
                &copy; <?php echo date('Y'); ?> HealthTurkey. <?php echo $ft['rights_reserved']; ?>
            </p>
        </div>
    </div>
</footer>

<script>
let isPlaying = localStorage.getItem('easterEggActive') === 'true';
let audio = new Audio(window.location.origin + '/health_tourism/assets/images/hehe/benlazziyaa.mp3');
audio.loop = true;
const twitterIcon = document.getElementById('twitter-easter-egg');

// Sayfa yüklendiğinde easter egg durumunu kontrol et
document.addEventListener('DOMContentLoaded', () => {
    // Easter egg aktifse devam ettir
    if (isPlaying && twitterIcon) {
        audio.play().then(() => {
            twitterIcon.style.animation = "fly 2s infinite";
            document.body.style.animation = "gentleShake 0.5s infinite";
            // Rastgele takla atma başlat
            startRandomFlips();
        }).catch(error => console.error('Ses başlatılamadı:', error));
    }
});

// Rastgele takla atma fonksiyonu
let flipInterval;
function startRandomFlips() {
    flipInterval = setInterval(() => {
        if (isPlaying && Math.random() < 0.30) {
            document.body.style.animation = "none";
            document.body.offsetHeight; // Reflow
            document.body.classList.add('flipping');
            document.body.style.animation = "pageFlip 1s cubic-bezier(0.4, 0, 0.2, 1)";
            
            setTimeout(() => {
                document.body.classList.remove('flipping');
                if (isPlaying) {
                    document.body.style.animation = "gentleShake 0.5s infinite";
                }
            }, 1000);
        }
    }, 3000);
}

function toggleEasterEgg() {
    if (!isPlaying) {
        let playPromise = audio.play();
        
        if (playPromise !== undefined) {
            playPromise.then(_ => {
                // İlk animasyon: Yukarı doğru uçuş
                if (twitterIcon) {
                    twitterIcon.style.animation = "initialFly 2s cubic-bezier(0.4, 0, 0.2, 1)";
                    
                    // 2 saniye sonra normal kaçma davranışını başlat
                    setTimeout(() => {
                        twitterIcon.style.animation = "fly 2s infinite";
                        twitterIcon.style.opacity = "1";
                        twitterIcon.style.transform = "none";
                        document.body.style.animation = "gentleShake 0.5s infinite";
                        // Rastgele takla atma başlat
                        startRandomFlips();
                    }, 2000);
                }
                
                isPlaying = true;
                localStorage.setItem('easterEggActive', 'true');
            })
            .catch(error => {
                console.error('Ses çalınamadı:', error);
                alert('Ses çalınamadı! Hata: ' + error);
            });
        }
    } else {
        audio.pause();
        audio.currentTime = 0;
        document.body.style.animation = "";
        document.body.classList.remove('flipping');
        if (twitterIcon) {
            twitterIcon.style.animation = "";
            twitterIcon.style.position = '';
            twitterIcon.style.left = '';
            twitterIcon.style.top = '';
            twitterIcon.style.opacity = '1';
            twitterIcon.style.transform = 'none';
        }
        isPlaying = false;
        localStorage.setItem('easterEggActive', 'false');
        // Rastgele takla atmayı durdur
        if (flipInterval) {
            clearInterval(flipInterval);
            flipInterval = null;
        }
    }
}

// Twitter ikonunun kaçma davranışı
document.addEventListener('mousemove', (e) => {
    if(isPlaying && twitterIcon) {
        const rect = twitterIcon.getBoundingClientRect();
        const iconCenterX = rect.left + rect.width / 2;
        const iconCenterY = rect.top + rect.height / 2;
        
        const distance = Math.sqrt(
            Math.pow(e.clientX - iconCenterX, 2) + 
            Math.pow(e.clientY - iconCenterY, 2)
        );
        
        if(distance < 300) {
            // Mouse'un yönünü hesapla
            const directionX = e.clientX - iconCenterX;
            const directionY = e.clientY - iconCenterY;
            
            // Temel kaçma açısını mouse'un tersi yönünde ayarla
            let angle = Math.atan2(-directionY, -directionX);
            
            // Açıya rastgele sapma ekle (-30 ile +30 derece arası)
            const randomAngleOffset = (Math.random() - 0.5) * Math.PI / 3;
            angle += randomAngleOffset;
            
            // Mesafeye göre dinamik kaçma mesafesi
            const jumpDistance = 300 * Math.pow((300 - distance) / 300, 2);
            
            let newX = iconCenterX + Math.cos(angle) * jumpDistance;
            let newY = iconCenterY + Math.sin(angle) * jumpDistance;
            
            // Ekran sınırlarını kontrol et
            newX = Math.max(rect.width, Math.min(window.innerWidth - rect.width, newX));
            newY = Math.max(rect.height, Math.min(window.innerHeight - rect.height, newY));
            
            twitterIcon.style.position = 'fixed';
            twitterIcon.style.left = (newX - rect.width / 2) + 'px';
            twitterIcon.style.top = (newY - rect.height / 2) + 'px';
            twitterIcon.style.zIndex = '9999';
        }
    }
});
</script>

<style>
@keyframes gentleShake {
    0% { transform: translate(0px, 0px) rotate(0deg); }
    25% { transform: translate(1px, 1px) rotate(0.5deg); }
    50% { transform: translate(-1px, -1px) rotate(-0.5deg); }
    75% { transform: translate(1px, -1px) rotate(0.5deg); }
    100% { transform: translate(0px, 0px) rotate(0deg); }
}

@keyframes pageFlip {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(-360deg); }
}

@keyframes initialFly {
    0% { transform: translate(0, 0) rotate(0deg) scale(1); opacity: 1; }
    100% { transform: translate(0, -100vh) rotate(720deg) scale(0.5); opacity: 0; }
}

@keyframes fly {
    0% { transform: translate(0, 0) rotate(0deg) scale(1); }
    25% { transform: translate(15px, -15px) rotate(5deg) scale(1.1); }
    50% { transform: translate(0, 0) rotate(0deg) scale(1); }
    75% { transform: translate(-15px, -15px) rotate(-5deg) scale(1.1); }
    100% { transform: translate(0, 0) rotate(0deg) scale(1); }
}

#twitter-easter-egg {
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    will-change: transform, left, top;
}

#twitter-easter-egg:hover {
    transform: scale(1.2) rotate(15deg);
}

body.flipping {
    animation: pageFlip 1s cubic-bezier(0.4, 0, 0.2, 1);
    transform-origin: center center;
}
</style>
