<?php
declare(strict_types=1);

require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/inc/auth.php';

require_login();

// Fetch search and filter values from GET
$search = trim((string)($_GET['q'] ?? ''));
$filter_room = trim((string)($_GET['room'] ?? ''));
$filter_status = trim((string)($_GET['status'] ?? ''));
$filter_asset = trim((string)($_GET['asset'] ?? ''));
$filter_model = trim((string)($_GET['model'] ?? ''));
$filter_sn = trim((string)($_GET['sn'] ?? ''));
$filter_user = trim((string)($_GET['user'] ?? ''));

$sql = "
    SELECT d.*, u.first_name, u.last_name, u.username as u_login, dt.name as type_name
    FROM device d 
    LEFT JOIN users u ON d.user_id = u.id
    LEFT JOIN device_type dt ON d.type_id = dt.id
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
if ($filter_asset !== '') {
    $where[] = "d.asset_number LIKE :asset";
    $params[':asset'] = '%' . $filter_asset . '%';
}
if ($filter_model !== '') {
    $where[] = "d.model LIKE :model";
    $params[':model'] = '%' . $filter_model . '%';
}
if ($filter_sn !== '') {
    $where[] = "d.serial_number LIKE :sn";
    $params[':sn'] = '%' . $filter_sn . '%';
}
if ($filter_user !== '') {
    if ($filter_user === '0') {
        $where[] = "d.user_id IS NULL";
    } else {
        $where[] = "d.user_id = :user";
        $params[':user'] = $filter_user;
    }
}

if (!empty($where)) {
    $sql .= " WHERE " . implode(" AND ", $where);
}
$sql .= " ORDER BY d.created_at DESC";

try {
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die('Błąd podczas pobierania danych do eksportu: ' . htmlspecialchars($e->getMessage()));
}

// 1. Fetch app_fields for dynamic columns
$app_fields = db()->query("SELECT * FROM app_fields ORDER BY order_form ASC")->fetchAll(PDO::FETCH_ASSOC);

// 2. Fetch custom field values for all devices
$device_ids = array_column($items, 'id');
$custom_fields_by_device = [];
if (!empty($device_ids)) {
    $placeholders = str_repeat('?,', count($device_ids) - 1) . '?';
    $cf_stmt = db()->prepare("
        SELECT dfv.device_id, df.label as name, dfv.value, df.field_type
        FROM device_field_value dfv
        JOIN app_fields df ON dfv.field_id = df.id
        WHERE dfv.device_id IN ($placeholders)
    ");
    $cf_stmt->execute($device_ids);
    foreach ($cf_stmt->fetchAll(PDO::FETCH_ASSOC) as $cf) {
        if ($cf['value'] !== '' && $cf['value'] !== null) {
            if ($cf['field_type'] === 'checkbox') {
                $cf['value'] = ($cf['value'] === '1' || $cf['value'] === 'Tak') ? 'Tak' : 'Nie';
            }
            $custom_fields_by_device[$cf['device_id']][$cf['name']] = $cf['value'];
        }
    }
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

// Build Header row from app_fields
$header_row = [];
foreach ($app_fields as $f) {
    $header_row[] = $f['label'];
}
$header_row[] = 'Data utworzenia';
fputcsv($output, $header_row, ';'); // Semicolon for European Excel

// Build Data rows
foreach ($items as $item) {
    $row = [];
    $custom = $custom_fields_by_device[$item['id']] ?? [];
    
    foreach ($app_fields as $f) {
        $val = '';
        if ($f['source_type'] === 'native') {
            $k = $f['field_key'];
            if ($k === 'user_id') {
                $val = $item['user_id'] ? ($item['first_name'] . ' ' . $item['last_name'] . ' (' . $item['u_login'] . ')') : '';
            } elseif ($k === 'type_id') {
                $val = $item['type_name'] ?? '';
            } else {
                $val = $item[$k] ?? '';
            }
        } else {
            // Check if this custom field belongs to this device type
            if ($f['type_id'] == $item['type_id'] || empty($f['type_id'])) {
                $val = $custom[$f['label']] ?? '';
            }
        }
        $row[] = $val;
    }
    $row[] = $item['created_at'] ?? '';
    fputcsv($output, $row, ';');
}

fclose($output);
exit;
