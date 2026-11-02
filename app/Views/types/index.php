<?php if ($msg): ?>
  <div class="alert alert-success alert-dismissible fade show" role="alert">
    <?= htmlspecialchars($msg) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
<?php endif; ?>

<div class="row">
  <!-- Dodaj Typ Urządzenia -->
  <div class="col-md-4">
    <div class="card border-0 shadow-sm mb-4 h-100">
      <div class="card-header bg-transparent border-bottom border-secondary border-opacity-25 py-3">
        <h5 class="fw-bold text-primary mb-0">Dodaj typ urządzenia</h5>
      </div>
      <div class="card-body p-4">
        <form method="post">
          <input type="hidden" name="action" value="add_type">
          <div class="mb-3">
            <label class="form-label text-muted small fw-bold">Nazwa typu</label>
            <input type="text" name="name" class="form-control bg-dark text-light border-secondary" required placeholder="np. Komputer">
          </div>
          <div class="mb-3">
            <label class="form-label text-muted small fw-bold">Krótki opis</label>
            <input type="text" name="description" class="form-control bg-dark text-light border-secondary" placeholder="Opcjonalnie...">
          </div>
          <button type="submit" class="btn btn-primary w-100 fw-bold">Dodaj typ</button>
        </form>
      </div>
    </div>
  </div>

  <!-- Lista typów i ich atrybutów -->
  <div class="col-md-8">
    <div class="card border-0 shadow-sm mb-5">
      <div class="card-header bg-transparent border-bottom border-secondary border-opacity-25 py-3">
        <h5 class="fw-bold text-primary mb-0">Zarządzanie Typami i Polami Dynamicznymi</h5>
      </div>
      <div class="card-body p-4">
        <div class="accordion" id="typesAccordion">
          <?php foreach ($types as $idx => $t): ?>
            <?php $tid = $t['id']; ?>
            <div class="accordion-item bg-dark border-secondary mb-2 rounded">
              <h2 class="accordion-header" id="heading<?= $tid ?>">
                <button class="accordion-button <?= $idx === 0 ? '' : 'collapsed' ?> bg-dark text-light fw-bold" type="button" data-bs-toggle="collapse" data-bs-target="#collapse<?= $tid ?>">
                  <?= htmlspecialchars($t['name']) ?>
                  <?php if ($t['description']): ?>
                    <span class="text-muted small fw-normal ms-2">- <?= htmlspecialchars($t['description']) ?></span>
                  <?php endif; ?>
                </button>
              </h2>
              <div id="collapse<?= $tid ?>" class="accordion-collapse collapse <?= $idx === 0 ? 'show' : '' ?>" data-bs-parent="#typesAccordion">
                <div class="accordion-body border-top border-secondary border-opacity-25">
                  <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="text-info fw-bold mb-0">Pola dynamiczne dla tego typu:</h6>
                    <form method="post" class="m-0" onsubmit="return confirm('Usunąć ten typ urządzenia? Zniknie on z formularzy.');">
                      <input type="hidden" name="action" value="delete_type">
                      <input type="hidden" name="id" value="<?= $tid ?>">
                      <button type="submit" class="btn btn-outline-danger btn-sm">Usuń ten typ</button>
                    </form>
                  </div>
                  <?php $my_fields = $fields_by_type[$tid] ?? []; ?>
                  <?php if (empty($my_fields)): ?>
                    <p class="text-muted small mb-3">Brak dodatkowych pól. Będą używane tylko pola systemowe (SN, Pracownik, itp).</p>
                  <?php else: ?>
                    <table class="table table-hover table-bordered align-middle mb-0 small mb-3">
                      <thead>
                        <tr>
                          <th>Nazwa pola</th>
                          <th>Typ kontrolki</th>
                          <th>Sort (layerorder)</th>
                          <th class="text-end">Akcja</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php foreach ($my_fields as $f): ?>
                          <tr>
                            <td><?= htmlspecialchars($f['label']) ?></td>
                            <td><span class="badge bg-secondary"><?= htmlspecialchars($f['field_type']) ?></span></td>
                            <td><?= $f['order_form'] ?></td>
                            <td class="text-end">
                              <form method="post" class="m-0" onsubmit="return confirm('Usunąć to pole? Możesz utracić dane wpisane w to pole u istniejących urządzeń!');">
                                <input type="hidden" name="action" value="delete_field">
                                <input type="hidden" name="id" value="<?= $f['id'] ?>">
                                <button type="submit" class="btn btn-outline-danger btn-sm py-0">Usuń</button>
                              </form>
                            </td>
                          </tr>
                        <?php endforeach; ?>
                      </tbody>
                    </table>
                  <?php endif; ?>
                  
                  <div class="p-3 border border-secondary border-opacity-25 rounded bg-black bg-opacity-10 mt-3">
                    <form method="post" class="row g-2 align-items-end">
                      <input type="hidden" name="action" value="add_field">
                      <input type="hidden" name="type_id" value="<?= $tid ?>">
                      
                      <div class="col-md-4">
                        <label class="form-label small text-muted mb-1">Nazwa nowego pola</label>
                        <input type="text" name="name" class="form-control form-control-sm bg-dark text-light border-secondary" required placeholder="np. Karta Graficzna">
                      </div>
                      <div class="col-md-4">
                        <label class="form-label small text-muted mb-1">Typ kontrolki</label>
                        <select name="field_type" class="form-select form-select-sm bg-dark text-light border-secondary">
                          <option value="text">Tekst (krótki)</option>
                          <option value="textarea">Tekst (długi / opis)</option>
                          <option value="number">Liczba</option>
                          <option value="date">Data</option>
                          <option value="checkbox">Tak/Nie (Przełącznik)</option>
                        </select>
                      </div>
                      <div class="col-md-2">
                        <label class="form-label small text-muted mb-1">Kolejność</label>
                        <input type="number" name="display_order" class="form-control form-control-sm bg-dark text-light border-secondary" placeholder="0">
                      </div>
                      <div class="col-md-2">
                        <button type="submit" class="btn btn-success btn-sm w-100 fw-bold">Dodaj pole</button>
                      </div>
                    </form>
                  </div>

                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>
</div>