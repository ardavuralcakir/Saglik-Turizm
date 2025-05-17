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
   <link rel="icon" type="image/png" href="/health_tourism/assets/images/favicon/favicon.png">
   <link rel="shortcut icon" type="image/png" href="/health_tourism/assets/images/favicon/favicon.png">
   
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

       /* Badem CSS Stilleri */
       #badem-world {
           position: fixed;
           top: 0;
           left: 0;
           width: 100%;
           height: 100%;
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

       /* Disko Modu Stilleri */
       .disco-button {
           padding: 8px 16px;
           border-radius: 20px;
           background: linear-gradient(45deg, #ff00ff, #00ffff);
           color: white;
           cursor: pointer;
           transition: all 0.3s ease;
           border: none;
           font-weight: bold;
           position: relative;
           overflow: hidden;
       }

       .disco-button:hover {
           transform: scale(1.05);
           box-shadow: 0 0 15px rgba(255, 0, 255, 0.5);
       }

       .disco-mode {
           animation: discoColors 2s infinite;
       }

       .disco-text {
           animation: discoText 1s infinite;
       }

       @keyframes discoColors {
           0% { background: rgba(255, 0, 0, 0.2); }
           25% { background: rgba(0, 255, 0, 0.2); }
           50% { background: rgba(0, 0, 255, 0.2); }
           75% { background: rgba(255, 255, 0, 0.2); }
           100% { background: rgba(255, 0, 0, 0.2); }
       }

       @keyframes discoText {
           0% { color: #ff0000; }
           25% { color: #00ff00; }
           50% { color: #0000ff; }
           75% { color: #ffff00; }
           100% { color: #ff0000; }
       }

       .disco-light {
           position: fixed;
           width: 100px;
           height: 100px;
           border-radius: 50%;
           pointer-events: none;
           mix-blend-mode: screen;
           animation: discoLight 1s infinite;
           z-index: 9999;
       }

       @keyframes discoLight {
           0% { background: radial-gradient(circle, rgba(255,0,0,0.8) 0%, transparent 70%); }
           33% { background: radial-gradient(circle, rgba(0,255,0,0.8) 0%, transparent 70%); }
           66% { background: radial-gradient(circle, rgba(0,0,255,0.8) 0%, transparent 70%); }
           100% { background: radial-gradient(circle, rgba(255,0,0,0.8) 0%, transparent 70%); }
       }
   </style>

   <script>
       /* Matter.js ve Badem JavaScript Kodları */
       document.addEventListener('DOMContentLoaded', function() {
           // Matter.js modüllerini al
           const Engine = Matter.Engine,
                 Render = Matter.Render,
                 World = Matter.World,
                 Bodies = Matter.Bodies,
                 Body = Matter.Body,
                 Mouse = Matter.Mouse,
                 MouseConstraint = Matter.MouseConstraint,
                 Composite = Matter.Composite,
                 Query = Matter.Query,
                 Vector = Matter.Vector;

           // Motor ve dünya oluştur
           const engine = Engine.create();
           engine.gravity.y = 0.6;
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
           const wallOptions = {
               isStatic: true,
               friction: 0.2,
               restitution: 0.4,
               render: { visible: false }
           };

           const ground = Bodies.rectangle(
               window.innerWidth / 2,
               window.innerHeight + 30,
               window.innerWidth * 2,
               60,
               wallOptions
           );

           const leftWall = Bodies.rectangle(
               -30,
               window.innerHeight / 2,
               60,
               window.innerHeight * 2,
               wallOptions
           );

           const rightWall = Bodies.rectangle(
               window.innerWidth + 30,
               window.innerHeight / 2,
               60,
               window.innerHeight * 2,
               wallOptions
           );

           World.add(engine.world, [ground, leftWall, rightWall]);

           // Mouse kontrolü
           const mouse = Mouse.create(render.canvas);
           const mouseConstraint = MouseConstraint.create(engine, {
               mouse: mouse,
               constraint: {
                   stiffness: 0.2,
                   render: {
                       visible: false
                   }
               }
           });

           World.add(engine.world, mouseConstraint);
           render.mouse = mouse;

           // Kedi burnu ve badem oluşturma
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

           function createBadem() {
               if (bademCount >= maxBademCount) return;

               const noseRect = catNose.getBoundingClientRect();
               const size = 15 + Math.random() * 10;
               const rotation = Math.random() * Math.PI * 2;

               const badem = Bodies.circle(
                   noseRect.left + noseRect.width / 2,
                   noseRect.top + noseRect.height / 2,
                   size,
                   {
                       restitution: 0.6,
                       friction: 0.1,
                       frictionAir: 0.002,
                       density: 0.001,
                       angle: rotation,
                       render: {
                           sprite: {
                               texture: 'assets/images/hehe/badem.png',
                               xScale: 0.3 * (size / 15),
                               yScale: 0.3 * (size / 15)
                           }
                       }
                   }
               );

               // Başlangıç hızı ve açısal hız ver
               const speed = 2 + Math.random() * 3;
               const angle = -Math.PI/4 + (Math.random() - 0.5) * Math.PI/2;
               Body.setVelocity(badem, {
                   x: Math.cos(angle) * speed,
                   y: Math.sin(angle) * speed
               });
               Body.setAngularVelocity(badem, (Math.random() - 0.5) * 0.2);

               World.add(engine.world, badem);
               bademCount++;

               // 10 saniye sonra bademi sil
               setTimeout(() => {
                   World.remove(engine.world, badem);
                   bademCount--;
               }, 10000);
           }

           // Mouse takibi ve fizik etkileşimi
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
                       const force = 0.003;
                       const repelStrength = (150 - distance) / 150;
                       const forceX = (dx / distance) * force * repelStrength;
                       const forceY = (dy / distance) * force * repelStrength;
                       
                       Body.applyForce(body, body.position, {
                           x: forceX,
                           y: forceY
                       });
                       
                       // Hafif dönme efekti ekle
                       Body.setAngularVelocity(body, body.angularVelocity + (Math.random() - 0.5) * 0.05);
                   }
               });
           });

           catNose.addEventListener('click', function() {
               if (!isAnimating) {
                   meowSound.play();
                   isAnimating = true;
                   bademInterval = setInterval(createBadem, 100);
                   
                   // 3 saniye sonra bademleri durduralım
                   setTimeout(() => {
                       isAnimating = false;
                       clearInterval(bademInterval);
                   }, 3000);
               }
           });

           // Pencere boyutu değiştiğinde duvarları güncelle
           window.addEventListener('resize', function() {
               render.canvas.width = window.innerWidth;
               render.canvas.height = window.innerHeight;
               
               Body.setPosition(ground, {
                   x: window.innerWidth / 2,
                   y: window.innerHeight + 30
               });
               
               Body.setPosition(rightWall, {
                   x: window.innerWidth + 30,
                   y: window.innerHeight / 2
               });
           });

           // Motoru ve render'ı başlat
           Engine.run(engine);
           Render.run(render);

           // Disko Modu JavaScript
           const discoButton = document.querySelector('.disco-button');
           let isDiscoMode = false;
           let discoLights = [];
           let audioContext;
           let oscillator;

           function createDiscoLight() {
               const light = document.createElement('div');
               light.className = 'disco-light';
               document.body.appendChild(light);
               return light;
           }

           function updateDiscoLights() {
               const time = Date.now() * 0.002;
               discoLights.forEach((light, index) => {
                   const x = window.innerWidth * (0.5 + Math.sin(time + index * 1.5) * 0.4);
                   const y = window.innerHeight * (0.5 + Math.cos(time + index * 1.5) * 0.4);
                   light.style.left = x + 'px';
                   light.style.top = y + 'px';
               });

               if (isDiscoMode) {
                   requestAnimationFrame(updateDiscoLights);
               }
           }

           function toggleDiscoMode() {
               isDiscoMode = !isDiscoMode;
               const body = document.body;
               const allElements = document.querySelectorAll('*');

               if (isDiscoMode) {
                   body.classList.add('disco-mode');
                   discoButton.textContent = '🕺 Disko Durdur 💃';
                   
                   // Disko ışıkları oluştur
                   for (let i = 0; i < 5; i++) {
                       discoLights.push(createDiscoLight());
                   }
                   updateDiscoLights();

                   // Disco müziği başlat
                   if (!audioContext) {
                       audioContext = new (window.AudioContext || window.webkitAudioContext)();
                   }
                   oscillator = audioContext.createOscillator();
                   oscillator.connect(audioContext.destination);
                   oscillator.type = 'square';
                   oscillator.frequency.setValueAtTime(440, audioContext.currentTime);
                   oscillator.start();

                   // Tüm metinlere disko efekti
                   allElements.forEach(el => {
                       if (el.childNodes.length === 1 && el.childNodes[0].nodeType === 3) {
                           el.classList.add('disco-text');
                       }
                   });
               } else {
                   body.classList.remove('disco-mode');
                   discoButton.textContent = '🎵 Disko Başlat 🎵';
                   
                   // Disko ışıklarını kaldır
                   discoLights.forEach(light => light.remove());
                   discoLights = [];

                   // Disco müziği durdur
                   if (oscillator) {
                       oscillator.stop();
                       oscillator.disconnect();
                   }

                   // Disko efektlerini kaldır
                   allElements.forEach(el => {
                       el.classList.remove('disco-text');
                   });
               }
           }

           // Disko butonunu oluştur ve yerleştir
           const discoButton = document.createElement('button');
           discoButton.className = 'disco-button';
           discoButton.textContent = '🎵 Disko Başlat 🎵';
           discoButton.addEventListener('click', toggleDiscoMode);

           // Butonu header'a ekle
           const headerRight = document.querySelector('.header-wrapper .flex.items-center.space-x-8');
           headerRight.insertBefore(discoButton, headerRight.firstChild);
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