<div class="row">
  <!-- Create User Form -->
  <div class="col-md-4 mb-4">
    <div class="card border-0 shadow-sm">
      <div class="card-body p-4">
        <h3 class="fw-bold mb-4">Dodaj pracownika / usera</h3>
        
        <form method="post" action="users.php">
          <input type="hidden" name="action" value="create_user">
          
          <div class="mb-3">
            <label class="form-label text-muted small fw-bold">Login (Krótki identyfikator)</label>
            <input type="text" name="username" class="form-control bg-dark text-light border-secondary" required>
          </div>

          <div class="mb-3">
            <label class="form-label text-muted small fw-bold">Imię</label>
            <input type="text" name="first_name" class="form-control bg-dark text-light border-secondary" required>
          </div>

          <div class="mb-3">
            <label class="form-label text-muted small fw-bold">Nazwisko</label>
            <input type="text" name="last_name" class="form-control bg-dark text-light border-secondary" required>
          </div>

          <div class="mb-3">
            <label class="form-label text-muted small fw-bold">Adres Email (opcjonalnie)</label>
            <input type="email" name="email" class="form-control bg-dark text-light border-secondary">
          </div>
          
          <div class="mb-3">
            <label class="form-label text-muted small fw-bold">Hasło</label>
            <input type="password" name="password" class="form-control bg-dark text-light border-secondary" required>
            <div class="form-text text-muted small">Wymagane do logowania, nawet dla pracownika (np. do portalu pracowniczego w przyszłości).</div>
          </div>
          
          <div class="mb-3">
            <label class="form-label text-muted small fw-bold">Rola w systemie</label>
            <select name="role" class="form-select bg-dark text-light border-secondary">
              <option value="user">Pracownik (tylko odczyt własnych / brak logowania)</option>
              <option value="admin">Administrator (pełny dostęp)</option>
            </select>
          </div>
          
          <button type="submit" class="btn btn-primary w-100 fw-bold mt-2">Dodaj użytkownika</button>
        </form>
      </div>
    </div>
  </div>

  <!-- User List -->
  <div class="col-md-8">
    <div class="card border-0 shadow-sm">
      <div class="card-body p-4">
        <h3 class="fw-bold mb-4">Lista pracowników</h3>
        
        <div class="table-responsive">
          <table class="table table-hover table-bordered align-middle mb-0 small">
            <thead>
              <tr class="text-muted">
                <th>ID</th>
                <th>Login</th>
                <th>Imię i Nazwisko</th>
                <th>Email</th>
                <th>Rola</th>
                <th>Akcja</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($users as $u): ?>
                <tr>
                  <td><?= $u['id'] ?></td>
                  <td class="fw-bold text-light"><?= htmlspecialchars($u['username']) ?></td>
                  <td><?= htmlspecialchars($u['first_name'] . ' ' . $u['last_name']) ?></td>
                  <td><?= htmlspecialchars((string)$u['email']) ?></td>
                  <td>
                    <?php if ($u['role'] === 'admin'): ?>
                      <span class="badge bg-danger">Admin</span>
                    <?php else: ?>
                      <span class="badge bg-secondary">Pracownik</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <div class="d-flex align-items-center gap-1">
                      <?php if ((int)$u['id'] !== get_logged_user_id()): ?>
                        <button type="button" class="btn btn-outline-warning btn-sm py-0 btn-reset-user-pass" data-id="<?= $u['id'] ?>" data-username="<?= htmlspecialchars($u['username']) ?>" title="Zmień hasło użytkownikowi">
                          <i class="bi bi-key"></i> Hasło
                        </button>
                        <form method="post" action="users.php" class="m-0 d-inline" onsubmit="return confirm('Na pewno usunąć? (Historia wpisów dla tego usera może stracić powiązanie).');">
                          <input type="hidden" name="action" value="delete_user">
                          <input type="hidden" name="id" value="<?= $u['id'] ?>">
                          <button type="submit" class="btn btn-outline-danger btn-sm py-0">Usuń</button>
                        </form>
                      <?php else: ?>
                        <button type="button" class="btn btn-outline-info btn-sm py-0" data-bs-toggle="modal" data-bs-target="#changePasswordModal" title="Zmień swoje hasło">
                          <i class="bi bi-key-fill"></i> Moje hasło
                        </button>
                      <?php endif; ?>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
              <?php if (empty($users)): ?>
                <tr><td colspan="6" class="text-center text-muted">Brak użytkowników.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Modal Resetu Hasła przez Administratora -->
<div class="modal fade" id="adminResetPassModal" tabindex="-1" aria-labelledby="adminResetPassModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" style="max-width: 420px;">
    <div class="modal-content border-0 shadow-lg" style="border-radius: 18px; background-color: #18181b;">
      <div class="modal-header border-bottom border-secondary border-opacity-25 pb-3">
        <h5 class="modal-title fw-bold text-light d-flex align-items-center gap-2" id="adminResetPassModalLabel">
          <span class="badge bg-warning bg-opacity-25 text-warning p-2 rounded-3"><i class="bi bi-shield-lock-fill fs-6"></i></span>
          Zmień hasło użytkownikowi
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Zamknij"></button>
      </div>
      <form method="POST" action="users.php">
        <input type="hidden" name="action" value="reset_user_password">
        <input type="hidden" name="id" id="resetUserId" value="">
        <div class="modal-body p-3 p-md-4">
          <p class="text-muted small mb-3">
            Ustawiasz nowe hasło dla konta: <strong class="text-light" id="resetUserUsername"></strong>
          </p>
          <div class="mb-3">
            <label class="form-label text-muted small fw-bold mb-1">Nowe hasło</label>
            <div class="input-group">
              <span class="input-group-text bg-dark border-secondary text-muted"><i class="bi bi-key"></i></span>
              <input type="password" name="new_password" id="adminResetInputPass" class="form-control bg-dark text-light border-secondary" placeholder="Minimum 4 znaki" minlength="4" required>
              <button class="btn btn-outline-secondary toggle-pass-visibility" type="button" data-target="#adminResetInputPass" tabindex="-1">
                <i class="bi bi-eye"></i>
              </button>
            </div>
          </div>
        </div>
        <div class="modal-footer border-top border-secondary border-opacity-25 py-2 justify-content-between">
          <button type="button" class="btn btn-secondary btn-sm px-3" data-bs-dismiss="modal">Anuluj</button>
          <button type="submit" class="btn btn-warning btn-sm px-4 fw-bold text-dark">
            Zapisz nowe hasło
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  document.querySelectorAll('.btn-reset-user-pass').forEach(function(btn) {
    btn.addEventListener('click', function() {
      const id = this.getAttribute('data-id');
      const username = this.getAttribute('data-username');
      document.getElementById('resetUserId').value = id;
      document.getElementById('resetUserUsername').textContent = username;
      const modal = new bootstrap.Modal(document.getElementById('adminResetPassModal'));
      modal.show();
    });
  });
});
</script>
