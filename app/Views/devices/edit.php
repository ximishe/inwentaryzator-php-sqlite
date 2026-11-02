<!-- Breadcrumb & Top Bar -->
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3 mb-md-4">
  <a href="<?= htmlspecialchars($return_url ?? 'index.php') ?>" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center gap-1 shadow-sm">
    <i class="bi bi-arrow-left"></i> <span>Wróć do listy sprzętu<?= !empty($_SESSION['inventory_filters']['room']) ? ' (Pokój ' . htmlspecialchars($_SESSION['inventory_filters']['room']) . ')' : '' ?></span>
  </a>

  <!-- Mobile quick jump to timeline button (visible only on mobile/tablet < 992px) -->
  <button type="button" class="btn btn-outline-info btn-sm d-lg-none shadow-sm d-flex align-items-center gap-1" data-bs-toggle="collapse" data-bs-target="#mobileTimelineCollapse" aria-expanded="false" onclick="setTimeout(function(){ document.getElementById('mobileTimelineCollapse').scrollIntoView({behavior: 'smooth', block: 'start'}); }, 150);">
    <i class="bi bi-clock-history"></i>
    <span>Oś czasu (<?= count($history) ?>)</span>
    <i class="bi bi-chevron-down"></i>
  </button>
</div>

<div class="row g-4">
  <!-- ========================================================
       LEWA KOLUMNA: FORMULARZ EDYCJI WŁAŚCIWOŚCI SPRZĘTU
       ======================================================== -->
  <div class="col-12 col-lg-7 col-xl-8">
    <div class="card border-0 shadow-sm">
      <div class="card-body p-3 p-sm-4 p-md-5">
        <!-- Card Header Info -->
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 border-bottom border-secondary border-opacity-25 pb-3 mb-4">
          <div>
            <span class="text-muted small text-uppercase tracking-wider">Edycja właściwości</span>
            <h2 class="h4 fw-bold text-light mb-1">
              Nr inwentarzowy: <span class="text-primary"><?= htmlspecialchars($device['asset_number'] ?? '') ?></span>
            </h2>
            <div class="text-muted small">
              <?= htmlspecialchars((string)($device['manufacturer'] ?? '')) ?> <?= htmlspecialchars((string)($device['model'] ?? '')) ?>
              <?php if (!empty($device['serial_number'])): ?>
                &bull; S/N: <code class="text-light"><?= htmlspecialchars($device['serial_number']) ?></code>
              <?php endif; ?>
            </div>
          </div>
          
          <div>
            <?php 
              $st = $device['status'] ?? 'ACTIVE';
              $badgeClass = match($st) {
                'ACTIVE', 'Aktywny' => 'bg-success text-white',
                'WAREHOUSE', 'Magazyn' => 'bg-secondary text-white',
                'REPAIR', 'W naprawie' => 'bg-warning text-dark',
                'DISPOSED', 'Zlikwidowany' => 'bg-danger text-white',
                default => 'bg-dark border border-secondary text-light'
              };
            ?>
            <span class="badge <?= $badgeClass ?> fs-6 px-3 py-2"><?= htmlspecialchars($st) ?></span>
          </div>
        </div>
        
        <!-- Main Form -->
        <form method="POST" action="edit.php?id=<?= $device['id'] ?>">
          <input type="hidden" name="return" value="<?= htmlspecialchars($return_url ?? 'index.php') ?>">
          <div class="row g-3 g-md-4">
            <?php foreach ($form_fields as $f): 
                $fk = $f['field_key'];
                $is_native = ($f['source_type'] === 'native');
                
                // Value logic
                $val = '';
                if ($is_native) {
                    $val = $device[$fk] ?? '';
                } else {
                    $val = $device_custom[$f['id']] ?? '';
                }
                
                // Name logic
                $input_name = $is_native ? "native[{$fk}]" : "custom[{$f['id']}]";
                $req = $f['is_required'] ? 'required' : '';
            ?>
              <div class="col-12 col-md-6">
                <label class="form-label text-muted small fw-bold mb-1">
                  <?= htmlspecialchars($f['label']) ?>
                  <?php if($f['is_required']): ?><span class="text-danger">*</span><?php endif; ?>
                </label>

                <?php if ($f['field_type'] === 'textarea'): ?>
                  <textarea name="<?= $input_name ?>" class="form-control bg-dark text-light border-secondary" rows="3" <?= $req ?> placeholder="Wpisz treść..."><?= htmlspecialchars((string)$val) ?></textarea>
                
                <?php elseif ($f['field_type'] === 'checkbox'): ?>
                  <select name="<?= $input_name ?>" class="form-select bg-dark text-light border-secondary" <?= $req ?>>
                    <option value="">Wybierz...</option>
                    <option value="1" <?= $val == '1' || $val === 'Tak' ? 'selected' : '' ?>>Tak</option>
                    <option value="0" <?= $val == '0' || $val === 'Nie' ? 'selected' : '' ?>>Nie</option>
                  </select>

                <?php elseif ($is_native && $fk === 'user_id'): ?>
                  <select name="<?= $input_name ?>" class="form-select bg-dark text-light border-secondary" <?= $req ?>>
                    <option value="">-- Magazyn (Brak przypisanego pracownika) --</option>
                    <?php foreach ($users as $u): ?>
                      <option value="<?= $u['id'] ?>" <?= (string)$val === (string)$u['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($u['last_name'] . ' ' . $u['first_name'] . ' (' . $u['username'] . ')') ?>
                      </option>
                    <?php endforeach; ?>
                  </select>

                <?php elseif ($is_native && $fk === 'type_id'): ?>
                  <select name="<?= $input_name ?>" id="typeSelect" class="form-select bg-dark text-light border-secondary" <?= $req ?>>
                    <option value="">-- Wybierz Typ --</option>
                    <?php foreach ($types as $t): ?>
                      <option value="<?= $t['id'] ?>" <?= (string)$val === (string)$t['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($t['name']) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                
                <?php elseif ($is_native && $fk === 'status'): ?>
                  <select name="<?= $input_name ?>" class="form-select bg-dark text-light border-secondary" <?= $req ?>>
                    <option value="ACTIVE" <?= in_array($val, ['ACTIVE', 'Aktywny']) ? 'selected' : '' ?>>ACTIVE (W użyciu)</option>
                    <option value="WAREHOUSE" <?= in_array($val, ['WAREHOUSE', 'Magazyn']) ? 'selected' : '' ?>>WAREHOUSE (Magazyn)</option>
                    <option value="REPAIR" <?= in_array($val, ['REPAIR', 'W naprawie']) ? 'selected' : '' ?>>REPAIR (W naprawie)</option>
                    <option value="RESERVED" <?= $val === 'RESERVED' ? 'selected' : '' ?>>RESERVED (Zarezerwowany)</option>
                    <option value="DISPOSED" <?= in_array($val, ['DISPOSED', 'Zlikwidowany']) ? 'selected' : '' ?>>DISPOSED (Zlikwidowany)</option>
                    <option value="LOST" <?= $val === 'LOST' ? 'selected' : '' ?>>LOST (Zgubiony / Skradziony)</option>
                  </select>
                
                <?php else: ?>
                  <!-- text, number, date -->
                  <input type="<?= htmlspecialchars($f['field_type']) ?>" name="<?= $input_name ?>" value="<?= htmlspecialchars((string)$val) ?>" class="form-control bg-dark text-light border-secondary" <?= $req ?>>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>

            <!-- Optional manual history log -->
            <div class="col-12 mt-3 mt-md-4">
              <div class="p-3 rounded-3 bg-dark bg-opacity-50 border border-secondary border-opacity-25">
                <label class="form-label text-warning small fw-bold mb-1 d-flex align-items-center gap-1">
                  <i class="bi bi-journal-text"></i> Dodaj ręczny wpis do osi czasu (opcjonalnie)
                </label>
                <input type="text" name="event_description" class="form-control bg-dark text-light border-warning border-opacity-50" placeholder="np. Wymiana dysku SSD, oddanie do serwisu, ponowna instalacja systemu...">
                <div class="form-text text-muted small">Wpis zostanie natychmiast zapisany na osi czasu obok automatycznych logów zmiany parametrów.</div>
              </div>
            </div>
            
          </div>
          
          <!-- Bottom Action Buttons -->
          <div class="d-flex flex-column flex-sm-row justify-content-between align-items-stretch align-items-sm-center gap-3 mt-4 pt-3 border-top border-secondary border-opacity-25">
            <a href="<?= htmlspecialchars($return_url ?? 'index.php') ?>" class="btn btn-secondary px-4 py-2 order-3 order-sm-1">
              <i class="bi bi-arrow-left me-1"></i> Wróć bez zmian
            </a>
            <div class="d-flex flex-column flex-sm-row gap-2 order-1 order-sm-2">
              <button type="submit" name="save_and_return" value="1" class="btn btn-success px-4 py-2 fw-bold shadow-sm d-flex align-items-center justify-content-center gap-2">
                <i class="bi bi-check2-circle fs-5"></i> <span>Zapisz i wróć<?= !empty($_SESSION['inventory_filters']['room']) ? ' (Pokój ' . htmlspecialchars($_SESSION['inventory_filters']['room']) . ')' : '' ?></span>
              </button>
              <button type="submit" class="btn btn-primary px-4 py-2 fw-bold shadow-sm d-flex align-items-center justify-content-center gap-2">
                <i class="bi bi-check-lg fs-5"></i> <span>Zapisz</span>
              </button>
            </div>
          </div>
        </form>

        <!-- ========================================================
             WIDOK OSI CZASU NA MOBILE - CHOWA SIĘ POD WIDOK EDYCJI
             Domyślnie zwinięty w elegancki akordeon (collapse)
             ======================================================== -->
        <div class="d-lg-none mt-4 pt-3 border-top border-secondary border-opacity-25">
          <div class="d-grid">
            <button class="btn btn-outline-info d-flex align-items-center justify-content-between p-3 rounded-3 shadow-sm" type="button" data-bs-toggle="collapse" data-bs-target="#mobileTimelineCollapse" aria-expanded="false" aria-controls="mobileTimelineCollapse">
              <span class="fw-bold d-flex align-items-center gap-2">
                <i class="bi bi-clock-history fs-5"></i>
                <span>Oś czasu / Historia zmian</span>
                <span class="badge bg-secondary rounded-pill"><?= count($history) ?></span>
              </span>
              <span class="small text-muted">Rozwiń / Zwiń ▾</span>
            </button>
          </div>

          <!-- Collapsible Timeline Content -->
          <div class="collapse mt-3" id="mobileTimelineCollapse">
            <div class="card border-0 shadow-sm history-collapse-card p-3">
              <div class="timeline ps-2">
                <?php if (empty($history)): ?>
                  <p class="text-muted small py-3 mb-0 text-center">Brak historii zdarzeń dla tego urządzenia.</p>
                <?php else: ?>
                  <?php foreach ($history as $h): ?>
                    <div class="timeline-item mb-3 pb-3 border-bottom border-secondary border-opacity-10">
                      <div class="small text-muted mb-1 d-flex justify-content-between align-items-center">
                        <span><i class="bi bi-calendar3 me-1"></i><?= htmlspecialchars((string)($h['created_at'] ?? $h['date'] ?? '')) ?></span>
                        <span class="badge bg-secondary"><?= htmlspecialchars((string)($h['changed_by'] ?? '')) ?></span>
                      </div>
                      
                      <?php if ($h['event_type'] === 'INFO'): ?>
                        <div class="text-warning fw-bold small"><i class="bi bi-chat-left-text me-1"></i><?= htmlspecialchars((string)($h['description'] ?? '')) ?></div>
                      <?php elseif ($h['event_type'] === 'UPDATE'): ?>
                        <div class="small text-light">
                          Zmieniono: <strong><?= htmlspecialchars((string)($h['field_name'] ?? '')) ?></strong><br>
                          <del class="text-danger opacity-75"><?= htmlspecialchars((string)($h['old_value'] ?? '(puste)')) ?></del> 
                          <span class="text-muted mx-1">&rarr;</span> 
                          <span class="text-success fw-bold"><?= htmlspecialchars((string)($h['new_value'] ?? '(puste)')) ?></span>
                        </div>
                      <?php elseif ($h['event_type'] === 'CREATE'): ?>
                        <div class="text-success fw-bold small"><i class="bi bi-plus-circle me-1"></i>Utworzono urządzenie w systemie.</div>
                      <?php endif; ?>

                      <?php if ($h['description'] && $h['event_type'] !== 'INFO'): ?>
                        <div class="small text-warning mt-1 text-break"><?= htmlspecialchars((string)$h['description']) ?></div>
                      <?php endif; ?>
                    </div>
                  <?php endforeach; ?>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>

      </div>
    </div>
  </div>

  <!-- ========================================================
       PRAWA KOLUMNA: OŚ CZASU / HISTORIA (TABLET & DESKTOP >= 992px)
       Na dużych ekranach wyświetla się elegancko obok formularza
       ======================================================== -->
  <div class="col-12 col-lg-5 col-xl-4 d-none d-lg-block">
    <div class="card border-0 shadow-sm sticky-top" style="top: 1.5rem;">
      <div class="card-body p-4">
        <h5 class="fw-bold text-info mb-3 d-flex align-items-center justify-content-between">
          <span class="d-flex align-items-center gap-2">
            <i class="bi bi-clock-history"></i> Oś czasu / Historia
          </span>
          <span class="badge bg-dark border border-secondary text-muted fs-6"><?= count($history) ?></span>
        </h5>
        <p class="text-muted small mb-4">Chronologiczny zapis modyfikacji parametrów i ręcznych wpisów serwisowych.</p>

        <div class="timeline ps-2" style="max-height: 600px; overflow-y: auto;">
          <?php if (empty($history)): ?>
            <p class="text-muted small py-4 text-center">Brak zarejestrowanej historii dla tego sprzętu.</p>
          <?php else: ?>
            <?php foreach ($history as $h): ?>
              <div class="timeline-item mb-3 pb-3 border-bottom border-secondary border-opacity-10">
                <div class="small text-muted mb-1 d-flex justify-content-between align-items-center">
                  <span><i class="bi bi-clock me-1"></i><?= htmlspecialchars((string)($h['created_at'] ?? $h['date'] ?? '')) ?></span>
                  <span class="badge bg-secondary ms-2" style="font-size: 0.7rem;"><?= htmlspecialchars((string)($h['changed_by'] ?? '')) ?></span>
                </div>
                
                <?php if ($h['event_type'] === 'INFO'): ?>
                  <div class="text-warning fw-bold small"><i class="bi bi-chat-left-text me-1"></i><?= htmlspecialchars((string)($h['description'] ?? '')) ?></div>
                <?php elseif ($h['event_type'] === 'UPDATE'): ?>
                  <div class="small text-light">
                    Zmieniono <strong><?= htmlspecialchars((string)($h['field_name'] ?? '')) ?></strong><br>
                    <del class="text-danger opacity-75"><?= htmlspecialchars((string)($h['old_value'] ?? '(puste)')) ?></del> 
                    <span class="text-muted mx-1">&rarr;</span> 
                    <span class="text-success fw-bold"><?= htmlspecialchars((string)($h['new_value'] ?? '(puste)')) ?></span>
                  </div>
                <?php elseif ($h['event_type'] === 'CREATE'): ?>
                  <div class="text-success fw-bold small"><i class="bi bi-plus-circle me-1"></i>Utworzono urządzenie w systemie.</div>
                <?php endif; ?>

                <?php if ($h['description'] && $h['event_type'] !== 'INFO'): ?>
                  <div class="small text-warning mt-1 text-break"><?= htmlspecialchars((string)$h['description']) ?></div>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>
