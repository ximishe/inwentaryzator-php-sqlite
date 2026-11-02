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
                    <?php if ((int)$u['id'] !== get_logged_user_id()): ?>
                      <form method="post" action="users.php" class="m-0 d-inline" onsubmit="return confirm('Na pewno usunąć? (Historia wpisów dla tego usera może stracić powiązanie).');">
                        <input type="hidden" name="action" value="delete_user">
                        <input type="hidden" name="id" value="<?= $u['id'] ?>">
                        <button type="submit" class="btn btn-outline-danger btn-sm py-0">Usuń</button>
                      </form>
                    <?php else: ?>
                      <span class="text-muted small">To ty</span>
                    <?php endif; ?>
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
