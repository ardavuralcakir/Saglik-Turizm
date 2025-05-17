<?php
session_start();

$lang = $_SESSION['lang'] ?? 'tr';
$translations_file = __DIR__ . "/translations/translation_{$lang}.php";

if (file_exists($translations_file)) {
    $translations = require $translations_file;
    $pt = array_merge($translations['tr'] ?? [], $translations ?? []); // Ana dizi ve alt diziyi birleştir
} else {
    die("Translation file not found: {$translations_file}");
}

include_once './config/database.php';
include_once './includes/new-header.php';
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>HealthTurkey - <?php echo $pt['login_page_title']; ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" />
    <script src="https://cdn.tailwindcss.com"></script>

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Montserrat', sans-serif;
        }

        body {
            background: linear-gradient(135deg, #f6f8fc 0%, #eef2f7 100%);
            min-height: 100vh;
        }

        .main-container {
            min-height: calc(100vh - 140px);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 6rem 1rem 1rem 1rem;
        }

        .container {
            background-color: #fff;
            border-radius: 30px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            position: relative;
            overflow: hidden;
            width: 100%;
            max-width: 768px;
            min-height: 550px;
        }

        .form-container {
            position: absolute;
            top: 0;
            height: 100%;
            transition: all 0.6s ease-in-out;
        }

        .sign-in {
            left: 0;
            width: 50%;
            z-index: 2;
        }

        .container.active .sign-in {
            transform: translateX(100%);
        }

        .sign-up {
            left: 0;
            width: 50%;
            opacity: 0;
            z-index: 1;
        }

        .container.active .sign-up {
            transform: translateX(100%);
            opacity: 1;
            z-index: 5;
            animation: move 0.6s;
        }

        @keyframes move {
            0%, 49.99% {
                opacity: 0;
                z-index: 1;
            }
            50%, 100% {
                opacity: 1;
                z-index: 5;
            }
        }

        .container form {
            background-color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            padding: 0 25px;
            height: 100%;
            gap: 10px;
        }

        .container h1 {
            color: #333;
            font-size: 24px;
            font-weight: 500;
            margin-bottom: 15px;
        }

        .input-group {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
            width: 100%;
        }

        .container input {
            background-color: #f6f6f6;
            border: none;
            margin: 4px 0;
            padding: 10px 12px;
            width: 100%;
            border-radius: 8px;
            outline: none;
            transition: background-color 0.3s;
        }

        .container input:focus {
            background-color: #eee;
        }

        .container button {
            background: linear-gradient(to right, #512da8, #673ab7);
            color: #fff;
            font-size: 12px;
            padding: 10px 35px;
            border: none;
            border-radius: 50px;
            font-weight: 600;
            letter-spacing: 1px;
            text-transform: uppercase;
            margin-top: 10px;
            cursor: pointer;
            transition: transform 0.3s, box-shadow 0.3s;
            display: block;
        }

        .container button:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(81, 45, 168, 0.3);
        }

        .container button.hidden {
            background: transparent;
            border: 1px solid #fff;
            display: block;
            color: #fff;
        }

        .social-icons {
            margin: 12px 0;
            display: flex;
            gap: 8px;
        }

        .social-icons a {
            border: 1px solid #ddd;
            border-radius: 20%;
            display: inline-flex;
            justify-content: center;
            align-items: center;
            width: 35px;
            height: 35px;
            transition: all 0.3s;
        }

        .social-icons a:hover {
            transform: translateY(-2px);
            border-color: #512da8;
            color: #512da8;
        }

        .toggle-container {
            position: absolute;
            top: 0;
            left: 50%;
            width: 50%;
            height: 100%;
            overflow: hidden;
            transition: all 0.6s ease-in-out;
            border-radius: 150px 0 0 100px;
            z-index: 1000;
        }

        .container.active .toggle-container {
            transform: translateX(-100%);
            border-radius: 0 150px 100px 0;
        }

        .toggle {
            background: linear-gradient(to right, #512da8, #673ab7);
            color: #fff;
            position: relative;
            left: -100%;
            height: 100%;
            width: 200%;
            transform: translateX(0);
            transition: all 0.6s ease-in-out;
            display: flex;
        }

        .container.active .toggle {
            transform: translateX(50%);
        }

        .toggle-panel {
            position: absolute;
            width: 50%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            padding: 0 25px;
            text-align: center;
            top: 0;
            transform: translateX(0);
            transition: all 0.6s ease-in-out;
        }

        .toggle-panel h1 {
            color: #fff;
            margin-bottom: 10px;
        }

        .toggle-panel p {
            color: #fff;
            font-size: 14px;
            line-height: 1.5;
        }

        .toggle-left {
            transform: translateX(-200%);
        }

        .container.active .toggle-left {
            transform: translateX(0);
        }

        .toggle-right {
            right: 0;
            transform: translateX(0);
        }

        .container.active .toggle-right {
            transform: translateX(200%);
        }

        @media (max-width: 650px) {
            .container {
                margin: 1rem;
                min-height: 580px;
            }

            .form-container {
                width: 100%;
                left: 0;
                top: 95px;
            }

            .sign-in, .sign-up {
                width: 100%;
            }

            .toggle-container {
                top: 0;
                left: 0;
                width: 100%;
                height: 95px;
                border-radius: 0;
            }

            .toggle {
                flex-direction: row;
                width: 100%;
                left: 0;
            }

            .toggle-panel {
                flex-direction: row;
                justify-content: space-around;
                padding: 0 15px;
            }

            .toggle-panel h1 {
                font-size: 20px;
                margin: 0;
            }

            .toggle-panel p {
                display: none;
            }

            .container button {
                padding: 10px 30px;
            }
        }
    </style>
</head>
<body>
    <div class="main-container">
        <div class="container" id="container">
            <!-- Kayıt Formu -->
            <div class="form-container sign-up">
                <form method="POST" action="register_process.php" id="registrationForm">
                    <h1><?php echo $pt['signup_title']; ?></h1>
                    <div class="social-icons">
                        <a href="#"><i class="fab fa-google"></i></a>
                        <a href="#"><i class="fab fa-facebook-f"></i></a>
                        <a href="#"><i class="fab fa-github"></i></a>
                    </div>
                    <span><?php echo $pt['signup_social_text']; ?></span>
                    <div class="input-group">
                        <input type="text" name="username" placeholder="<?php echo $pt['placeholder_username']; ?>" required>
                        <input type="text" name="full_name" placeholder="<?php echo $pt['placeholder_full_name']; ?>" required>
                    </div>
                    <div class="input-group">
                        <input type="email" name="email" placeholder="<?php echo $pt['placeholder_email']; ?>" required>
                        <input type="tel" name="phone" placeholder="<?php echo $pt['placeholder_phone']; ?>">
                    </div>
                    <div class="input-group">
                        <input type="password" name="password" placeholder="<?php echo $pt['placeholder_password']; ?>" required minlength="6">
                        <input type="password" name="confirm_password" placeholder="<?php echo $pt['placeholder_confirm_pw']; ?>" required minlength="6">
                    </div>
                    <button type="submit"><?php echo $pt['register_button']; ?></button>
                </form>
            </div>

            <!-- Giriş Formu -->
            <div class="form-container sign-in">
                <form method="POST" action="login_process.php" id="loginForm">
                    <h1><?php echo $pt['signin_title']; ?></h1>
                    <div class="social-icons">
                        <a href="#"><i class="fab fa-google"></i></a>
                        <a href="#"><i class="fab fa-facebook-f"></i></a>
                        <a href="#"><i class="fab fa-github"></i></a>
                    </div>
                    <span><?php echo $pt['signin_social_text']; ?></span>
                    <input type="text" name="email" placeholder="<?php echo $pt['placeholder_email_or_un']; ?>" required>
                    <input type="password" name="password" placeholder="<?php echo $pt['placeholder_password']; ?>" required>
                    
                    <!-- Şifremi Unuttum -->
                    <a href="javascript:void(0)" id="forgotPasswordLink">
                        <?php echo $pt['forgot_password']; ?>
                    </a>

                    <button type="submit"><?php echo $pt['signin_button']; ?></button>
                </form>
            </div>
 
            <!-- Toggle Container (Sağ-Sol Panel) -->
            <div class="toggle-container">
                <div class="toggle">
                    <div class="toggle-panel toggle-left">
                        <h1><?php echo $pt['welcome_back_title']; ?></h1>
                        <p><?php echo $pt['welcome_back_subtitle']; ?></p>
                        <button class="hidden" id="login"><?php echo $pt['toggle_login_button']; ?></button>
                    </div>
                    <div class="toggle-panel toggle-right">
                        <h1><?php echo $pt['hello_title']; ?></h1>
                        <p><?php echo $pt['hello_subtitle']; ?></p>
                        <button class="hidden" id="register"><?php echo $pt['toggle_register_button']; ?></button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Kayıt Sonrası Doğrulama Modal (6 Haneli Kod) -->
    <div id="verificationModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-[9999]">
        <div class="bg-white rounded-lg p-8 max-w-md w-full mx-4 transform transition-transform duration-300 scale-0">
            <div class="text-center mb-6">
                <i class="fas fa-envelope text-4xl text-purple-600 mb-4"></i>
                <h2 class="text-2xl font-semibold">
                    <?php echo ($lang == 'tr') ? 'E-posta Doğrulama' : 'Email Verification'; ?>
                </h2>
                <p class="text-gray-600 mt-2">
                    <?php echo ($lang == 'tr') 
                        ? 'E-posta adresinize gönderilen 6 haneli doğrulama kodunu giriniz' 
                        : 'Enter the 6-digit verification code sent to your email'; ?>
                </p>
            </div>
            
            <div class="flex justify-center gap-2 mb-6">
                <input type="text" maxlength="1" class="codeInput w-12 h-12 text-center border rounded-lg text-xl focus:border-purple-500 focus:ring-2 focus:ring-purple-200" />
                <input type="text" maxlength="1" class="codeInput w-12 h-12 text-center border rounded-lg text-xl focus:border-purple-500 focus:ring-2 focus:ring-purple-200" />
                <input type="text" maxlength="1" class="codeInput w-12 h-12 text-center border rounded-lg text-xl focus:border-purple-500 focus:ring-2 focus:ring-purple-200" />
                <input type="text" maxlength="1" class="codeInput w-12 h-12 text-center border rounded-lg text-xl focus:border-purple-500 focus:ring-2 focus:ring-purple-200" />
                <input type="text" maxlength="1" class="codeInput w-12 h-12 text-center border rounded-lg text-xl focus:border-purple-500 focus:ring-2 focus:ring-purple-200" />
                <input type="text" maxlength="1" class="codeInput w-12 h-12 text-center border rounded-lg text-xl focus:border-purple-500 focus:ring-2 focus:ring-purple-200" />
            </div>
            
            <button id="verifyButton" class="w-full bg-gradient-to-r from-[#512da8] to-[#673ab7] text-white py-3 px-4 rounded-full font-semibold hover:opacity-90 transition-opacity">
                <?php echo ($lang == 'tr') ? 'Doğrula' : 'Verify'; ?>
            </button>
        </div>
    </div>

    <!-- Şifremi Unuttum Modal (E-posta soracak) -->
    <div id="forgotPasswordModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-[9999]">
        <div class="bg-white rounded-lg p-8 max-w-md w-full mx-4 transform transition-transform duration-300 scale-0">
            <div class="text-center mb-6">
                <i class="fas fa-unlock text-4xl text-purple-600 mb-4"></i>
                <h2 class="text-2xl font-semibold">
                    <?php echo $pt['forgotpw_modal_title']; ?>
                </h2>
                <p class="text-gray-600 mt-2">
                    <?php echo $pt['forgotpw_modal_sub']; ?>
                </p>
            </div>
            
            <input type="email" id="forgotEmailInput" class="w-full mb-4 border rounded p-2" placeholder="<?php echo $pt['placeholder_email']; ?>" required>
            
            <button id="sendForgotCodeButton" class="w-full bg-gradient-to-r from-[#512da8] to-[#673ab7] text-white py-3 px-4 rounded-full font-semibold hover:opacity-90 transition-opacity">
                <?php echo $pt['forgotpw_sendcode_btn']; ?>
            </button>
        </div>
    </div>

    <!-- Yeni Şifre Belirleme Modal (kodu doğrulayınca açılacak) -->
    <div id="resetPasswordModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-[9999]">
        <div class="bg-white rounded-lg p-8 max-w-md w-full mx-4 transform transition-transform duration-300 scale-0">
            <div class="text-center mb-6">
                <i class="fas fa-key text-4xl text-purple-600 mb-4"></i>
                <h2 class="text-2xl font-semibold"><?php echo $pt['resetpw_modal_title']; ?></h2>
            </div>

            <div class="mb-4">
                <input type="password" id="newPasswordInput" class="w-full border rounded p-2" 
                       placeholder="<?php echo $pt['resetpw_placeholder_new']; ?>" required>
            </div>
            <div class="mb-6">
                <input type="password" id="confirmPasswordInput" class="w-full border rounded p-2"
                       placeholder="<?php echo $pt['resetpw_placeholder_conf']; ?>" required>
            </div>

            <button id="resetPasswordButton" class="w-full bg-gradient-to-r from-[#512da8] to-[#673ab7] text-white py-3 px-4 rounded-full font-semibold hover:opacity-90 transition-opacity">
                <?php echo $pt['resetpw_button']; ?>
            </button>
        </div>
    </div>

    <script>
        // =================== Temel Değişkenler ve Elemanlar ===================
        const container = document.getElementById('container');
        const registerBtn = document.getElementById('register');
        const loginBtn = document.getElementById('login');
        
        // Kayıt formu
        const registrationForm = document.getElementById('registrationForm');

        // Kayıt sonrası doğrulama modalları
        const verificationModal = document.getElementById('verificationModal');
        const verificationModalContent = verificationModal.querySelector('div');
        const codeInputs = verificationModal.querySelectorAll('.codeInput');
        const verifyButton = document.getElementById('verifyButton');
        
        // Şifremi unuttum modalları
        const forgotPasswordModal = document.getElementById('forgotPasswordModal');
        const forgotPasswordModalContent = forgotPasswordModal.querySelector('div');
        const forgotEmailInput = document.getElementById('forgotEmailInput');
        const sendForgotCodeButton = document.getElementById('sendForgotCodeButton');

        const resetPasswordModal = document.getElementById('resetPasswordModal');
        const resetPasswordModalContent = resetPasswordModal.querySelector('div');
        const newPasswordInput = document.getElementById('newPasswordInput');
        const confirmPasswordInput = document.getElementById('confirmPasswordInput');
        const resetPasswordButton = document.getElementById('resetPasswordButton');

        // Email saklamak için değişkenler
        let userEmail = null;           // Kayıt sonrası doğrulamada kullanılan email
        let forgotEmail = null;         // Şifremi unuttum akışında kullanılan email
        let isForgotPasswordFlow = false; // Doğrulama kodunun hangi akış için girildiğini anlamak için

        // =================== Panel Geçiş Butonları (Kayıt/Giriş) ===================
        registerBtn.addEventListener('click', () => {
            container.classList.add("active");
        });
        loginBtn.addEventListener('click', () => {
            container.classList.remove("active");
        });

        // =================== Kayıt Formu Submit ===================
        registrationForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(registrationForm);
            
            try {
                const response = await fetch('register_process.php', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();

                if (result.success) {
                    // Kayıt başarılı => formdaki email'i alıp global değişkende saklıyoruz
                    userEmail = formData.get('email');
                    console.log("Kayıt yapılan e-posta:", userEmail);

                    // Şifre unutma akışında değiliz
                    isForgotPasswordFlow = false;

                    // Ardından doğrulama modal'ını aç
                    openVerificationModal();
                } else {
                    alert((result.errors || [result.error]).join('\n'));
                }
            } catch (error) {
                console.error('register error:', error);
                alert('Bir hata oluştu, lütfen tekrar deneyin.');
            }
        });

        // =================== Login Formu Submit ===================
        const loginForm = document.getElementById('loginForm');
        loginForm.addEventListener('submit', function(e) {
            // Form verilerini kontrol et
            const emailInput = loginForm.querySelector('input[name="email"]');
            const passwordInput = loginForm.querySelector('input[name="password"]');
            
            if (!emailInput.value.trim() || !passwordInput.value.trim()) {
                e.preventDefault();
                alert('Lütfen e-posta ve şifrenizi giriniz.');
                return false;
            }
            
            // Form normal POST ile gönderilecek
            return true;
        });

        // =================== "Şifremi Unuttum" Linki ===================
        const forgotPasswordLink = document.getElementById('forgotPasswordLink');
        forgotPasswordLink.addEventListener('click', () => {
            // forgot password modal'ını aç
            forgotPasswordModal.style.display = 'flex';
            setTimeout(() => {
                forgotPasswordModalContent.style.transform = 'scale(1)';
            }, 10);
        });

        // =================== "Kod Gönder" (Şifremi unuttum modal) ===================
        sendForgotCodeButton.addEventListener('click', async () => {
            const email = forgotEmailInput.value.trim();
            if (!email) {
                alert('Lütfen e-posta giriniz.');
                return;
            }
            forgotEmail = email; // Sakla

            try {
                // "forgot_password_process.php" dosyası senin oluşturacağın bir backend endpoint'i
                // E-posta'ya doğrulama kodu gönderecek, tıpkı register_process gibi
                const response = await fetch('forgot_password_process.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ email })
                });
                const result = await response.json();

                if (result.success) {
                    alert('Doğrulama kodu e-postanıza gönderildi.');
                    
                    // Şifremi unuttum modali kapat
                    closeModal(forgotPasswordModal, forgotPasswordModalContent);
                    
                    // Kod doğrulama modali => Bu sefer forgot password flow
                    isForgotPasswordFlow = true;
                    openVerificationModal(); 
                } else {
                    alert(result.error || 'Kod gönderilemedi');
                }
            } catch (error) {
                console.error('forgot password error:', error);
                alert('Bir hata oluştu, tekrar deneyin.');
            }
        });

        // =================== Kod Giriş Pop-up (Hem kayıt sonrası hem forgot) ===================
        codeInputs.forEach((input, index) => {
            input.addEventListener('keyup', (e) => {
                if (e.key !== 'Backspace' && index < codeInputs.length - 1 && input.value) {
                    codeInputs[index + 1].focus();
                }
                if (e.key === 'Backspace' && index > 0 && !input.value) {
                    codeInputs[index - 1].focus();
                }
            });
        });

        verifyButton.addEventListener('click', async () => {
            // 6 karakterli kodu birleştir
            const code = Array.from(codeInputs).map(inp => inp.value).join('');
            console.log('Girilen kod:', code);

            // Hangi e-postayı kullanacağız?
            const emailToVerify = isForgotPasswordFlow ? forgotEmail : userEmail;
            console.log('Kod doğrulanacak e-posta:', emailToVerify);

            if (!emailToVerify) {
                alert('Email bulunamadı.');
                return;
            }

            const postData = {
                verification_code: code,
                email: emailToVerify
            };

            try {
                const response = await fetch('verify.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(postData),
                    credentials: 'include'
                });
                const result = await response.json();
                console.log('verify response:', result);

                if (result.success) {
                    if (!isForgotPasswordFlow) {
                        // Kayıt sonrası doğrulama başarılı
                        verificationModalContent.innerHTML = `
                            <div class="text-center p-6">
                                <svg class="w-16 h-16 mx-auto text-green-500 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                <h2 class="text-2xl font-semibold mb-2">Hesabınız Başarıyla Doğrulandı!</h2>
                                <p class="text-gray-600">Artık giriş yapabilirsiniz.</p>
                            </div>
                        `;
                        setTimeout(() => {
                            verificationModalContent.style.transform = 'scale(0)';
                            setTimeout(() => {
                                verificationModal.style.display = 'none';
                                container.classList.remove('active');
                                window.location.reload(); 
                            }, 300);
                        }, 2000);
                    } else {
                        // Şifre sıfırlama kodu doğrulandı
                        // => Verification modal kapat, resetPasswordModal aç
                        closeModal(verificationModal, verificationModalContent);
                        openResetPasswordModal();
                    }
                } else {
                    alert(result.error || 'Doğrulama başarısız oldu');
                }
            } catch (error) {
                console.error('verify error:', error);
                alert('Kod doğrulama sırasında bir hata oluştu');
            }
        });

        // =================== Yeni Şifre Belirleme ===================
        resetPasswordButton.addEventListener('click', async () => {
            const newPass = newPasswordInput.value.trim();
            const confPass = confirmPasswordInput.value.trim();

            if (!newPass || !confPass) {
                alert('Lütfen yeni şifre ve tekrarını giriniz.');
                return;
            }
            if (newPass !== confPass) {
                alert('Şifreler eşleşmiyor!');
                return;
            }

            try {
                // "reset_password_process.php" => Bu senin yazacağın endpoint
                // orada newPass, email vb. alıp DB'de güncelleme yapacaksın.
                const response = await fetch('reset_password_process.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        email: forgotEmail,    // E-postayı saklıyorduk
                        new_password: newPass
                    })
                });
                const result = await response.json();

                if (result.success) {
                    alert('Şifreniz güncellendi. Artık yeni şifre ile giriş yapabilirsiniz.');
                    closeModal(resetPasswordModal, resetPasswordModalContent);
                } else {
                    alert(result.error || 'Şifre güncellenemedi.');
                }
            } catch (error) {
                console.error('reset password error:', error);
                alert('Şifre güncelleme sırasında bir hata oluştu');
            }
        });

        // =================== Modal Açma/Kapatma Fonksiyonları ===================
        function openVerificationModal() {
            verificationModal.style.display = 'flex';
            setTimeout(() => {
                verificationModalContent.style.transform = 'scale(1)';
                // Kod kutucuklarını temizleyip ilkine odaklan
                codeInputs.forEach(inp => inp.value = '');
                codeInputs[0].focus();
            }, 10);
        }

        function openResetPasswordModal() {
            resetPasswordModal.style.display = 'flex';
            setTimeout(() => {
                resetPasswordModalContent.style.transform = 'scale(1)';
                // inputları temizle
                newPasswordInput.value = '';
                confirmPasswordInput.value = '';
                newPasswordInput.focus();
            }, 10);
        }

        function closeModal(modalElem, modalContentElem) {
            modalContentElem.style.transform = 'scale(0)';
            setTimeout(() => {
                modalElem.style.display = 'none';
            }, 300);
        }
    </script>

    <?php include_once './includes/new-footer.php'; ?>
</body>
</html>
