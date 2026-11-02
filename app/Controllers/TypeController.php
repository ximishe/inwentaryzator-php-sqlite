<?php
namespace App\Controllers;

use App\Models\Model;

class TypeController extends Controller {
    public function index() {
        require_once __DIR__ . '/../../inc/auth.php';
        require_login();
        if (!is_admin()) {
            $this->redirect('/INVENTARYZATOR/index.php');
        }

        $model = new Model();
        $pdo = $model->db;
        $msg = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (isset($_POST['action']) && $_POST['action'] === 'add_type') {
                $name = trim($_POST['name'] ?? '');
                $desc = trim($_POST['description'] ?? '');
                if ($name) {
                    $stmt = $pdo->prepare("INSERT INTO device_type (name, description) VALUES (?, ?)");
                    $stmt->execute([$name, $desc]);
                    $msg = "Typ urządzenia dodany pomyślnie.";
                }
            } elseif (isset($_POST['action']) && $_POST['action'] === 'delete_type') {
                $id = (int)$_POST['id'];
                $pdo->prepare("DELETE FROM device_type WHERE id = ?")->execute([$id]);
                $msg = "Typ urządzenia usunięty.";
            } elseif (isset($_POST['action']) && $_POST['action'] === 'add_field') {
                $type_id = (int)$_POST['type_id'];
                $name = trim($_POST['name'] ?? '');
                $field_type = $_POST['field_type'] ?? 'text';
                $order = (int)($_POST['display_order'] ?? 0);
                $order = $order > 0 ? 200 + $order : 200 + time() % 100;

                if ($name && $type_id) {
                    $field_key = 'custom_' . time() . '_' . rand(100,999);
                    $stmt = $pdo->prepare("INSERT INTO app_fields (field_key, label, field_type, source_type, type_id, order_form, order_main, order_details) VALUES (?, ?, ?, 'custom', ?, ?, ?, ?)");
                    $stmt->execute([$field_key, $name, $field_type, $type_id, $order, $order, $order]);
                    $msg = "Pole dodane pomyślnie.";
                }
            } elseif (isset($_POST['action']) && $_POST['action'] === 'delete_field') {
                $id = (int)$_POST['id'];
                $pdo->prepare("DELETE FROM app_fields WHERE id = ? AND source_type = 'custom'")->execute([$id]);
                $msg = "Pole usunięte.";
            }
        }

        $types = $pdo->query("SELECT * FROM device_type ORDER BY name")->fetchAll(\PDO::FETCH_ASSOC);
        $all_fields = $pdo->query("SELECT * FROM app_fields WHERE source_type = 'custom' ORDER BY order_form")->fetchAll(\PDO::FETCH_ASSOC);
        
        $fields_by_type = [];
        foreach ($all_fields as $f) {
            $fields_by_type[$f['type_id']][] = $f;
        }

        $this->render('types/index', [
            'msg' => $msg,
            'types' => $types,
            'fields_by_type' => $fields_by_type
        ]);
    }
}