<?php
declare(strict_types=1);

require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/inc/auth.php';

require_login();

// Fetch search and filter values from GET (just like in index.php)
$search = trim((string)($_GET['q'] ?? ''));
$filter_room = trim((string)($_GET['room'] ?? ''));
$filter_type = trim((string)($_GET['type'] ?? ''));
$filter_manufacturer = trim((string)($_GET['manufacturer'] ?? ''));

$sql = "
    SELECT i.*, m.name AS manufacturer_name, d.name AS device_type_name, u.username AS creator_name 
    FROM inventory i 
    LEFT JOIN manufacturers m ON i.manufacturer_id = m.id 
    LEFT JOIN device_types d ON i.device_type_id = d.id 
    JOIN users u ON i.created_by = u.id
";

$where = [];
$params = [];

if ($search !== '') {
    $where[] = "(i.serial_number LIKE :search OR i.inventory_number LIKE :search OR i.room_number LIKE :search OR i.notes LIKE :search OR i.model LIKE :search)";
    $params[':search'] = '%' . $search . '%';
}
if ($filter_room !== '') {
    $where[] = "i.room_number = :room";
    $params[':room'] = $filter_room;
}
if ($filter_type !== '') {
    $where[] = "i.device_type_id = :type";
    $params[':type'] = (int)$filter_type;
}
if ($filter_manufacturer !== '') {
    $where[] = "i.manufacturer_id = :manufacturer";
    $params[':manufacturer'] = (int)$filter_manufacturer;
}

if (!empty($where)) {
    $sql .= " WHERE " . implode(" AND ", $where);
}

$sql .= " ORDER BY LENGTH(i.room_number) ASC, i.room_number ASC, i.created_at DESC";

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
    'ID',
    'Numer pokoju',
    'Typ urządzenia',
    'Producent',
    'Model',
    'Numer seryjny',
    'Numer inwentarzowy',
    'Uwagi',
    'Dodane przez',
    'Data utworzenia'
], ';'); // Semicolon is standard for Polish/European regional settings in Excel

// Data rows
foreach ($items as $item) {
    fputcsv($output, [
        $item['id'],
        $item['room_number'],
        $item['device_type_name'] ?? 'Nieokreślony',
        $item['manufacturer_name'] ?? 'Nieokreślony',
        $item['model'] ?? '',
        $item['serial_number'],
        $item['inventory_number'],
        $item['notes'] ?? '',
        $item['creator_name'],
        $item['created_at']
    ], ';');
}

fclose($output);
exit;
