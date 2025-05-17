<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
include_once '../config/database.php';

header('Content-Type: application/json');

$lang = $_SESSION['lang'] ?? 'tr';
$translations_file = dirname(__DIR__) . "/translations/translation_{$lang}.php";

if (file_exists($translations_file)) {
   $t = require $translations_file;
} else {
   die("Translation file not found: {$translations_file}");
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
   echo json_encode([
       'success' => false,
       'message' => ($lang === 'en') ? 'Unauthorized access' : 'Yetkisiz erişim'
   ]);
   exit();
}

try {
   $database = new Database();
   $db = $database->getConnection();

   $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
   if ($id <= 0) {
       echo json_encode([
           'success' => false,
           'message' => ($lang === 'en') ? 'Invalid Service ID' : 'Geçersiz hizmet ID\'si'
       ]);
       exit();
   }

   $query = "SELECT
               id, category_id, name, name_en, name_de, name_fr, name_es, name_it, name_ru, name_zh,
               description, description_en, description_de, description_fr, description_es, 
               description_it, description_ru, description_zh,
               price, status, image_url
             FROM services
             WHERE id = :id";

   $stmt = $db->prepare($query);
   $stmt->bindValue(':id', $id, PDO::PARAM_INT);
   $stmt->execute();

   $service = $stmt->fetch(PDO::FETCH_ASSOC);

   if (!$service) {
       echo json_encode([
           'success' => false,
           'message' => ($lang === 'en') ? 'Service not found' : 'Hizmet bulunamadı'
       ]);
       exit();
   }

   if ($service['category_id']) {
       $catQuery = ($lang === 'en') 
           ? "SELECT name_en AS name FROM service_categories WHERE id = :cat_id"
           : "SELECT name AS name FROM service_categories WHERE id = :cat_id";

       $catStmt = $db->prepare($catQuery);
       $catStmt->bindValue(':cat_id', $service['category_id'], PDO::PARAM_INT);
       $catStmt->execute();

       $category = $catStmt->fetch(PDO::FETCH_ASSOC);
       $service['category_name'] = $category ? $category['name'] : null;
   } else {
       $service['category_name'] = null;
   }

   // Extra languages data
   $extraLanguages = [
       'de' => ['name' => $service['name_de'], 'description' => $service['description_de']],
       'fr' => ['name' => $service['name_fr'], 'description' => $service['description_fr']],
       'es' => ['name' => $service['name_es'], 'description' => $service['description_es']],
       'it' => ['name' => $service['name_it'], 'description' => $service['description_it']],
       'ru' => ['name' => $service['name_ru'], 'description' => $service['description_ru']],
       'zh' => ['name' => $service['name_zh'], 'description' => $service['description_zh']]
   ];

   $service['extra_languages'] = json_encode($extraLanguages);

   echo json_encode([
       'success' => true,
       'data' => $service
   ]);
   
} catch (PDOException $e) {
   error_log('Database Error: ' . $e->getMessage());
   echo json_encode([
       'success' => false,
       'message' => ($lang === 'en') ? 'Database error' : 'Veritabanı hatası'
   ]);
} catch (Exception $e) {
   error_log('General Error: ' . $e->getMessage());
   echo json_encode([
       'success' => false,
       'message' => ($lang === 'en') ? 'An error occurred' : 'Bir hata oluştu'
   ]);
}
?>