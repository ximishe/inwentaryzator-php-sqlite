<?php
declare(strict_types=1);

require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/inc/auth.php';

require_login();

// Fetch search and filter values from GET (just like in index.php)
$search = trim((string)($_GET['q'] ?? ''));
$filter_room = trim((string)($_GET['room'] ?? ''));
$filter_status = trim((string)($_GET['status'] ?? ''));

$sql = "
    SELECT d.*, u.first_name, u.last_name, u.username 
    FROM device d 
    LEFT JOIN users u ON d.user_id = u.id
";

$where = [];
$params = [];

if ($search !== '') {
    $where[] = "(d.serial_number LIKE :search OR d.asset_number LIKE :search OR d.room LIKE :search OR d.model LIKE :search OR d.manufacturer LIKE :search)";
    $params[':search'] = '%' . $search . '%';
}
if ($filter_room !== '') {
    $where[] = "d.room = :room";
    $params[':room'] = $filter_room;
}
if ($filter_status !== '') {
    $where[] = "d.status = :status";
    $params[':status'] = $filter_status;
}

if (!empty($where)) {
    $sql .= " WHERE " . implode(" AND ", $where);
}

$sql .= " ORDER BY d.created_at DESC";

try {
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $items = $stmt->fetchAll();
} catch (PDOException $e) {
    die('Błąd podczas pobierania danych do eksportu: ' . htmlspecialchars($e->getMessage()));
}

// Generate CSV
$filename = 'inwentaryzacja_' . date('Ymd_His') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

$output = fopen('php://output', 'w');

// Add UTF-8 BOM for Excel compatibility
fwrite($output, "\xEF\xBB\xBF");

// Header row
fputcsv($output, [
    'Numer inwentarzowy',
    'Producent',
    'Model',
    'Numer seryjny',
    'Data zakupu',
    'Cena zakupu',
    'Nr faktury',
    'Gwarancja do',
    'Przypisany pracownik',
    'Pokój / Lokalizacja',
    'Status',
    'Opis / Uwagi',
    'Data utworzenia'
], ';'); // Semicolon is standard for Polish/European regional settings in Excel

// Data rows
foreach ($items as $item) {
    fputcsv($output, [
        $item['asset_number'],
        $item['manufacturer'] ?? '',
        $item['model'] ?? '',
        $item['serial_number'] ?? '',
        $item['purchase_date'] ?? '',
        $item['purchase_price'] ?? '',
        $item['invoice'] ?? '',
        $item['warranty_until'] ?? '',
        $item['user_id'] ? ($item['first_name'] . ' ' . $item['last_name'] . ' (' . $item['username'] . ')') : '',
        $item['room'] ?? '',
        $item['status'] ?? '',
        $item['description'] ?? '',
        $item['created_at']
    ], ';');
}

fclose($output);
exit;
