<?php
if (!isset($_SESSION)) {
    session_start();
}

// Dil listesi: tr, en, de, ru, es, zh, fr, it (İtalyanca ekledik)
if (isset($_GET['lang'])) {
    $allowed_languages = ['tr', 'en', 'de', 'ru', 'es', 'zh', 'fr', 'it']; // 'it' eklendi
    if (in_array($_GET['lang'], $allowed_languages)) {
        $_SESSION['lang'] = $_GET['lang'];
    }
    $redirect = strtok($_SERVER['REQUEST_URI'], '?');
    header("Location: " . $redirect);
    exit();
}

// Varsayılan dil
if (!isset($_SESSION['lang'])) {
    $_SESSION['lang'] = 'tr';
}

$lang = $_SESSION['lang'];
$translations_file = dirname(__DIR__) . "/translations/translation_{$lang}.php";

// Dil dosyasını yükle (varsa), yoksa Türkçe'ye düş
if (file_exists($translations_file)) {
    $translations = require $translations_file;
    $t = $translations;
} else {
    $translations = require dirname(__DIR__) . "/translations/translation_tr.php";
    $t = $translations;
}
?>

<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">
<head>
   <meta charset="UTF-8">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title>HealthTurkey - <?php echo $lang == 'tr' ? 'Sağlık Turizmi' : 'Health Tourism'; ?></title>
   
   <!-- Favicon -->
   <link rel="icon" type="image/png" href="/health_tourism - Kopya - lms yükleme yedek/assets/images/favicon/favicon.png">
   <link rel="shortcut icon" type="image/png" href="/health_tourism - Kopya - lms yükleme yedek/assets/images/favicon/favicon.png">
   
   <link href="https://cdnjs.cloudflare.com/ajax/libs/tailwindcss/2.2.19/tailwind.min.css" rel="stylesheet">
   <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
   <script src="https://cdnjs.cloudflare.com/ajax/libs/matter-js/0.19.0/matter.min.js"></script>
   
   <style>
       .header-wrapper {
           background: rgba(255, 255, 255, 0.9);
           backdrop-filter: blur(10px);
           border-bottom: 1px solid rgba(0,0,0,0.1);
           position: relative;
       }

       .nav-link {
           position: relative;
           color: #4B5563;
           text-decoration: none;
           font-weight: 500;
           padding: 0.5rem 0;
           font-size: 0.95rem;
       }
       
       .nav-link::after {
           content: '';
           position: absolute;
           width: 0;
           height: 2px;
           bottom: 0;
           left: 0;
           background: linear-gradient(to right, #4F46E5, #6366F1);
           transition: width 0.3s ease;
           border-radius: 1px;
       }
       
       .nav-link:hover {
           color: #4F46E5;
       }
       
       .nav-link:hover::after {
           width: 100%;
       }

       .brand-text {
           background: linear-gradient(to right, #4F46E5, #6366F1);
           -webkit-background-clip: text;
           -webkit-text-fill-color: transparent;
           font-size: 1.5rem;
           letter-spacing: -0.5px;
       }

       .language-switcher {
           position: relative;
           display: inline-block;
       }

       .flag-button {
           width: 36px;
           height: 24px;
           border-radius: 4px;
           overflow: hidden;
           position: relative;
           box-shadow: 0 2px 4px rgba(0,0,0,0.1);
           transition: all 0.3s ease;
           border: 1px solid rgba(0,0,0,0.1);
           cursor: pointer;
       }

       .flag-button:hover {
           transform: translateY(-1px);
           box-shadow: 0 4px 6px rgba(0,0,0,0.1);
       }

       .language-menu {
           position: absolute;
           top: 100%;
           left: 0;
           margin-top: 0.5rem;
           background: white;
           border-radius: 8px;
           box-shadow: 0 4px 12px rgba(0,0,0,0.1);
           opacity: 0;
           visibility: hidden;
           transform: translateY(-10px);
           transition: all 0.3s ease;
           min-width: 140px;
           border: 1px solid rgba(0,0,0,0.1);
           z-index: 50;
       }

       .language-switcher:hover .language-menu {
           opacity: 1;
           visibility: visible;
           transform: translateY(0);
       }

       .language-option {
           display: flex;
           align-items: center;
           padding: 0.75rem 1rem;
           transition: all 0.2s ease;
           color: #4B5563;
       }

       .language-option:first-child {
           border-radius: 8px 8px 0 0;
       }

       .language-option:last-child {
           border-radius: 0 0 8px 8px;
       }

       .language-option:hover {
           background-color: #f3f4f6;
           color: #4F46E5;
       }

       .login-button, .logout-button {
           background: linear-gradient(to right, #4F46E5, #6366F1);
           transition: all 0.3s ease;
           position: relative;
           overflow: hidden;
       }

       .login-button:hover, .logout-button:hover {
           transform: translateY(-1px);
       }

       .logout-button {
           background: linear-gradient(to right, #ef4444, #dc2626);
       }

       /* Kar tanesi stilleri */
       .snowflake {
           position: fixed;
           top: -10px;
           z-index: 9999;
           color: #fff;
           user-select: none;
           pointer-events: none;
           animation: fall linear forwards;
       }

       @keyframes fall {
           to {
               transform: translateY(100vh);
           }
       }

       /* Buzlanma efekti */
       .frost-overlay {
           position: fixed;
           top: 0;
           left: 0;
           width: 100%;
           height: 100%;
           background: linear-gradient(135deg, 
               rgba(255,255,255,0.15) 0%, 
               rgba(255,255,255,0.25) 25%,
               rgba(255,255,255,0.35) 50%, 
               rgba(255,255,255,0.25) 75%,
               rgba(255,255,255,0.15) 100%
           );
           pointer-events: none;
           opacity: 0;
           transition: opacity 2s ease;
           z-index: 9998;
           backdrop-filter: blur(5px);
           background-image: 
               radial-gradient(circle at 30% 20%, rgba(255,255,255,0.15) 0%, transparent 50%),
               radial-gradient(circle at 70% 60%, rgba(255,255,255,0.12) 0%, transparent 45%),
               radial-gradient(circle at 45% 85%, rgba(255,255,255,0.1) 0%, transparent 40%),
               radial-gradient(circle at 85% 30%, rgba(255,255,255,0.08) 0%, transparent 35%),
               repeating-conic-gradient(from 0deg at 50% 50%, 
                   rgba(255,255,255,0.03) 0deg, 
                   rgba(255,255,255,0.06) 5deg, 
                   rgba(255,255,255,0.03) 10deg,
                   rgba(255,255,255,0.08) 15deg,
                   rgba(255,255,255,0.03) 20deg
               ),
               linear-gradient(120deg, 
                   rgba(255,255,255,0.02) 0%, 
                   rgba(255,255,255,0.05) 30%, 
                   rgba(255,255,255,0.03) 50%,
                   rgba(255,255,255,0.06) 70%,
                   rgba(255,255,255,0.02) 100%
               );
       }

       .frost-crack {
           position: absolute;
           background: radial-gradient(ellipse at center, 
               rgba(255,255,255,0.95) 0%, 
               rgba(255,255,255,0.8) 15%, 
               rgba(255,255,255,0.4) 35%,
               transparent 70%
           );
           pointer-events: none;
           transform-origin: center;
           overflow: hidden;
           border-radius: 50% 30% 60% 40%;
           box-shadow: 
               0 0 10px rgba(255,255,255,0.3),
               inset 0 0 15px rgba(255,255,255,0.5);
       }

       .frost-crack::before {
           content: '';
           position: absolute;
           top: 0;
           left: 0;
           right: 0;
           bottom: 0;
           background: 
               radial-gradient(ellipse at center, transparent 20%, rgba(255,255,255,0.3) 70%),
               conic-gradient(from 0deg at 50% 50%,
                   transparent 0deg,
                   rgba(255,255,255,0.4) 90deg,
                   transparent 180deg,
                   rgba(255,255,255,0.4) 270deg,
                   transparent 360deg
               ),
               linear-gradient(90deg, rgba(255,255,255,0.2), transparent);
           transform: rotate(var(--rotation));
           filter: brightness(1.2) contrast(1.2);
           border-radius: inherit;
       }

       .frost-crack::after {
           content: '';
           position: absolute;
           top: 0;
           left: 0;
           right: 0;
           bottom: 0;
           background: 
               radial-gradient(ellipse at center,
                   rgba(255,255,255,0.8) 0%,
                   rgba(255,255,255,0.4) 30%,
                   transparent 70%
               ),
               linear-gradient(to right,
                   transparent 20%,
                   rgba(255,255,255,0.2) 40%,
                   rgba(255,255,255,0.6) 50%,
                   rgba(255,255,255,0.2) 60%,
                   transparent 80%
               );
           transform: rotate(calc(var(--rotation) + var(--random-angle, 45deg)));
           border-radius: inherit;
           filter: brightness(1.3);
       }

       .frost-particle {
           position: absolute;
           background: radial-gradient(circle at center,
               rgba(255,255,255,0.95) 0%,
               rgba(255,255,255,0.7) 40%,
               rgba(255,255,255,0.3) 70%,
               transparent 100%
           );
           border-radius: 50% 30% 60% 40%;
           pointer-events: none;
           animation: particleFall 1s ease-in forwards;
           box-shadow: 0 0 5px rgba(255,255,255,0.3);
       }

       @keyframes particleFall {
           0% {
               transform: translateY(0) rotate(0deg) scale(1);
               opacity: 1;
               border-radius: 50% 30% 60% 40%;
           }
           50% {
               transform: translateY(50px) rotate(180deg) scale(0.8);
               opacity: 0.7;
               border-radius: 30% 60% 40% 50%;
           }
           100% {
               transform: translateY(100px) rotate(360deg) scale(0.5);
               opacity: 0;
               border-radius: 60% 40% 50% 30%;
           }
       }

       /* Yılbaşı ağacı ikonu stilleri */
       .christmas-tree {
           cursor: pointer;
           font-size: 1.5rem;
           color: #2F855A;
           transition: all 0.3s ease;
       }

       .christmas-tree:hover {
           color: #38A169;
           transform: scale(1.1);
       }

       @keyframes shake {
           0%, 100% { transform: translateX(0); }
           25% { transform: translateX(-2px) rotate(-2deg); }
           75% { transform: translateX(2px) rotate(2deg); }
       }

       @keyframes ultraShake {
           0% { transform: translate(0, 0) rotate(0deg) scale(1); }
           10% { transform: translate(-4px, -2px) rotate(-8deg) scale(1.1); }
           20% { transform: translate(4px, 2px) rotate(8deg) scale(0.9); }
           30% { transform: translate(-4px, -4px) rotate(-12deg) scale(1.2); }
           40% { transform: translate(4px, 4px) rotate(12deg) scale(0.8); }
           50% { transform: translate(-2px, 2px) rotate(-16deg) scale(1.1); }
           60% { transform: translate(2px, -2px) rotate(16deg) scale(0.9); }
           70% { transform: translate(-4px, 4px) rotate(-12deg) scale(1.2); }
           80% { transform: translate(4px, -4px) rotate(12deg) scale(0.8); }
           90% { transform: translate(-2px, -2px) rotate(-8deg) scale(1.1); }
           100% { transform: translate(0, 0) rotate(0deg) scale(1); }
       }

       @keyframes crazyShake {
           0% { transform: translate(0, 0) rotate(0deg) scale(1); filter: hue-rotate(0deg); }
           10% { transform: translate(-8px, -8px) rotate(-20deg) scale(1.3); filter: hue-rotate(36deg); }
           20% { transform: translate(8px, 8px) rotate(20deg) scale(0.7); filter: hue-rotate(72deg); }
           30% { transform: translate(-12px, 4px) rotate(-40deg) scale(1.4); filter: hue-rotate(108deg); }
           40% { transform: translate(12px, -4px) rotate(40deg) scale(0.6); filter: hue-rotate(144deg); }
           50% { transform: translate(0, -12px) rotate(-60deg) scale(1.5); filter: hue-rotate(180deg); }
           60% { transform: translate(0, 12px) rotate(60deg) scale(0.5); filter: hue-rotate(216deg); }
           70% { transform: translate(-12px, -8px) rotate(-40deg) scale(1.4); filter: hue-rotate(252deg); }
           80% { transform: translate(12px, 8px) rotate(40deg) scale(0.6); filter: hue-rotate(288deg); }
           90% { transform: translate(-8px, 4px) rotate(-20deg) scale(1.3); filter: hue-rotate(324deg); }
           100% { transform: translate(0, 0) rotate(0deg) scale(1); filter: hue-rotate(360deg); }
       }

       /* Badem container için stil */
       #badem-container {
           position: fixed;
           top: 0;
           left: 0;
           width: 100%;
           height: 100%;
           pointer-events: none;
           z-index: 9999;
           overflow: hidden;
       }

       .badem {
           position: absolute;
           width: 40px;
           height: 40px;
           transition: transform 0.3s ease;
           pointer-events: none;
           z-index: 9999;
       }

       /* Kedi burnu ve bıyıkları */
       .cat-nose {
           position: absolute;
           right: 20px;
           top: 50%;
           transform: translateY(-50%);
           opacity: 0;
           transition: all 0.5s cubic-bezier(0.34, 1.56, 0.64, 1);
           cursor: pointer;
           z-index: 1000;
           filter: drop-shadow(0 4px 8px rgba(0,0,0,0.1));
           padding: 10px;
       }

       .header-wrapper:hover .cat-nose {
           opacity: 0;
       }

       .cat-nose:hover {
           opacity: 1 !important;
           filter: drop-shadow(0 6px 12px rgba(79,70,229,0.2));
       }

       .whiskers {
           position: relative;
           width: 120px;
           height: 60px;
           background: url('assets/images/hehe/mustache.png') no-repeat center;
           background-size: contain;
           transform-origin: center;
           transition: all 0.3s ease;
       }

       .cat-nose:hover .whiskers {
           transform: scale(1.1);
           filter: brightness(1.2);
       }
   </style>

   <script>
       let snowInterval;
       let snowSpeed = 100; // Başlangıç hızı
       let isSnowing = localStorage.getItem('isSnowing') === 'true';
       let clickCount = parseInt(localStorage.getItem('snowClickCount') || '0');
       let snowStartTime = null;
       let frostOverlay = null;
       const speeds = [100, 80, 60, 40, 20, 5, 0.5, 0.1]; // 8 seviye (son seviye 0.1ms)

       // Ses dosyaları
       let jingleAudio = new Audio(window.location.origin + '/health_tourism/assets/images/hehe/jingle.mp3');
       let metalJingleAudio = new Audio(window.location.origin + '/health_tourism/assets/images/hehe/jinglemetal.mp3');
       jingleAudio.loop = true;
       metalJingleAudio.loop = true;

       // Sesleri önceden yükle
       jingleAudio.load();
       metalJingleAudio.load();

       // Ses çalma fonksiyonları
       async function playJingle() {
           try {
               await jingleAudio.play();
               console.log('Jingle başlatıldı');
           } catch (error) {
               console.error('Jingle başlatılamadı:', error);
           }
       }

       async function playMetalJingle() {
           try {
               await metalJingleAudio.play();
               console.log('Metal jingle başlatıldı');
           } catch (error) {
               console.error('Metal jingle başlatılamadı:', error);
           }
       }

       // Sayfa yüklendiğinde kar yağışı durumunu kontrol et
       document.addEventListener('DOMContentLoaded', () => {
           const treeIcon = document.querySelector('.christmas-tree');
           
           // Eğer kar yağışı aktifse, devam ettir
           if (isSnowing && clickCount > 0 && clickCount <= 9) {
               startSnow(speeds[clickCount - 1]);
               
               // Ağacın rengini ayarla
               const green = Math.max(10, 90 - (clickCount * 10));
               treeIcon.style.color = `rgb(47, ${green}, ${green})`;

               // Titreme animasyonları
               if (clickCount === 8) {
                   treeIcon.style.animation = 'shake 0.5s infinite';
               } else if (clickCount === 9) {
                   treeIcon.style.animation = 'ultraShake 0.3s infinite';
               }

               // Ses kontrolü
               if (clickCount === 9) {
                   // Metal versiyon
                   jingleAudio.pause();
                   jingleAudio.currentTime = 0;
                   playMetalJingle();
               } else {
                   // Normal jingle
                   metalJingleAudio.pause();
                   metalJingleAudio.currentTime = 0;
                   playJingle();
               }
           }
           
           treeIcon.addEventListener('click', () => {
               // Easter egg aktifse, onu durdur ve kar yağışını başlat
               if (localStorage.getItem('easterEggActive') === 'true') {
                   window.toggleEasterEgg(); // Easter egg'i durdur
                   localStorage.setItem('easterEggActive', 'false');
                   
                   // Kar yağışını başlat
                   clickCount = 1;
                   localStorage.setItem('snowClickCount', clickCount.toString());
                   startSnow(speeds[clickCount - 1]);
                   localStorage.setItem('isSnowing', 'true');
                   
                   // Ağacın rengini ayarla
                   const green = Math.max(10, 90 - (clickCount * 10));
                   treeIcon.style.color = `rgb(47, ${green}, ${green})`;
                   
                   // Müziği başlat
                   metalJingleAudio.pause();
                   metalJingleAudio.currentTime = 0;
                   playJingle();
                   return;
               }

               clickCount++;
               localStorage.setItem('snowClickCount', clickCount.toString());
               
               if (clickCount <= 9) {
                   // Kar yağışını başlat veya hızını artır
                   startSnow(speeds[clickCount - 1]);
                   localStorage.setItem('isSnowing', 'true');
                   
                   // Ağacın rengini koyulaştır
                   const green = Math.max(10, 90 - (clickCount * 10));
                   treeIcon.style.color = `rgb(47, ${green}, ${green})`;

                   // Titreme animasyonları
                   if (clickCount === 8) {
                       treeIcon.style.animation = 'shake 0.5s infinite';
                   } else if (clickCount === 9) {
                       treeIcon.style.animation = 'ultraShake 0.3s infinite';
                   }

                   // Ses kontrolü
                   if (clickCount === 9) {
                       // Metal versiyona geç
                       jingleAudio.pause();
                       jingleAudio.currentTime = 0;
                       playMetalJingle();
                   } else if (clickCount === 1) {
                       // İlk başlangıç
                       metalJingleAudio.pause();
                       metalJingleAudio.currentTime = 0;
                       playJingle();
                   }
               } else {
                   // 10. tıklamada durdur ve sıfırla
                   stopSnow();
                   localStorage.setItem('isSnowing', 'false');
                   localStorage.setItem('snowClickCount', '0');
                   treeIcon.style.color = '#2F855A';
                   treeIcon.style.animation = 'none';
                   // Sesleri durdur
                   jingleAudio.pause();
                   jingleAudio.currentTime = 0;
                   metalJingleAudio.pause();
                   metalJingleAudio.currentTime = 0;
               }
           });
       });

       function createSnowflake() {
           const snowflake = document.createElement('div');
           snowflake.classList.add('snowflake');
           snowflake.innerHTML = '❄';
           snowflake.style.left = Math.random() * window.innerWidth + 'px';
           snowflake.style.opacity = Math.random();
           snowflake.style.fontSize = (Math.random() * 10 + 10) + 'px';
           snowflake.style.animationDuration = (Math.random() * 3 + 2) + 's';
           
           document.body.appendChild(snowflake);

           snowflake.addEventListener('animationend', () => {
               snowflake.remove();
           });

           // Kar yağışı süresini kontrol et
           if (snowStartTime === null) {
               snowStartTime = Date.now();
           } else {
               const elapsedTime = Date.now() - snowStartTime;
               const frostTime = Math.max(5000, 15000 - (clickCount * 1000)); // Kar yağışı şiddetine göre buzlanma süresini ayarla
               if (elapsedTime > frostTime && !frostOverlay) {
                   createFrostOverlay();
               }
           }
       }

       function createFrostOverlay() {
           // Kar yağışı hızına göre buzlanma süresini ve yoğunluğunu ayarla
           const currentSpeed = speeds[clickCount - 1];
           const baseTime = 15000; // Baz süre (15 saniye)
           const frostTime = Math.max(5000, baseTime - (clickCount * 1000)); // Her seviyede 1 saniye azalt, minimum 5 saniye

           frostOverlay = document.createElement('div');
           frostOverlay.className = 'frost-overlay';
           document.body.appendChild(frostOverlay);

           // Kar yağışı şiddetine göre blur efektini ayarla
           const blurAmount = Math.min(15, 5 + clickCount); // Her seviyede blur artar, maksimum 15px
           frostOverlay.style.backdropFilter = `blur(${blurAmount}px)`;

           // Yavaşça buzlanma efektini göster
           setTimeout(() => {
               frostOverlay.style.opacity = Math.min(0.9, 0.4 + (clickCount * 0.05)); // Her seviyede opaklık artar
           }, 100);

           // Tıklama olayını ekle
           frostOverlay.style.pointerEvents = 'auto';
           frostOverlay.addEventListener('click', createCrack);
       }

       function createCrack(event) {
           const crack = document.createElement('div');
           crack.className = 'frost-crack';
           
           // Tıklanan noktanın yüzdesel konumu
           const x = (event.clientX / window.innerWidth) * 100;
           const y = (event.clientY / window.innerHeight) * 100;
           crack.style.setProperty('--x', x + '%');
           crack.style.setProperty('--y', y + '%');
           
           // Rastgele boyut ve şekil
           const size = 80 + (clickCount * 15) + (Math.random() * 40);
           crack.style.width = size + 'px';
           crack.style.height = size + 'px';
           crack.style.borderRadius = `${30 + Math.random() * 40}% ${60 + Math.random() * 40}% ${20 + Math.random() * 40}% ${50 + Math.random() * 40}%`;
           
           // Tıklanan noktaya göre pozisyon
           crack.style.left = (event.clientX - size/2) + 'px';
           crack.style.top = (event.clientY - size/2) + 'px';
           
           // Rastgele rotasyon ve açı
           const rotation = Math.random() * 360;
           const randomAngle = Math.random() * 90;
           crack.style.setProperty('--rotation', rotation + 'deg');
           crack.style.setProperty('--random-angle', randomAngle + 'deg');
           
           // Animasyon
           crack.style.animation = 'crackFade 1.2s forwards, crackSpread 0.4s ease-out';
           
           frostOverlay.appendChild(crack);

           // Buz parçacıkları efekti
           for(let i = 0; i < 12; i++) {
               const particle = document.createElement('div');
               particle.className = 'frost-particle';
               particle.style.left = event.clientX + 'px';
               particle.style.top = event.clientY + 'px';
               
               // Rastgele boyut ve şekil
               const particleSize = Math.random() * 6 + 2;
               particle.style.width = particleSize + 'px';
               particle.style.height = particleSize + 'px';
               particle.style.borderRadius = `${30 + Math.random() * 40}% ${60 + Math.random() * 40}% ${20 + Math.random() * 40}% ${50 + Math.random() * 40}%`;
               
               // Rastgele hareket
               const angle = (Math.random() * 360) * (Math.PI / 180);
               const distance = Math.random() * 60 + 20;
               const translateX = Math.cos(angle) * distance;
               const translateY = Math.sin(angle) * distance;
               
               particle.style.transform = `translate(${translateX}px, ${translateY}px) rotate(${Math.random() * 360}deg)`;
               document.body.appendChild(particle);
               
               // Parçacıkları temizle
               setTimeout(() => particle.remove(), 1000);
           }

           // Çatlak efekti sesi
           const crackSound = new Audio('assets/images/hehe/crack.mp3');
           crackSound.volume = 0.3;
           crackSound.playbackRate = 1 + (Math.random() * 0.5); // Rastgele pitch
           crackSound.play();

           // Belirli bir sayıda tıklamadan sonra buzu tamamen kır
           const requiredClicks = Math.max(5, 10 - Math.floor(clickCount/2));
           const cracks = frostOverlay.getElementsByClassName('frost-crack').length;
           
           if (cracks >= requiredClicks) {
               // Büyük kırılma efekti
               const finalCrack = document.createElement('div');
               finalCrack.className = 'frost-crack';
               finalCrack.style.width = '300%';
               finalCrack.style.height = '300%';
               finalCrack.style.left = '-100%';
               finalCrack.style.top = '-100%';
               
               // Final kırılma animasyonu
               finalCrack.style.animation = 'crackSpread 0.8s ease-out';
               finalCrack.style.setProperty('--x', '50%');
               finalCrack.style.setProperty('--y', '50%');
               
               frostOverlay.appendChild(finalCrack);

               // Çok sayıda buz parçacığı oluştur
               for(let i = 0; i < 50; i++) {
                   setTimeout(() => {
                       const particle = document.createElement('div');
                       particle.className = 'frost-particle';
                       particle.style.left = (Math.random() * window.innerWidth) + 'px';
                       particle.style.top = (Math.random() * window.innerHeight) + 'px';
                       particle.style.width = Math.random() * 6 + 2 + 'px';
                       particle.style.height = particle.style.width;
                       document.body.appendChild(particle);
                       
                       setTimeout(() => particle.remove(), 1000);
                   }, i * 20);
               }

               setTimeout(() => {
                   frostOverlay.style.opacity = '0';
                   frostOverlay.style.backdropFilter = 'blur(0px)';
                   setTimeout(() => {
                       frostOverlay.remove();
                       frostOverlay = null;
                       snowStartTime = Date.now();
                   }, 1000);
               }, 500);
           }
       }

       function startSnow(speed) {
           if (snowInterval) {
               clearInterval(snowInterval);
           }
           isSnowing = true;
           snowStartTime = Date.now();
           snowInterval = setInterval(createSnowflake, speed);
       }

       function stopSnow() {
           if (snowInterval) {
               clearInterval(snowInterval);
               snowInterval = null;
           }
           isSnowing = false;
           clickCount = 0;
           snowStartTime = null;
           document.querySelectorAll('.snowflake').forEach(flake => flake.remove());
           if (frostOverlay) {
               frostOverlay.remove();
               frostOverlay = null;
           }
       }

       // Matter.js kodları
       document.addEventListener('DOMContentLoaded', function() {
           // Matter.js modüllerini al
           const Engine = Matter.Engine,
                 Render = Matter.Render,
                 World = Matter.World,
                 Bodies = Matter.Bodies,
                 Body = Matter.Body,
                 Mouse = Matter.Mouse,
                 Composite = Matter.Composite;

           // Motor ve dünya oluştur
           const engine = Engine.create();
           engine.gravity.y = 1;
           engine.gravity.scale = 0.001;

           const render = Render.create({
               element: document.body,
               engine: engine,
               canvas: document.createElement('canvas'),
               options: {
                   width: window.innerWidth,
                   height: window.innerHeight,
                   wireframes: false,
                   background: 'transparent',
                   pixelRatio: window.devicePixelRatio
               }
           });

           render.canvas.id = 'badem-world';
           render.canvas.style.position = 'fixed';
           render.canvas.style.top = '0';
           render.canvas.style.left = '0';
           render.canvas.style.width = '100%';
           render.canvas.style.height = '100%';
           render.canvas.style.pointerEvents = 'none';
           render.canvas.style.zIndex = '9999';

           // Zemin ve duvarlar
           const ground = Bodies.rectangle(
               window.innerWidth / 2,
               window.innerHeight + 30,
               window.innerWidth * 2,
               60,
               { 
                   isStatic: true,
                   friction: 0.5,
                   render: { visible: false }
               }
           );

           const leftWall = Bodies.rectangle(
               -30,
               window.innerHeight / 2,
               60,
               window.innerHeight * 2,
               {
                   isStatic: true,
                   friction: 0.5,
                   render: { visible: false }
               }
           );

           const rightWall = Bodies.rectangle(
               window.innerWidth + 30,
               window.innerHeight / 2,
               60,
               window.innerHeight * 2,
               {
                   isStatic: true,
                   friction: 0.5,
                   render: { visible: false }
               }
           );

           World.add(engine.world, [ground, leftWall, rightWall]);

           function createBadem() {
               if (bademCount >= maxBademCount) return;

               const noseRect = catNose.getBoundingClientRect();
               const badem = Bodies.circle(
                   noseRect.left + noseRect.width / 2,
                   noseRect.top + noseRect.height / 2,
                   15,
                   {
                       restitution: 0.6,
                       friction: 0.1,
                       density: 0.001,
                       render: {
                           sprite: {
                               texture: 'assets/images/hehe/badem.png',
                               xScale: 0.3,
                               yScale: 0.3
                           }
                       }
                   }
               );

               // Başlangıç hızı ver
               Body.setVelocity(badem, {
                   x: (Math.random() - 0.5) * 5,
                   y: Math.random() * 2
               });

               World.add(engine.world, badem);
               bademCount++;
           }

           // Mouse takibi
           let mouseX = 0;
           let mouseY = 0;
           document.addEventListener('mousemove', (e) => {
               mouseX = e.clientX;
               mouseY = e.clientY;
               
               const bodies = Composite.allBodies(engine.world);
               bodies.forEach(body => {
                   if (body.isStatic) return;
                   
                   const dx = body.position.x - mouseX;
                   const dy = body.position.y - mouseY;
                   const distance = Math.sqrt(dx * dx + dy * dy);
                   
                   if (distance < 150) {
                       const force = 0.002;
                       Body.applyForce(body, body.position, {
                           x: (dx / distance) * force * (150 - distance),
                           y: (dy / distance) * force * (150 - distance)
                       });
                   }
               });
           });

           // Motoru ve render'ı başlat
           Engine.run(engine);
           Render.run(render);

           const catNose = document.createElement('div');
           catNose.className = 'cat-nose';
           catNose.innerHTML = `<div class="whiskers"></div>`;

           const headerWrapper = document.querySelector('.header-wrapper');
           headerWrapper.appendChild(catNose);

           // Ses efekti
           const meowSound = new Audio('assets/images/hehe/miyav.mp3');

           let isAnimating = false;
           let bademInterval;
           const maxBademCount = 10000;
           let bademCount = 0;

           catNose.addEventListener('click', function() {
               if (!isAnimating) {
                   meowSound.play();
                   isAnimating = true;
                   bademInterval = setInterval(createBadem, 100);
               } else {
                   isAnimating = false;
                   clearInterval(bademInterval);
               }
           });

           // Pencere boyutu değiştiğinde duvarları güncelle
           window.addEventListener('resize', function() {
               render.canvas.width = window.innerWidth;
               render.canvas.height = window.innerHeight;
               
               Body.setPosition(ground, {
                   x: window.innerWidth / 2,
                   y: window.innerHeight
               });
               
               Body.setPosition(rightWall, {
                   x: window.innerWidth,
                   y: window.innerHeight / 2
               });
           });
       });
   </script>
</head>
<body>
   <div class="header-wrapper fixed w-full z-50">
   <div class="max-w-7xl mx-auto px-4">
       <div class="flex items-center justify-between h-20">
           <!-- Sol Taraf: Logo ve Dil Seçici -->
           <div class="flex items-center space-x-6">
               <div class="language-switcher">
                   <button class="flag-button">
                       <?php 
                       // Mevcut dili kontrol edip bayrak gösterelim
                       if ($_SESSION['lang'] == 'tr'): ?>
                           <!-- Türk Bayrağı -->
                           <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 800" class="w-full h-full">
                               <rect width="1200" height="800" fill="#E30A17"/>
                               <circle cx="425" cy="400" r="200" fill="#fff"/>
                               <circle cx="475" cy="400" r="160" fill="#E30A17"/>
                               <g fill="#fff" transform="translate(640,400) scale(1.6)">
                                   <path d="M0,-60 L17.63,-18.54 L69.02,-18.54 L26.69,7.08 L44.33,48.54 L0,22.9 L-44.33,48.54 L-26.69,7.08 L-69.02,-18.54 L-17.63,-18.54 Z"/>
                               </g>
                           </svg>
                       <?php elseif ($_SESSION['lang'] == 'en'): ?>
                           <!-- İngiliz Bayrağı -->
                           <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 60 40" class="w-full h-full">
                               <rect width="60" height="40" fill="#012169"/>
                               <path d="M0,0 L60,40 M60,0 L0,40" stroke="#fff" stroke-width="4"/>
                               <path d="M0,0 L60,40 M60,0 L0,40" stroke="#C8102E" stroke-width="2"/>
                               <path d="M30,0 L30,40 M0,20 L60,20" stroke="#fff" stroke-width="8"/>
                               <path d="M30,0 L30,40 M0,20 L60,20" stroke="#C8102E" stroke-width="4"/>
                           </svg>
                       <?php elseif ($_SESSION['lang'] == 'de'): ?>
                           <!-- Almanya Bayrağı -->
                           <svg width="60" height="40" viewBox="0 0 3 2" class="w-full h-full">
                               <rect width="3" height="2" fill="#ffce00"/>
                               <rect width="3" height="1.3333" y="0" fill="#d00"/>
                               <rect width="3" height="0.6667" y="0" fill="#000"/>
                           </svg>
                       <?php elseif ($_SESSION['lang'] == 'ru'): ?>
                           <!-- Rusya Bayrağı -->
                           <svg width="60" height="40" viewBox="0 0 3 2" class="w-full h-full">
                               <rect width="3" height="2" fill="#fff"/>
                               <rect width="3" height="0.6667" y="0.6667" fill="#0039A6"/>
                               <rect width="3" height="0.6666" y="1.3333" fill="#D52B1E"/>
                           </svg>
                       <?php elseif ($_SESSION['lang'] == 'es'): ?>
                           <!-- İspanya Bayrağı -->
                           <svg width="60" height="40" viewBox="0 0 3 2" class="w-full h-full">
                               <rect width="3" height="2" fill="#AA151B"/>
                               <rect width="3" height="0.6667" y="0.6667" fill="#F1BF00"/>
                           </svg>
                       <?php elseif ($_SESSION['lang'] == 'zh'): ?>
                           <!-- Çin Bayrağı -->
                           <svg width="60" height="40" viewBox="0 0 30 20" class="w-full h-full">
                               <rect width="30" height="20" fill="#DE2910"/>
                               <!-- Büyük yıldız -->
                               <polygon fill="#FFDE00" points="5,2.5  6,4 8,4.5 6,5 5,7 4,5 2,4.5 4,4" />
                               <!-- 4 küçük yıldız (Basit konumlar) -->
                               <polygon fill="#FFDE00" points="10,4  10.5,5 11.5,5.2 10.8,6 11,7 10,6.5 9,7 9.2,6 8.5,5.2 9.5,5" />
                               <polygon fill="#FFDE00" points="10,9  10.5,10 11.5,10.2 10.8,11 11,12 10,11.5 9,12 9.2,11 8.5,10.2 9.5,10" />
                               <polygon fill="#FFDE00" points="6,6.5  6.5,7.5 7.5,7.7 6.8,8.5 7,9.5 6,9 5,9.5 5.2,8.5 4.5,7.7 5.5,7.5" />
                               <polygon fill="#FFDE00" points="5,11  5.5,12 6.5,12.2 5.8,13 6,14 5,13.5 4,14 4.2,13 3.5,12.2 4.5,12" />
                           </svg>
                       <?php elseif ($_SESSION['lang'] == 'fr'): ?>
                           <!-- Fransa Bayrağı -->
                           <svg width="60" height="40" viewBox="0 0 3 2" class="w-full h-full">
                               <rect width="1" height="2" x="0" y="0" fill="#0055A4" />
                               <rect width="1" height="2" x="1" y="0" fill="#fff" />
                               <rect width="1" height="2" x="2" y="0" fill="#EF4135" />
                           </svg>
                       <?php elseif ($_SESSION['lang'] == 'it'): ?>
                           <!-- İtalyanca Bayrağı -->
                           <svg width="60" height="40" viewBox="0 0 3 2" class="w-full h-full">
                               <rect width="1" height="2" x="0" y="0" fill="#009246" />
                               <rect width="1" height="2" x="1" y="0" fill="#fff" />
                               <rect width="1" height="2" x="2" y="0" fill="#CE2B37" />
                           </svg>
                       <?php else: ?>
                           <!-- Varsayılan: Türk Bayrağı -->
                           <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 800" class="w-full h-full">
                               <rect width="1200" height="800" fill="#E30A17"/>
                               <circle cx="425" cy="400" r="200" fill="#fff"/>
                               <circle cx="475" cy="400" r="160" fill="#E30A17"/>
                               <g fill="#fff" transform="translate(640,400) scale(1.6)">
                                   <path d="M0,-60 L17.63,-18.54 L69.02,-18.54 L26.69,7.08 L44.33,48.54 L0,22.9 L-44.33,48.54 L-26.69,7.08 L-69.02,-18.54 L-17.63,-18.54 Z"/>
                               </g>
                           </svg>
                       <?php endif; ?>
                   </button>
                   <div class="language-menu">
                       <!-- Türkçe -->
                       <a href="?lang=tr" class="language-option">
                           <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 800" class="w-6 h-4">
                               <rect width="1200" height="800" fill="#E30A17"/>
                               <circle cx="425" cy="400" r="200" fill="#fff"/>
                               <circle cx="475" cy="400" r="160" fill="#E30A17"/>
                               <g fill="#fff" transform="translate(640, 400) scale(1.6)">
                                   <path d="M0,-60 L17.63,-18.54 L69.02,-18.54 L26.69,7.08 L44.33,48.54 L0,22.9 L-44.33,48.54 L-26.69,7.08 L-69.02,-18.54 L-17.63,-18.54 Z"/>
                               </g>
                           </svg>
                           <span class="ml-2">Türkçe</span>
                       </a>
                       <!-- İngilizce -->
                       <a href="?lang=en" class="language-option">
                           <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 60 40" class="w-6 h-4">
                               <rect width="60" height="40" fill="#012169"/>
                               <path d="M0,0 L60,40 M60,0 L0,40" stroke="#fff" stroke-width="4"/>
                               <path d="M0,0 L60,40 M60,0 L0,40" stroke="#C8102E" stroke-width="2"/>
                               <path d="M30,0 L30,40 M0,20 L60,20" stroke="#fff" stroke-width="8"/>
                               <path d="M30,0 L30,40 M0,20 L60,20" stroke="#C8102E" stroke-width="4"/>
                           </svg>
                           <span class="ml-2">English</span>
                       </a>
                       <!-- Almanca -->
                       <a href="?lang=de" class="language-option">
                           <svg width="60" height="40" viewBox="0 0 3 2" class="w-6 h-4">
                               <rect width="3" height="2" fill="#ffce00"/>
                               <rect width="3" height="1.3333" y="0" fill="#d00"/>
                               <rect width="3" height="0.6667" y="0" fill="#000"/>
                           </svg>
                           <span class="ml-2">Deutsch</span>
                       </a>
                       <!-- Rusça -->
                       <a href="?lang=ru" class="language-option">
                           <svg width="60" height="40" viewBox="0 0 3 2" class="w-6 h-4">
                               <rect width="3" height="2" fill="#fff"/>
                               <rect width="3" height="0.6667" y="0.6667" fill="#0039A6"/>
                               <rect width="3" height="0.6666" y="1.3333" fill="#D52B1E"/>
                           </svg>
                           <span class="ml-2">Русский</span>
                       </a>
                       <!-- İspanyolca -->
                       <a href="?lang=es" class="language-option">
                           <svg width="60" height="40" viewBox="0 0 3 2" class="w-6 h-4">
                               <rect width="3" height="2" fill="#AA151B"/>
                               <rect width="3" height="0.6667" y="0.6667" fill="#F1BF00"/>
                           </svg>
                           <span class="ml-2">Español</span>
                       </a>
                       <!-- Çince -->
                       <a href="?lang=zh" class="language-option">
                           <svg width="60" height="40" viewBox="0 0 30 20" class="w-6 h-4">
                               <rect width="30" height="20" fill="#DE2910"/>
                               <!-- Büyük yıldız -->
                               <polygon fill="#FFDE00" points="5,2.5  6,4 8,4.5 6,5 5,7 4,5 2,4.5 4,4" />
                               <!-- 4 küçük yıldız (Basit konumlar) -->
                               <polygon fill="#FFDE00" points="10,4  10.5,5 11.5,5.2 10.8,6 11,7 10,6.5 9,7 9.2,6 8.5,5.2 9.5,5" />
                               <polygon fill="#FFDE00" points="10,9  10.5,10 11.5,10.2 10.8,11 11,12 10,11.5 9,12 9.2,11 8.5,10.2 9.5,10" />
                               <polygon fill="#FFDE00" points="6,6.5  6.5,7.5 7.5,7.7 6.8,8.5 7,9.5 6,9 5,9.5 5.2,8.5 4.5,7.7 5.5,7.5" />
                               <polygon fill="#FFDE00" points="5,11  5.5,12 6.5,12.2 5.8,13 6,14 5,13.5 4,14 4.2,13 3.5,12.2 4.5,12" />
                           </svg>
                           <span class="ml-2">中文</span>
                       </a>
                       <!-- Fransızca -->
                       <a href="?lang=fr" class="language-option">
                           <svg width="60" height="40" viewBox="0 0 3 2" class="w-6 h-4">
                               <rect width="1" height="2" x="0" y="0" fill="#0055A4" />
                               <rect width="1" height="2" x="1" y="0" fill="#fff" />
                               <rect width="1" height="2" x="2" y="0" fill="#EF4135" />
                           </svg>
                           <span class="ml-2">Français</span>
                       </a>
                       <!-- İtalyanca -->
                       <a href="?lang=it" class="language-option">
                           <svg width="60" height="40" viewBox="0 0 3 2" class="w-6 h-4">
                               <rect width="1" height="2" x="0" y="0" fill="#009246" />
                               <rect width="1" height="2" x="1" y="0" fill="#fff" />
                               <rect width="1" height="2" x="2" y="0" fill="#CE2B37" />
                           </svg>
                           <span class="ml-2">Italiano</span>
                       </a>
                   </div>
               </div>
               <i class="fas fa-tree christmas-tree"></i>
               <a href="/health_tourism/index.php" class="text-2xl font-bold brand-text">
                   HealthTurkey
               </a>
           </div>
           <!-- Sağ Taraf: Navigasyon ve Giriş/Çıkış -->
           <div class="flex items-center space-x-8">
               <a href="/health_tourism/index.php" class="nav-link">
                   <?php echo $t['home']; ?>
               </a>
               <a href="/health_tourism/services.php" class="nav-link">
                   <?php echo $t['services']; ?>
               </a>
               <a href="/health_tourism/doctors.php" class="nav-link">
                   <?php echo $t['doctors']; ?>
               </a>

               <?php if (isset($_SESSION['user_id'])): ?>
                   <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                       <a href="/health_tourism/admin/dashboard.php" class="nav-link">
                           <?php echo $t['admin_panel']; ?>
                       </a>
                   <?php endif; ?>
                   <a href="/health_tourism/profile.php" class="nav-link">
                       <?php echo $t['profile']; ?>
                   </a>
                   <a href="/health_tourism/logout.php" 
                      class="logout-button px-6 py-2 rounded-full text-white hover:shadow-lg">
                       <?php echo $t['logout']; ?>
                   </a>
               <?php else: ?>
                   <a href="/health_tourism/login.php" 
                      class="login-button px-6 py-2 rounded-full text-white hover:shadow-lg">
                       <?php echo $t['login']; ?>
                   </a>
               <?php endif; ?>
           </div>
       </div>
   </div>
</div>
</body>
</html>
