<?php
namespace App\Controllers;

use App\Models\Model;
use PDO;
use Throwable;

class DashboardController extends Controller {
    public static function generateAssetNumber(\PDO $pdo): string {
        $next_id = (int)$pdo->query("SELECT COALESCE(MAX(id), 0) + 1 FROM device")->fetchColumn();
        for ($i = 0; $i < 200; $i++) {
            $candidate = sprintf('IT-%06d', $next_id + $i);
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM device WHERE asset_number = ?");
            $stmt->execute([$candidate]);
            if ((int)$stmt->fetchColumn() === 0) {
                return $candidate;
            }
        }
        return 'IT-' . date('ym') . '-' . strtoupper(substr(uniqid(), -4));
    }

    public function index() {
        require_once __DIR__ . '/../../inc/auth.php';
        require_once __DIR__ . '/../../inc/view_renderer.php';
        require_login();

        $model = new Model();
        $pdo = $model->db;
        
        $user_id = get_logged_user_id();
        $user_role = $_SESSION['role'] ?? 'user';
        $username = get_logged_username();

$err = '';
$ok = '';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');

    // Add new device
    if ($action === 'create_device') {
        $native = $_POST['native'] ?? [];
        $asset_number = trim((string)($_POST['asset_number'] ?? $native['asset_number'] ?? ''));
        $manufacturer = trim((string)($_POST['manufacturer'] ?? $native['manufacturer'] ?? ''));
        $model_name = trim((string)($_POST['model'] ?? $native['model'] ?? ''));
        $serial_number = trim((string)($_POST['serial_number'] ?? $native['serial_number'] ?? ''));
        $purchase_date = trim((string)($_POST['purchase_date'] ?? $native['purchase_date'] ?? ''));
        $purchase_price = (float)($_POST['purchase_price'] ?? $native['purchase_price'] ?? 0);
        $invoice = trim((string)($_POST['invoice'] ?? $native['invoice'] ?? ''));
        $warranty_until = trim((string)($_POST['warranty_until'] ?? $native['warranty_until'] ?? ''));
        $assigned_user_id = (int)($_POST['assigned_user_id'] ?? $_POST['user_id'] ?? $native['user_id'] ?? 0);
        $room = trim((string)($_POST['room'] ?? $native['room'] ?? ''));
        $status = trim((string)($_POST['status'] ?? $native['status'] ?? 'ACTIVE'));
        $description = trim((string)($_POST['description'] ?? $native['description'] ?? ''));
        $type_id = (int)($_POST['type_id'] ?? $native['type_id'] ?? 0);

        // Auto-generate unique incremental asset number if left blank
        if ($asset_number === '') {
            $asset_number = self::generateAssetNumber($pdo);
        }

        try {
            $pdo->beginTransaction();

            // Insert device
            $stmt = $pdo->prepare("
                INSERT INTO device (asset_number, manufacturer, model, serial_number, purchase_date, purchase_price, invoice, warranty_until, user_id, room, status, description, type_id)
                VALUES (:asset_number, :manufacturer, :model, :serial_number, :purchase_date, :purchase_price, :invoice, :warranty_until, :user_id, :room, :status, :description, :type_id)
            ");
            $stmt->execute([
                ':asset_number' => $asset_number,
                ':manufacturer' => $manufacturer !== '' ? $manufacturer : null,
                ':model' => $model_name !== '' ? $model_name : null,
                ':serial_number' => $serial_number !== '' ? $serial_number : null,
                ':purchase_date' => $purchase_date !== '' ? $purchase_date : null,
                ':purchase_price' => $purchase_price > 0 ? $purchase_price : null,
                ':invoice' => $invoice !== '' ? $invoice : null,
                ':warranty_until' => $warranty_until !== '' ? $warranty_until : null,
                ':user_id' => $assigned_user_id > 0 ? $assigned_user_id : null,
                ':room' => $room !== '' ? $room : null,
                ':status' => $status,
                ':description' => $description !== '' ? $description : null,
                ':type_id' => $type_id > 0 ? $type_id : null,
            ]);

            $new_device_id = (int)$pdo->lastInsertId();

            // Add history record: CREATE
            $hist_stmt = $pdo->prepare("
                INSERT INTO device_history (device_id, event_type, new_user_id, new_room, new_status, description, changed_by)
                VALUES (:device_id, 'CREATE', :new_user_id, :new_room, :new_status, 'Utworzono urządzenie w systemie.', :changed_by)
            ");
            $hist_stmt->execute([
                ':device_id' => $new_device_id,
                ':new_user_id' => $assigned_user_id > 0 ? $assigned_user_id : null,
                ':new_room' => $room !== '' ? $room : null,
                ':new_status' => $status,
                ':changed_by' => $username
            ]);

            $pdo->commit();
            $ok = 'Urządzenie zostało pomyślnie dodane! Przypisany numer inwentarzowy: ' . htmlspecialchars($asset_number);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $err = 'Błąd zapisu: ' . $e->getMessage();
        }
    } elseif ($action === 'move_room') {
        $device_id = (int)($_POST['device_id'] ?? 0);
        $new_room = trim((string)($_POST['new_room'] ?? ''));

        if ($device_id > 0 && $new_room !== '') {
            try {
                $stmt = $pdo->prepare("SELECT asset_number, room FROM device WHERE id = ?");
                $stmt->execute([$device_id]);
                $dev = $stmt->fetch();

                if ($dev) {
                    $old_room = (string)($dev['room'] ?? '');
                    
                    $pdo->beginTransaction();

                    $upd = $pdo->prepare("UPDATE device SET room = :room, updated_at = CURRENT_TIMESTAMP WHERE id = :id");
                    $upd->execute([
                        ':room' => $new_room,
                        ':id' => $device_id
                    ]);

                    // Add history record
                    $hist = $pdo->prepare("
                        INSERT INTO device_history (device_id, event_type, field_name, old_value, new_value, old_room, new_room, description, changed_by)
                        VALUES (:device_id, 'UPDATE', 'Pokój', :old_value, :new_value, :old_room, :new_room, :description, :changed_by)
                    ");
                    $hist->execute([
                        ':device_id' => $device_id,
                        ':old_value' => $old_room !== '' ? $old_room : 'Brak',
                        ':new_value' => $new_room,
                        ':old_room' => $old_room !== '' ? $old_room : null,
                        ':new_room' => $new_room,
                        ':description' => "Weryfikacja: przeniesiono sprzęt do {$new_room}",
                        ':changed_by' => $username
                    ]);

                    $pdo->commit();
                    $ok = "Sprzęt " . htmlspecialchars($dev['asset_number']) . " został pomyślnie przeniesiony do: " . htmlspecialchars($new_room) . ".";
                } else {
                    $err = "Nie znaleziono urządzenia o podanym ID.";
                }
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $err = "Błąd przenoszenia: " . $e->getMessage();
            }
        } else {
            $err = "Wymagane jest wskazanie urządzenia oraz nowego miejsca / pokoju.";
        }
    } elseif ($action === 'verify_device') {
        $device_id = (int)($_POST['device_id'] ?? 0);
        $is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest' 
                   || (isset($_POST['ajax']) && $_POST['ajax'] == '1');

        if ($device_id > 0) {
            try {
                $stmt = $pdo->prepare("SELECT asset_number, room, manufacturer, model FROM device WHERE id = ?");
                $stmt->execute([$device_id]);
                $dev = $stmt->fetch();

                if ($dev) {
                    $now = date('Y-m-d H:i:s');
                    $pdo->beginTransaction();

                    $upd = $pdo->prepare("UPDATE device SET updated_at = :now WHERE id = :id");
                    $upd->execute([':now' => $now, ':id' => $device_id]);

                    // Add history record: VERIFICATION
                    $hist = $pdo->prepare("
                        INSERT INTO device_history (device_id, event_type, date, description, changed_by)
                        VALUES (:device_id, 'VERIFICATION', :date, :description, :changed_by)
                    ");
                    $hist->execute([
                        ':device_id' => $device_id,
                        ':date' => $now,
                        ':description' => "Weryfikacja inwentaryzacyjna (Inv): odhaczono sprzęt w pokoju: " . ($dev['room'] ?: 'Brak'),
                        ':changed_by' => $username
                    ]);

                    $pdo->commit();

                    if ($is_ajax) {
                        header('Content-Type: application/json');
                        echo json_encode([
                            'success' => true,
                            'device_id' => $device_id,
                            'asset_number' => $dev['asset_number'],
                            'time_display' => 'Dzisiaj ' . date('H:i', strtotime($now)),
                            'message' => "Odhaczono sprzęt {$dev['asset_number']} ({$dev['manufacturer']} {$dev['model']})"
                        ]);
                        exit;
                    }

                    $ok = "Odhaczono sprzęt " . htmlspecialchars($dev['asset_number']) . " jako zweryfikowany (" . date('H:i') . ").";
                } else {
                    if ($is_ajax) {
                        http_response_code(404);
                        echo json_encode(['success' => false, 'error' => 'Nie znaleziono urządzenia o podanym ID.']);
                        exit;
                    }
                    $err = "Nie znaleziono urządzenia o podanym ID.";
                }
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                if ($is_ajax) {
                    http_response_code(500);
                    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
                    exit;
                }
                $err = "Błąd zapisu weryfikacji: " . $e->getMessage();
            }
        }
    } elseif ($action === 'delete_device') {
        $device_id = (int)($_POST['device_id'] ?? 0);
        if ($device_id > 0) {
            try {
                $stmt = $pdo->prepare("SELECT asset_number FROM device WHERE id = ?");
                $stmt->execute([$device_id]);
                $asset = $stmt->fetchColumn();

                if ($asset) {
                    $del = $pdo->prepare("DELETE FROM device WHERE id = ?");
                    $del->execute([$device_id]);
                    $ok = "Urządzenie " . htmlspecialchars($asset) . " zostało trwale usunięte z bazy.";
                } else {
                    $err = "Nie znaleziono urządzenia o ID {$device_id}.";
                }
            } catch (Throwable $e) {
                $err = "Błąd usuwania urządzenia: " . $e->getMessage();
            }
        }
    }
}

// Handle explicit filter reset
if (isset($_GET['reset'])) {
    unset($_SESSION['inventory_filters']);
    header("Location: index.php");
    exit;
}

// Flash messages from redirects (e.g. returning from edit)
if (!empty($_SESSION['flash_ok'])) {
    $ok = $_SESSION['flash_ok'];
    unset($_SESSION['flash_ok']);
}
if (!empty($_SESSION['flash_err'])) {
    $err = $_SESSION['flash_err'];
    unset($_SESSION['flash_err']);
}

// Check if user submitted any filter or if we should restore from session
$has_explicit_filter = isset($_GET['q']) || isset($_GET['room']) || isset($_GET['status']) || isset($_GET['type_id']) || isset($_GET['asset']) || isset($_GET['model']) || isset($_GET['sn']) || isset($_GET['user']);

if ($has_explicit_filter) {
    $_SESSION['inventory_filters'] = [
        'q' => $_GET['q'] ?? '',
        'room' => $_GET['room'] ?? '',
        'status' => $_GET['status'] ?? '',
        'type_id' => $_GET['type_id'] ?? '',
        'asset' => $_GET['asset'] ?? '',
        'model' => $_GET['model'] ?? '',
        'sn' => $_GET['sn'] ?? '',
        'user' => $_GET['user'] ?? '',
    ];
} elseif (!empty($_SESSION['inventory_filters'])) {
    // Restore saved room/filters so returning from edit never resets the room!
    $_GET = array_merge($_SESSION['inventory_filters'], $_GET);
}

// Fetch search and filter values from GET
$search = trim((string)($_GET['q'] ?? ''));
$filter_room = trim((string)($_GET['room'] ?? ''));
$filter_status = trim((string)($_GET['status'] ?? ''));
$filter_type = trim((string)($_GET['type_id'] ?? ''));
$filter_asset = trim((string)($_GET['asset'] ?? ''));
$filter_model = trim((string)($_GET['model'] ?? ''));
$filter_sn = trim((string)($_GET['sn'] ?? ''));
$filter_user = trim((string)($_GET['user'] ?? ''));

// SQL query construction
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
if ($filter_type !== '') {
    $where[] = "d.type_id = :type_id";
    $params[':type_id'] = $filter_type;
}
if ($filter_asset !== '') {
    $where[] = "d.asset_number LIKE :asset";
    $params[':asset'] = '%' . $filter_asset . '%';
}
if ($filter_model !== '') {
    $where[] = "(d.model LIKE :model OR d.manufacturer LIKE :model)";
    $params[':model'] = '%' . $filter_model . '%';
}
if ($filter_sn !== '') {
    $where[] = "d.serial_number LIKE :sn";
    $params[':sn'] = '%' . $filter_sn . '%';
}
if ($filter_user !== '') {
    $where[] = "d.user_id = :user";
    $params[':user'] = $filter_user;
}

if (!empty($where)) {
    $sql .= " WHERE " . implode(" AND ", $where);
}

$sql .= " ORDER BY COALESCE(d.updated_at, d.created_at) DESC, d.id DESC";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $items = $stmt->fetchAll();

    // Fetch filters lists
    $rooms = $pdo->query("SELECT DISTINCT room FROM device WHERE room IS NOT NULL AND room != ''")->fetchAll(PDO::FETCH_COLUMN);
    natsort($rooms);
    $statuses = $pdo->query("SELECT DISTINCT status FROM device")->fetchAll(PDO::FETCH_COLUMN);
    $all_users = $pdo->query("SELECT id, first_name, last_name, username FROM users WHERE role = 'user' ORDER BY last_name ASC")->fetchAll();
    $device_types = $pdo->query("SELECT id, name FROM device_type ORDER BY name")->fetchAll();

    // Fetch history for displayed items
    $history_by_device = [];
    $device_ids = array_column($items, 'id');
    if (!empty($device_ids)) {
        $placeholders = implode(',', array_fill(0, count($device_ids), '?'));
        $hist_stmt = $pdo->prepare("
            SELECT h.*, 
                   u1.first_name as o_fn, u1.last_name as o_ln, 
                   u2.first_name as n_fn, u2.last_name as n_ln 
            FROM device_history h
            LEFT JOIN users u1 ON h.old_user_id = u1.id
            LEFT JOIN users u2 ON h.new_user_id = u2.id
            WHERE h.device_id IN ($placeholders) 
            ORDER BY h.date DESC, h.id DESC
        ");
        $hist_stmt->execute($device_ids);
        $all_history = $hist_stmt->fetchAll();
        foreach ($all_history as $h) {
            $history_by_device[$h['device_id']][] = $h;
        }
    }

    // Load dynamic fields
    $app_fields = get_app_fields();
    $main_fields = get_main_fields($app_fields);
    $detail_fields = get_detail_fields($app_fields);

    // Fetch custom fields for displayed items
    $custom_fields_by_device = [];
    if (!empty($device_ids)) {
        $cf_stmt = $pdo->prepare("
            SELECT dfv.device_id, df.label as name, dfv.value, df.field_type
            FROM device_field_value dfv
            JOIN app_fields df ON dfv.field_id = df.id
            WHERE dfv.device_id IN ($placeholders)
            ORDER BY df.order_main ASC, df.id ASC
        ");
        $cf_stmt->execute($device_ids);
        foreach ($cf_stmt->fetchAll() as $cf) {
            if ($cf['value'] !== '' && $cf['value'] !== null) {
                if ($cf['field_type'] === 'checkbox') {
                    $cf['value'] = ($cf['value'] === '1' || $cf['value'] === 'Tak') ? 'Tak' : 'Nie';
                }
                $custom_fields_by_device[$cf['device_id']][] = $cf;
            }
        }
    }

} catch (PDOException $e) {
    die('Błąd pobierania danych: ' . htmlspecialchars($e->getMessage()));
}
        $this->render('dashboard/index', [
            'err' => $err,
            'ok' => $ok,
            'search' => $search,
            'filter_room' => $filter_room,
            'filter_status' => $filter_status,
            'filter_type' => $filter_type,
            'filter_asset' => $filter_asset,
            'filter_model' => $filter_model,
            'filter_sn' => $filter_sn,
            'filter_user' => $filter_user,
            'items' => $items,
            'rooms' => $rooms,
            'statuses' => $statuses,
            'all_users' => $all_users,
            'device_types' => $device_types,
            'history_by_device' => $history_by_device,
            'main_fields' => $main_fields,
            'detail_fields' => $detail_fields,
            'custom_fields_by_device' => $custom_fields_by_device
        ]);
    }
}