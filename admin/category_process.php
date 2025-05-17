<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

include_once '../config/database.php';

$lang = $_SESSION['lang'] ?? 'tr';
$translations_file = dirname(__DIR__) . "/translations/translation_{$lang}.php";

if (!file_exists($translations_file)) {
    $lang = 'tr';
    $translations_file = dirname(__DIR__) . "/translations/translation_tr.php";
    if (!file_exists($translations_file)) {
        die("Translation file not found (fallback failed): {$translations_file}");
    }
}

$t = require $translations_file;

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die(json_encode([
        'success' => false, 
        'message' => $t['unauthorized_access']
    ]));
}

$database = new Database();
$db = $database->getConnection();

$action = $_POST['action'] ?? '';

if ($action === 'add') {
    $name = trim($_POST['name_tr'] ?? '');
    $name_en = trim($_POST['name_en'] ?? '');
    $extraLanguages = json_decode($_POST['extra_languages'] ?? '{}', true);

    if ($name === '' || $name_en === '') {
        echo json_encode([
            'success' => false,
            'message' => 'Kategori adı boş olamaz'
        ]);
        exit;
    }

    $checkQuery = "SELECT COUNT(*) FROM service_categories WHERE name = :name OR name_en = :name_en";
    $checkStmt = $db->prepare($checkQuery);
    $checkStmt->bindParam(':name', $name);
    $checkStmt->bindParam(':name_en', $name_en);
    $checkStmt->execute();
    
    if ($checkStmt->fetchColumn() > 0) {
        echo json_encode([
            'success' => false,
            'message' => $t['duplicate_category']
        ]);
        exit;
    }

    try {
        $db->beginTransaction();
        $query = "INSERT INTO service_categories (
            name, name_en, name_de, name_fr, name_es, 
            name_it, name_ru, name_zh
        ) VALUES (
            :name, :name_en, :name_de, :name_fr, :name_es, 
            :name_it, :name_ru, :name_zh
        )";
        $stmt = $db->prepare($query);
        
        // Ana diller için bindValue kullan
        $stmt->bindValue(':name', $name);
        $stmt->bindValue(':name_en', $name_en);
        
        // Diğer diller için bindValue ile değerleri bağla
        $languages = ['de', 'fr', 'es', 'it', 'ru', 'zh'];
        foreach ($languages as $lang) {
            $value = isset($extraLanguages[$lang]) ? $extraLanguages[$lang]['name'] : null;
            $stmt->bindValue(":name_{$lang}", $value); // bindParam yerine bindValue kullan
        }
     
        if ($stmt->execute()) {
            $db->commit();
            echo json_encode([
                'success' => true,
                'message' => $t['category_added_success']
            ]);
        } else {
            $db->rollBack();
            echo json_encode([
                'success' => false,
                'message' => $t['error_adding_category']
            ]);
        }
    } catch (PDOException $e) {
        $db->rollBack();
        echo json_encode([
            'success' => false,
            'message' => $t['db_error'] . $e->getMessage()
        ]);
    }

} elseif ($action === 'delete') {
    if (!isset($_POST['category_id'])) {
        die(json_encode([
            'success' => false,
            'message' => $t['category_id_needed']
        ]));
    }

    $category_id = $_POST['category_id'];

    $checkQuery = "SELECT COUNT(*) FROM services WHERE category_id = :category_id";
    $checkStmt = $db->prepare($checkQuery);
    $checkStmt->bindParam(':category_id', $category_id);
    $checkStmt->execute();

    if ($checkStmt->fetchColumn() > 0) {
        echo json_encode([
            'success' => false,
            'message' => $t['category_attached_services']
        ]);
        exit;
    }

    try {
        $stmt = $db->prepare("DELETE FROM service_categories WHERE id = :category_id");
        $stmt->bindParam(':category_id', $category_id);

        if ($stmt->execute()) {
            echo json_encode([
                'success' => true,
                'message' => $t['category_delete_success']
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => $t['category_delete_error']
            ]);
        }
    } catch (PDOException $e) {
        echo json_encode([
            'success' => false,
            'message' => $t['db_error'] . $e->getMessage()
        ]);
    }

} else {
    echo json_encode([
        'success' => false,
        'message' => $t['invalid_request']
    ]);
}