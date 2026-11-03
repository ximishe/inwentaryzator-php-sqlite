<!DOCTYPE html>
<html lang="pl" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <title>Inwentaryzator - Mobile & Tablet Ready</title>
    
    <!-- Google Fonts Outfit -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- DataTables CSS -->
    <link href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/responsive/2.4.1/css/responsive.bootstrap5.min.css" rel="stylesheet">
    
    <!-- Custom Theme Stylesheet -->
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-3 mb-md-4 shadow-sm border-bottom border-secondary border-opacity-25">
    <div class="container-fluid px-3 px-md-4">
        <a class="navbar-brand fw-bold d-flex align-items-center gap-2" href="index.php">
            <span class="badge bg-primary p-2 rounded-3 text-white"><i class="bi bi-boxes fs-6"></i></span>
            <span class="fs-5 tracking-wide">Inwentaryzator</span>
        </a>
        <button class="navbar-toggler border-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain" aria-controls="navbarMain" aria-expanded="false" aria-label="Menu nawigacji">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarMain">
            <ul class="navbar-nav me-auto mb-3 mb-lg-0 pt-2 pt-lg-0">
                <li class="nav-item">
                    <a class="nav-link py-2 <?= (basename($_SERVER['PHP_SELF']) === 'index.php' || basename($_SERVER['PHP_SELF']) === 'dashboard.php') ? 'active text-primary fw-bold' : '' ?>" href="index.php">
                        <i class="bi bi-grid-fill me-1"></i> Sprzęt
                    </a>
                </li>
                <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                <li class="nav-item">
                    <a class="nav-link py-2 <?= basename($_SERVER['PHP_SELF']) === 'users.php' ? 'active text-primary fw-bold' : '' ?>" href="users.php">
                        <i class="bi bi-people me-1"></i> Użytkownicy
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link py-2 <?= basename($_SERVER['PHP_SELF']) === 'types.php' ? 'active text-primary fw-bold' : '' ?>" href="types.php">
                        <i class="bi bi-tags me-1"></i> Typy sprzętu
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link py-2 <?= basename($_SERVER['PHP_SELF']) === 'settings_fields.php' ? 'active text-primary fw-bold' : '' ?>" href="settings_fields.php">
                        <i class="bi bi-sliders me-1"></i> Ustawienia pól
                    </a>
                </li>
                <?php endif; ?>
            </ul>
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 pt-3 pt-lg-0 border-top border-lg-0 border-secondary border-opacity-25">
                <?php if (isset($_SESSION['username'])): ?>
                    <div class="text-secondary small d-flex align-items-center gap-2">
                        <i class="bi bi-person-circle fs-5 text-light"></i>
                        <span>Zalogowano: <strong class="text-light"><?= htmlspecialchars($_SESSION['username']) ?></strong></span>
                        <span class="badge bg-dark border border-secondary text-info text-uppercase" style="font-size: 0.65rem;"><?= htmlspecialchars($_SESSION['role'] ?? 'user') ?></span>
                    </div>
                    <button type="button" class="btn btn-outline-info btn-sm d-flex align-items-center gap-1 px-2 py-1" data-bs-toggle="modal" data-bs-target="#changePasswordModal" title="Zmień swoje hasło">
                        <i class="bi bi-key-fill"></i> <span class="d-none d-sm-inline">Zmień hasło</span>
                    </button>
                <?php endif; ?>
                <a href="logout.php" class="btn btn-outline-danger btn-sm d-flex align-items-center gap-1 px-3 py-1">
                    <i class="bi bi-box-arrow-right"></i> Wyloguj
                </a>
            </div>
        </div>
    </div>
</nav>

<div class="container-fluid px-2 px-sm-3 px-md-4 pb-5">
    <?php 
      $display_err = $err ?? ($_SESSION['flash_err'] ?? '');
      if (!empty($_SESSION['flash_err'])) unset($_SESSION['flash_err']);
      $display_ok = $ok ?? ($_SESSION['flash_ok'] ?? '');
      if (!empty($_SESSION['flash_ok'])) unset($_SESSION['flash_ok']);
    ?>
    <?php if (!empty($display_err)): ?>
        <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($display_err) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Zamknij"></button>
        </div>
    <?php endif; ?>
    <?php if (!empty($display_ok)): ?>
        <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i><?= htmlspecialchars($display_ok) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Zamknij"></button>
        </div>
    <?php endif; ?>

    <!-- WSTRZYKNIĘCIE WIDOKU -->
    <?= $content ?? '' ?>
</div>

<!-- Modal Skanera Kodów Kreskowych i QR -->
<div class="modal fade" id="barcodeScannerModal" tabindex="-1" aria-labelledby="barcodeScannerModalLabel" aria-hidden="true" data-bs-backdrop="static">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content border-0 shadow-lg" style="border-radius: 20px; background-color: #121214;">
      <div class="modal-header border-bottom border-secondary border-opacity-25 pb-3">
        <div>
          <h5 class="modal-title fw-bold text-light d-flex align-items-center gap-2 mb-1" id="barcodeScannerModalLabel">
            <span class="badge bg-primary p-2 rounded-3"><i class="bi bi-upc-scan fs-6"></i></span>
            Skaner kodów aparatem
          </h5>
          <div class="text-muted small" id="scannerTargetLabel">Skieruj aparat na kod kreskowy lub QR</div>
        </div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Zamknij"></button>
      </div>
      <div class="modal-body p-2 p-md-3">
        <!-- Komunikat błędu / ostrzeżenia -->
        <div id="scannerAlert" class="alert alert-warning d-none py-2 px-3 small mb-2" role="alert"></div>

        <!-- Główny kontener kamery -->
        <div class="scanner-viewport-wrapper position-relative overflow-hidden rounded-3 bg-black">
          <div id="scanner-reader"></div>
          
          <!-- Nakładka celownika (Aiming reticle) -->
          <div class="scanner-overlay d-none" id="scannerReticleOverlay">
            <div class="scanner-reticle">
              <div class="scanner-laser"></div>
              <div class="reticle-corner top-left"></div>
              <div class="reticle-corner top-right"></div>
              <div class="reticle-corner bottom-left"></div>
              <div class="reticle-corner bottom-right"></div>
            </div>
            <div class="scanner-hint text-center text-white-50 small mt-2">
              <i class="bi bi-camera me-1"></i> Tylny aparat aktywny. Ustaw kod wewnątrz ramki.
            </div>
          </div>
        </div>

        <!-- Pasek narzędzi skanera -->
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3 pt-2 border-top border-secondary border-opacity-25">
          <div class="d-flex align-items-center gap-2">
            <!-- Wybór kamery -->
            <select id="scannerCameraSelect" class="form-select form-select-sm bg-dark text-light border-secondary" style="max-width: 210px;">
              <option value="">Wykrywanie aparatów...</option>
            </select>
            <!-- Przycisk latarki -->
            <button type="button" id="scannerTorchBtn" class="btn btn-outline-warning btn-sm d-none" title="Włącz/wyłącz latarkę">
              <i class="bi bi-flashlight"></i> Latarka
            </button>
          </div>

          <div class="d-flex align-items-center gap-2">
            <!-- Alternatywa: zrób zdjęcie natywnym aparatem lub wybierz z galerii -->
            <input type="file" id="scannerFileInput" accept="image/*" capture="environment" style="display: none;">
            <button type="button" id="scannerUploadBtn" class="btn btn-outline-secondary btn-sm">
              <i class="bi bi-camera-fill me-1"></i> Zrób zdjęcie / Wgraj
            </button>
          </div>
        </div>
      </div>
      <div class="modal-footer border-top border-secondary border-opacity-25 py-2">
        <button type="button" class="btn btn-secondary btn-sm px-4" data-bs-dismiss="modal">Anuluj</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal Zmiany Hasła Użytkownika -->
<div class="modal fade" id="changePasswordModal" tabindex="-1" aria-labelledby="changePasswordModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" style="max-width: 440px;">
    <div class="modal-content border-0 shadow-lg" style="border-radius: 18px; background-color: #18181b;">
      <div class="modal-header border-bottom border-secondary border-opacity-25 pb-3">
        <h5 class="modal-title fw-bold text-light d-flex align-items-center gap-2" id="changePasswordModalLabel">
          <span class="badge bg-info bg-opacity-25 text-info p-2 rounded-3"><i class="bi bi-key-fill fs-6"></i></span>
          Zmień swoje hasło
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Zamknij"></button>
      </div>
      <form id="changePasswordForm" method="POST" action="change_password.php">
        <input type="hidden" name="return_url" value="<?= htmlspecialchars($_SERVER['REQUEST_URI'] ?? 'index.php') ?>">
        <div class="modal-body p-3 p-md-4">
          <!-- Alert feedback -->
          <div id="changePassAlert" class="alert d-none py-2 px-3 small mb-3"></div>

          <div class="mb-3">
            <label class="form-label text-muted small fw-bold mb-1">Aktualne hasło</label>
            <div class="input-group">
              <span class="input-group-text bg-dark border-secondary text-muted"><i class="bi bi-lock"></i></span>
              <input type="password" name="current_password" id="inputCurrentPass" class="form-control bg-dark text-light border-secondary" placeholder="Twoje obecne hasło" required autocomplete="current-password">
              <button class="btn btn-outline-secondary toggle-pass-visibility" type="button" data-target="#inputCurrentPass" tabindex="-1">
                <i class="bi bi-eye"></i>
              </button>
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label text-muted small fw-bold mb-1">Nowe hasło</label>
            <div class="input-group">
              <span class="input-group-text bg-dark border-secondary text-muted"><i class="bi bi-shield-lock"></i></span>
              <input type="password" name="new_password" id="inputNewPass" class="form-control bg-dark text-light border-secondary" placeholder="Minimum 4 znaki" minlength="4" required autocomplete="new-password">
              <button class="btn btn-outline-secondary toggle-pass-visibility" type="button" data-target="#inputNewPass" tabindex="-1">
                <i class="bi bi-eye"></i>
              </button>
            </div>
            <div class="form-text text-muted small">Wpisz nowe hasło (minimum 4 znaki).</div>
          </div>

          <div class="mb-2">
            <label class="form-label text-muted small fw-bold mb-1">Powtórz nowe hasło</label>
            <div class="input-group">
              <span class="input-group-text bg-dark border-secondary text-muted"><i class="bi bi-check2-circle"></i></span>
              <input type="password" name="confirm_password" id="inputConfirmPass" class="form-control bg-dark text-light border-secondary" placeholder="Powtórz nowe hasło" minlength="4" required autocomplete="new-password">
              <button class="btn btn-outline-secondary toggle-pass-visibility" type="button" data-target="#inputConfirmPass" tabindex="-1">
                <i class="bi bi-eye"></i>
              </button>
            </div>
          </div>
        </div>
        <div class="modal-footer border-top border-secondary border-opacity-25 py-2 justify-content-between">
          <button type="button" class="btn btn-secondary btn-sm px-3" data-bs-dismiss="modal">Anuluj</button>
          <button type="submit" id="btnSubmitChangePass" class="btn btn-info btn-sm px-4 fw-bold text-dark d-flex align-items-center gap-1">
            <i class="bi bi-check-lg"></i> Zapisz nowe hasło
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.4.1/js/dataTables.responsive.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.4.1/js/responsive.bootstrap5.min.js"></script>

<!-- Barcode & QR Code Scanner Library and Controller -->
<script src="assets/html5-qrcode.min.js"></script>
<script src="assets/scanner.js"></script>

<script>
$(document).ready(function() {
  // Podgląd hasła (oko)
  $(document).on('click', '.toggle-pass-visibility', function(e) {
    e.preventDefault();
    const targetSel = $(this).data('target');
    const $input = $(targetSel);
    const $icon = $(this).find('i');
    if ($input.attr('type') === 'password') {
      $input.attr('type', 'text');
      $icon.removeClass('bi-eye').addClass('bi-eye-slash');
    } else {
      $input.attr('type', 'password');
      $icon.removeClass('bi-eye-slash').addClass('bi-eye');
    }
  });

  // Obsługa formularza zmiany hasła
  $('#changePasswordForm').on('submit', async function(e) {
    e.preventDefault();
    const $form = $(this);
    const $alert = $('#changePassAlert');
    const $btn = $('#btnSubmitChangePass');
    const originalBtnHtml = $btn.html();

    const newPass = $('#inputNewPass').val();
    const confirmPass = $('#inputConfirmPass').val();

    if (newPass.length < 4) {
      $alert.removeClass('alert-success d-none').addClass('alert-danger').html('Nowe hasło musi zawierać co najmniej 4 znaki.');
      return;
    }

    if (newPass !== confirmPass) {
      $alert.removeClass('alert-success d-none').addClass('alert-danger').html('Nowe hasła nie są ze sobą zgodne.');
      return;
    }

    $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Zapisywanie...');
    $alert.addClass('d-none').empty();

    try {
      const formData = new FormData(this);
      formData.append('ajax', '1');

      const response = await fetch('change_password.php', {
        method: 'POST',
        body: formData,
        headers: {
          'X-Requested-With': 'XMLHttpRequest'
        }
      });

      const data = await response.json();
      if (data.success) {
        $alert.removeClass('alert-danger d-none').addClass('alert-success').html(data.message);
        $form[0].reset();
        setTimeout(function() {
          const modalEl = document.getElementById('changePasswordModal');
          const modalInst = bootstrap.Modal.getInstance(modalEl);
          if (modalInst) modalInst.hide();
          $alert.addClass('d-none');
        }, 1300);
      } else {
        $alert.removeClass('alert-success d-none').addClass('alert-danger').html(data.message || 'Wystąpił błąd podczas zmiany hasła.');
      }
    } catch (err) {
      $alert.removeClass('alert-success d-none').addClass('alert-danger').html('Wystąpił problem z połączeniem. Spróbuj ponownie.');
    } finally {
      $btn.prop('disabled', false).html(originalBtnHtml);
    }
  });

  // Reset pól po ukryciu modala
  const passModal = document.getElementById('changePasswordModal');
  if (passModal) {
    passModal.addEventListener('hidden.bs.modal', function() {
      $('#changePassAlert').addClass('d-none').empty();
      $('#changePasswordForm')[0].reset();
      $('#inputCurrentPass, #inputNewPass, #inputConfirmPass').attr('type', 'password');
      $('.toggle-pass-visibility i').removeClass('bi-eye-slash').addClass('bi-eye');
    });
  }
});
</script>

<?php if (isset($extra_js)) echo $extra_js; ?>

</body>
</html>
