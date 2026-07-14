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

// Any logged-in user can edit any item.


$err = '';
$ok = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $room_number = trim((string)($_POST['room_number'] ?? ''));
    $device_type_id = $_POST['device_type_id'] ?? '';
    $manufacturer_id = $_POST['manufacturer_id'] ?? '';
    $model = trim((string)($_POST['model'] ?? ''));
    $serial_number = trim((string)($_POST['serial_number'] ?? ''));
    $inventory_number = trim((string)($_POST['inventory_number'] ?? ''));
    $notes_select = $_POST['notes_select'] ?? '';
    if ($notes_select === 'custom') {
        $notes = trim((string)($_POST['notes_custom'] ?? ''));
    } else {
        $notes = trim((string)$notes_select);
    }

    if ($room_number === '') {
        $err = 'Wymagane jest pole: Numer pokoju.';
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
                    model = :model,
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
                ':model' => $model !== '' ? $model : null,
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
  <!-- HTML5 QR Code Library -->
  <script src="https://unpkg.com/html5-qrcode"></script>
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
    #scanner-container {
      width: 100%;
      max-width: 450px;
      margin: 0 auto;
      border-radius: 12px;
      overflow: hidden;
      background-color: #000;
      position: relative;
    }
    #reader video {
      object-fit: cover !important;
    }
  </style>
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
              <!-- General Scan Button -->
              <div class="mb-3">
                <button type="button" class="btn btn-primary w-100 py-3 d-flex align-items-center justify-content-center shadow-sm" onclick="startScanner('auto')" style="border-radius: 12px; font-size: 1.05rem; letter-spacing: 0.5px;">
                  <span class="fs-4 me-2">📷</span> <strong>SKANUJ TABLICZKĘ / KOD</strong>
                </button>
              </div>

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

                <!-- Model -->
                <div class="col-md-6 mb-3">
                  <label for="model" class="form-label small fw-semibold">Model</label>
                  <input type="text" class="form-control form-control-sm" id="model" name="model" value="<?= htmlspecialchars($item['model'] ?? '') ?>" placeholder="Wpisz model ręcznie">
                </div>

                <!-- Numer seryjny -->
                <div class="col-md-6 mb-3">
                  <label for="serial_number" class="form-label small fw-semibold">Numer seryjny</label>
                  <div class="input-group input-group-sm">
                    <input type="text" class="form-control form-control-sm" id="serial_number" name="serial_number" value="<?= htmlspecialchars($item['serial_number']) ?>">
                    <button class="btn btn-outline-primary" type="button" onclick="startScanner('serial_number')">📷 Skanuj</button>
                  </div>
                </div>

                <!-- Numer inwentarzowy -->
                <div class="col-md-6 mb-3">
                  <label for="inventory_number" class="form-label small fw-semibold">Numer inwentarzowy</label>
                  <div class="input-group input-group-sm">
                    <input type="text" class="form-control form-control-sm" id="inventory_number" name="inventory_number" value="<?= htmlspecialchars($item['inventory_number']) ?>">
                    <button class="btn btn-outline-primary" type="button" onclick="startScanner('inventory_number')">📷 Skanuj</button>
                  </div>
                </div>

                <!-- Uwagi -->
                <?php
                $predefined_notes = ["SPRAWNY", "NIESPRAWNY"];
                $current_notes = $item['notes'] ?? '';
                $is_custom_notes = ($current_notes !== '' && !in_array($current_notes, $predefined_notes, true));
                ?>
                <div class="col-md-6 mb-3">
                  <label for="notes_select" class="form-label small fw-semibold">Stan / uwagi</label>
                  <select class="form-select form-select-sm" id="notes_select" name="notes_select" onchange="toggleCustomInput('notes_select', 'custom_notes_div', 'notes_custom')">
                    <option value="">-- Wybierz stan / uwagi (opcjonalnie) --</option>
                    <?php foreach ($predefined_notes as $pn): ?>
                      <option value="<?= $pn ?>" <?= $current_notes === $pn ? 'selected' : '' ?>><?= $pn ?></option>
                    <?php endforeach; ?>
                    <option value="custom" <?= $is_custom_notes ? 'selected' : '' ?>>Inny (wpisz ręcznie)...</option>
                  </select>
                  <div class="mt-2 <?= $is_custom_notes ? '' : 'd-none' ?>" id="custom_notes_div">
                    <textarea class="form-control form-control-sm border-danger" id="notes_custom" name="notes_custom" rows="2" placeholder="Wpisz inne uwagi..."><?= $is_custom_notes ? htmlspecialchars($current_notes) : '' ?></textarea>
                  </div>
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

  <!-- Modal Skanera -->
  <div class="modal fade" id="scannerModal" tabindex="-1" aria-labelledby="scannerModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content border-0 shadow-lg" style="border-radius: 16px;">
        <div class="modal-header border-0 pb-0">
          <h5 class="modal-title fw-bold" id="scannerModalLabel">📷 Skanowanie kodu</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zamknij" onclick="stopScanner()"></button>
        </div>
        <div class="modal-body text-center">
          <p class="text-muted small">Skieruj aparat na kod kreskowy lub kod QR tabliczki znamionowej.</p>
          <div id="scanner-container">
            <div id="reader" style="width: 100%;"></div>
          </div>
          <div class="mt-3">
            <label for="cameraSelect" class="form-label small fw-semibold">Wybierz kamerę:</label>
            <select id="cameraSelect" class="form-select form-select-sm" onchange="switchCamera(this.value)"></select>
          </div>
        </div>
        <div class="modal-footer border-0 pt-0 justify-content-center">
          <button type="button" class="btn btn-secondary btn-sm px-4" data-bs-dismiss="modal" onclick="stopScanner()">Anuluj</button>
        </div>
      </div>
    </div>
  </div>
  
  <!-- JavaScript for conditional custom fields and scanner -->
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

    let html5QrCode;
    let targetInputId = '';
    let currentCameraId = '';

    function startScanner(inputId) {
        targetInputId = inputId;
        const modalEl = document.getElementById('scannerModal');
        const modal = new bootstrap.Modal(modalEl);
        modal.show();

        // Wait for modal to show, then init scanner
        modalEl.addEventListener('shown.bs.modal', function onShown() {
            initScanner();
            modalEl.removeEventListener('shown.bs.modal', onShown);
        });
    }

    function initScanner() {
        if (!window.isSecureContext) {
            alert("⚠️ SKANER WYMAGA POŁĄCZENIA HTTPS!\n\nPrzeglądarki mobilne blokują dostęp do kamery na stronach bez certyfikatu SSL (zaczynających się od http://). Uruchom stronę pod adresem zaczynającym się od https://");
            const modalEl = document.getElementById('scannerModal');
            const modal = bootstrap.Modal.getInstance(modalEl);
            if (modal) {
                modal.hide();
            }
            return;
        }

        // Start scanning immediately with environment facingMode (rear camera)
        startCamera(null);
    }

    function startCamera(cameraId) {
        if (html5QrCode) {
            html5QrCode.stop().then(() => {
                launchCamera(cameraId);
            }).catch(err => {
                console.error(err);
                launchCamera(cameraId);
            });
        } else {
            launchCamera(cameraId);
        }
    }

    function launchCamera(cameraId) {
        html5QrCode = new Html5Qrcode("reader");
        const config = { fps: 15, qrbox: { width: 250, height: 150 } };
        
        // Define camera source: either specific ID or facingMode: environment
        const cameraSource = cameraId ? cameraId : { facingMode: "environment" };
        
        html5QrCode.start(
            cameraSource, 
            config,
            (decodedText, decodedResult) => {
                // Success callback
                let assignedField = '';
                if (targetInputId === 'auto') {
                    // Smart detection: if code starts with INW or contains slashes, put into inventory_number
                    if (decodedText.toLowerCase().includes('inw') || decodedText.includes('/') || decodedText.includes('\\')) {
                        document.getElementById('inventory_number').value = decodedText;
                        assignedField = 'Numer inwentarzowy';
                    } else {
                        document.getElementById('serial_number').value = decodedText;
                        assignedField = 'Numer seryjny';
                    }
                    alert('Odczytano kod: "' + decodedText + '". Przypisano do: ' + assignedField);
                } else {
                    document.getElementById(targetInputId).value = decodedText;
                }
                
                // Vibrate if supported
                if (navigator.vibrate) {
                    navigator.vibrate(100);
                }
                
                // Hide modal and clean up
                const modalEl = document.getElementById('scannerModal');
                const modal = bootstrap.Modal.getInstance(modalEl);
                if (modal) {
                    modal.hide();
                }
                stopScanner();
            },
            (errorMessage) => {
                // Ignore scanning failures
            }
        ).then(() => {
            // Once running, load cameras list in background (if not already loaded) to populate cameraSelect dropdown
            Html5Qrcode.getCameras().then(devices => {
                if (devices && devices.length > 0) {
                    const cameraSelect = document.getElementById('cameraSelect');
                    cameraSelect.innerHTML = '';
                    devices.forEach(device => {
                        const option = document.createElement('option');
                        option.value = device.id;
                        option.text = device.label || `Kamera ${cameraSelect.length + 1}`;
                        // If this is currently active camera, select it
                        if (cameraId === device.id || (!cameraId && (device.label.toLowerCase().includes('back') || device.label.toLowerCase().includes('environment') || device.label.toLowerCase().includes('tył') || device.label.toLowerCase().includes('rear')))) {
                            option.selected = true;
                        }
                        cameraSelect.appendChild(option);
                    });
                }
            }).catch(e => console.log("Silent ignore cameras list", e));
        }).catch(err => {
            console.error("Nie można uruchomić kamery:", err);
            alert("Nie można uruchomić kamery. Upewnij się, że udzieliłeś uprawnień do aparatu dla tej witryny.");
        });
    }

    function switchCamera(cameraId) {
        startCamera(cameraId);
    }

    function stopScanner() {
        if (html5QrCode) {
            if (html5QrCode.isScanning) {
                html5QrCode.stop().then(() => {
                    html5QrCode = null;
                }).catch(err => {
                    console.error("Błąd podczas zatrzymywania skanera:", err);
                });
            } else {
                html5QrCode = null;
            }
        }
    }
    
    // Clean up if modal is closed
    document.getElementById('scannerModal').addEventListener('hidden.bs.modal', function () {
        stopScanner();
    });
  </script>
</body>
</html>
