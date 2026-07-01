<?php
declare(strict_types=1);

require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/inc/auth.php';

require_login();

$user_id = get_logged_user_id();
$user_role = $_SESSION['role'] ?? 'user';
$username = get_logged_username();

$item_id = (int)($_GET['id'] ?? 0);
if ($item_id <= 0) {
    header('Location: index.php');
    exit;
}

try {
    $stmt = db()->prepare("SELECT * FROM inventory WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $item_id]);
    $item = $stmt->fetch();
} catch (PDOException $e) {
    die('Błąd bazy danych: ' . htmlspecialchars($e->getMessage()));
}

if (!$item) {
    header('Location: index.php');
    exit;
}

// Check authorization: only admin or the creator can edit
if ($user_role !== 'admin' && (int)$item['created_by'] !== $user_id) {
    header('Location: index.php');
    exit;
}

$err = '';
$ok = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $room_number = trim((string)($_POST['room_number'] ?? ''));
    $device_type_id = $_POST['device_type_id'] ?? '';
    $manufacturer_id = $_POST['manufacturer_id'] ?? '';
    $serial_number = trim((string)($_POST['serial_number'] ?? ''));
    $inventory_number = trim((string)($_POST['inventory_number'] ?? ''));
    $notes = trim((string)($_POST['notes'] ?? ''));

    if ($room_number === '' || $serial_number === '' || $inventory_number === '') {
        $err = 'Wymagane są pola: Numer pokoju, Numer seryjny, Numer inwentarzowy.';
    } else {
        try {
            db()->beginTransaction();

            // Handle custom device type
            if ($device_type_id === 'custom') {
                $custom_type = trim((string)($_POST['device_type_custom'] ?? ''));
                if ($custom_type === '') {
                    throw new Exception('Wpisz własny typ urządzenia.');
                }
                
                $stmt = db()->prepare("SELECT id FROM device_types WHERE LOWER(name) = LOWER(:name) LIMIT 1");
                $stmt->execute([':name' => $custom_type]);
                $row = $stmt->fetch();
                if ($row) {
                    $device_type_id = (int)$row['id'];
                } else {
                    $insert = db()->prepare("INSERT INTO device_types (name) VALUES (:name)");
                    $insert->execute([':name' => $custom_type]);
                    $device_type_id = (int)db()->lastInsertId();
                }
            } else {
                $device_type_id = $device_type_id !== '' ? (int)$device_type_id : null;
            }

            // Handle custom manufacturer
            if ($manufacturer_id === 'custom') {
                $custom_man = trim((string)($_POST['manufacturer_custom'] ?? ''));
                if ($custom_man === '') {
                    throw new Exception('Wpisz własnego producenta.');
                }

                $stmt = db()->prepare("SELECT id FROM manufacturers WHERE LOWER(name) = LOWER(:name) LIMIT 1");
                $stmt->execute([':name' => $custom_man]);
                $row = $stmt->fetch();
                if ($row) {
                    $manufacturer_id = (int)$row['id'];
                } else {
                    $insert = db()->prepare("INSERT INTO manufacturers (name) VALUES (:name)");
                    $insert->execute([':name' => $custom_man]);
                    $manufacturer_id = (int)db()->lastInsertId();
                }
            } else {
                $manufacturer_id = $manufacturer_id !== '' ? (int)$manufacturer_id : null;
            }

            // Update item
            $stmt = db()->prepare("
                UPDATE inventory 
                SET room_number = :room, 
                    device_type_id = :type, 
                    manufacturer_id = :manufacturer, 
                    serial_number = :serial, 
                    inventory_number = :inventory, 
                    notes = :notes,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :id
            ");
            $stmt->execute([
                ':room' => $room_number,
                ':type' => $device_type_id,
                ':manufacturer' => $manufacturer_id,
                ':serial' => $serial_number,
                ':inventory' => $inventory_number,
                ':notes' => $notes !== '' ? $notes : null,
                ':id' => $item_id
            ]);

            db()->commit();
            
            // Reload updated data
            $stmt = db()->prepare("SELECT * FROM inventory WHERE id = :id LIMIT 1");
            $stmt->execute([':id' => $item_id]);
            $item = $stmt->fetch();

            $ok = 'Dane urządzenia zostały pomyślnie zaktualizowane!';
        } catch (Throwable $e) {
            if (db()->inTransaction()) {
                db()->rollBack();
            }
            $err = 'Błąd zapisu: ' . $e->getMessage();
        }
    }
}

// Fetch helper data
try {
    $device_types = db()->query("SELECT * FROM device_types ORDER BY name ASC")->fetchAll();
    $manufacturers = db()->query("SELECT * FROM manufacturers ORDER BY name ASC")->fetchAll();
} catch (PDOException $e) {
    die('Błąd pobierania danych: ' . htmlspecialchars($e->getMessage()));
}
?>
<!DOCTYPE html>
<html lang="pl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Edycja Urządzenia - Inwentaryzator</title>
  <!-- Bootstrap 5 CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

  <!-- Navbar -->
  <nav class="navbar navbar-expand-lg navbar-dark bg-primary mb-4 shadow-sm">
    <div class="container">
      <a class="navbar-brand fw-bold" href="index.php">Inwentaryzator</a>
      <div class="d-flex align-items-center text-white">
        <span class="me-3 d-none d-sm-inline">Witaj, <strong><?= htmlspecialchars($username) ?></strong></span>
        <a href="index.php" class="btn btn-outline-light btn-sm me-2">Panel główny</a>
        <a href="logout.php" class="btn btn-danger btn-sm">Wyloguj</a>
      </div>
    </div>
  </nav>

  <div class="container pb-5">
    <div class="row justify-content-center">
      <div class="col-md-8">
        
        <?php if ($err): ?>
          <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($err) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Zamknij"></button>
          </div>
        <?php endif; ?>
        <?php if ($ok): ?>
          <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($ok) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Zamknij"></button>
          </div>
        <?php endif; ?>

        <div class="card border-0 shadow-sm rounded">
          <div class="card-body p-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
              <h5 class="fw-bold text-primary mb-0">✏️ Edycja sprzętu w inwentarzu</h5>
              <a href="index.php" class="btn btn-secondary btn-sm">Wróć</a>
            </div>

            <form method="POST" action="edit.php?id=<?= $item_id ?>">
              <div class="row">
                <!-- Numer pokoju -->
                <div class="col-md-6 mb-3">
                  <label for="room_number" class="form-label small fw-semibold">Numer pokoju</label>
                  <input type="text" class="form-control form-control-sm" id="room_number" name="room_number" required value="<?= htmlspecialchars($item['room_number']) ?>">
                </div>

                <!-- Typ urządzenia -->
                <div class="col-md-6 mb-3">
                  <label for="device_type_id" class="form-label small fw-semibold">Typ urządzenia</label>
                  <select class="form-select form-select-sm" id="device_type_id" name="device_type_id" onchange="toggleCustomInput('device_type_id', 'custom_device_type_div', 'device_type_custom')" required>
                    <option value="">-- Wybierz typ --</option>
                    <?php foreach ($device_types as $d): ?>
                      <option value="<?= $d['id'] ?>" <?= (int)$item['device_type_id'] === (int)$d['id'] ? 'selected' : '' ?>><?= htmlspecialchars($d['name']) ?></option>
                    <?php endforeach; ?>
                    <option value="custom">Inny (wpisz ręcznie)...</option>
                  </select>
                  <div class="mt-2 d-none" id="custom_device_type_div">
                    <input type="text" class="form-control form-control-sm border-danger" id="device_type_custom" name="device_type_custom" placeholder="Wpisz typ urządzenia">
                  </div>
                </div>

                <!-- Producent -->
                <div class="col-md-6 mb-3">
                  <label for="manufacturer_id" class="form-label small fw-semibold">Producent</label>
                  <select class="form-select form-select-sm" id="manufacturer_id" name="manufacturer_id" onchange="toggleCustomInput('manufacturer_id', 'custom_manufacturer_div', 'manufacturer_custom')" required>
                    <option value="">-- Wybierz producenta --</option>
                    <?php foreach ($manufacturers as $m): ?>
                      <option value="<?= $m['id'] ?>" <?= (int)$item['manufacturer_id'] === (int)$m['id'] ? 'selected' : '' ?>><?= htmlspecialchars($m['name']) ?></option>
                    <?php endforeach; ?>
                    <option value="custom">Inny (wpisz ręcznie)...</option>
                  </select>
                  <div class="mt-2 d-none" id="custom_manufacturer_div">
                    <input type="text" class="form-control form-control-sm border-danger" id="manufacturer_custom" name="manufacturer_custom" placeholder="Wpisz producenta">
                  </div>
                </div>

                <!-- Numer seryjny -->
                <div class="col-md-6 mb-3">
                  <label for="serial_number" class="form-label small fw-semibold">Numer seryjny</label>
                  <input type="text" class="form-control form-control-sm" id="serial_number" name="serial_number" required value="<?= htmlspecialchars($item['serial_number']) ?>">
                </div>

                <!-- Numer inwentarzowy -->
                <div class="col-md-6 mb-3">
                  <label for="inventory_number" class="form-label small fw-semibold">Numer inwentarzowy</label>
                  <input type="text" class="form-control form-control-sm" id="inventory_number" name="inventory_number" required value="<?= htmlspecialchars($item['inventory_number']) ?>">
                </div>

                <!-- Uwagi -->
                <div class="col-md-6 mb-3">
                  <label for="notes" class="form-label small fw-semibold">Uwagi</label>
                  <textarea class="form-control form-control-sm" id="notes" name="notes" rows="2"><?= htmlspecialchars($item['notes'] ?? '') ?></textarea>
                </div>
              </div>

              <div class="text-end mt-3">
                <button type="submit" class="btn btn-primary btn-sm px-4 py-2">💾 Zapisz zmiany</button>
              </div>
            </form>
          </div>
        </div>

      </div>
    </div>
  </div>

  <!-- Bootstrap 5 JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  
  <script>
    function toggleCustomInput(selectId, customDivId, customInputId) {
        const select = document.getElementById(selectId);
        const customDiv = document.getElementById(customDivId);
        const customInput = document.getElementById(customInputId);
        
        if (select.value === 'custom') {
            customDiv.classList.remove('d-none');
            customInput.setAttribute('required', 'required');
            customInput.focus();
        } else {
            customDiv.classList.add('d-none');
            customInput.removeAttribute('required');
            customInput.value = '';
        }
    }
  </script>
</body>
</html>
