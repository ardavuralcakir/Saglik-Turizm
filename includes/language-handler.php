<?php
// /health_tourism/includes/handle-language.php

session_start();

if (isset($_GET['lang'])) {
    $allowed_languages = ['tr', 'en'];
    $lang = $_GET['lang'];
    
    if (in_array($lang, $allowed_languages)) {
        $_SESSION['lang'] = $lang;
    }
}

// Kullanıcıyı geri yönlendir
$redirect = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '/health_tourism/index.php';
header("Location: " . $redirect);
exit();
?>