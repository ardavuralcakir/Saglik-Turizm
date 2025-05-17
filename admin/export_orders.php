<?php
session_start();
include_once '../config/database.php';
require '../vendor/autoload.php';

// Dil dosyasını yükle
$lang = $_SESSION['lang'] ?? 'tr';
$translation_file = __DIR__ . "/../translations/translation_{$lang}.php";
if (file_exists($translation_file)) {
    $t = require $translation_file;
} else {
    die("Translation file not found: {$translation_file}");
}

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Writer\Csv;

// Admin kontrolü
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: /health_tourism/login.php");
    exit();
}

// Eğer format parametresi varsa, dosya indirme işlemini başlat
if (isset($_GET['format'])) {
$database = new Database();
$db = $database->getConnection();

// Filtreleme parametreleri
$service_id = isset($_GET['service']) ? $_GET['service'] : '';
$date = isset($_GET['date']) ? $_GET['date'] : '';
$status = isset($_GET['status']) ? $_GET['status'] : '';
    $format = $_GET['format'];
    
    // Dosya adını al
    $filename = isset($_GET['filename']) && !empty($_GET['filename']) ? $_GET['filename'] : 'siparisler';
    
    // Dosya adında sadece alfanümerik karakterler, tire ve alt çizgi olsun
    $filename = preg_replace('/[^a-zA-Z0-9-_]/', '', $filename);
    
    // Eğer dosya adı boşsa varsayılan ismi kullan
    if (empty($filename)) {
        $filename = 'siparisler';
    }

// Siparişleri sorgula
$query = "SELECT o.*, u.username, u.full_name, s.name as service_name, d.name as doctor_name
          FROM orders o
          JOIN users u ON o.user_id = u.id
          JOIN services s ON o.service_id = s.id
          LEFT JOIN doctors d ON o.doctor_id = d.id
          WHERE 1=1";

$params = [];

if ($service_id) {
    $query .= " AND o.service_id = ?";
    $params[] = $service_id;
}

if ($date) {
    $query .= " AND DATE(o.created_at) = ?";
    $params[] = $date;
}

if ($status) {
    $query .= " AND o.status = ?";
    $params[] = $status;
}

$query .= " ORDER BY o.created_at DESC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Başlıklar
    $headers = [
        $t['order_id'],
        $t['customer'],
        $t['username'],
        $t['service'],
        $t['doctor'],
        $t['date'],
        $t['amount'],
        $t['status']
    ];

    // Spreadsheet oluştur
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    // Başlıkları ekle
    $column = 'A';
    foreach ($headers as $header) {
        $sheet->setCellValue($column . '1', $header);
        $column++;
    }

    // Verileri ekle
    $row = 2;
    foreach ($orders as $order) {
        $sheet->setCellValue('A' . $row, $order['id']);
        $sheet->setCellValue('B' . $row, $order['full_name']);
        $sheet->setCellValue('C' . $row, $order['username']);
        $sheet->setCellValue('D' . $row, $order['service_name']);
        $sheet->setCellValue('E' . $row, $order['doctor_name']);
        $sheet->setCellValue('F' . $row, date('d.m.Y H:i', strtotime($order['created_at'])));
        $sheet->setCellValue('G' . $row, $order['total_amount']);
        $sheet->setCellValue('H' . $row, $order['status']);
        $row++;
    }

    // Sütun genişliklerini otomatik ayarla
    foreach (range('A', 'H') as $column) {
        $sheet->getColumnDimension($column)->setAutoSize(true);
    }

    // Dosya uzantısını ekle
    $full_filename = $filename . '.' . $format;

    if ($format === 'xlsx') {
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $full_filename . '"');
        header('Cache-Control: max-age=0');
        
        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
    } elseif ($format === 'csv') {
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $full_filename . '"');
        
        $writer = new Csv($spreadsheet);
        $writer->setDelimiter(',');
        $writer->setEnclosure('"');
        $writer->setLineEnding("\r\n");
        $writer->setSheetIndex(0);
        $writer->save('php://output');
    } elseif ($format === 'txt') {
        header('Content-Type: text/plain; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $full_filename . '"');
        
        $output = "";
        // Başlıkları ekle
        $output .= implode("\t", $headers) . "\n";
        
        // Verileri ekle
foreach ($orders as $order) {
            $output .= $order['id'] . "\t";
            $output .= $order['full_name'] . "\t";
            $output .= $order['username'] . "\t";
            $output .= $order['service_name'] . "\t";
            $output .= $order['doctor_name'] . "\t";
            $output .= date('d.m.Y H:i', strtotime($order['created_at'])) . "\t";
            $output .= $order['total_amount'] . "\t";
            $output .= $order['status'] . "\n";
        }
        
        echo $output;
    }
    exit();
}
?>

<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">
<head>
    <meta charset="UTF-8">
    <title><?php echo $t['download_orders']; ?></title>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>

<script>
function exportOrders() {
    Swal.fire({
        title: '<?php echo $t['export_title']; ?>',
        html: `
            <div class="space-y-4">
                <p class="text-gray-600 mb-4"><?php echo $t['export_subtitle']; ?></p>
                
                <!-- Dosya Adı Input -->
                <div class="text-left">
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        <?php echo $t['export_filename_label']; ?>
                    </label>
                    <input type="text" id="filename" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm 
                        focus:ring-blue-500 focus:border-blue-500" 
                        value="<?php echo $t['export_filename_placeholder']; ?>" 
                        placeholder="<?php echo $t['export_filename_placeholder']; ?>">
                </div>

                <!-- Format Seçimi -->
                <div class="text-left mt-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        <?php echo $t['export_format_label']; ?>
                    </label>
                    <div class="grid grid-cols-3 gap-3">
                        <button type="button" onclick="selectFormat('xlsx')" 
                                class="format-btn px-4 py-2 border border-gray-300 rounded-md hover:bg-blue-50 
                                hover:border-blue-500 transition-colors duration-200">
                            <i class="fas fa-file-excel text-green-600 mb-1"></i>
                            <span class="block text-sm"><?php echo $t['export_excel']; ?></span>
                        </button>
                        <button type="button" onclick="selectFormat('csv')" 
                                class="format-btn px-4 py-2 border border-gray-300 rounded-md hover:bg-blue-50 
                                hover:border-blue-500 transition-colors duration-200">
                            <i class="fas fa-file-csv text-blue-600 mb-1"></i>
                            <span class="block text-sm"><?php echo $t['export_csv']; ?></span>
                        </button>
                        <button type="button" onclick="selectFormat('txt')" 
                                class="format-btn px-4 py-2 border border-gray-300 rounded-md hover:bg-blue-50 
                                hover:border-blue-500 transition-colors duration-200">
                            <i class="fas fa-file-alt text-gray-600 mb-1"></i>
                            <span class="block text-sm"><?php echo $t['export_txt']; ?></span>
                        </button>
                    </div>
                </div>
            </div>
        `,
        showCancelButton: true,
        confirmButtonText: '<?php echo $t['export_download']; ?>',
        cancelButtonText: '<?php echo $t['export_cancel']; ?>',
        customClass: {
            container: 'export-modal',
            popup: 'rounded-xl shadow-2xl',
            header: 'border-b pb-4',
            title: 'text-xl font-semibold text-gray-800',
            content: 'pt-4',
            confirmButton: 'bg-blue-600 hover:bg-blue-700 text-white font-medium px-6 py-2 rounded-lg transition-colors duration-200',
            cancelButton: 'bg-gray-100 hover:bg-gray-200 text-gray-800 font-medium px-6 py-2 rounded-lg transition-colors duration-200'
        },
        didOpen: () => {
            // Font Awesome ikonlarını ekle
            const link = document.createElement('link');
            link.href = 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css';
            link.rel = 'stylesheet';
            document.head.appendChild(link);

            // Seçili format için stil
            const buttons = document.querySelectorAll('.format-btn');
            buttons.forEach(btn => {
                btn.addEventListener('click', function() {
                    buttons.forEach(b => b.classList.remove('selected-format'));
                    this.classList.add('selected-format');
                });
            });
        }
    }).then((result) => {
        if (result.isConfirmed) {
            const filename = document.getElementById('filename').value || '<?php echo $t['export_filename_placeholder']; ?>';
            const selectedBtn = document.querySelector('.format-btn.selected-format');
            const format = selectedBtn ? selectedBtn.getAttribute('data-format') : 'xlsx';

            // Mevcut filtreleri al
            const date = document.getElementById('date').value;
            const status = document.getElementById('status').value;
            const service = document.getElementById('service').value;

            // URL oluştur
            let url = `export_orders.php?format=${format}&filename=${encodeURIComponent(filename)}`;
            if (date) url += `&date=${date}`;
            if (status) url += `&status=${status}`;
            if (service) url += `&service=${service}`;

            // Dosyayı indir
            window.location.href = url;
        }
    });
}

function exportAsTxt() {
    window.location.href = 'export_orders.php?format=txt';
    Swal.close();
}
</script>

<button onclick="exportOrders()" class="export-btn"><?php echo $t['download_orders']; ?></button>

<style>
.export-btn {
    padding: 10px 20px;
    background-color: #4CAF50;
    color: white;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 16px;
    margin: 20px;
}

.export-btn:hover {
    background-color: #45a049;
}

/* SweetAlert2 özelleştirmeleri */
.swal2-styled {
    margin: 5px;
    padding: 10px 20px;
}
</style>

</body>
</html>