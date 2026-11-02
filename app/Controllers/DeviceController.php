<?php
namespace App\Controllers;

use App\Models\Model;

class DeviceController extends Controller {
    public function edit() {
        require_once __DIR__ . '/../../inc/auth.php';
        require_once __DIR__ . '/../../inc/view_renderer.php';
        require_login();
        
        $model = new Model();
        $pdo = $model->db;

        $id = (int)($_GET['id'] ?? 0);
        if (!$id) {
            $this->redirect('index.php');
        }

        // Resolve return URL (e.g. index.php?room=5)
        $return_url = 'index.php';
        if (!empty($_REQUEST['return'])) {
            $return_url = $_REQUEST['return'];
        } elseif (!empty($_SESSION['inventory_filters'])) {
            $cleaned_filters = array_filter($_SESSION['inventory_filters'], fn($v) => $v !== '');
            $return_url = !empty($cleaned_filters) ? 'index.php?' . http_build_query($cleaned_filters) : 'index.php';
        }

        $err = '';
        $ok = '';

        // Fetch all app_fields
        $stmt = $pdo->query("SELECT * FROM app_fields ORDER BY order_form ASC");
        $app_fields = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        $form_fields = array_filter($app_fields, fn($f) => $f['show_in_form'] == 1);
        usort($form_fields, fn($a, $b) => $a['order_form'] <=> $b['order_form']);

        $custom_fields_by_id = [];
        $native_fields_by_key = [];
        foreach ($app_fields as $f) {
            if ($f['source_type'] === 'custom') {
                $custom_fields_by_id[$f['id']] = $f;
            } else {
                $native_fields_by_key[$f['field_key']] = $f;
            }
        }

        $users = $pdo->query("SELECT id, username, first_name, last_name FROM users ORDER BY last_name, first_name")->fetchAll(\PDO::FETCH_ASSOC);
        $types = $pdo->query("SELECT id, name FROM device_type ORDER BY name")->fetchAll(\PDO::FETCH_ASSOC);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $native = $_POST['native'] ?? [];
            $custom = $_POST['custom'] ?? [];
            $event_desc = trim($_POST['event_description'] ?? '');
            
            try {
                $stmt = $pdo->prepare("SELECT * FROM device WHERE id = ?");
                $stmt->execute([$id]);
                $old_device = $stmt->fetch(\PDO::FETCH_ASSOC);

                if (!$old_device) {
                    throw new \Exception("Nie znaleziono urządzenia.");
                }

                // If asset_number is left empty, preserve existing or auto-generate
                if (empty($native['asset_number'])) {
                    $native['asset_number'] = !empty($old_device['asset_number'])
                        ? $old_device['asset_number']
                        : \App\Controllers\DashboardController::generateAssetNumber($pdo);
                }

                $pdo->beginTransaction();

                    $stmt = $pdo->prepare("SELECT field_id, value FROM device_field_value WHERE device_id = ?");
                    $stmt->execute([$id]);
                    $old_cf_raw = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                    $old_custom = [];
                    foreach ($old_cf_raw as $r) {
                        $old_custom[$r['field_id']] = $r['value'];
                    }

                    // UPDATE NATIVE
                    $cols = [];
                    $params = [];
                    foreach ($native as $k => $v) {
                        if ($v === '') $v = null;
                        $cols[] = "$k = :$k";
                        $params[":$k"] = $v;
                    }
                    $cols[] = "updated_at = CURRENT_TIMESTAMP";
                    $params[':id'] = $id;
                    
                    if (!empty($cols)) {
                        $sql = "UPDATE device SET " . implode(', ', $cols) . " WHERE id = :id";
                        $pdo->prepare($sql)->execute($params);
                    }

                    // UPDATE CUSTOM
                    $stmt_del = $pdo->prepare("DELETE FROM device_field_value WHERE device_id = ? AND field_id = ?");
                    $stmt_ins = $pdo->prepare("INSERT INTO device_field_value (device_id, field_id, value) VALUES (?, ?, ?)");
                    
                    foreach ($custom as $fid => $fval) {
                        if ($fval === '') {
                            $stmt_del->execute([$id, (int)$fid]);
                        } else {
                            $stmt_del->execute([$id, (int)$fid]);
                            $stmt_ins->execute([$id, (int)$fid, $fval]);
                        }
                    }

                    // HISTORY LOGGING
                    $hist_stmt = $pdo->prepare("
                        INSERT INTO device_history (device_id, event_type, field_name, old_value, new_value, old_user_id, new_user_id, old_room, new_room, old_status, new_status, description, changed_by)
                        VALUES (:device_id, :event_type, :field_name, :old_value, :new_value, :old_user_id, :new_user_id, :old_room, :new_room, :old_status, :new_status, :description, :changed_by)
                    ");

                    foreach ($native as $k => $v) {
                        if ($v === '') $v = null;
                        $old_val = $old_device[$k];
                        
                        if ($old_val != $v) {
                            $field_def = $native_fields_by_key[$k] ?? null;
                            if ($field_def && $field_def['log_history'] == 1) {
                                $hist_stmt->execute([
                                    ':device_id' => $id,
                                    ':event_type' => 'UPDATE',
                                    ':field_name' => $field_def['label'],
                                    ':old_value' => (string)$old_val,
                                    ':new_value' => (string)$v,
                                    ':old_user_id' => $k === 'user_id' ? $old_val : null,
                                    ':new_user_id' => $k === 'user_id' ? $v : null,
                                    ':old_room' => $k === 'room' ? $old_val : null,
                                    ':new_room' => $k === 'room' ? $v : null,
                                    ':old_status' => $k === 'status' ? $old_val : null,
                                    ':new_status' => $k === 'status' ? $v : null,
                                    ':description' => null,
                                    ':changed_by' => $_SESSION['username']
                                ]);
                            }
                        }
                    }

                    foreach ($custom as $fid => $v) {
                        if ($v === '') $v = null;
                        $old_val = $old_custom[$fid] ?? null;
                        
                        if ($old_val !== $v) {
                            $field_def = $custom_fields_by_id[$fid] ?? null;
                            if ($field_def && $field_def['log_history'] == 1) {
                                $hist_stmt->execute([
                                    ':device_id' => $id,
                                    ':event_type' => 'UPDATE',
                                    ':field_name' => $field_def['label'],
                                    ':old_value' => (string)$old_val,
                                    ':new_value' => (string)$v,
                                    ':old_user_id' => null,
                                    ':new_user_id' => null,
                                    ':old_room' => null,
                                    ':new_room' => null,
                                    ':old_status' => null,
                                    ':new_status' => null,
                                    ':description' => null,
                                    ':changed_by' => $_SESSION['username']
                                ]);
                            }
                        }
                    }

                    if ($event_desc !== '') {
                        $hist_stmt->execute([
                            ':device_id' => $id,
                            ':event_type' => 'INFO',
                            ':field_name' => null,
                            ':old_value' => null,
                            ':new_value' => null,
                            ':old_user_id' => null,
                            ':new_user_id' => null,
                            ':old_room' => null,
                            ':new_room' => null,
                            ':old_status' => null,
                            ':new_status' => null,
                            ':description' => $event_desc,
                            ':changed_by' => $_SESSION['username']
                        ]);
                    }

                    $pdo->commit();
                    $ok = "Zapisano zmiany.";

                    if (!empty($_POST['save_and_return'])) {
                        $_SESSION['flash_ok'] = "Pomyślnie zapisano zmiany w urządzeniu {$native['asset_number']}.";
                        header("Location: " . $return_url);
                        exit;
                    }
                } catch (\Exception $e) {
                    if ($pdo->inTransaction()) $pdo->rollBack();
                    $err = "Błąd zapisu: " . $e->getMessage();
                }
        }

        $stmt = $pdo->prepare("SELECT * FROM device WHERE id = ?");
        $stmt->execute([$id]);
        $device = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$device) {
            echo "Nie znaleziono urządzenia.";
            exit;
        }

        $stmt = $pdo->prepare("SELECT field_id, value FROM device_field_value WHERE device_id = ?");
        $stmt->execute([$id]);
        $cf_raw = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $device_custom = [];
        foreach ($cf_raw as $r) {
            $device_custom[$r['field_id']] = $r['value'];
        }

        // Fetch History
        $hist_stmt = $pdo->prepare("
            SELECT h.*, h.date as created_at,
                   u1.username as old_u, u2.username as new_u
            FROM device_history h
            LEFT JOIN users u1 ON h.old_user_id = u1.id
            LEFT JOIN users u2 ON h.new_user_id = u2.id
            WHERE h.device_id = ?
            ORDER BY h.date DESC, h.id DESC
        ");
        $hist_stmt->execute([$id]);
        $history = $hist_stmt->fetchAll(\PDO::FETCH_ASSOC);

        $this->render('devices/edit', [
            'err' => $err,
            'ok' => $ok,
            'device' => $device,
            'form_fields' => $form_fields,
            'device_custom' => $device_custom,
            'users' => $users,
            'types' => $types,
            'history' => $history,
            'return_url' => $return_url
        ]);
    }
}
