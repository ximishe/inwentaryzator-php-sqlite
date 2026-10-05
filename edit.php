<?php
declare(strict_types=1);

require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/inc/auth.php';

require_login();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    die('Brak ID urządzenia.');
}

$user_role = $_SESSION['role'] ?? 'user';
$username = get_logged_username();
$err = '';
$ok = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');
    
    if ($action === 'update_device') {
        // Fetch current to compare
        $stmt_current = db()->prepare("SELECT * FROM device WHERE id = :id");
        $stmt_current->execute([':id' => $id]);
        $old_device = $stmt_current->fetch();
        
        if (!$old_device) {
            die('Urządzenie nie istnieje.');
        }

        $new_asset_number = trim((string)($_POST['asset_number'] ?? ''));
        $new_manufacturer = trim((string)($_POST['manufacturer'] ?? ''));
        $new_model = trim((string)($_POST['model'] ?? ''));
        $new_serial_number = trim((string)($_POST['serial_number'] ?? ''));
        $new_purchase_date = trim((string)($_POST['purchase_date'] ?? ''));
        $new_purchase_price = (float)($_POST['purchase_price'] ?? 0);
        $new_invoice = trim((string)($_POST['invoice'] ?? ''));
        $new_warranty_until = trim((string)($_POST['warranty_until'] ?? ''));
        $new_user_id = (int)($_POST['assigned_user_id'] ?? 0);
        $new_room = trim((string)($_POST['room'] ?? ''));
        $new_status = trim((string)($_POST['status'] ?? 'ACTIVE'));
        $new_description = trim((string)($_POST['description'] ?? ''));

        if ($new_asset_number === '') {
            $err = 'Wymagane jest pole: Numer inwentarzowy.';
        } else {
            try {
                db()->beginTransaction();
                
                // Compare values and create history logs
                
                // Fields to compare
                $fields_map = [
                    'asset_number' => 'Numer inwentarzowy',
                    'manufacturer' => 'Producent',
                    'model' => 'Model',
                    'serial_number' => 'Numer seryjny',
                    'purchase_date' => 'Data zakupu',
                    'purchase_price' => 'Cena zakupu',
                    'invoice' => 'Nr faktury',
                    'warranty_until' => 'Gwarancja do',
                    'description' => 'Opis',
                ];
                
                $post_values = [
                    'asset_number' => $new_asset_number,
                    'manufacturer' => $new_manufacturer,
                    'model' => $new_model,
                    'serial_number' => $new_serial_number,
                    'purchase_date' => $new_purchase_date,
                    'purchase_price' => $new_purchase_price > 0 ? (string)$new_purchase_price : null,
                    'invoice' => $new_invoice,
                    'warranty_until' => $new_warranty_until,
                    'description' => $new_description,
                ];
                
                foreach ($fields_map as $db_field => $pl_name) {
                    $old_val = (string)$old_device[$db_field];
                    $new_val = (string)$post_values[$db_field];
                    if ($post_values[$db_field] === null && $old_device[$db_field] === null) continue;
                    
                    if ($old_val !== $new_val) {
                        $hist = db()->prepare("INSERT INTO device_history (device_id, event_type, field_name, old_value, new_value, description, changed_by) VALUES (?, 'UPDATE', ?, ?, ?, ?, ?)");
                        $hist->execute([$id, $db_field, $old_val, $new_val, "Zmieniono pole $pl_name", $username]);
                    }
                }
                
                // Specific logic for assignment, move, status
                $old_uid = $old_device['user_id'] ? (int)$old_device['user_id'] : null;
                $new_uid = $new_user_id > 0 ? $new_user_id : null;
                
                if ($old_uid !== $new_uid) {
                    $event = ($new_uid === null) ? 'RETURN' : ($old_uid === null ? 'ASSIGN' : 'TRANSFER');
                    $hist = db()->prepare("INSERT INTO device_history (device_id, event_type, old_user_id, new_user_id, description, changed_by) VALUES (?, ?, ?, ?, 'Zmiana przypisania pracownika', ?)");
                    $hist->execute([$id, $event, $old_uid, $new_uid, $username]);
                }
                
                if ((string)$old_device['room'] !== $new_room) {
                    $hist = db()->prepare("INSERT INTO device_history (device_id, event_type, old_room, new_room, description, changed_by) VALUES (?, 'MOVE', ?, ?, 'Przeniesienie do innego pokoju/lokalizacji', ?)");
                    $hist->execute([$id, (string)$old_device['room'], $new_room, $username]);
                }
                
                if ((string)$old_device['status'] !== $new_status) {
                    $hist = db()->prepare("INSERT INTO device_history (device_id, event_type, old_status, new_status, description, changed_by) VALUES (?, 'STATUS_CHANGE', ?, ?, 'Zmiana statusu urządzenia', ?)");
                    $hist->execute([$id, (string)$old_device['status'], $new_status, $username]);
                }
                
                // Finally, update device
                $stmt = db()->prepare("
                    UPDATE device SET 
                        asset_number = :a, manufacturer = :b, model = :c, serial_number = :d, 
                        purchase_date = :e, purchase_price = :f, invoice = :g, warranty_until = :h,
                        user_id = :i, room = :j, status = :k, description = :l, updated_at = CURRENT_TIMESTAMP
                    WHERE id = :id
                ");
                $stmt->execute([
                    ':a' => $new_asset_number,
                    ':b' => $new_manufacturer !== '' ? $new_manufacturer : null,
                    ':c' => $new_model !== '' ? $new_model : null,
                    ':d' => $new_serial_number !== '' ? $new_serial_number : null,
                    ':e' => $new_purchase_date !== '' ? $new_purchase_date : null,
                    ':f' => $new_purchase_price > 0 ? $new_purchase_price : null,
                    ':g' => $new_invoice !== '' ? $new_invoice : null,
                    ':h' => $new_warranty_until !== '' ? $new_warranty_until : null,
                    ':i' => $new_uid,
                    ':j' => $new_room !== '' ? $new_room : null,
                    ':k' => $new_status,
                    ':l' => $new_description !== '' ? $new_description : null,
                    ':id' => $id
                ]);

                db()->commit();
                $ok = 'Zapisano zmiany.';
            } catch (Throwable $e) {
                if (db()->inTransaction()) {
                    db()->rollBack();
                }
                $err = 'Błąd aktualizacji: ' . $e->getMessage();
            }
        }
    }
}

// Fetch device data
$stmt = db()->prepare("SELECT * FROM device WHERE id = :id");
$stmt->execute([':id' => $id]);
$device = $stmt->fetch();

if (!$device) {
    die("Urządzenie nie zostało znalezione.");
}

// Fetch users for assignment dropdown
$all_users = db()->query("SELECT id, first_name, last_name, username FROM users WHERE role = 'user' ORDER BY last_name ASC")->fetchAll();

// Fetch history
$hist_stmt = db()->prepare("
    SELECT h.*, 
           u1.first_name as o_fn, u1.last_name as o_ln, 
           u2.first_name as n_fn, u2.last_name as n_ln 
    FROM device_history h
    LEFT JOIN users u1 ON h.old_user_id = u1.id
    LEFT JOIN users u2 ON h.new_user_id = u2.id
    WHERE h.device_id = :id 
    ORDER BY h.date DESC, h.id DESC
");
$hist_stmt->execute([':id' => $id]);
$history = $hist_stmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="pl" data-bs-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Zarządzaj urządzeniem - Inwentaryzator Mobile</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/style.css">
</head>
<body>

  <nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4 shadow-sm">
    <div class="container">
      <a class="navbar-brand fw-bold" href="index.php">⬅ Wróć do listy</a>
    </div>
  </nav>

  <div class="container pb-5">
    <?php if ($err): ?><div class="alert alert-danger"><?= htmlspecialchars($err) ?></div><?php endif; ?>
    <?php if ($ok): ?><div class="alert alert-success"><?= htmlspecialchars($ok) ?></div><?php endif; ?>

    <div class="row">
      <!-- Edit form -->
      <div class="col-lg-6 mb-4">
        <div class="card p-4">
          <h4 class="fw-bold mb-4 text-primary">Szczegóły urządzenia</h4>
          <form method="POST">
            <input type="hidden" name="action" value="update_device">
            
            <div class="row">
              <div class="col-md-6 mb-3">
                <label class="form-label small fw-semibold">Nr inwentarzowy</label>
                <input type="text" class="form-control" name="asset_number" value="<?= htmlspecialchars((string)$device['asset_number']) ?>" required>
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label small fw-semibold">S/N</label>
                <input type="text" class="form-control" name="serial_number" value="<?= htmlspecialchars((string)$device['serial_number']) ?>">
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label small fw-semibold">Producent</label>
                <input type="text" class="form-control" name="manufacturer" value="<?= htmlspecialchars((string)$device['manufacturer']) ?>">
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label small fw-semibold">Model</label>
                <input type="text" class="form-control" name="model" value="<?= htmlspecialchars((string)$device['model']) ?>">
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label small fw-semibold">Data zakupu</label>
                <input type="date" class="form-control" name="purchase_date" value="<?= htmlspecialchars((string)$device['purchase_date']) ?>">
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label small fw-semibold">Cena (PLN)</label>
                <input type="number" step="0.01" class="form-control" name="purchase_price" value="<?= $device['purchase_price'] ? htmlspecialchars((string)$device['purchase_price']) : '' ?>">
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label small fw-semibold">Faktura</label>
                <input type="text" class="form-control" name="invoice" value="<?= htmlspecialchars((string)$device['invoice']) ?>">
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label small fw-semibold">Gwarancja do</label>
                <input type="date" class="form-control" name="warranty_until" value="<?= htmlspecialchars((string)$device['warranty_until']) ?>">
              </div>
              
              <hr class="my-3">
              
              <div class="col-md-12 mb-3">
                <label class="form-label small fw-semibold">Przypisany pracownik</label>
                <select class="form-select" name="assigned_user_id">
                  <option value="">-- Brak (Magazyn) --</option>
                  <?php foreach ($all_users as $u): ?>
                    <option value="<?= $u['id'] ?>" <?= $device['user_id'] == $u['id'] ? 'selected' : '' ?>>
                      <?= htmlspecialchars($u['first_name'] . ' ' . $u['last_name']) ?> (<?= htmlspecialchars($u['username']) ?>)
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label small fw-semibold">Lokalizacja (Pokój)</label>
                <input type="text" class="form-control" name="room" value="<?= htmlspecialchars((string)$device['room']) ?>">
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label small fw-semibold">Status</label>
                <select class="form-select" name="status">
                  <?php 
                  $statuses = ['ACTIVE', 'WAREHOUSE', 'RESERVED', 'REPAIR', 'DISPOSED', 'LOST'];
                  foreach ($statuses as $st): ?>
                    <option value="<?= $st ?>" <?= $device['status'] === $st ? 'selected' : '' ?>><?= $st ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-12 mb-3">
                <label class="form-label small fw-semibold">Opis / Uwagi</label>
                <textarea class="form-control" name="description" rows="2"><?= htmlspecialchars((string)$device['description']) ?></textarea>
              </div>
            </div>
            
            <button type="submit" class="btn btn-primary w-100">💾 Zapisz zmiany</button>
          </form>
        </div>
      </div>

      <!-- History -->
      <div class="col-lg-6">
        <div class="card p-4 h-100">
          <h4 class="fw-bold mb-4 text-primary">Historia urządzenia</h4>
          <div class="timeline">
            <?php foreach ($history as $h): ?>
              <div class="timeline-item">
                <div class="timeline-icon <?= $h['event_type'] === 'CREATE' ? 'bg-success border-success' : '' ?>"></div>
                <div class="small text-muted mb-1"><?= htmlspecialchars($h['date']) ?> przez <strong><?= htmlspecialchars($h['changed_by']) ?></strong></div>
                
                <h6 class="fw-bold mb-1">
                  <?php 
                  switch($h['event_type']) {
                    case 'CREATE': echo "Utworzenie"; break;
                    case 'UPDATE': echo "Edycja danych"; break;
                    case 'ASSIGN': echo "Wydanie pracownikowi"; break;
                    case 'RETURN': echo "Zwrot do magazynu"; break;
                    case 'TRANSFER': echo "Przekazanie urządzenia"; break;
                    case 'MOVE': echo "Zmiana lokalizacji (pokoju)"; break;
                    case 'STATUS_CHANGE': echo "Zmiana statusu"; break;
                    default: echo htmlspecialchars($h['event_type']);
                  }
                  ?>
                </h6>
                
                <div class="small p-2 rounded" style="background-color: var(--bs-tertiary-bg);">
                  <?php if ($h['description']): ?>
                    <div><?= htmlspecialchars($h['description']) ?></div>
                  <?php endif; ?>
                  
                  <?php if ($h['event_type'] === 'UPDATE' && $h['field_name']): ?>
                    <div class="mt-1">
                      <span class="text-danger text-decoration-line-through"><?= htmlspecialchars((string)$h['old_value']) ?: '<i>(pusto)</i>' ?></span> 
                      &rarr; 
                      <span class="text-success fw-bold"><?= htmlspecialchars((string)$h['new_value']) ?: '<i>(pusto)</i>' ?></span>
                    </div>
                  <?php endif; ?>

                  <?php if ($h['event_type'] === 'ASSIGN' || $h['event_type'] === 'TRANSFER' || $h['event_type'] === 'RETURN'): ?>
                    <div class="mt-1">
                      Od: <strong><?= $h['old_user_id'] ? htmlspecialchars($h['o_fn'].' '.$h['o_ln']) : 'Magazyn' ?></strong> 
                      &rarr; 
                      Do: <strong><?= $h['new_user_id'] ? htmlspecialchars($h['n_fn'].' '.$h['n_ln']) : 'Magazyn' ?></strong>
                    </div>
                  <?php endif; ?>
                  
                  <?php if ($h['event_type'] === 'MOVE'): ?>
                    <div class="mt-1">
                      Z: <strong><?= htmlspecialchars((string)$h['old_room']) ?: 'Brak' ?></strong> 
                      &rarr; 
                      Do: <strong><?= htmlspecialchars((string)$h['new_room']) ?: 'Brak' ?></strong>
                    </div>
                  <?php endif; ?>
                  
                  <?php if ($h['event_type'] === 'STATUS_CHANGE'): ?>
                    <div class="mt-1">
                      Z: <span class="badge bg-secondary"><?= htmlspecialchars((string)$h['old_status']) ?></span> 
                      &rarr; 
                      Do: <span class="badge bg-primary"><?= htmlspecialchars((string)$h['new_status']) ?></span>
                    </div>
                  <?php endif; ?>
                </div>
              </div>
            <?php endforeach; ?>
            <?php if (empty($history)): ?>
              <div class="text-muted small">Brak wpisów w historii.</div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>

  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
