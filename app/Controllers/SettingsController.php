<?php
namespace App\Controllers;

use App\Models\Model;

class SettingsController extends Controller {
    public function fields() {
        require_once __DIR__ . '/../../inc/auth.php';
        require_admin();

        $model = new Model();
        $pdo = $model->db;

        $err = '';
        $ok = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (isset($_POST['action']) && $_POST['action'] === 'save_fields') {
                try {
                    $pdo->beginTransaction();
                    
                    // First, reset all checkboxes to 0
                    $pdo->exec("UPDATE app_fields SET show_in_main = 0, show_in_details = 0, show_in_form = 0, log_history = 0");
                    
                    // Update orders and checkboxes
                    if (isset($_POST['fields']) && is_array($_POST['fields'])) {
                        $stmt = $pdo->prepare("
                            UPDATE app_fields 
                            SET show_in_main = ?, show_in_details = ?, show_in_form = ?, log_history = ?, order_main = ?, order_details = ?, order_form = ?
                            WHERE id = ?
                        ");
                        
                        $order = 10;
                        foreach ($_POST['fields'] as $id => $data) {
                            $show_in_main = isset($data['show_in_main']) ? 1 : 0;
                            $show_in_details = isset($data['show_in_details']) ? 1 : 0;
                            $show_in_form = isset($data['show_in_form']) ? 1 : 0;
                            $log_history = isset($data['log_history']) ? 1 : 0;
                            
                            $stmt_req = $pdo->prepare("SELECT is_required FROM app_fields WHERE id = ?");
                            $stmt_req->execute([(int)$id]);
                            if ($stmt_req->fetchColumn() == 1) {
                                $show_in_form = 1;
                            }
                            
                            $stmt->execute([
                                $show_in_main, $show_in_details, $show_in_form, $log_history,
                                $order, $order, $order, 
                                (int)$id
                            ]);
                            
                            $order += 10;
                        }
                    }
                    $pdo->commit();
                    $ok = "Zapisano ustawienia pól i relacji.";
                } catch (\Exception $e) {
                    $pdo->rollBack();
                    $err = "Błąd zapisu: " . $e->getMessage();
                }
            }
        }

        $fields = $pdo->query("SELECT * FROM app_fields ORDER BY order_form ASC")->fetchAll(\PDO::FETCH_ASSOC);

        $this->render('settings/fields', [
            'err' => $err,
            'ok' => $ok,
            'fields' => $fields,
            'extra_js' => '<script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function() {
    var el = document.getElementById("sortableList");
    if(el) {
        Sortable.create(el, {
            handle: ".drag-handle",
            animation: 150,
            ghostClass: "sortable-ghost"
        });
    }
});
</script>'
        ]);
    }
}
