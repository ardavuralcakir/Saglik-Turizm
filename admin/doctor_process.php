<?php
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);
error_reporting(0);

session_start();
include_once '../config/database.php';

// Dil dosyası
$lang = $_SESSION['lang'] ?? 'tr';
$translation_file = __DIR__ . "/../translations/translation_{$lang}.php";
if (file_exists($translation_file)) {
    $t = require $translation_file;
} else {
    // Dil dosyası yoksa JSON döndürüp çıkalım
    echo json_encode(['success' => false, 'message' => 'Translation file not found']);
    exit;
}

// Admin kontrolü
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => $t['unauthorized']]);
    exit;
}

// Veritabanı...
$database = new Database();
$db = $database->getConnection();

// $_POST['action'] al
$action = $_POST['action'] ?? '';
if (!$action) {
    echo json_encode(['success' => false, 'message' => $t['invalid_action']]);
    exit;
}

// 5) Resim yükleme fonksiyonu
function uploadImage($file) {
    $target_dir = "../assets/images/";
    $file_extension = strtolower(pathinfo($file["name"], PATHINFO_EXTENSION));
    $new_filename   = uniqid() . '.' . $file_extension;
    $target_file    = $target_dir . $new_filename;
    $allowed_types  = ['jpg','jpeg','png','gif'];

    if (!in_array($file_extension, $allowed_types)) {
        // Burada projedeki çeviri sistemine uygun bir Exception mesajı atın
        throw new Exception('Invalid image format');
    }

    // 2MB = 2*1024*1024 => 2097152
    if ($file["size"] > 2_000_000) {
        throw new Exception('Image size is too large');
    }

    if (!move_uploaded_file($file["tmp_name"], $target_file)) {
        throw new Exception('Error uploading file');
    }
    return $new_filename;
}

// 6) EKLE (action=add)
if ($action === 'add') {
    try {
        // Gerekli alanlar var mı
        if (empty($_POST['name'])) {
            throw new Exception($t['missing_fields']);
        }

        $db->beginTransaction();

        // Doktor ekle
        $query = "INSERT INTO doctors (name, specialty, bio_tr, bio_en, status) 
                  VALUES (:name, :specialty, :bio_tr, :bio_en, 'active')";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':name',      $_POST['name']);
        $stmt->bindParam(':specialty', $_POST['specialty']);
        $stmt->bindParam(':bio_tr',    $_POST['bio_tr']);
        $stmt->bindParam(':bio_en',    $_POST['bio_en']);
        $stmt->execute();

        // Yeni eklenen doktorun ID'si
        $doctor_id = $db->lastInsertId();

        // Seçilen servis(ler)
        $serviceIdsString = $_POST['service_ids_string'] ?? '';
        $serviceIdsArray  = array_filter(array_map('intval', explode(',', $serviceIdsString)));
        if (!empty($serviceIdsArray)) {
            $insertSrv = $db->prepare("INSERT INTO doctor_services (doctor_id, service_id) VALUES (:doctor_id, :service_id)");
            foreach ($serviceIdsArray as $sid) {
                $insertSrv->execute([
                    ':doctor_id' => $doctor_id,
                    ':service_id'=> $sid
                ]);
            }
        }

        // Resim varsa yükle
        if (isset($_FILES['image']) && $_FILES['image']['size'] > 0) {
            $image_filename = uploadImage($_FILES['image']);
            $updateImageQuery = "UPDATE doctors SET image_url = :image_url WHERE id = :doctor_id";
            $updateImageStmt  = $db->prepare($updateImageQuery);
            $updateImageStmt->bindParam(':image_url',  $image_filename);
            $updateImageStmt->bindParam(':doctor_id',  $doctor_id);
            $updateImageStmt->execute();
        }

        $db->commit();
        echo json_encode(['success' => true, 'message' => $t['add_success']]);
    } catch (Exception $e) {
        $db->rollBack();
        echo json_encode(['success' => false, 'message' => $t['add_error'] . ': ' . $e->getMessage()]);
    }
    exit;
}

// 7) GÜNCELLE (action=edit)
if ($action === 'edit') {
    try {
        if (empty($_POST['doctor_id']) || empty($_POST['name'])) {
            throw new Exception($t['missing_fields']);
        }
        $doctorId = (int)$_POST['doctor_id'];

        $db->beginTransaction();
        // Doktor güncelle
        $query = "
            UPDATE doctors
               SET name      = :name,
                   specialty = :specialty,
                   bio_tr    = :bio_tr,
                   bio_en    = :bio_en
             WHERE id        = :doctor_id
        ";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':name',      $_POST['name']);
        $stmt->bindParam(':specialty', $_POST['specialty']);
        $stmt->bindParam(':bio_tr',    $_POST['bio_tr']);
        $stmt->bindParam(':bio_en',    $_POST['bio_en']);
        $stmt->bindParam(':doctor_id', $doctorId);
        $stmt->execute();

        // Doktorun servislerini sıfırla & tekrar ekle
        $serviceIdsString = $_POST['service_ids_string'] ?? '';
        $serviceIdsArray  = array_filter(array_map('intval', explode(',', $serviceIdsString)));

        $deleteServicesQuery = "DELETE FROM doctor_services WHERE doctor_id = :doctor_id";
        $delSrvStmt = $db->prepare($deleteServicesQuery);
        $delSrvStmt->bindParam(':doctor_id', $doctorId);
        $delSrvStmt->execute();

        if (!empty($serviceIdsArray)) {
            $insertSrv = $db->prepare("INSERT INTO doctor_services (doctor_id, service_id) VALUES (:doctor_id, :service_id)");
            foreach ($serviceIdsArray as $sid) {
                $insertSrv->execute([
                    ':doctor_id' => $doctorId,
                    ':service_id'=> $sid
                ]);
            }
        }

        // Resim güncelle
        if (isset($_FILES['image']) && $_FILES['image']['size'] > 0) {
            $image_filename = uploadImage($_FILES['image']);

            // Eski resmi silelim
            $getOldImageQuery = "SELECT image_url FROM doctors WHERE id = :doctor_id";
            $getOldImageStmt  = $db->prepare($getOldImageQuery);
            $getOldImageStmt->bindParam(':doctor_id', $doctorId);
            $getOldImageStmt->execute();
            $oldImage = $getOldImageStmt->fetch(PDO::FETCH_ASSOC);

            if ($oldImage && $oldImage['image_url']) {
                $oldImagePath = "../assets/images/" . $oldImage['image_url'];
                if (file_exists($oldImagePath)) {
                    unlink($oldImagePath);
                }
            }
            // Yeni resmi kaydet
            $updateImageQuery = "UPDATE doctors SET image_url = :image_url WHERE id = :doctor_id";
            $updateImageStmt  = $db->prepare($updateImageQuery);
            $updateImageStmt->bindParam(':image_url',  $image_filename);
            $updateImageStmt->bindParam(':doctor_id',  $doctorId);
            $updateImageStmt->execute();
        }

        $db->commit();
        echo json_encode(['success' => true, 'message' => $t['update_success']]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $t['update_error'] . ': ' . $e->getMessage()]);
    }
    exit;
}

// 8) SİL (action=delete)
if ($action === 'delete') {
    try {
        if (empty($_POST['doctor_id'])) {
            throw new Exception($t['missing_fields']);
        }
        $doctor_id = (int)$_POST['doctor_id'];

        // Aktif sipariş var mı kontrolü
        $orderCheckQuery = "
            SELECT COUNT(*) as order_count
              FROM orders
             WHERE doctor_id = :doctor_id
               AND status != 'completed'
        ";
        $orderCheckStmt = $db->prepare($orderCheckQuery);
        $orderCheckStmt->bindParam(':doctor_id', $doctor_id);
        $orderCheckStmt->execute();
        $orderCount = $orderCheckStmt->fetch(PDO::FETCH_ASSOC)['order_count'];
        if ($orderCount > 0) {
            throw new Exception($t['active_orders_exist']);
        }

        $db->beginTransaction();

        // Önce doctor_services tablosundan temizle
        $deleteServicesQuery = "DELETE FROM doctor_services WHERE doctor_id = :doctor_id";
        $deleteServicesStmt  = $db->prepare($deleteServicesQuery);
        $deleteServicesStmt->bindParam(':doctor_id', $doctor_id);
        $deleteServicesStmt->execute();

        // Doktor kaydını sil
        $deleteQuery = "DELETE FROM doctors WHERE id = :doctor_id";
        $deleteStmt  = $db->prepare($deleteQuery);
        $deleteStmt->bindParam(':doctor_id', $doctor_id);
        $deleteStmt->execute();

        $db->commit();
        echo json_encode(['success' => true, 'message' => $t['delete_success']]);
    } catch (Exception $e) {
        $db->rollBack();
        echo json_encode(['success' => false, 'message' => $t['delete_error'] . ': ' . $e->getMessage()]);
    }
    exit;
}

// 9) DURUM GÜNCELLEME (action=toggle_status)
if ($action === 'toggle_status') {
    try {
        if (empty($_POST['doctor_id']) || !isset($_POST['status'])) {
            throw new Exception($t['missing_fields']);
        }
        $doctor_id  = (int)$_POST['doctor_id'];
        $new_status = trim($_POST['status']);

        $query = "UPDATE doctors SET status = :new_status WHERE id = :doc_id";
        $stmt  = $db->prepare($query);
        $stmt->bindParam(':new_status', $new_status);
        $stmt->bindParam(':doc_id', $doctor_id);
        $stmt->execute();

        echo json_encode(['success' => true, 'message' => $t['status_update_success']]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $t['status_update_error'] . ': ' . $e->getMessage()]);
    }
    exit;
}

// 10) Geçersiz action
echo json_encode(['success' => false, 'message' => $t['invalid_action']]);
exit;
