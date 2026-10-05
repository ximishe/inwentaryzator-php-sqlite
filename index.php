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

    // Add new device
    if ($action === 'create_device') {
        $asset_number = trim((string)($_POST['asset_number'] ?? ''));
        $manufacturer = trim((string)($_POST['manufacturer'] ?? ''));
        $model = trim((string)($_POST['model'] ?? ''));
        $serial_number = trim((string)($_POST['serial_number'] ?? ''));
        $purchase_date = trim((string)($_POST['purchase_date'] ?? ''));
        $purchase_price = (float)($_POST['purchase_price'] ?? 0);
        $invoice = trim((string)($_POST['invoice'] ?? ''));
        $warranty_until = trim((string)($_POST['warranty_until'] ?? ''));
        $assigned_user_id = (int)($_POST['assigned_user_id'] ?? 0);
        $room = trim((string)($_POST['room'] ?? ''));
        $status = trim((string)($_POST['status'] ?? 'ACTIVE'));
        $description = trim((string)($_POST['description'] ?? ''));

        if ($asset_number === '') {
            $err = 'Wymagane jest pole: Numer inwentarzowy.';
        } else {
            try {
                db()->beginTransaction();

                // Insert device
                $stmt = db()->prepare("
                    INSERT INTO device (asset_number, manufacturer, model, serial_number, purchase_date, purchase_price, invoice, warranty_until, user_id, room, status, description)
                    VALUES (:asset_number, :manufacturer, :model, :serial_number, :purchase_date, :purchase_price, :invoice, :warranty_until, :user_id, :room, :status, :description)
                ");
                $stmt->execute([
                    ':asset_number' => $asset_number,
                    ':manufacturer' => $manufacturer !== '' ? $manufacturer : null,
                    ':model' => $model !== '' ? $model : null,
                    ':serial_number' => $serial_number !== '' ? $serial_number : null,
                    ':purchase_date' => $purchase_date !== '' ? $purchase_date : null,
                    ':purchase_price' => $purchase_price > 0 ? $purchase_price : null,
                    ':invoice' => $invoice !== '' ? $invoice : null,
                    ':warranty_until' => $warranty_until !== '' ? $warranty_until : null,
                    ':user_id' => $assigned_user_id > 0 ? $assigned_user_id : null,
                    ':room' => $room !== '' ? $room : null,
                    ':status' => $status,
                    ':description' => $description !== '' ? $description : null,
                ]);

                $new_device_id = (int)db()->lastInsertId();

                // Add history record: CREATE
                $hist_stmt = db()->prepare("
                    INSERT INTO device_history (device_id, event_type, new_user_id, new_room, new_status, description, changed_by)
                    VALUES (:device_id, 'CREATE', :new_user_id, :new_room, :new_status, 'Utworzono urządzenie w systemie.', :changed_by)
                ");
                $hist_stmt->execute([
                    ':device_id' => $new_device_id,
                    ':new_user_id' => $assigned_user_id > 0 ? $assigned_user_id : null,
                    ':new_room' => $room !== '' ? $room : null,
                    ':new_status' => $status,
                    ':changed_by' => $username
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
}

// Fetch search and filter values from GET
$search = trim((string)($_GET['q'] ?? ''));
$filter_room = trim((string)($_GET['room'] ?? ''));
$filter_status = trim((string)($_GET['status'] ?? ''));
$filter_asset = trim((string)($_GET['asset'] ?? ''));
$filter_model = trim((string)($_GET['model'] ?? ''));
$filter_sn = trim((string)($_GET['sn'] ?? ''));
$filter_user = trim((string)($_GET['user'] ?? ''));

// SQL query construction
$sql = "
    SELECT d.*, u.first_name, u.last_name, u.username as u_login
    FROM device d 
    LEFT JOIN users u ON d.user_id = u.id
";

$where = [];
$params = [];

if ($search !== '') {
    $where[] = "(d.serial_number LIKE :search OR d.asset_number LIKE :search OR d.room LIKE :search OR d.model LIKE :search OR d.manufacturer LIKE :search)";
    $params[':search'] = '%' . $search . '%';
}
if ($filter_room !== '') {
    $where[] = "d.room = :room";
    $params[':room'] = $filter_room;
}
if ($filter_status !== '') {
    $where[] = "d.status = :status";
    $params[':status'] = $filter_status;
}
if ($filter_asset !== '') {
    $where[] = "d.asset_number LIKE :asset";
    $params[':asset'] = '%' . $filter_asset . '%';
}
if ($filter_model !== '') {
    $where[] = "(d.model LIKE :model OR d.manufacturer LIKE :model)";
    $params[':model'] = '%' . $filter_model . '%';
}
if ($filter_sn !== '') {
    $where[] = "d.serial_number LIKE :sn";
    $params[':sn'] = '%' . $filter_sn . '%';
}
if ($filter_user !== '') {
    $where[] = "d.user_id = :user";
    $params[':user'] = $filter_user;
}

if (!empty($where)) {
    $sql .= " WHERE " . implode(" AND ", $where);
}

$sql .= " ORDER BY d.created_at DESC";

try {
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $items = $stmt->fetchAll();

    // Fetch filters lists
    $rooms = db()->query("SELECT DISTINCT room FROM device WHERE room IS NOT NULL AND room != ''")->fetchAll(PDO::FETCH_COLUMN);
    natsort($rooms);
    $statuses = db()->query("SELECT DISTINCT status FROM device")->fetchAll(PDO::FETCH_COLUMN);
    $all_users = db()->query("SELECT id, first_name, last_name, username FROM users WHERE role = 'user' ORDER BY last_name ASC")->fetchAll();

    // Fetch history for displayed items
    $history_by_device = [];
    $device_ids = array_column($items, 'id');
    if (!empty($device_ids)) {
        $placeholders = implode(',', array_fill(0, count($device_ids), '?'));
        $hist_stmt = db()->prepare("
            SELECT h.*, 
                   u1.first_name as o_fn, u1.last_name as o_ln, 
                   u2.first_name as n_fn, u2.last_name as n_ln 
            FROM device_history h
            LEFT JOIN users u1 ON h.old_user_id = u1.id
            LEFT JOIN users u2 ON h.new_user_id = u2.id
            WHERE h.device_id IN ($placeholders) 
            ORDER BY h.date DESC, h.id DESC
        ");
        $hist_stmt->execute($device_ids);
        $all_history = $hist_stmt->fetchAll();
        foreach ($all_history as $h) {
            $history_by_device[$h['device_id']][] = $h;
        }
    }

} catch (PDOException $e) {
    die('Błąd pobierania danych: ' . htmlspecialchars($e->getMessage()));
}
?>
<!DOCTYPE html>
<html lang="pl" data-bs-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Panel Główny - Inwentaryzator Mobile</title>
  <!-- Bootstrap 5 CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Google Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  
  <link rel="stylesheet" href="assets/style.css">
</head>
<body>

  <nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4 shadow-sm">
    <div class="container d-flex justify-content-between align-items-center">
      <a class="navbar-brand fw-bold" href="index.php">Inwentaryzator</a>
      <div class="d-flex align-items-center text-white">
        <span class="me-3 d-none d-sm-inline">Witaj, <strong><?= htmlspecialchars($username) ?></strong></span>
        <?php if ($user_role === 'admin'): ?>
          <a href="users.php" class="btn btn-outline-light btn-sm me-2">Użytkownicy</a>
          <a href="types.php" class="btn btn-outline-info btn-sm me-2">Struktura bazy</a>
        <?php endif; ?>
        <a href="logout.php" class="btn btn-danger btn-sm">Wyloguj</a>
      </div>
    </div>
  </nav>

  <div class="container pb-5">
    
    <?php if ($err): ?>
      <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?= htmlspecialchars($err) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    <?php endif; ?>
    <?php if ($ok): ?>
      <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?= htmlspecialchars($ok) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    <?php endif; ?>

    <!-- Top Action Bar -->
    <div class="d-flex justify-content-between align-items-center mb-4">
      <h3 class="fw-bold mb-0">Dashboard sprzętu</h3>
      <button type="button" class="btn btn-primary fw-bold px-4 py-2" data-bs-toggle="modal" data-bs-target="#addDeviceModal">
        + Dodaj sprzęt
      </button>
    </div>

    <!-- Filters -->
    <div class="card border-0 shadow-sm mb-4">
      <div class="card-body">
        <form method="GET" action="index.php" class="row g-2 align-items-center">
          <div class="col-12 col-md-5">
            <input type="text" name="q" class="form-control form-control-sm" placeholder="Wyszukaj sprzęt (S/N, Inw, Model)..." value="<?= htmlspecialchars($search) ?>">
          </div>
          <div class="col-6 col-md-3 d-md-none">
            <select name="room" class="form-select form-select-sm">
              <option value="">-- Filtruj po pokoju --</option>
              <?php foreach ($rooms as $room): ?>
                <option value="<?= htmlspecialchars((string)$room) ?>" <?= $filter_room === (string)$room ? 'selected' : '' ?>><?= htmlspecialchars((string)$room) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-6 col-md-3 d-md-none">
            <select name="status" class="form-select form-select-sm">
              <option value="">-- Filtruj po statusie --</option>
              <?php foreach ($statuses as $st): ?>
                <option value="<?= htmlspecialchars((string)$st) ?>" <?= $filter_status === (string)$st ? 'selected' : '' ?>><?= htmlspecialchars((string)$st) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          
          <!-- Hidden inputs for desktop form submission to preserve filters -->
          <div class="d-none d-md-block">
            <?php if ($filter_room !== ''): ?><input type="hidden" name="room" value="<?= htmlspecialchars($filter_room) ?>"><?php endif; ?>
            <?php if ($filter_status !== ''): ?><input type="hidden" name="status" value="<?= htmlspecialchars($filter_status) ?>"><?php endif; ?>
          </div>

          <div class="col-12 col-md-7 text-end">
            <?php if ($search !== '' || $filter_room !== '' || $filter_status !== ''): ?>
              <a href="index.php" class="btn btn-secondary btn-sm">Wyczyść filtry</a>
            <?php endif; ?>
            <button type="submit" class="btn btn-primary btn-sm px-3">Szukaj</button>
            <a href="export.php?<?= http_build_query($_GET) ?>" class="btn btn-success btn-sm ms-1" target="_blank" title="Eksportuj CSV">📥</a>
          </div>
        </form>
      </div>
    </div>

    <!-- Desktop list -->
    <div class="card border-0 shadow-sm d-none d-md-block">
      <div class="card-body p-0">
        <div class="table-responsive" style="min-height: 400px;">
          <form method="GET" action="index.php">
            <!-- Wyszukiwarka z góry zostaje przekazana -->
            <input type="hidden" name="q" value="<?= htmlspecialchars($search) ?>">
            
            <table class="table table-hover align-middle mb-0 small" style="margin-bottom: 0;">
              <thead class="table-dark text-muted">
                <tr>
                  <!-- Status -->
                  <th class="dropdown">
                    <button class="btn btn-sm text-muted fw-bold dropdown-toggle p-0 border-0" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside">
                      Status
                    </button>
                    <div class="dropdown-menu p-2 bg-dark border-secondary shadow" style="min-width: 200px;">
                      <select name="status" class="form-select form-select-sm mb-2 bg-dark text-light border-secondary">
                        <option value="">Wszystkie</option>
                        <?php foreach ($statuses as $st): ?>
                          <option value="<?= htmlspecialchars((string)$st) ?>" <?= $filter_status === (string)$st ? 'selected' : '' ?>><?= htmlspecialchars((string)$st) ?></option>
                        <?php endforeach; ?>
                      </select>
                      <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary btn-sm flex-grow-1">Filtruj</button>
                        <a href="index.php" class="btn btn-outline-secondary btn-sm">X</a>
                      </div>
                    </div>
                  </th>
                  <!-- Nr inwentarzowy -->
                  <th class="dropdown">
                    <button class="btn btn-sm text-muted fw-bold dropdown-toggle p-0 border-0" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside">
                      Nr inwentarzowy
                    </button>
                    <div class="dropdown-menu p-2 bg-dark border-secondary shadow" style="min-width: 200px;">
                      <input type="text" name="asset" class="form-control form-control-sm mb-2 bg-dark text-light border-secondary" placeholder="Szukaj..." value="<?= htmlspecialchars($filter_asset) ?>">
                      <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary btn-sm flex-grow-1">Filtruj</button>
                        <a href="index.php" class="btn btn-outline-secondary btn-sm">X</a>
                      </div>
                    </div>
                  </th>
                  <!-- Sprzęt (Producent / Model) -->
                  <th class="dropdown">
                    <button class="btn btn-sm text-muted fw-bold dropdown-toggle p-0 border-0" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside">
                      Sprzęt (Producent / Model)
                    </button>
                    <div class="dropdown-menu p-2 bg-dark border-secondary shadow" style="min-width: 200px;">
                      <input type="text" name="model" class="form-control form-control-sm mb-2 bg-dark text-light border-secondary" placeholder="Szukaj..." value="<?= htmlspecialchars($filter_model) ?>">
                      <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary btn-sm flex-grow-1">Filtruj</button>
                        <a href="index.php" class="btn btn-outline-secondary btn-sm">X</a>
                      </div>
                    </div>
                  </th>
                  <!-- S/N -->
                  <th class="dropdown">
                    <button class="btn btn-sm text-muted fw-bold dropdown-toggle p-0 border-0" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside">
                      S/N
                    </button>
                    <div class="dropdown-menu p-2 bg-dark border-secondary shadow" style="min-width: 200px;">
                      <input type="text" name="sn" class="form-control form-control-sm mb-2 bg-dark text-light border-secondary" placeholder="Szukaj..." value="<?= htmlspecialchars($filter_sn) ?>">
                      <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary btn-sm flex-grow-1">Filtruj</button>
                        <a href="index.php" class="btn btn-outline-secondary btn-sm">X</a>
                      </div>
                    </div>
                  </th>
                  <!-- Przypisany -->
                  <th class="dropdown">
                    <button class="btn btn-sm text-muted fw-bold dropdown-toggle p-0 border-0" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside">
                      Przypisany
                    </button>
                    <div class="dropdown-menu p-2 bg-dark border-secondary shadow" style="min-width: 200px;">
                      <select name="user" class="form-select form-select-sm mb-2 bg-dark text-light border-secondary">
                        <option value="">Wszyscy</option>
                        <option value="0" <?= $filter_user === '0' ? 'selected' : '' ?>>Brak (Magazyn)</option>
                        <?php foreach ($all_users as $u): ?>
                          <option value="<?= $u['id'] ?>" <?= $filter_user === (string)$u['id'] ? 'selected' : '' ?>><?= htmlspecialchars($u['first_name'] . ' ' . $u['last_name']) ?></option>
                        <?php endforeach; ?>
                      </select>
                      <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary btn-sm flex-grow-1">Filtruj</button>
                        <a href="index.php" class="btn btn-outline-secondary btn-sm">X</a>
                      </div>
                    </div>
                  </th>
                  <!-- Lokalizacja -->
                  <th class="dropdown">
                    <button class="btn btn-sm text-muted fw-bold dropdown-toggle p-0 border-0" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside">
                      Lokalizacja
                    </button>
                    <div class="dropdown-menu p-2 bg-dark border-secondary shadow border-end-0" style="min-width: 200px;">
                      <select name="room" class="form-select form-select-sm mb-2 bg-dark text-light border-secondary">
                        <option value="">Wszystkie</option>
                        <?php foreach ($rooms as $room): ?>
                          <option value="<?= htmlspecialchars((string)$room) ?>" <?= $filter_room === (string)$room ? 'selected' : '' ?>><?= htmlspecialchars((string)$room) ?></option>
                        <?php endforeach; ?>
                      </select>
                      <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary btn-sm flex-grow-1">Filtruj</button>
                        <a href="index.php" class="btn btn-outline-secondary btn-sm">X</a>
                      </div>
                    </div>
                  </th>
                  <th class="text-end px-3">Opcje</th>
                </tr>
              </thead>
            <tbody>
              <?php if (empty($items)): ?>
                <tr>
                  <td colspan="7" class="text-center text-muted py-4">Brak sprzętu spełniającego kryteria.</td>
                </tr>
              <?php endif; ?>
              <?php foreach ($items as $item): ?>
                <tr class="clickable-row" title="Kliknij, aby rozwinąć historię">
                  <td data-bs-toggle="collapse" data-bs-target="#history-<?= $item['id'] ?>">
                    <?php if($item['status'] == 'ACTIVE') echo '<span class="badge bg-success">ACTIVE</span>';
                          else if($item['status'] == 'WAREHOUSE') echo '<span class="badge bg-secondary">WAREHOUSE</span>';
                          else if($item['status'] == 'REPAIR') echo '<span class="badge bg-warning text-dark">REPAIR</span>';
                          else echo '<span class="badge bg-dark">'.$item['status'].'</span>'; ?>
                  </td>
                  <td data-bs-toggle="collapse" data-bs-target="#history-<?= $item['id'] ?>"><strong><?= htmlspecialchars($item['asset_number']) ?></strong></td>
                  <td data-bs-toggle="collapse" data-bs-target="#history-<?= $item['id'] ?>">
                    <div class="fw-bold"><?= htmlspecialchars((string)$item['manufacturer']) ?></div>
                    <div class="text-muted" style="font-size: 0.85em;"><?= htmlspecialchars((string)$item['model']) ?></div>
                  </td>
                  <td data-bs-toggle="collapse" data-bs-target="#history-<?= $item['id'] ?>"><code class="text-light"><?= htmlspecialchars((string)$item['serial_number']) ?></code></td>
                  <td data-bs-toggle="collapse" data-bs-target="#history-<?= $item['id'] ?>">
                    <?php if($item['user_id']): ?>
                      <?= htmlspecialchars($item['first_name'] . ' ' . $item['last_name']) ?>
                      <div class="text-muted" style="font-size: 0.8em;">(<?= htmlspecialchars($item['u_login']) ?>)</div>
                    <?php else: ?>
                      <span class="text-muted">-</span>
                    <?php endif; ?>
                  </td>
                  <td data-bs-toggle="collapse" data-bs-target="#history-<?= $item['id'] ?>"><span class="badge bg-info text-dark"><?= htmlspecialchars((string)$item['room']) ?: 'Brak' ?></span></td>
                  <td class="text-end px-3">
                    <a href="edit.php?id=<?= $item['id'] ?>" class="btn btn-primary btn-sm py-1">Edytuj sprzęt</a>
                  </td>
                </tr>
                <!-- Expandable History Row -->
                <tr class="collapse" id="history-<?= $item['id'] ?>" style="background-color: #121212;">
                  <td colspan="7" class="p-0 border-0">
                    <div class="p-4 m-2 rounded" style="background-color: #18181b; border: 1px solid #27272a;">
                      <div class="row">
                        <div class="col-md-5 border-end border-secondary border-opacity-25">
                          <h6 class="text-uppercase text-muted fw-bold mb-3" style="font-size: 0.8rem; letter-spacing: 1px;">Szczegóły urządzenia</h6>
                          <div class="d-flex flex-column gap-2 small">
                            <div class="d-flex justify-content-between border-bottom border-secondary border-opacity-10 pb-1">
                              <span class="text-muted">Opis / Uwagi:</span>
                              <span class="text-light text-end"><?= htmlspecialchars((string)$item['description']) ?: '<span class="text-muted fst-italic">Brak</span>' ?></span>
                            </div>
                            <div class="d-flex justify-content-between border-bottom border-secondary border-opacity-10 pb-1">
                              <span class="text-muted">Data zakupu:</span>
                              <span class="text-light"><?= htmlspecialchars((string)$item['purchase_date']) ?: '<span class="text-muted fst-italic">Brak</span>' ?></span>
                            </div>
                            <div class="d-flex justify-content-between border-bottom border-secondary border-opacity-10 pb-1">
                              <span class="text-muted">Cena zakupu:</span>
                              <span class="text-light"><?= $item['purchase_price'] ? htmlspecialchars((string)$item['purchase_price']).' PLN' : '<span class="text-muted fst-italic">Brak</span>' ?></span>
                            </div>
                            <div class="d-flex justify-content-between border-bottom border-secondary border-opacity-10 pb-1">
                              <span class="text-muted">Faktura:</span>
                              <span class="text-light"><?= htmlspecialchars((string)$item['invoice']) ?: '<span class="text-muted fst-italic">Brak</span>' ?></span>
                            </div>
                            <div class="d-flex justify-content-between pb-1">
                              <span class="text-muted">Gwarancja do:</span>
                              <span class="text-light"><?= htmlspecialchars((string)$item['warranty_until']) ?: '<span class="text-muted fst-italic">Brak</span>' ?></span>
                            </div>
                          </div>
                        </div>
                        <div class="col-md-7 ps-md-4">
                          <h6 class="text-uppercase text-muted fw-bold mb-3" style="font-size: 0.8rem; letter-spacing: 1px;">Historia wpisów</h6>
                          <div class="timeline pe-2" style="max-height: 250px; overflow-y: auto;">
                            <?php $hist = $history_by_device[$item['id']] ?? []; ?>
                            <?php if(empty($hist)): ?>
                              <div class="text-muted small fst-italic">Brak historii.</div>
                            <?php endif; ?>
                            <?php foreach ($hist as $h): ?>
                              <div class="timeline-item">
                                <div class="timeline-icon <?= $h['event_type'] === 'CREATE' ? 'bg-success' : '' ?>"></div>
                                <div class="small text-muted mb-1"><?= htmlspecialchars($h['date']) ?> &bull; <strong><?= htmlspecialchars($h['changed_by']) ?></strong></div>
                                <div class="small p-2 rounded" style="background-color: #27272a; border: 1px solid #3f3f46;">
                                  <strong class="text-light"><?= htmlspecialchars($h['event_type']) ?>:</strong> <span class="text-muted"><?= htmlspecialchars((string)$h['description']) ?></span>
                                  <?php if ($h['event_type'] === 'UPDATE' && $h['field_name']): ?>
                                    <div class="mt-2 pt-2 border-top border-secondary border-opacity-25">
                                      <s class="text-muted"><?= htmlspecialchars((string)$h['old_value']) ?: '(puste)' ?></s> &rarr; <span class="text-info"><?= htmlspecialchars((string)$h['new_value']) ?: '(puste)' ?></span>
                                    </div>
                                  <?php endif; ?>
                                  <?php if ($h['event_type'] === 'ASSIGN' || $h['event_type'] === 'TRANSFER' || $h['event_type'] === 'RETURN'): ?>
                                    <div class="mt-2 pt-2 border-top border-secondary border-opacity-25 text-light">
                                      <span class="text-muted">Z:</span> <?= $h['old_user_id'] ? htmlspecialchars($h['o_fn'].' '.$h['o_ln']) : 'Magazyn' ?> &rarr; <span class="text-muted">Do:</span> <?= $h['new_user_id'] ? htmlspecialchars($h['n_fn'].' '.$h['n_ln']) : 'Magazyn' ?>
                                    </div>
                                  <?php endif; ?>
                                  <?php if ($h['event_type'] === 'MOVE'): ?>
                                    <div class="mt-2 pt-2 border-top border-secondary border-opacity-25 text-light">
                                      <span class="text-muted">Pokój:</span> <?= htmlspecialchars((string)$h['old_room']) ?: 'Brak' ?> &rarr; <?= htmlspecialchars((string)$h['new_room']) ?: 'Brak' ?>
                                    </div>
                                  <?php endif; ?>
                                </div>
                              </div>
                            <?php endforeach; ?>
                          </div>
                        </div>
                      </div>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
          </form>
        </div>
      </div>
    </div>

    <!-- Mobile list -->
    <div class="row d-md-none">
      <?php if (empty($items)): ?>
        <div class="col-12 text-center text-muted py-4">Brak wpisów.</div>
      <?php endif; ?>
      <?php foreach ($items as $item): ?>
        <div class="col-12 mb-3">
          <div class="card border-0 shadow-sm">
            <div class="card-body">
              <div class="d-flex justify-content-between align-items-start mb-2">
                <span class="badge bg-secondary"><?= htmlspecialchars($item['status']) ?></span>
                <span class="badge bg-info text-dark">Pokój: <?= htmlspecialchars((string)$item['room']) ?></span>
              </div>
              <h6 class="fw-bold card-title mb-1"><?= htmlspecialchars((string)$item['manufacturer']) ?> - <?= htmlspecialchars((string)$item['model']) ?></h6>
              <div class="small mb-1"><strong>S/N:</strong> <code class="text-light"><?= htmlspecialchars((string)$item['serial_number']) ?></code></div>
              <div class="small mb-1"><strong>Inw:</strong> <code><?= htmlspecialchars($item['asset_number']) ?></code></div>
              <div class="small mb-2"><strong>Osoba:</strong> <?= $item['user_id'] ? htmlspecialchars($item['first_name'] . ' ' . $item['last_name']) : '<span class="text-muted">Brak</span>' ?></div>
              
              <div class="d-flex justify-content-between mt-3 border-top pt-3">
                <button class="btn btn-outline-secondary btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#mob-history-<?= $item['id'] ?>">Historia ▾</button>
                <a href="edit.php?id=<?= $item['id'] ?>" class="btn btn-primary btn-sm px-3">Edytuj</a>
              </div>

              <!-- Mobile History Collapse -->
              <div class="collapse mt-3" id="mob-history-<?= $item['id'] ?>">
                <div class="p-3 rounded mb-3" style="background-color: #121212; border: 1px solid #27272a;">
                  <h6 class="text-uppercase text-muted fw-bold mb-2" style="font-size: 0.75rem; letter-spacing: 1px;">Szczegóły</h6>
                  <div class="small text-light mb-1"><span class="text-muted">Opis:</span> <?= htmlspecialchars((string)$item['description']) ?: 'Brak' ?></div>
                  <div class="small text-light mb-1"><span class="text-muted">Zakup:</span> <?= htmlspecialchars((string)$item['purchase_date']) ?: 'Brak' ?> (<?= $item['purchase_price'] ? htmlspecialchars((string)$item['purchase_price']).' PLN' : 'Brak' ?>)</div>
                  <div class="small text-light mb-1"><span class="text-muted">Faktura:</span> <?= htmlspecialchars((string)$item['invoice']) ?: 'Brak' ?></div>
                  <div class="small text-light"><span class="text-muted">Gw. do:</span> <?= htmlspecialchars((string)$item['warranty_until']) ?: 'Brak' ?></div>
                </div>
                <h6 class="text-uppercase text-muted fw-bold mb-3 ms-1" style="font-size: 0.75rem; letter-spacing: 1px;">Historia wpisów</h6>
                <div class="timeline" style="border-left-color: #3f3f46; padding-left: 1rem;">
                  <?php $hist = $history_by_device[$item['id']] ?? []; ?>
                  <?php if(empty($hist)): ?>
                    <div class="text-muted small fst-italic">Brak historii.</div>
                  <?php endif; ?>
                  <?php foreach ($hist as $h): ?>
                    <div class="timeline-item mb-3">
                      <div class="timeline-icon <?= $h['event_type'] === 'CREATE' ? 'bg-success' : '' ?>" style="width: 1rem; height: 1rem; left: -1.6rem; top: 0.2rem;"></div>
                      <div class="small fw-bold text-light"><?= htmlspecialchars($h['date']) ?></div>
                      <div class="small text-muted mb-1"><?= htmlspecialchars($h['event_type']) ?> &bull; <?= htmlspecialchars($h['changed_by']) ?></div>
                      <div class="small p-2 rounded" style="background-color: #27272a; border: 1px solid #3f3f46;">
                        <span class="text-light"><?= htmlspecialchars((string)$h['description']) ?></span>
                        <?php if ($h['event_type'] === 'UPDATE' && $h['field_name']): ?>
                          <div class="mt-2 pt-2 border-top border-secondary border-opacity-25">
                            <span class="text-info"><?= htmlspecialchars((string)$h['new_value']) ?: '(puste)' ?></span>
                          </div>
                        <?php endif; ?>
                      </div>
                    </div>
                  <?php endforeach; ?>
                </div>
              </div>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

  </div>

  <!-- ADD DEVICE MODAL -->
  <div class="modal fade" id="addDeviceModal" tabindex="-1" aria-labelledby="addDeviceModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
      <div class="modal-content border-0 shadow-lg" style="border-radius: 16px;">
        <div class="modal-header border-bottom-0 pb-0">
          <h5 class="modal-title fw-bold text-primary" id="addDeviceModalLabel">📋 Dodaj nowe urządzenie</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zamknij"></button>
        </div>
        <div class="modal-body">
          <form method="POST" action="index.php">
            <input type="hidden" name="action" value="create_device">
            <div class="row">
              <div class="col-md-6 mb-3">
                <label class="form-label small fw-semibold">Numer inwentarzowy (wymagany)</label>
                <input type="text" class="form-control form-control-sm" name="asset_number" required placeholder="np. IT-000842">
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label small fw-semibold">Producent</label>
                <input type="text" class="form-control form-control-sm" name="manufacturer" placeholder="np. NTT, Dell, HP">
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label small fw-semibold">Model</label>
                <input type="text" class="form-control form-control-sm" name="model" placeholder="np. Latitude 5520">
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label small fw-semibold">Numer seryjny (S/N)</label>
                <input type="text" class="form-control form-control-sm" name="serial_number" placeholder="np. ABC123XYZ">
              </div>
              <div class="col-md-4 mb-3">
                <label class="form-label small fw-semibold">Data zakupu</label>
                <input type="date" class="form-control form-control-sm" name="purchase_date">
              </div>
              <div class="col-md-4 mb-3">
                <label class="form-label small fw-semibold">Cena zakupu (PLN)</label>
                <input type="number" step="0.01" class="form-control form-control-sm" name="purchase_price" placeholder="0.00">
              </div>
              <div class="col-md-4 mb-3">
                <label class="form-label small fw-semibold">Nr faktury</label>
                <input type="text" class="form-control form-control-sm" name="invoice" placeholder="FV/...">
              </div>
              <div class="col-md-4 mb-3">
                <label class="form-label small fw-semibold">Gwarancja do</label>
                <input type="date" class="form-control form-control-sm" name="warranty_until">
              </div>
              <div class="col-md-4 mb-3">
                <label class="form-label small fw-semibold">Przypisany pracownik</label>
                <select class="form-select form-select-sm" name="assigned_user_id">
                  <option value="">-- Brak (Magazyn) --</option>
                  <?php foreach ($all_users as $u): ?>
                    <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['first_name'] . ' ' . $u['last_name']) ?> (<?= htmlspecialchars($u['username']) ?>)</option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-4 mb-3">
                <label class="form-label small fw-semibold">Pokój (Lokalizacja)</label>
                <input type="text" class="form-control form-control-sm" name="room" placeholder="np. 9, Magazyn, Serwerownia">
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label small fw-semibold">Status</label>
                <select class="form-select form-select-sm" name="status">
                  <option value="ACTIVE" selected>ACTIVE (W użyciu)</option>
                  <option value="WAREHOUSE">WAREHOUSE (W magazynie)</option>
                  <option value="RESERVED">RESERVED (Zarezerwowany)</option>
                  <option value="REPAIR">REPAIR (W naprawie)</option>
                  <option value="DISPOSED">DISPOSED (Zutylizowany)</option>
                  <option value="LOST">LOST (Zgubiony/Skradziony)</option>
                </select>
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label small fw-semibold">Opis / Uwagi</label>
                <textarea class="form-control form-control-sm" name="description" rows="2"></textarea>
              </div>
            </div>
            <div class="text-end border-top pt-3 mt-2">
              <button type="button" class="btn btn-secondary px-3" data-bs-dismiss="modal">Anuluj</button>
              <button type="submit" class="btn btn-primary px-4">💾 Zapisz sprzęt</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
