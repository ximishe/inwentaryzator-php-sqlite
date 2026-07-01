<?php
declare(strict_types=1);

require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/inc/auth.php';

require_login();

$user_id = get_logged_user_id();
$user_role = $_SESSION['role'] ?? 'user';
$username = get_logged_username();

$err = '';
$ok = '';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');

    // Add new inventory item
    if ($action === 'create_item') {
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
                    
                    // Check duplicate case-insensitive
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

                    // Check duplicate case-insensitive
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

                // Insert inventory item
                $stmt = db()->prepare("
                    INSERT INTO inventory (room_number, device_type_id, manufacturer_id, serial_number, inventory_number, notes, created_by)
                    VALUES (:room, :type, :manufacturer, :serial, :inventory, :notes, :created_by)
                ");
                $stmt->execute([
                    ':room' => $room_number,
                    ':type' => $device_type_id,
                    ':manufacturer' => $manufacturer_id,
                    ':serial' => $serial_number,
                    ':inventory' => $inventory_number,
                    ':notes' => $notes !== '' ? $notes : null,
                    ':created_by' => $user_id
                ]);

                db()->commit();
                $ok = 'Urządzenie zostało pomyślnie dodane do inwentarza!';
            } catch (Throwable $e) {
                if (db()->inTransaction()) {
                    db()->rollBack();
                }
                $err = 'Błąd zapisu: ' . $e->getMessage();
            }
        }
    }

    // Delete item
    if ($action === 'delete') {
        $item_id = (int)($_POST['item_id'] ?? 0);
        if ($item_id > 0) {
            try {
                if ($user_role === 'admin') {
                    $stmt = db()->prepare("DELETE FROM inventory WHERE id = :id");
                    $stmt->execute([':id' => $item_id]);
                } else {
                    $stmt = db()->prepare("DELETE FROM inventory WHERE id = :id AND created_by = :created_by");
                    $stmt->execute([':id' => $item_id, ':created_by' => $user_id]);
                }
                $ok = 'Wpis z inwentarza został usunięty.';
            } catch (PDOException $e) {
                $err = 'Błąd usuwania: ' . $e->getMessage();
            }
        }
    }
}

// Fetch search and filter values from GET
$search = trim((string)($_GET['q'] ?? ''));
$filter_room = trim((string)($_GET['room'] ?? ''));
$filter_type = trim((string)($_GET['type'] ?? ''));
$filter_manufacturer = trim((string)($_GET['manufacturer'] ?? ''));

// SQL query construction
$sql = "
    SELECT i.*, m.name AS manufacturer_name, d.name AS device_type_name, u.username AS creator_name 
    FROM inventory i 
    LEFT JOIN manufacturers m ON i.manufacturer_id = m.id 
    LEFT JOIN device_types d ON i.device_type_id = d.id 
    JOIN users u ON i.created_by = u.id
";

$where = [];
$params = [];

if ($search !== '') {
    $where[] = "(i.serial_number LIKE :search OR i.inventory_number LIKE :search OR i.room_number LIKE :search OR i.notes LIKE :search)";
    $params[':search'] = '%' . $search . '%';
}
if ($filter_room !== '') {
    $where[] = "i.room_number = :room";
    $params[':room'] = $filter_room;
}
if ($filter_type !== '') {
    $where[] = "i.device_type_id = :type";
    $params[':type'] = (int)$filter_type;
}
if ($filter_manufacturer !== '') {
    $where[] = "i.manufacturer_id = :manufacturer";
    $params[':manufacturer'] = (int)$filter_manufacturer;
}

if (!empty($where)) {
    $sql .= " WHERE " . implode(" AND ", $where);
}

$sql .= " ORDER BY i.created_at DESC";

try {
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $items = $stmt->fetchAll();

    // Fetch filters lists
    $rooms = db()->query("SELECT DISTINCT room_number FROM inventory WHERE room_number != '' ORDER BY room_number ASC")->fetchAll(PDO::FETCH_COLUMN);
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
  <title>Panel Główny - Inwentaryzator Mobile</title>
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
        <?php if ($user_role === 'admin'): ?>
          <a href="users.php" class="btn btn-outline-light btn-sm me-2">Użytkownicy</a>
        <?php endif; ?>
        <a href="logout.php" class="btn btn-danger btn-sm">Wyloguj</a>
      </div>
    </div>
  </nav>

  <div class="container pb-5">
    
    <!-- Toast notifications -->
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

    <!-- Collapsible ADD inventory Form -->
    <div class="accordion mb-4" id="accordionAdd">
      <div class="accordion-item border-0 shadow-sm rounded">
        <h2 class="accordion-header" id="headingAdd">
          <button class="accordion-button fw-bold text-primary bg-white rounded" type="button" data-bs-toggle="collapse" data-bs-target="#collapseAdd" aria-expanded="true" aria-controls="collapseAdd">
            📋 Dodaj nowy sprzęt do inwentarza
          </button>
        </h2>
        <div id="collapseAdd" class="accordion-collapse collapse show" aria-labelledby="headingAdd" data-bs-parent="#accordionAdd">
          <div class="accordion-body bg-white">
            <form method="POST" action="index.php">
              <input type="hidden" name="action" value="create_item">
              
              <div class="row">
                <!-- Numer pokoju -->
                <div class="col-md-6 mb-3">
                  <label for="room_number" class="form-label small fw-semibold">Numer pokoju</label>
                  <input type="text" class="form-control form-control-sm" id="room_number" name="room_number" required placeholder="np. 102, Magazyn A">
                </div>

                <!-- Typ urządzenia -->
                <div class="col-md-6 mb-3">
                  <label for="device_type_id" class="form-label small fw-semibold">Typ urządzenia</label>
                  <select class="form-select form-select-sm" id="device_type_id" name="device_type_id" onchange="toggleCustomInput('device_type_id', 'custom_device_type_div', 'device_type_custom')" required>
                    <option value="">-- Wybierz typ --</option>
                    <?php foreach ($device_types as $d): ?>
                      <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['name']) ?></option>
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
                      <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['name']) ?></option>
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
                  <input type="text" class="form-control form-control-sm" id="serial_number" name="serial_number" required placeholder="np. S/N, Service Tag">
                </div>

                <!-- Numer inwentarzowy -->
                <div class="col-md-6 mb-3">
                  <label for="inventory_number" class="form-label small fw-semibold">Numer inwentarzowy</label>
                  <input type="text" class="form-control form-control-sm" id="inventory_number" name="inventory_number" required placeholder="np. INW/2026/001">
                </div>

                <!-- Uwagi -->
                <div class="col-md-6 mb-3">
                  <label for="notes" class="form-label small fw-semibold">Uwagi</label>
                  <textarea class="form-control form-control-sm" id="notes" name="notes" rows="2" placeholder="Opcjonalne uwagi..."></textarea>
                </div>
              </div>

              <div class="text-end">
                <button type="submit" class="btn btn-primary btn-sm px-4 py-2">💾 Zapisz urządzenie</button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>

    <!-- Filters and CRM export -->
    <div class="card border-0 shadow-sm mb-4">
      <div class="card-body">
        <h5 class="card-title fw-bold text-primary mb-3">Filtrowanie i Eksport</h5>
        <form method="GET" action="index.php" class="row g-2 mb-3">
          <div class="col-6 col-sm-3">
            <input type="text" name="q" class="form-control form-control-sm" placeholder="Szukaj..." value="<?= htmlspecialchars($search) ?>">
          </div>
          <div class="col-6 col-sm-3">
            <select name="room" class="form-select form-select-sm">
              <option value="">Wszystkie pokoje</option>
              <?php foreach ($rooms as $room): ?>
                <option value="<?= htmlspecialchars($room) ?>" <?= $filter_room === $room ? 'selected' : '' ?>><?= htmlspecialchars($room) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-6 col-sm-3">
            <select name="type" class="form-select form-select-sm">
              <option value="">Wszystkie typy</option>
              <?php foreach ($device_types as $d): ?>
                <option value="<?= $d['id'] ?>" <?= $filter_type === (string)$d['id'] ? 'selected' : '' ?>><?= htmlspecialchars($d['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-6 col-sm-3">
            <select name="manufacturer" class="form-select form-select-sm">
              <option value="">Wszyscy producenci</option>
              <?php foreach ($manufacturers as $m): ?>
                <option value="<?= $m['id'] ?>" <?= $filter_manufacturer === (string)$m['id'] ? 'selected' : '' ?>><?= htmlspecialchars($m['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-12 text-end">
            <?php if ($search !== '' || $filter_room !== '' || $filter_type !== '' || $filter_manufacturer !== ''): ?>
              <a href="index.php" class="btn btn-secondary btn-sm me-1">Wyczyść</a>
            <?php endif; ?>
            <button type="submit" class="btn btn-primary btn-sm px-3">Szukaj</button>
            <a href="export.php?<?= http_build_query($_GET) ?>" class="btn btn-success btn-sm px-3 ms-1" target="_blank">📥 Eksportuj do CRM (CSV)</a>
          </div>
        </form>
      </div>
    </div>

    <!-- Inventory list (Responsive design) -->
    <h5 class="fw-bold text-primary mb-3">Zapisane urządzenia (<?= count($items) ?>)</h5>
    
    <!-- Grid for Cards on Mobile, Table on Desktop -->
    <div class="row d-md-none">
      <!-- Mobile list -->
      <?php if (empty($items)): ?>
        <div class="col-12 text-center text-muted py-4">Brak wpisów pasujących do kryteriów wyszukiwania.</div>
      <?php endif; ?>
      <?php foreach ($items as $item): ?>
        <div class="col-12 mb-3">
          <div class="card border-0 shadow-sm">
            <div class="card-body">
              <div class="d-flex justify-content-between align-items-start mb-2">
                <span class="badge bg-secondary">Pokój: <?= htmlspecialchars($item['room_number']) ?></span>
                <div class="dropdown">
                  <button class="btn btn-light btn-sm" type="button" data-bs-toggle="dropdown" aria-expanded="false">⚙️</button>
                  <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-menu-item text-primary px-3 py-1 d-block text-decoration-none" href="edit.php?id=<?= $item['id'] ?>">Edytuj</a></li>
                    <li>
                      <form method="POST" action="index.php" onsubmit="return confirm('Czy na pewno chcesz usunąć to urządzenie z inwentarza?');">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="item_id" value="<?= $item['id'] ?>">
                        <button type="submit" class="dropdown-menu-item text-danger border-0 bg-transparent px-3 py-1 text-start w-100">Usuń</button>
                      </form>
                    </li>
                  </ul>
                </div>
              </div>
              <h6 class="fw-bold card-title mb-1"><?= htmlspecialchars($item['device_type_name'] ?? 'Nieokreślony') ?> - <?= htmlspecialchars($item['manufacturer_name'] ?? 'Nieokreślony') ?></h6>
              <div class="small mb-1"><strong>S/N:</strong> <code class="text-dark"><?= htmlspecialchars($item['serial_number']) ?></code></div>
              <div class="small mb-1"><strong>Inw:</strong> <code><?= htmlspecialchars($item['inventory_number']) ?></code></div>
              <?php if ($item['notes']): ?>
                <div class="small p-2 bg-light rounded text-muted mt-2 border-start border-primary" style="font-style: italic;">
                  <?= htmlspecialchars($item['notes']) ?>
                </div>
              <?php endif; ?>
              <div class="text-end text-muted mt-2" style="font-size: 11px;">
                Dodane przez: <?= htmlspecialchars($item['creator_name']) ?> (<?= substr($item['created_at'], 0, 16) ?>)
              </div>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <!-- Desktop list -->
    <div class="card border-0 shadow-sm d-none d-md-block">
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0 small">
            <thead class="table-light text-muted">
              <tr>
                <th>Pokój</th>
                <th>Typ urządzenia</th>
                <th>Producent</th>
                <th>Numer seryjny</th>
                <th>Numer inwentarzowy</th>
                <th>Uwagi</th>
                <th>Kto dodał</th>
                <th class="text-end px-3">Opcje</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($items)): ?>
                <tr>
                  <td colspan="8" class="text-center text-muted py-4">Brak wpisów pasujących do kryteriów wyszukiwania.</td>
                </tr>
              <?php endif; ?>
              <?php foreach ($items as $item): ?>
                <tr>
                  <td><strong><?= htmlspecialchars($item['room_number']) ?></strong></td>
                  <td><?= htmlspecialchars($item['device_type_name'] ?? 'Nieokreślony') ?></td>
                  <td><?= htmlspecialchars($item['manufacturer_name'] ?? 'Nieokreślony') ?></td>
                  <td><code class="text-dark"><?= htmlspecialchars($item['serial_number']) ?></code></td>
                  <td><code><?= htmlspecialchars($item['inventory_number']) ?></code></td>
                  <td class="text-muted" style="max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?= htmlspecialchars($item['notes'] ?? '') ?>"><?= htmlspecialchars($item['notes'] ?? '') ?></td>
                  <td><?= htmlspecialchars($item['creator_name']) ?></td>
                  <td class="text-end px-3">
                    <a href="edit.php?id=<?= $item['id'] ?>" class="btn btn-outline-primary btn-sm me-1 py-1">Edytuj</a>
                    <form method="POST" action="index.php" class="d-inline" onsubmit="return confirm('Czy na pewno chcesz usunąć to urządzenie z inwentarza?');">
                      <input type="hidden" name="action" value="delete">
                      <input type="hidden" name="item_id" value="<?= $item['id'] ?>">
                      <button type="submit" class="btn btn-outline-danger btn-sm py-1">Usuń</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

  </div>

  <!-- Bootstrap 5 JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  
  <!-- JavaScript for conditional custom fields -->
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
