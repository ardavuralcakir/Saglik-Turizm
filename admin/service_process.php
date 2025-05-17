<?php
session_start();
include_once '../config/database.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', '../logs/service_errors.log');

function logError($message, $context = []) {
   $timestamp = date('Y-m-d H:i:s');
   $userId = $_SESSION['user_id'] ?? 'unknown';
   $contextStr = !empty($context) ? json_encode($context) : '';
   $logMessage = "[{$timestamp}] User: {$userId} - {$message} {$contextStr}\n";
   error_log($logMessage, 3, '../logs/service_errors.log');
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
   logError('Unauthorized access attempt', ['ip' => $_SERVER['REMOTE_ADDR']]);
   header("Location: /health_tourism/login.php");
   exit();
}

try {
   $database = new Database();
   $db = $database->getConnection();
} catch(PDOException $e) {
   logError('Database connection failed', ['error' => $e->getMessage()]);
   die('Database connection failed');
}

$action = $_POST['action'] ?? '';
$response = ['success' => false, 'message' => ''];

try {
   switch ($action) {
       case 'add':
           try {
               logError('Adding new service attempt', [
                   'post_data' => $_POST,
                   'files' => isset($_FILES) ? array_keys($_FILES) : []
               ]);

               $name = trim($_POST['name']);
               $name_en = trim($_POST['name_en']);
               $description = trim($_POST['description']);
               $description_en = trim($_POST['description_en']);
               $price = floatval($_POST['price']);
               $category_id = !empty($_POST['category_id']) ? intval($_POST['category_id']) : null;

               // JSON'dan extra dilleri çıkar
               $extra_languages = !empty($_POST['extra_languages']) ? json_decode($_POST['extra_languages'], true) : [];

               // Tüm dil değerlerini hazırla
               $name_de = $extra_languages['de']['name'] ?? null;
               $name_fr = $extra_languages['fr']['name'] ?? null;
               $name_es = $extra_languages['es']['name'] ?? null;
               $name_it = $extra_languages['it']['name'] ?? null;
               $name_ru = $extra_languages['ru']['name'] ?? null;
               $name_zh = $extra_languages['zh']['name'] ?? null;

               $description_de = $extra_languages['de']['description'] ?? null;
               $description_fr = $extra_languages['fr']['description'] ?? null;
               $description_es = $extra_languages['es']['description'] ?? null;
               $description_it = $extra_languages['it']['description'] ?? null;
               $description_ru = $extra_languages['ru']['description'] ?? null;
               $description_zh = $extra_languages['zh']['description'] ?? null;

               if (empty($name) || empty($name_en)) {
                   throw new Exception('Hizmet adı boş olamaz');
               }

               $image_url = null;
               if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                   $allowed = ['jpg', 'jpeg', 'png', 'gif'];
                   $filename = $_FILES['image']['name'];
                   $temp_name = $_FILES['image']['tmp_name'];
                   $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

                   if (!in_array($ext, $allowed)) {
                       throw new Exception('Geçersiz dosya türü');
                   }

                   $new_filename = uniqid() . '.' . $ext;
                   $upload_path = '../assets/images/services/' . $new_filename;

                   if (!move_uploaded_file($temp_name, $upload_path)) {
                       throw new Exception('Dosya yüklenirken bir hata oluştu');
                   }

                   $image_url = $new_filename;
               }

               $stmt = $db->prepare("
                   INSERT INTO services (
                       name, name_en, name_de, name_fr, name_es, name_it, name_ru, name_zh,
                       description, description_en, description_de, description_fr, 
                       description_es, description_it, description_ru, description_zh,
                       price, category_id, image_url, status
                   ) VALUES (
                       ?, ?, ?, ?, ?, ?, ?, ?,
                       ?, ?, ?, ?, ?, ?, ?, ?,
                       ?, ?, ?, 'active'
                   )
               ");

               $stmt->execute([
                   $name, $name_en, $name_de, $name_fr, 
                   $name_es, $name_it, $name_ru, $name_zh,
                   $description, $description_en, $description_de, $description_fr,
                   $description_es, $description_it, $description_ru, $description_zh,
                   $price, $category_id, $image_url
               ]);

               $response = [
                   'success' => true,
                   'message' => 'Hizmet başarıyla eklendi'
               ];
           } catch(Exception $e) {
               logError('Error adding service', [
                   'error' => $e->getMessage(),
                   'post_data' => $_POST
               ]);
               throw $e;
           }
           break;

       case 'edit':
           try {
               logError('Editing service attempt', [
                   'service_id' => $_POST['service_id'] ?? null,
                   'post_data' => $_POST
               ]);

               $id = intval($_POST['service_id']);
               $name = trim($_POST['name']);
               $name_en = trim($_POST['name_en']);
               $description = trim($_POST['description']);
               $description_en = trim($_POST['description_en']);
               $price = floatval($_POST['price']);
               $category_id = !empty($_POST['category_id']) ? intval($_POST['category_id']) : null;

               // JSON'dan extra dilleri çıkar
               $extra_languages = !empty($_POST['extra_languages']) ? json_decode($_POST['extra_languages'], true) : [];

               // Tüm dil değerlerini hazırla
               $name_de = $extra_languages['de']['name'] ?? null;
               $name_fr = $extra_languages['fr']['name'] ?? null;
               $name_es = $extra_languages['es']['name'] ?? null;
               $name_it = $extra_languages['it']['name'] ?? null;
               $name_ru = $extra_languages['ru']['name'] ?? null;
               $name_zh = $extra_languages['zh']['name'] ?? null;

               $description_de = $extra_languages['de']['description'] ?? null;
               $description_fr = $extra_languages['fr']['description'] ?? null;
               $description_es = $extra_languages['es']['description'] ?? null;
               $description_it = $extra_languages['it']['description'] ?? null;
               $description_ru = $extra_languages['ru']['description'] ?? null;
               $description_zh = $extra_languages['zh']['description'] ?? null;

               if (empty($name) || empty($name_en)) {
                   throw new Exception('Hizmet adı boş olamaz');
               }

               $stmt = $db->prepare("SELECT image_url FROM services WHERE id = ?");
               $stmt->execute([$id]);
               $current_image = $stmt->fetchColumn();

               if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                   $allowed = ['jpg', 'jpeg', 'png', 'gif'];
                   $filename = $_FILES['image']['name'];
                   $temp_name = $_FILES['image']['tmp_name'];
                   $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

                   if (!in_array($ext, $allowed)) {
                       throw new Exception('Geçersiz dosya türü');
                   }

                   $new_filename = uniqid() . '.' . $ext;
                   $upload_path = '../assets/images/services/' . $new_filename;

                   if (!move_uploaded_file($temp_name, $upload_path)) {
                       throw new Exception('Dosya yüklenirken bir hata oluştu');
                   }

                   if ($current_image && file_exists('../assets/images/services/' . $current_image)) {
                       unlink('../assets/images/services/' . $current_image);
                   }

                   $image_url = $new_filename;
               } else {
                   $image_url = $current_image;
               }

               $stmt = $db->prepare("
                   UPDATE services SET 
                       name = ?, name_en = ?, name_de = ?, name_fr = ?, 
                       name_es = ?, name_it = ?, name_ru = ?, name_zh = ?,
                       description = ?, description_en = ?, description_de = ?, description_fr = ?,
                       description_es = ?, description_it = ?, description_ru = ?, description_zh = ?,
                       price = ?, category_id = ?, image_url = ?
                   WHERE id = ?
               ");

               $stmt->execute([
                   $name, $name_en, $name_de, $name_fr, 
                   $name_es, $name_it, $name_ru, $name_zh,
                   $description, $description_en, $description_de, $description_fr,
                   $description_es, $description_it, $description_ru, $description_zh,
                   $price, $category_id, $image_url, $id
               ]);

               $response = [
                   'success' => true,
                   'message' => 'Hizmet başarıyla güncellendi'
               ];
           } catch(Exception $e) {
               logError('Error editing service', [
                   'error' => $e->getMessage(),
                   'service_id' => $_POST['service_id'] ?? null
               ]);
               throw $e;
           }
           break;

       case 'delete':
           try {
               logError('Delete service attempt', [
                   'service_id' => $_POST['service_id'] ?? null
               ]);

               $id = intval($_POST['service_id']);
           
               $stmt = $db->prepare("SELECT image_url FROM services WHERE id = ?");
               $stmt->execute([$id]);
               $image_url = $stmt->fetchColumn();
           
               if ($image_url && file_exists('../assets/images/services/' . $image_url)) {
                   unlink('../assets/images/services/' . $image_url);
               }
           
               $stmt = $db->prepare("UPDATE services SET status = 'deleted' WHERE id = ?");
               $stmt->execute([$id]);
           
               if ($stmt->rowCount() > 0) {
                   $response = [
                       'success' => true,
                       'message' => 'Hizmet başarıyla silindi'
                   ];
               } else {
                   $response = [
                       'success' => false,
                       'message' => 'Hizmet silinirken bir hata oluştu'
                   ];
               }
           } catch(Exception $e) {
               logError('Error deleting service', [
                   'error' => $e->getMessage(),
                   'service_id' => $_POST['service_id'] ?? null
               ]);
               throw $e;
           }
           break;

       case 'toggle_status':
           try {
               logError('Toggle status attempt', [
                   'service_id' => $_POST['service_id'] ?? null,
                   'new_status' => $_POST['status'] ?? null
               ]);

               $id = intval($_POST['service_id']);
               $status = $_POST['status'] === 'active' ? 'active' : 'inactive';

               $stmt = $db->prepare("UPDATE services SET status = ? WHERE id = ?");
               $stmt->execute([$status, $id]);

               $response = [
                   'success' => true,
                   'message' => $status === 'active' ? 'Hizmet aktif edildi' : 'Hizmet pasif edildi'
               ];
           } catch(Exception $e) {
               logError('Error toggling service status', [
                   'error' => $e->getMessage(),
                   'service_id' => $_POST['service_id'] ?? null
               ]);
               throw $e;
           }
           break;

       default:
           logError('Invalid action attempted', ['action' => $action]);
           throw new Exception('Geçersiz işlem');
   }
} catch(Exception $e) {
   logError('General error in service process', [
       'error' => $e->getMessage(),
       'action' => $action
   ]);
   $response = [
       'success' => false,
       'message' => $e->getMessage()
   ];
}

header('Content-Type: application/json');
echo json_encode($response);