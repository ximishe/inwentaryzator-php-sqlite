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
            } elseif ($action === 'reset_user_password') {
                $target_id = (int)($_POST['id'] ?? 0);
                $new_pass = (string)($_POST['new_password'] ?? '');
                if ($target_id && mb_strlen($new_pass) >= 4) {
                    $new_hash = password_hash($new_pass, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                    $stmt->execute([$new_hash, $target_id]);
                    $ok = "Hasło użytkownika zostało zaktualizowane przez administratora.";
                } else {
                    $err = "Nowe hasło musi mieć co najmniej 4 znaki.";
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

    /**
     * Zmiana hasła przez dowolnego zalogowanego użytkownika (User i Admin)
     */
    public function changePassword() {
        require_once __DIR__ . '/../../inc/auth.php';
        require_login(); // Dostępne dla każdego zalogowanego użytkownika

        $is_ajax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') 
                   || (isset($_POST['ajax']) && $_POST['ajax'] === '1');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            if ($is_ajax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Niedozwolona metoda żądania.']);
                exit;
            }
            $this->redirect('index.php');
        }

        $user_id = get_logged_user_id();
        $current_pass = (string)($_POST['current_password'] ?? '');
        $new_pass = (string)($_POST['new_password'] ?? '');
        $confirm_pass = (string)($_POST['confirm_password'] ?? '');
        $return_url = (string)($_POST['return_url'] ?? 'index.php');

        $model = new Model();
        $pdo = $model->db;

        // Pobranie danych zalogowanego użytkownika
        $stmt = $pdo->prepare("SELECT id, username, password_hash FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$user) {
            $msg = "Nie znaleziono konta użytkownika w bazie danych.";
            if ($is_ajax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => $msg]);
                exit;
            }
            $_SESSION['flash_err'] = $msg;
            $this->redirect($return_url);
        }

        // Weryfikacja aktualnego hasła
        if (!password_verify($current_pass, $user['password_hash'])) {
            $msg = "Podane aktualne hasło jest niepoprawne.";
            if ($is_ajax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => $msg]);
                exit;
            }
            $_SESSION['flash_err'] = $msg;
            $this->redirect($return_url);
        }

        // Walidacja nowego hasła
        if (mb_strlen($new_pass) < 4) {
            $msg = "Nowe hasło musi zawierać co najmniej 4 znaki.";
            if ($is_ajax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => $msg]);
                exit;
            }
            $_SESSION['flash_err'] = $msg;
            $this->redirect($return_url);
        }

        if ($new_pass !== $confirm_pass) {
            $msg = "Nowe hasła nie są ze sobą zgodne.";
            if ($is_ajax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => $msg]);
                exit;
            }
            $_SESSION['flash_err'] = $msg;
            $this->redirect($return_url);
        }

        // Zapis nowego hasła
        $new_hash = password_hash($new_pass, PASSWORD_DEFAULT);
        $update = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
        $update->execute([$new_hash, $user_id]);

        $msg = "Twoje hasło zostało pomyślnie zmienione!";
        if ($is_ajax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => $msg]);
            exit;
        }

        $_SESSION['flash_ok'] = $msg;
        $this->redirect($return_url);
    }
}
