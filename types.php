<?php
require_once 'inc/config.php';
require_once 'inc/db.php';
require_once 'inc/auth.php';

requireLogin();
if (!isAdmin()) {
    header("Location: index.php");
    exit;
}

$pdo = db();
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
        if ($name && $type_id) {
            $stmt = $pdo->prepare("INSERT INTO device_field (type_id, name, field_type, display_order) VALUES (?, ?, ?, ?)");
            $stmt->execute([$type_id, $name, $field_type, $order]);
            $msg = "Pole dodane pomyślnie.";
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'delete_field') {
        $id = (int)$_POST['id'];
        $pdo->prepare("DELETE FROM device_field WHERE id = ?")->execute([$id]);
        $msg = "Pole usunięte.";
    }
}

$types = $pdo->query("SELECT * FROM device_type ORDER BY name")->fetchAll();
?>
<!DOCTYPE html>
<html lang="pl" data-bs-theme="dark">
<head>
  <meta charset="UTF-8">
  <title>Typy urządzeń - Inventaryzator</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="assets/style.css">
</head>
<body class="bg-body-tertiary">
<nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4 border-bottom border-secondary border-opacity-25 shadow-sm">
  <div class="container-fluid">
    <a class="navbar-brand fw-bold text-uppercase tracking-wider" href="index.php" style="letter-spacing: 1px;">
      📦 INVENTARYZATOR
    </a>
    <div class="navbar-nav ms-auto">
      <a class="nav-link" href="index.php">Wróć do Dashboardu</a>
    </div>
  </div>
</nav>

<div class="container pb-5">
  <h2 class="mb-4">Zarządzanie Typami Urządzeń (Struktura)</h2>
  <?php if ($msg): ?>
    <div class="alert alert-success"><?= htmlspecialchars($msg) ?></div>
  <?php endif; ?>

  <div class="card bg-dark border-secondary mb-5 shadow-sm">
    <div class="card-header bg-dark border-secondary">
      Dodaj nowy typ urządzenia (np. Komputer, Monitor)
    </div>
    <div class="card-body">
      <form method="POST" action="types.php" class="row g-3">
        <input type="hidden" name="action" value="add_type">
        <div class="col-md-4">
          <input type="text" name="name" class="form-control" placeholder="Nazwa typu (np. Komputer)" required>
        </div>
        <div class="col-md-6">
          <input type="text" name="description" class="form-control" placeholder="Krótki opis (opcjonalnie)">
        </div>
        <div class="col-md-2">
          <button type="submit" class="btn btn-primary w-100">Dodaj Typ</button>
        </div>
      </form>
    </div>
  </div>

  <div class="row row-cols-1 row-cols-md-2 g-4">
    <?php foreach ($types as $type): ?>
      <?php
      $stmt = $pdo->prepare("SELECT * FROM device_field WHERE type_id = ? ORDER BY display_order");
      $stmt->execute([$type['id']]);
      $fields = $stmt->fetchAll();
      ?>
      <div class="col">
        <div class="card bg-dark border-secondary shadow-sm h-100">
          <div class="card-header bg-dark border-secondary d-flex justify-content-between align-items-center">
            <h5 class="mb-0 text-info fw-bold"><?= htmlspecialchars($type['name']) ?></h5>
            <form method="POST" action="types.php" onsubmit="return confirm('Uwaga: Usunięcie typu spowoduje usunięcie wszystkich powiązanych pól!');">
              <input type="hidden" name="action" value="delete_type">
              <input type="hidden" name="id" value="<?= $type['id'] ?>">
              <button type="submit" class="btn btn-danger btn-sm">Usuń typ</button>
            </form>
          </div>
          <div class="card-body">
            <p class="text-muted small"><?= htmlspecialchars((string)$type['description']) ?></p>
            
            <h6 class="fw-bold mt-4 mb-3">Pola dynamiczne / Atrybuty:</h6>
            <?php if (empty($fields)): ?>
              <p class="text-muted small fst-italic">Brak zdefiniowanych pól dla tego typu.</p>
            <?php else: ?>
              <ul class="list-group list-group-flush mb-3">
                <?php foreach ($fields as $field): ?>
                  <li class="list-group-item bg-transparent border-secondary text-light d-flex justify-content-between align-items-center px-0">
                    <div>
                      <strong><?= htmlspecialchars($field['name']) ?></strong> 
                      <span class="badge bg-secondary ms-2"><?= htmlspecialchars($field['field_type']) ?></span>
                      <div class="small text-muted mt-1">Kolejność (layerorder): <?= $field['display_order'] ?></div>
                    </div>
                    <form method="POST" action="types.php">
                      <input type="hidden" name="action" value="delete_field">
                      <input type="hidden" name="id" value="<?= $field['id'] ?>">
                      <button type="submit" class="btn btn-outline-danger btn-sm px-2 py-0">X</button>
                    </form>
                  </li>
                <?php endforeach; ?>
              </ul>
            <?php endif; ?>

            <form method="POST" action="types.php" class="mt-4 border-top border-secondary pt-3">
              <input type="hidden" name="action" value="add_field">
              <input type="hidden" name="type_id" value="<?= $type['id'] ?>">
              <div class="row g-2 align-items-end">
                <div class="col-12">
                  <label class="form-label small text-muted mb-1">Nazwa pola (np. Windows 11)</label>
                  <input type="text" name="name" class="form-control form-control-sm" required>
                </div>
                <div class="col-5">
                  <label class="form-label small text-muted mb-1">Typ</label>
                  <select name="field_type" class="form-select form-select-sm">
                    <option value="text">Tekst</option>
                    <option value="number">Liczba</option>
                    <option value="date">Data</option>
                    <option value="checkbox">Tak/Nie</option>
                  </select>
                </div>
                <div class="col-3">
                  <label class="form-label small text-muted mb-1">Order</label>
                  <input type="number" name="display_order" class="form-control form-control-sm" value="0">
                </div>
                <div class="col-4">
                  <button type="submit" class="btn btn-success btn-sm w-100">Dodaj</button>
                </div>
              </div>
            </form>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>
</body>
</html>
