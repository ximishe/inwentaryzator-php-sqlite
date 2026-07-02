<?php
declare(strict_types=1);

require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/inc/auth.php';

require_admin(); // Restrict to admin only

$user_id = get_logged_user_id();
$username = get_logged_username();

$err = '';
$ok = '';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'create_user') {
        $new_username = trim((string)($_POST['username'] ?? ''));
        $new_email = trim((string)($_POST['email'] ?? ''));
        $new_password = (string)($_POST['password'] ?? '');
        $role = (string)($_POST['role'] ?? 'user');

        if ($new_username === '' || $new_email === '' || $new_password === '') {
            $err = 'Wszystkie pola są wymagane do utworzenia użytkownika.';
        } elseif (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
            $err = 'Niepoprawny format adresu e-mail.';
        } elseif (!in_array($role, ['user', 'admin'], true)) {
            $err = 'Niepoprawna rola.';
        } else {
            $pass_hash = password_hash($new_password, PASSWORD_DEFAULT);
            try {
                $stmt = db()->prepare("
                    INSERT INTO users (username, email, password_hash, role, active)
                    VALUES (:username, :email, :password_hash, :role, 1)
                ");
                $stmt->execute([
                    ':username' => $new_username,
                    ':email' => $new_email,
                    ':password_hash' => $pass_hash,
                    ':role' => $role
                ]);
                $ok = 'Użytkownik został pomyślnie utworzony!';
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

        if ($target_user_id <= 0 || !in_array($role, ['admin', 'user'], true)) {
            $err = 'Nieprawidłowe dane.';
        } else {
            // Prevent self-deactivation or self-role-demotion to avoid lockout
            if ($target_user_id === $user_id && ($active !== 1 || $role !== 'admin')) {
                $err = 'Nie możesz wyłączyć ani zmienić roli własnego konta administratora.';
            } else {
                try {
                    $stmt = db()->prepare("
                        UPDATE users 
                        SET role = :role, 
                            active = :active 
                        WHERE id = :id
                    ");
                    $stmt->execute([
                        ':role' => $role,
                        ':active' => $active,
                        ':id' => $target_user_id
                    ]);
                    
                    // If target user is the currently logged in user, update session role
                    if ($target_user_id === $user_id) {
                        $_SESSION['role'] = $role;
                    }
                    
                    $ok = 'Dane użytkownika zostały zaktualizowane.';
                } catch (PDOException $e) {
                    $err = 'Błąd bazy danych: ' . $e->getMessage();
                }
            }
        }
    }
}

// Fetch all users
try {
    $stmt = db()->prepare("
        SELECT * FROM users 
        ORDER BY username ASC
    ");
    $stmt->execute();
    $users_list = $stmt->fetchAll();
} catch (PDOException $e) {
    die('Błąd pobierania danych: ' . htmlspecialchars($e->getMessage()));
}
?>
<!DOCTYPE html>
<html lang="pl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Użytkownicy - Inwentaryzator</title>
  <!-- Bootstrap 5 CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Google Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  
  <style>
    body {
      font-family: 'Outfit', sans-serif;
      background-color: #f3f4f6;
      color: #1f2937;
    }
    .navbar {
      background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%) !important;
      border-radius: 0 0 16px 16px;
    }
    .card {
      border-radius: 16px;
      border: none;
      box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
    }
    .btn {
      border-radius: 10px;
      font-weight: 500;
      transition: all 0.2s;
    }
    .btn-primary {
      background-color: #2563eb;
      border-color: #2563eb;
    }
    .btn-primary:hover {
      background-color: #1d4ed8;
      border-color: #1d4ed8;
    }
    .form-control, .form-select {
      border-radius: 10px;
      padding: 0.5rem 0.75rem;
      border: 1px solid #d1d5db;
    }
    .form-control:focus, .form-select:focus {
      border-color: #3b82f6;
      box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
    }
  </style>
</head>
<body class="bg-light">

  <nav class="navbar navbar-expand-lg navbar-dark bg-primary mb-4">
    <div class="container">
      <a class="navbar-brand fw-bold" href="index.php">Inwentaryzator</a>
      <div class="d-flex align-items-center text-white">
        <span class="me-3 d-none d-sm-inline">Witaj, <strong><?= htmlspecialchars($username) ?></strong> (Admin)</span>
        <a href="index.php" class="btn btn-outline-light btn-sm me-2">Panel główny</a>
        <a href="logout.php" class="btn btn-danger btn-sm">Wyloguj</a>
      </div>
    </div>
  </nav>

  <div class="container pb-5">
    <div class="row">
      <!-- Create User Card -->
      <div class="col-md-4 mb-4">
        <div class="card border-0 shadow-sm">
          <div class="card-body">
            <h5 class="card-title fw-bold text-primary mb-3">Dodaj użytkownika</h5>
            
            <?php if ($err && isset($_POST['action']) && $_POST['action'] === 'create_user'): ?>
              <div class="alert alert-danger p-2 small" role="alert"><?= htmlspecialchars($err) ?></div>
            <?php endif; ?>
            <?php if ($ok && isset($_POST['action']) && $_POST['action'] === 'create_user'): ?>
              <div class="alert alert-success p-2 small" role="alert"><?= htmlspecialchars($ok) ?></div>
            <?php endif; ?>

            <form method="POST" action="users.php">
              <input type="hidden" name="action" value="create_user">
              <div class="mb-3">
                <label for="new_username" class="form-label small fw-semibold">Nazwa użytkownika</label>
                <input type="text" class="form-control form-control-sm" id="new_username" name="username" required autocomplete="username">
              </div>
              <div class="mb-3">
                <label for="new_email" class="form-label small fw-semibold">Adres E-mail</label>
                <input type="email" class="form-control form-control-sm" id="new_email" name="email" required autocomplete="email">
              </div>
              <div class="mb-3">
                <label for="new_password" class="form-label small fw-semibold">Hasło</label>
                <input type="password" class="form-control form-control-sm" id="new_password" name="password" required autocomplete="new-password">
              </div>
              <div class="mb-3">
                <label for="new_role" class="form-label small fw-semibold">Rola</label>
                <select class="form-select form-select-sm" id="new_role" name="role">
                  <option value="user">User (Tylko inwentaryzacja)</option>
                  <option value="admin">Admin (Pełny dostęp)</option>
                </select>
              </div>
              <button type="submit" class="btn btn-primary btn-sm w-100 py-2">Utwórz użytkownika</button>
            </form>
          </div>
        </div>
      </div>

      <!-- Users List -->
      <div class="col-md-8">
        <div class="card border-0 shadow-sm">
          <div class="card-body">
            <h5 class="card-title fw-bold text-primary mb-3">Zarządzanie użytkownikami</h5>
            
            <?php if ($err && isset($_POST['action']) && $_POST['action'] === 'update_user'): ?>
              <div class="alert alert-danger p-2 small" role="alert"><?= htmlspecialchars($err) ?></div>
            <?php endif; ?>
            <?php if ($ok && isset($_POST['action']) && $_POST['action'] === 'update_user'): ?>
              <div class="alert alert-success p-2 small" role="alert"><?= htmlspecialchars($ok) ?></div>
            <?php endif; ?>

            <!-- Desktop List (Hidden on Mobile) -->
            <div class="table-responsive d-none d-md-block">
              <table class="table table-hover align-middle small">
                <thead class="table-light text-muted">
                  <tr>
                    <th>Użytkownik</th>
                    <th>E-mail</th>
                    <th>Rola</th>
                    <th>Status</th>
                    <th>Akcja</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($users_list as $u): ?>
                    <tr>
                      <td>
                        <strong><?= htmlspecialchars($u['username']) ?></strong>
                      </td>
                      <td><?= htmlspecialchars($u['email']) ?></td>
                      <form method="POST" action="users.php">
                        <input type="hidden" name="action" value="update_user">
                        <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                        <td>
                          <select name="role" class="form-select form-select-sm" style="min-width: 100px;">
                            <option value="user" <?= $u['role'] === 'user' ? 'selected' : '' ?>>User</option>
                            <option value="admin" <?= $u['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                          </select>
                        </td>
                        <td>
                          <select name="active" class="form-select form-select-sm" style="min-width: 100px;">
                            <option value="1" <?= (int)$u['active'] === 1 ? 'selected' : '' ?>>Aktywny</option>
                            <option value="0" <?= (int)$u['active'] === 0 ? 'selected' : '' ?>>Nieaktywny</option>
                          </select>
                        </td>
                        <td>
                          <button type="submit" class="btn btn-success btn-sm">Zapisz</button>
                        </td>
                      </form>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>

            <!-- Mobile View (Cards, Hidden on Desktop) -->
            <div class="d-md-none">
              <?php foreach ($users_list as $u): ?>
                <div class="card border-0 bg-light mb-3 p-3" style="border-radius: 12px;">
                  <form method="POST" action="users.php">
                    <input type="hidden" name="action" value="update_user">
                    <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                    
                    <div class="d-flex justify-content-between align-items-center mb-2">
                      <span class="fw-bold text-dark fs-6"><?= htmlspecialchars($u['username']) ?></span>
                      <span class="badge bg-secondary"><?= htmlspecialchars($u['email']) ?></span>
                    </div>
                    
                    <div class="row g-2 mb-3">
                      <div class="col-6">
                        <label class="form-label small fw-semibold text-muted mb-1">Rola</label>
                        <select name="role" class="form-select form-select-sm">
                          <option value="user" <?= $u['role'] === 'user' ? 'selected' : '' ?>>User</option>
                          <option value="admin" <?= $u['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                        </select>
                      </div>
                      <div class="col-6">
                        <label class="form-label small fw-semibold text-muted mb-1">Status</label>
                        <select name="active" class="form-select form-select-sm">
                          <option value="1" <?= (int)$u['active'] === 1 ? 'selected' : '' ?>>Aktywny</option>
                          <option value="0" <?= (int)$u['active'] === 0 ? 'selected' : '' ?>>Nieaktywny</option>
                        </select>
                      </div>
                    </div>
                    
                    <button type="submit" class="btn btn-success btn-sm w-100 py-2">💾 Zapisz zmiany</button>
                  </form>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Bootstrap 5 JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
