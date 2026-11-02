<?php
namespace App\Controllers;

use App\Models\Model;

class UserController extends Controller {
    public function index() {
        require_once __DIR__ . '/../../inc/auth.php';
        require_admin(); // Restrict to admin only

        $model = new Model();
        $pdo = $model->db;

        $err = '';
        $ok = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $action = (string)($_POST['action'] ?? '');

            if ($action === 'create_user') {
                $new_username = trim((string)($_POST['username'] ?? ''));
                $new_email = trim((string)($_POST['email'] ?? ''));
                $new_pass = (string)($_POST['password'] ?? '');
                $new_role = (string)($_POST['role'] ?? 'user');
                $new_first_name = trim((string)($_POST['first_name'] ?? ''));
                $new_last_name = trim((string)($_POST['last_name'] ?? ''));

                if ($new_username && $new_pass) {
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
                    $stmt->execute([$new_username]);
                    if ($stmt->fetchColumn() > 0) {
                        $err = "Użytkownik o takim loginie już istnieje.";
                    } else {
                        $hash = password_hash($new_pass, PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare("INSERT INTO users (username, password_hash, email, role, first_name, last_name) VALUES (?, ?, ?, ?, ?, ?)");
                        $stmt->execute([$new_username, $hash, $new_email, $new_role, $new_first_name, $new_last_name]);
                        $ok = "Dodano nowego pracownika/usera.";
                    }
                } else {
                    $err = "Login i hasło są wymagane.";
                }
            } elseif ($action === 'delete_user') {
                $id = (int)($_POST['id'] ?? 0);
                if ($id === get_logged_user_id()) {
                    $err = "Nie możesz usunąć samego siebie.";
                } else {
                    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                    $stmt->execute([$id]);
                    $ok = "Pracownik/user usunięty.";
                }
            }
        }

        $stmt = $pdo->query("SELECT id, username, email, role, first_name, last_name, created_at FROM users ORDER BY created_at DESC");
        $users = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $this->render('users/index', [
            'err' => $err,
            'ok' => $ok,
            'users' => $users
        ]);
    }
}
