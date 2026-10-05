<?php
declare(strict_types=1);

require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/inc/auth.php';

require_admin(); // Restrict to admin only

$user_id = get_logged_user_id();
$username = get_logged_username();

$err = '';
$ok = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'create_user') {
        $new_username = trim((string)($_POST['username'] ?? ''));
        $new_email = trim((string)($_POST['email'] ?? ''));
        $new_password = (string)($_POST['password'] ?? '');
        $role = (string)($_POST['role'] ?? 'user');
        
        $first_name = trim((string)($_POST['first_name'] ?? ''));
        $last_name = trim((string)($_POST['last_name'] ?? ''));
        $room = trim((string)($_POST['room'] ?? ''));
        $position = trim((string)($_POST['position'] ?? ''));
        $description = trim((string)($_POST['description'] ?? ''));

        if ($new_username === '' || $new_email === '' || $new_password === '') {
            $err = 'Nazwa użytkownika, e-mail oraz hasło są wymagane.';
        } else {
            $pass_hash = password_hash($new_password, PASSWORD_DEFAULT);
            try {
                $stmt = db()->prepare("
                    INSERT INTO users (username, email, password_hash, role, active, first_name, last_name, room, position, status, description)
                    VALUES (:username, :email, :password_hash, :role, 1, :first_name, :last_name, :room, :position, 'ACTIVE', :description)
                ");
                $stmt->execute([
                    ':username' => $new_username,
                    ':email' => $new_email,
                    ':password_hash' => $pass_hash,
                    ':role' => $role,
                    ':first_name' => $first_name !== '' ? $first_name : null,
                    ':last_name' => $last_name !== '' ? $last_name : null,
                    ':room' => $room !== '' ? $room : null,
                    ':position' => $position !== '' ? $position : null,
                    ':description' => $description !== '' ? $description : null
                ]);
                $ok = 'Pracownik / użytkownik został pomyślnie utworzony!';
            } catch (PDOException $e) {
                if (str_contains($e->getMessage(), 'UNIQUE')) {
                    $err = 'Nazwa użytkownika lub adres e-mail jest już zajęty.';
                } else {
                    $err = 'Błąd zapisu w bazie danych: ' . $e->getMessage();
                }
            }
        }
    } elseif ($action === 'update_user') {
        $target_user_id = (int)($_POST['user_id'] ?? 0);
        $role = trim((string)($_POST['role'] ?? ''));
        $active = (int)($_POST['active'] ?? 1);
        $status = trim((string)($_POST['status'] ?? 'ACTIVE'));
        $first_name = trim((string)($_POST['first_name'] ?? ''));
        $last_name = trim((string)($_POST['last_name'] ?? ''));
        $room = trim((string)($_POST['room'] ?? ''));
        $position = trim((string)($_POST['position'] ?? ''));

        if ($target_user_id <= 0) {
            $err = 'Nieprawidłowe dane.';
        } else {
            if ($target_user_id === $user_id && ($active !== 1 || $role !== 'admin')) {
                $err = 'Nie możesz wyłączyć ani zmienić roli własnego konta administratora.';
            } else {
                try {
                    $stmt = db()->prepare("
                        UPDATE users 
                        SET role = :role, 
                            active = :active,
                            status = :status,
                            first_name = :first_name,
                            last_name = :last_name,
                            room = :room,
                            position = :position
                        WHERE id = :id
                    ");
                    $stmt->execute([
                        ':role' => $role,
                        ':active' => $active,
                        ':status' => $status,
                        ':first_name' => $first_name !== '' ? $first_name : null,
                        ':last_name' => $last_name !== '' ? $last_name : null,
                        ':room' => $room !== '' ? $room : null,
                        ':position' => $position !== '' ? $position : null,
                        ':id' => $target_user_id
                    ]);
                    
                    if ($target_user_id === $user_id) {
                        $_SESSION['role'] = $role;
                    }
                    
                    $ok = 'Dane pracownika zostały zaktualizowane.';
                } catch (PDOException $e) {
                    $err = 'Błąd bazy danych: ' . $e->getMessage();
                }
            }
        }
    }
}

try {
    $stmt = db()->prepare("SELECT * FROM users ORDER BY last_name ASC, first_name ASC, username ASC");
    $stmt->execute();
    $users_list = $stmt->fetchAll();
} catch (PDOException $e) {
    die('Błąd pobierania danych: ' . htmlspecialchars($e->getMessage()));
}
?>
<!DOCTYPE html>
<html lang="pl" data-bs-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Pracownicy i Użytkownicy - Inwentaryzator</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/style.css">
</head>
<body>

  <nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4">
    <div class="container">
      <a class="navbar-brand fw-bold" href="index.php">Inwentaryzator</a>
      <div class="d-flex align-items-center text-white">
        <a href="index.php" class="btn btn-outline-light btn-sm me-2">Wróć do listy sprzętu</a>
      </div>
    </div>
  </nav>

  <div class="container pb-5">
    <div class="row">
      <!-- Create User Card -->
      <div class="col-md-4 mb-4">
        <div class="card p-4">
          <h5 class="fw-bold text-primary mb-3">Dodaj pracownika / usera</h5>
          
          <?php if ($err && isset($_POST['action']) && $_POST['action'] === 'create_user'): ?><div class="alert alert-danger p-2 small"><?= htmlspecialchars($err) ?></div><?php endif; ?>
          <?php if ($ok && isset($_POST['action']) && $_POST['action'] === 'create_user'): ?><div class="alert alert-success p-2 small"><?= htmlspecialchars($ok) ?></div><?php endif; ?>

          <form method="POST">
            <input type="hidden" name="action" value="create_user">
            <div class="mb-2">
              <label class="form-label small fw-semibold">Login (Wymagany)</label>
              <input type="text" class="form-control form-control-sm" name="username" required>
            </div>
            <div class="mb-2">
              <label class="form-label small fw-semibold">E-mail (Wymagany)</label>
              <input type="email" class="form-control form-control-sm" name="email" required>
            </div>
            <div class="mb-2">
              <label class="form-label small fw-semibold">Hasło (Wymagane)</label>
              <input type="password" class="form-control form-control-sm" name="password" required>
            </div>
            <div class="mb-2">
              <label class="form-label small fw-semibold">Imię</label>
              <input type="text" class="form-control form-control-sm" name="first_name">
            </div>
            <div class="mb-2">
              <label class="form-label small fw-semibold">Nazwisko</label>
              <input type="text" class="form-control form-control-sm" name="last_name">
            </div>
            <div class="mb-2">
              <label class="form-label small fw-semibold">Pokój</label>
              <input type="text" class="form-control form-control-sm" name="room">
            </div>
            <div class="mb-2">
              <label class="form-label small fw-semibold">Stanowisko</label>
              <input type="text" class="form-control form-control-sm" name="position">
            </div>
            <div class="mb-3">
              <label class="form-label small fw-semibold">Uprawnienia logowania</label>
              <select class="form-select form-select-sm" name="role">
                <option value="user">Zwykły pracownik / User</option>
                <option value="admin">Administrator (pełny dostęp)</option>
              </select>
            </div>
            <button type="submit" class="btn btn-primary btn-sm w-100">Utwórz pracownika</button>
          </form>
        </div>
      </div>

      <!-- Users List -->
      <div class="col-md-8">
        <div class="card p-4">
          <h5 class="fw-bold text-primary mb-3">Lista pracowników</h5>
          
          <?php if ($err && isset($_POST['action']) && $_POST['action'] === 'update_user'): ?><div class="alert alert-danger p-2 small"><?= htmlspecialchars($err) ?></div><?php endif; ?>
          <?php if ($ok && isset($_POST['action']) && $_POST['action'] === 'update_user'): ?><div class="alert alert-success p-2 small"><?= htmlspecialchars($ok) ?></div><?php endif; ?>

          <div class="table-responsive">
            <table class="table table-hover align-middle small">
              <thead class="table-dark">
                <tr>
                  <th>Imię i Nazwisko / Login</th>
                  <th>Pokój / Stanowisko</th>
                  <th>Rola / Status Pracownika</th>
                  <th>Aktywacja logowania</th>
                  <th>Akcja</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($users_list as $u): ?>
                  <tr>
                    <form method="POST">
                      <input type="hidden" name="action" value="update_user">
                      <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                      <td>
                        <input type="text" name="first_name" class="form-control form-control-sm mb-1" value="<?= htmlspecialchars((string)$u['first_name']) ?>" placeholder="Imię">
                        <input type="text" name="last_name" class="form-control form-control-sm mb-1" value="<?= htmlspecialchars((string)$u['last_name']) ?>" placeholder="Nazwisko">
                        <div class="text-muted small">Login: <?= htmlspecialchars($u['username']) ?></div>
                      </td>
                      <td>
                        <input type="text" name="room" class="form-control form-control-sm mb-1" value="<?= htmlspecialchars((string)$u['room']) ?>" placeholder="Pokój">
                        <input type="text" name="position" class="form-control form-control-sm" value="<?= htmlspecialchars((string)$u['position']) ?>" placeholder="Stanowisko">
                      </td>
                      <td>
                        <select name="role" class="form-select form-select-sm mb-1">
                          <option value="user" <?= $u['role'] === 'user' ? 'selected' : '' ?>>User</option>
                          <option value="admin" <?= $u['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                        </select>
                        <select name="status" class="form-select form-select-sm">
                          <option value="ACTIVE" <?= $u['status'] === 'ACTIVE' ? 'selected' : '' ?>>ACTIVE</option>
                          <option value="INACTIVE" <?= $u['status'] === 'INACTIVE' ? 'selected' : '' ?>>INACTIVE</option>
                        </select>
                      </td>
                      <td>
                        <select name="active" class="form-select form-select-sm">
                          <option value="1" <?= (int)$u['active'] === 1 ? 'selected' : '' ?>>Tak (Aktywny)</option>
                          <option value="0" <?= (int)$u['active'] === 0 ? 'selected' : '' ?>>Nie (Zablokowany)</option>
                        </select>
                      </td>
                      <td>
                        <button type="submit" class="btn btn-success btn-sm w-100 py-2">Zapisz</button>
                      </td>
                    </form>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>

        </div>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
