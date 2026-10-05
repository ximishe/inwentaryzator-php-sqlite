<?php
declare(strict_types=1);

require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/inc/auth.php';

start_session();

// If already logged in, redirect to dashboard
if (!empty($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $err = 'Nazwa użytkownika oraz hasło są wymagane.';
    } else {
        try {
            $stmt = db()->prepare("SELECT * FROM users WHERE username = :username LIMIT 1");
            $stmt->execute([':username' => $username]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                if ((int)$user['active'] !== 1) {
                    $err = 'Twoje konto jest nieaktywne. Skontaktuj się z administratorem.';
                } else {
                    $_SESSION['user_id'] = (int)$user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['role'] = $user['role'];
                    
                    header('Location: index.php');
                    exit;
                }
            } else {
                $err = 'Błędna nazwa użytkownika lub hasło.';
            }
        } catch (PDOException $e) {
            $err = 'Błąd logowania: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pl" data-bs-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Logowanie - Inwentaryzator Mobile</title>
  <!-- Bootstrap 5 CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Google Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  
  <link rel="stylesheet" href="assets/style.css">
</head>
<body class="login-page">
  <div class="card login-card p-4">
    <div class="text-center mb-4">
      <h2 class="h4 fw-bold text-primary">Inwentaryzator</h2>
      <p class="text-muted small">Zaloguj się, aby rozpocząć inwentaryzację</p>
    </div>

    <?php if ($err): ?>
      <div class="alert alert-danger p-2 small text-center" role="alert">
        <?= htmlspecialchars($err) ?>
      </div>
    <?php endif; ?>

    <form method="POST" action="login.php">
      <div class="mb-3">
        <label for="username" class="form-label small fw-semibold">Nazwa użytkownika</label>
        <input type="text" class="form-control" id="username" name="username" required autocomplete="username">
      </div>
      <div class="mb-4">
        <label for="password" class="form-label small fw-semibold">Hasło</label>
        <input type="password" class="form-control" id="password" name="password" required autocomplete="current-password">
      </div>
      <button type="submit" class="btn btn-primary w-100 py-2">Zaloguj się</button>
    </form>
  </div>
  
  <!-- Bootstrap 5 JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
