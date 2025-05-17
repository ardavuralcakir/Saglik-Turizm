<?php
session_start();

// Tüm session değişkenlerini temizle
session_unset();

// Session'ı sonlandır
session_destroy();

// Ana sayfaya yönlendir
header("Location: index.php");
exit();
?>