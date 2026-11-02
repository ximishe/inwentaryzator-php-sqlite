<h3 class="fw-bold mb-3">Ustawienia Systemowe Pól (UI Builder)</h3>
<p class="text-muted mb-4">Przeciągnij wiersze ↕, aby ustalić kolejność. Steruj widocznością kolumn i logowaniem historii za pomocą przełączników. Ustawienia te globalnie wpływają na to, jak system generuje formularze i tabele.</p>

<form method="POST" action="/INVENTARYZATOR/settings_fields.php">
  <input type="hidden" name="action" value="save_fields">
  
  <div class="card border-0 shadow-sm mb-4">
    <div class="table-responsive">
      <table class="table table-hover table-bordered align-middle mb-0 small" id="fieldsTable">
        <thead>
          <tr>
            <th style="width: 40px;"></th>
            <th>Nazwa Pola</th>
            <th>Klucz (Baza)</th>
            <th class="text-center">Widoczne: Formularz</th>
            <th class="text-center">Widoczne: Lista (Główna)</th>
            <th class="text-center">Widoczne: Szczegóły</th>
            <th class="text-center">Loguj w Historii</th>
          </tr>
        </thead>
        <tbody id="sortableList">
          <?php foreach ($fields as $f): ?>
            <tr>
              <td><span class="drag-handle fs-5" style="cursor: grab; color: #6c757d;">↕</span></td>
              <td class="fw-bold">
                <?= htmlspecialchars($f['label']) ?>
                <?php if ($f['source_type'] === 'native'): ?>
                    <span class="badge bg-primary ms-1" style="font-size:0.65rem;">Natywne</span>
                <?php else: ?>
                    <span class="badge bg-success ms-1" style="font-size:0.65rem;">Dodatkowe</span>
                <?php endif; ?>
              </td>
              <td><code class="text-light"><?= htmlspecialchars($f['field_key']) ?></code></td>
              
              <!-- Formularz -->
              <td class="text-center">
                <div class="form-check form-switch d-flex justify-content-center">
                  <input type="hidden" name="fields[<?= $f['id'] ?>][id]" value="<?= $f['id'] ?>">
                  <input class="form-check-input" type="checkbox" name="fields[<?= $f['id'] ?>][show_in_form]" value="1" <?= $f['show_in_form'] ? 'checked' : '' ?> <?= $f['is_required'] ? 'disabled' : '' ?>>
                  <?php if($f['is_required']): ?><input type="hidden" name="fields[<?= $f['id'] ?>][show_in_form]" value="1"><?php endif; ?>
                </div>
              </td>
              <!-- Lista Główna -->
              <td class="text-center">
                <div class="form-check form-switch d-flex justify-content-center">
                  <input class="form-check-input" type="checkbox" name="fields[<?= $f['id'] ?>][show_in_main]" value="1" <?= $f['show_in_main'] ? 'checked' : '' ?>>
                </div>
              </td>
              <!-- Szczegóły -->
              <td class="text-center">
                <div class="form-check form-switch d-flex justify-content-center">
                  <input class="form-check-input" type="checkbox" name="fields[<?= $f['id'] ?>][show_in_details]" value="1" <?= $f['show_in_details'] ? 'checked' : '' ?>>
                </div>
              </td>
              <!-- Historia -->
              <td class="text-center">
                <div class="form-check form-switch d-flex justify-content-center">
                  <input class="form-check-input" type="checkbox" name="fields[<?= $f['id'] ?>][log_history]" value="1" <?= $f['log_history'] ? 'checked' : '' ?>>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  
  <div class="text-end">
    <button type="submit" class="btn btn-success px-4 fw-bold shadow-sm">Zapisz ustawienia</button>
  </div>
</form>
