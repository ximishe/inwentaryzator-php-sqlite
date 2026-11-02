<!-- Dashboard Header & Top Bar -->
<div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3 mb-3 mb-md-4">
  <div>
    <h2 class="h4 fw-bold text-light mb-1 d-flex align-items-center gap-2">
      <i class="bi bi-clipboard2-check text-primary"></i> Weryfikacja i inwentarz sprzętu
    </h2>
    <p class="text-muted small mb-0">Łącznie zarejestrowanych pozycji: <strong class="text-light"><?= count($items) ?></strong></p>
  </div>
  
  <div class="d-flex flex-wrap align-items-center gap-2">
    <!-- View Mode Switcher -->
    <div class="btn-group view-mode-toggle shadow-sm" role="group" aria-label="Przełącznik widoku">
      <button type="button" class="btn btn-primary btn-sm active" id="btnViewCards" title="Widok kart (idealny na telefon / weryfikację)">
        <i class="bi bi-grid me-1"></i><span>Karty</span>
      </button>
      <button type="button" class="btn btn-outline-secondary btn-sm" id="btnViewTable" title="Widok tabeli / listy">
        <i class="bi bi-table me-1"></i><span>Tabela</span>
      </button>
    </div>

    <!-- Quick Export -->
    <a href="export.php?<?= http_build_query($_GET) ?>" class="btn btn-secondary btn-sm shadow-sm" title="Eksportuj do CSV (Excel)">
      <i class="bi bi-download me-1"></i><span class="d-none d-md-inline">CSV</span>
    </a>

    <!-- Add Device Button -->
    <button type="button" class="btn btn-primary btn-sm px-3 shadow-sm d-flex align-items-center gap-1" data-bs-toggle="modal" data-bs-target="#addDeviceModal">
      <i class="bi bi-plus-lg fs-6"></i> <span>Dodaj sprzęt</span>
    </button>
  </div>
</div>

<!-- Filters & Search Section -->
<div class="card border-0 shadow-sm mb-4">
  <div class="card-body p-3 p-md-4">
    <form method="GET" action="index.php" id="filterForm">
      <div class="row g-2 g-md-3 align-items-center">
        <!-- Main Search Bar -->
        <div class="col-12 col-lg-5">
          <div class="input-group">
            <span class="input-group-text bg-dark border-secondary text-muted"><i class="bi bi-search"></i></span>
            <input type="text" name="q" class="form-control bg-dark text-light border-secondary" placeholder="Szukaj: S/N, inwentarz, model, pokój..." value="<?= htmlspecialchars($search) ?>">
            <?php if ($search !== ''): ?>
              <a href="index.php?reset=1" class="btn btn-outline-secondary border-secondary text-muted" title="Wyczyść"><i class="bi bi-x-lg"></i></a>
            <?php endif; ?>
          </div>
        </div>

        <!-- Room Filter -->
        <div class="col-6 col-sm-4 col-lg-2">
          <select name="room" class="form-select bg-dark text-light border-secondary" onchange="document.getElementById('filterForm').submit();">
            <option value="">-- Pokój / Miejsce --</option>
            <?php foreach ($rooms as $room): ?>
              <option value="<?= htmlspecialchars((string)$room) ?>" <?= $filter_room === (string)$room ? 'selected' : '' ?>>
                <?= htmlspecialchars((string)$room) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <!-- Type Filter -->
        <div class="col-6 col-sm-4 col-lg-2">
          <select name="type_id" class="form-select bg-dark text-light border-secondary" onchange="document.getElementById('filterForm').submit();">
            <option value="">-- Typ urządzenia --</option>
            <?php foreach ($device_types as $dt): ?>
              <option value="<?= $dt['id'] ?>" <?= (isset($filter_type) && (string)$filter_type === (string)$dt['id']) ? 'selected' : '' ?>>
                <?= htmlspecialchars($dt['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <!-- Status Filter -->
        <div class="col-6 col-sm-4 col-lg-1">
          <select name="status" class="form-select bg-dark text-light border-secondary" onchange="document.getElementById('filterForm').submit();">
            <option value="">Status</option>
            <?php foreach ($statuses as $st): ?>
              <option value="<?= htmlspecialchars((string)$st) ?>" <?= $filter_status === (string)$st ? 'selected' : '' ?>>
                <?= htmlspecialchars((string)$st) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <!-- Action buttons -->
        <div class="col-6 col-lg-2 d-flex gap-2 justify-content-end">
          <button type="submit" class="btn btn-primary w-100">
            <i class="bi bi-funnel me-1"></i> Filtruj
          </button>
          <?php if ($search !== '' || $filter_room !== '' || $filter_status !== '' || (!empty($filter_type))): ?>
            <a href="index.php?reset=1" class="btn btn-secondary" title="Resetuj filtry">
              <i class="bi bi-arrow-counterclockwise"></i>
            </a>
          <?php endif; ?>
        </div>
      </div>
    </form>
  </div>
</div>

<!-- ========================================================
     1. WIDOK TABELI GŁÓWNEJ (TABLE VIEW) - ZOPTYMALIZOWANY
     Brak kolumny Status! Czas na końcu! Przyciski Przenieś / Usuń
     Kolumny: Pokój/Miejsce | Producent | Model | S/N | Typ | Nr Inwentarzowy | Czas/Edycja | Akcje
     ======================================================== -->
<div id="containerTableView" class="card border-0 shadow-sm mb-4 d-none">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover table-inventory align-middle mb-0">
        <thead>
          <tr>
            <th class="ps-3"><i class="bi bi-geo-alt me-1"></i> Pokój / Miejsce</th>
            <th>Producent</th>
            <th>Model</th>
            <th>Numer Seryjny (S/N)</th>
            <th>Typ</th>
            <th>Nr Inwentarzowy</th>
            <th><i class="bi bi-clock-history me-1"></i> Czas / Edycja</th>
            <th class="text-end pe-3">Akcje</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($items)): ?>
            <tr>
              <td colspan="8" class="text-center text-muted py-5">
                <i class="bi bi-inbox fs-1 d-block mb-2 text-secondary"></i>
                Brak sprzętu spełniającego wybrane kryteria.
              </td>
            </tr>
          <?php endif; ?>

          <?php foreach ($items as $item): 
            $edit_time = !empty($item['updated_at']) ? $item['updated_at'] : $item['created_at'];
            $is_today = (!empty($edit_time) && date('Y-m-d', strtotime($edit_time)) === date('Y-m-d'));
            $time_display = !empty($edit_time) ? ($is_today ? 'Dzisiaj ' . date('H:i', strtotime($edit_time)) : date('Y-m-d H:i', strtotime($edit_time))) : '-';
            $custom_fields = $custom_fields_by_device[$item['id']] ?? [];
            $device_history = $history_by_device[$item['id']] ?? [];
          ?>
            <tr class="clickable-row <?= $is_today ? 'row-verified' : '' ?>" data-device-id="<?= $item['id'] ?>">
              <!-- 1. Pokój / Miejsce -->
              <td class="ps-3 cell-room" data-label="Pokój / Miejsce">
                <span class="badge badge-room px-2 py-1 fs-6">
                  <i class="bi bi-geo-alt-fill me-1"></i><?= htmlspecialchars((string)($item['room'] ?: 'Brak')) ?>
                </span>
              </td>

              <!-- 2. Producent -->
              <td class="fw-medium text-light cell-mfg" data-label="Producent">
                <?= htmlspecialchars((string)($item['manufacturer'] ?: '-')) ?>
              </td>

              <!-- 3. Model -->
              <td class="fw-semibold text-light cell-model" data-label="Model">
                <?= htmlspecialchars((string)($item['model'] ?: '-')) ?>
              </td>

              <!-- 4. S/N -->
              <td class="cell-sn" data-label="Numer Seryjny (S/N)">
                <code class="text-light bg-dark px-2 py-1 rounded border border-secondary border-opacity-50"><?= htmlspecialchars((string)($item['serial_number'] ?: '-')) ?></code>
              </td>

              <!-- 5. Typ -->
              <td class="cell-type" data-label="Typ urządzenia">
                <span class="badge badge-type px-2 py-1">
                  <?= htmlspecialchars((string)($item['type_name'] ?: 'Inny')) ?>
                </span>
              </td>

              <!-- 6. Numer Inwentarzowy -->
              <td class="cell-asset" data-label="Nr Inwentarzowy">
                <strong class="text-primary fs-6"><?= htmlspecialchars($item['asset_number']) ?></strong>
              </td>

              <!-- 7. Czas / Edycja (przeniesiony na koniec) -->
              <td class="cell-time" data-label="Czas / Edycja">
                <span class="badge <?= $is_today ? 'badge-verified-today' : 'badge-time' ?> time-badge-<?= $item['id'] ?>" title="Ostatnia zmiana / weryfikacja">
                  <i class="bi <?= $is_today ? 'bi-check-circle-fill' : 'bi-clock text-info' ?> me-1"></i><?= htmlspecialchars($time_display) ?>
                </span>
              </td>

              <!-- 8. Akcje: Inv, Przenieś, Edytuj, Usuń, Szczegóły -->
              <td class="text-end pe-3 text-nowrap cell-actions">
                <!-- Inv (Zielony przycisk weryfikacji) -->
                <button type="button" class="btn btn-inv btn-sm px-2 py-1 me-1 btn-inv-check <?= $is_today ? 'btn-inv-checked' : '' ?>" 
                        data-id="<?= $item['id'] ?>" 
                        data-asset="<?= htmlspecialchars($item['asset_number']) ?>" 
                        data-model="<?= htmlspecialchars((string)($item['model'] ?: '')) ?>" 
                        title="Odhacz: potwierdź weryfikację sprzętu (zaktualizuj czas)">
                  <i class="bi <?= $is_today ? 'bi-check-circle-fill' : 'bi-check2-circle' ?> me-1"></i> Inv
                </button>

                <!-- Przenieś (Szybka weryfikacja) -->
                <button type="button" class="btn btn-outline-info btn-sm px-2 py-1 me-1 btn-open-move" 
                        data-id="<?= $item['id'] ?>" 
                        data-asset="<?= htmlspecialchars($item['asset_number']) ?>" 
                        data-room="<?= htmlspecialchars((string)($item['room'] ?: 'Brak')) ?>" 
                        data-model="<?= htmlspecialchars((string)($item['model'] ?: '')) ?>" 
                        title="Przenieś sprzęt (np. Zniszczone / Dozniszczenia)">
                  <i class="bi bi-arrow-left-right me-1"></i> Przenieś
                </button>

                <!-- Edytuj -->
                <a href="edit.php?id=<?= $item['id'] ?><?= !empty($_SERVER['QUERY_STRING']) ? '&return=' . urlencode('index.php?' . $_SERVER['QUERY_STRING']) : '' ?>" class="btn btn-primary btn-sm px-2 py-1 me-1" title="Pełna edycja">
                  <i class="bi bi-pencil-square"></i> <span class="d-inline d-lg-none">Edytuj</span>
                </a>

                <!-- Usuń -->
                <button type="button" class="btn btn-outline-danger btn-sm px-2 py-1 me-1 btn-open-delete" 
                        data-id="<?= $item['id'] ?>" 
                        data-asset="<?= htmlspecialchars($item['asset_number']) ?>" 
                        data-model="<?= htmlspecialchars((string)($item['model'] ?: '')) ?>" 
                        title="Usuń urządzenie z inwentarza">
                  <i class="bi bi-trash3"></i> <span class="d-inline d-lg-none">Usuń</span>
                </button>

                <!-- Rozwiń historię / szczegóły -->
                <button type="button" class="btn btn-secondary btn-sm px-2 py-1" data-bs-toggle="collapse" data-bs-target="#table-details-<?= $item['id'] ?>" title="Szczegóły i historia">
                  <i class="bi bi-chevron-down"></i> <span class="d-inline d-lg-none">Więcej</span>
                </button>
              </td>
            </tr>

            <!-- Table Row Collapsible Details & Timeline -->
            <tr class="p-0 border-0 row-details">
              <td colspan="8" class="p-0 border-0">
                <div class="collapse" id="table-details-<?= $item['id'] ?>">
                  <div class="p-4 m-2 rounded-3 border border-secondary border-opacity-50" style="background-color: #151518;">
                    <div class="row g-4">
                      <!-- Device Attributes -->
                      <div class="col-md-5 border-end border-secondary border-opacity-25">
                        <h6 class="text-uppercase text-muted fw-bold mb-3 small tracking-wider">
                          <i class="bi bi-card-checklist text-info me-1"></i> Szczegóły urządzenia
                        </h6>
                        <div class="d-flex flex-column gap-2 small">
                          <div class="d-flex justify-content-between border-bottom border-secondary border-opacity-25 pb-1">
                            <span class="text-muted">Przypisany pracownik:</span>
                            <span class="text-light fw-bold"><?= $item['user_id'] ? htmlspecialchars($item['first_name'] . ' ' . $item['last_name'] . ' (' . $item['u_login'] . ')') : '<span class="text-muted fst-italic">Magazyn / Brak</span>' ?></span>
                          </div>
                          <div class="d-flex justify-content-between border-bottom border-secondary border-opacity-25 pb-1">
                            <span class="text-muted">Status urządzenia:</span>
                            <span class="badge bg-secondary"><?= htmlspecialchars($item['status']) ?></span>
                          </div>
                          <div class="d-flex justify-content-between border-bottom border-secondary border-opacity-25 pb-1">
                            <span class="text-muted">Data zakupu:</span>
                            <span class="text-light"><?= htmlspecialchars((string)$item['purchase_date'] ?: 'Brak') ?></span>
                          </div>
                          <div class="d-flex justify-content-between border-bottom border-secondary border-opacity-25 pb-1">
                            <span class="text-muted">Cena zakupu:</span>
                            <span class="text-light"><?= $item['purchase_price'] ? htmlspecialchars((string)$item['purchase_price']).' PLN' : 'Brak' ?></span>
                          </div>
                          <div class="d-flex justify-content-between border-bottom border-secondary border-opacity-25 pb-1">
                            <span class="text-muted">Faktura:</span>
                            <span class="text-light"><?= htmlspecialchars((string)$item['invoice'] ?: 'Brak') ?></span>
                          </div>
                          <div class="d-flex justify-content-between border-bottom border-secondary border-opacity-25 pb-1">
                            <span class="text-muted">Gwarancja do:</span>
                            <span class="text-light"><?= htmlspecialchars((string)$item['warranty_until'] ?: 'Brak') ?></span>
                          </div>
                          <div class="d-flex justify-content-between border-bottom border-secondary border-opacity-25 pb-1">
                            <span class="text-muted">Opis / Uwagi:</span>
                            <span class="text-light text-break"><?= htmlspecialchars((string)$item['description'] ?: 'Brak') ?></span>
                          </div>
                          <?php foreach ($custom_fields as $cf): ?>
                            <div class="d-flex justify-content-between border-bottom border-secondary border-opacity-25 pb-1">
                              <span class="text-muted"><?= htmlspecialchars($cf['name']) ?>:</span>
                              <span class="text-info fw-bold"><?= htmlspecialchars($cf['value']) ?></span>
                            </div>
                          <?php endforeach; ?>
                        </div>
                      </div>

                      <!-- History Timeline -->
                      <div class="col-md-7 ps-md-4">
                        <h6 class="text-uppercase text-muted fw-bold mb-3 small tracking-wider">
                          <i class="bi bi-clock-history text-warning me-1"></i> Oś czasu / Historia zmian
                        </h6>
                        <div class="timeline pe-2" style="max-height: 220px; overflow-y: auto;">
                          <?php if (empty($device_history)): ?>
                            <div class="text-muted small fst-italic py-2">Brak zarejestrowanych zdarzeń w historii.</div>
                          <?php else: ?>
                            <?php foreach ($device_history as $h): ?>
                              <div class="timeline-item mb-2 pb-2 border-bottom border-secondary border-opacity-10">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                  <span class="badge bg-secondary text-uppercase" style="font-size: 0.65rem;"><?= htmlspecialchars((string)$h['event_type']) ?></span>
                                  <span class="text-muted small"><?= date('Y-m-d H:i', strtotime($h['date'])) ?></span>
                                </div>
                                <?php if ($h['field_name']): ?>
                                  <div class="small text-light">
                                    <span class="text-muted"><?= htmlspecialchars((string)$h['field_name']) ?>:</span>
                                    <del class="text-danger opacity-75"><?= htmlspecialchars((string)$h['old_value']) ?></del>
                                    <span class="text-muted mx-1">&rarr;</span>
                                    <span class="text-success"><?= htmlspecialchars((string)$h['new_value']) ?></span>
                                  </div>
                                <?php endif; ?>
                                <?php if ($h['description']): ?>
                                  <div class="small text-warning mt-1"><?= htmlspecialchars((string)$h['description']) ?></div>
                                <?php endif; ?>
                                <div class="text-secondary mt-1" style="font-size: 0.7rem;">Autor: <?= htmlspecialchars((string)$h['changed_by']) ?></div>
                              </div>
                            <?php endforeach; ?>
                          <?php endif; ?>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- ========================================================
     2. WIDOK KART (MOBILE CARD VIEW) - DLA WERYFIKACJI
     Zoptymalizowany pod dotyk, duże przyciski PRZENIEŚ / USUŃ
     ======================================================== -->
<div id="containerCardsView" class="mb-4">
  <div class="row g-3">
    <?php if (empty($items)): ?>
      <div class="col-12 text-center text-muted py-5 card border-0 bg-dark shadow-sm">
        <i class="bi bi-inbox fs-1 d-block mb-2 text-secondary"></i>
        Brak sprzętu spełniającego wybrane kryteria.
      </div>
    <?php endif; ?>

    <?php foreach ($items as $item): 
      $edit_time = !empty($item['updated_at']) ? $item['updated_at'] : $item['created_at'];
      $is_today = (!empty($edit_time) && date('Y-m-d', strtotime($edit_time)) === date('Y-m-d'));
      $time_display = !empty($edit_time) ? ($is_today ? 'Dzisiaj ' . date('H:i', strtotime($edit_time)) : date('Y-m-d H:i', strtotime($edit_time))) : '-';
      $custom_fields = $custom_fields_by_device[$item['id']] ?? [];
      $device_history = $history_by_device[$item['id']] ?? [];
    ?>
      <div class="col-12 col-md-6 col-xl-4">
        <div class="card device-mobile-card shadow-sm h-100 p-3 <?= $is_today ? 'card-verified' : '' ?>" data-device-id="<?= $item['id'] ?>">
          <!-- Top Row: Nr Inwentarzowy + Pokój (duży badge) + Czas -->
          <div class="card-header-bar mb-2">
            <div class="d-flex align-items-center gap-2">
              <strong class="text-primary fs-5"><?= htmlspecialchars($item['asset_number']) ?></strong>
              <span class="badge badge-room px-2 py-1">
                <i class="bi bi-geo-alt-fill me-1"></i><?= htmlspecialchars((string)($item['room'] ?: 'Brak')) ?>
              </span>
            </div>
            <div class="text-end" title="Czas edycji / weryfikacji">
              <span class="badge <?= $is_today ? 'badge-verified-today' : 'badge-time' ?> time-badge-<?= $item['id'] ?>">
                <i class="bi <?= $is_today ? 'bi-check-circle-fill' : 'bi-clock-history text-info' ?> me-1"></i><?= htmlspecialchars($time_display) ?>
              </span>
            </div>
          </div>

          <!-- Title: Producent + Model + Typ -->
          <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
            <div class="device-title">
              <?= htmlspecialchars((string)($item['manufacturer'] ?: 'Nieznany')) ?>
              <span class="text-light"><?= htmlspecialchars((string)($item['model'] ?: '')) ?></span>
            </div>
            <span class="badge badge-type text-nowrap">
              <?= htmlspecialchars((string)($item['type_name'] ?: 'Inny')) ?>
            </span>
          </div>

          <!-- Metadata: SN + Pracownik -->
          <div class="meta-grid mb-3 bg-dark bg-opacity-50 p-2 rounded-3 border border-secondary border-opacity-25">
            <div class="meta-item">
              <span class="meta-label">Numer Seryjny (S/N)</span>
              <span class="meta-value">
                <code class="text-light"><?= htmlspecialchars((string)($item['serial_number'] ?: '-')) ?></code>
              </span>
            </div>
            <div class="meta-item">
              <span class="meta-label">Pracownik</span>
              <span class="meta-value small">
                <?= $item['user_id'] ? htmlspecialchars($item['first_name'] . ' ' . $item['last_name']) : '<span class="text-muted fst-italic">Magazyn</span>' ?>
              </span>
            </div>
          </div>

          <!-- Action Buttons Bar: INV, PRZENIEŚ, EDYTUJ, USUŃ -->
          <div class="d-flex flex-wrap gap-2 mt-auto pt-2 border-top border-secondary border-opacity-25">
            <!-- Przycisk Inv (Zielony - Odhacz weryfikację) -->
            <button type="button" class="btn btn-inv btn-sm fw-bold px-3 btn-inv-check flex-grow-1 <?= $is_today ? 'btn-inv-checked' : '' ?>"
                    data-id="<?= $item['id'] ?>" 
                    data-asset="<?= htmlspecialchars($item['asset_number']) ?>" 
                    data-model="<?= htmlspecialchars((string)($item['model'] ?: '')) ?>"
                    title="Odhacz: potwierdź weryfikację sprzętu (zaktualizuj sygnaturę czasową)">
              <i class="bi <?= $is_today ? 'bi-check-circle-fill' : 'bi-check2-circle' ?> me-1"></i> Inv
            </button>

            <!-- Przycisk Przenieś -->
            <button type="button" class="btn btn-outline-info btn-sm flex-grow-1 fw-bold btn-open-move"
                    data-id="<?= $item['id'] ?>" 
                    data-asset="<?= htmlspecialchars($item['asset_number']) ?>" 
                    data-room="<?= htmlspecialchars((string)($item['room'] ?: 'Brak')) ?>" 
                    data-model="<?= htmlspecialchars((string)($item['model'] ?: '')) ?>">
              <i class="bi bi-arrow-left-right me-1"></i> Przenieś
            </button>

            <!-- Przycisk Edytuj -->
            <a href="edit.php?id=<?= $item['id'] ?><?= !empty($_SERVER['QUERY_STRING']) ? '&return=' . urlencode('index.php?' . $_SERVER['QUERY_STRING']) : '' ?>" class="btn btn-primary btn-sm px-3 fw-bold shadow-sm d-flex align-items-center gap-1">
              <i class="bi bi-pencil-square"></i> Edytuj
            </a>

            <!-- Przycisk Usuń -->
            <button type="button" class="btn btn-outline-danger btn-sm px-2 btn-open-delete"
                    data-id="<?= $item['id'] ?>" 
                    data-asset="<?= htmlspecialchars($item['asset_number']) ?>" 
                    data-model="<?= htmlspecialchars((string)($item['model'] ?: '')) ?>" 
                    title="Usuń">
              <i class="bi bi-trash3"></i>
            </button>

            <!-- Przycisk Rozwiń Szczegóły -->
            <button class="btn btn-secondary btn-sm px-2" type="button" data-bs-toggle="collapse" data-bs-target="#mob-card-details-<?= $item['id'] ?>" title="Szczegóły & historia">
              <i class="bi bi-chevron-down"></i>
            </button>
          </div>

          <!-- Collapsible Details & Timeline on Card -->
          <div class="collapse mt-3" id="mob-card-details-<?= $item['id'] ?>">
            <div class="p-3 rounded-3 history-collapse-card">
              <h6 class="text-uppercase text-muted fw-bold mb-2 small tracking-wider">Właściwości</h6>
              <div class="small text-light mb-1"><span class="text-muted">Status:</span> <?= htmlspecialchars($item['status']) ?></div>
              <div class="small text-light mb-1"><span class="text-muted">Opis:</span> <?= htmlspecialchars((string)$item['description'] ?: 'Brak') ?></div>
              <div class="small text-light mb-1"><span class="text-muted">Zakup:</span> <?= htmlspecialchars((string)$item['purchase_date'] ?: 'Brak') ?> (<?= $item['purchase_price'] ? htmlspecialchars((string)$item['purchase_price']).' PLN' : 'Brak' ?>)</div>
              <div class="small text-light mb-2"><span class="text-muted">Faktura:</span> <?= htmlspecialchars((string)$item['invoice'] ?: 'Brak') ?></div>
              
              <?php foreach ($custom_fields as $cf): ?>
                <div class="small text-light mb-1"><span class="text-muted"><?= htmlspecialchars($cf['name']) ?>:</span> <span class="text-info"><?= htmlspecialchars($cf['value']) ?></span></div>
              <?php endforeach; ?>

              <!-- Timeline -->
              <h6 class="text-uppercase text-muted fw-bold mt-3 mb-2 small tracking-wider">
                <i class="bi bi-clock-history text-warning me-1"></i> Oś czasu (<?= count($device_history) ?>)
              </h6>
              <div class="timeline ps-2" style="max-height: 180px; overflow-y: auto;">
                <?php if (empty($device_history)): ?>
                  <div class="text-muted small fst-italic">Brak historii dla tego urządzenia.</div>
                <?php else: ?>
                  <?php foreach ($device_history as $h): ?>
                    <div class="timeline-item mb-2 pb-2 border-bottom border-secondary border-opacity-10">
                      <div class="d-flex justify-content-between mb-1">
                        <strong class="text-light small"><?= htmlspecialchars((string)$h['event_type']) ?></strong>
                        <span class="text-muted" style="font-size: 0.75rem;"><?= date('Y-m-d H:i', strtotime($h['date'])) ?></span>
                      </div>
                      <?php if ($h['field_name']): ?>
                        <div class="small text-light">
                          <span class="text-muted"><?= htmlspecialchars((string)$h['field_name']) ?>:</span>
                          <del class="text-danger opacity-75"><?= htmlspecialchars((string)$h['old_value']) ?></del> &rarr; <span class="text-success"><?= htmlspecialchars((string)$h['new_value']) ?></span>
                        </div>
                      <?php endif; ?>
                      <?php if ($h['description']): ?>
                        <div class="small text-warning mt-1"><?= htmlspecialchars((string)$h['description']) ?></div>
                      <?php endif; ?>
                      <div class="text-secondary" style="font-size: 0.7rem;">Zmienił: <?= htmlspecialchars((string)$h['changed_by']) ?></div>
                    </div>
                  <?php endforeach; ?>
                <?php endif; ?>
              </div>
            </div>
          </div>

        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<!-- ========================================================
     MODAL: SZYBKIE PRZENIESIENIE SPRZĘTU (QUICK MOVE)
     Umożliwia 1 tapnięcie: "Zniszczone", "Dozniszczenia", "Magazyn"
     ======================================================== -->
<div class="modal fade" id="quickMoveModal" tabindex="-1" aria-labelledby="quickMoveModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg" style="border-radius: 16px; background-color: #18181b;">
      <div class="modal-header border-bottom border-secondary border-opacity-25 pb-3">
        <h5 class="modal-title fw-bold text-light d-flex align-items-center gap-2" id="quickMoveModalLabel">
          <i class="bi bi-arrow-left-right text-info"></i>
          <span>Przenieś sprzęt: <span id="quickMoveAsset" class="text-primary"></span></span>
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Zamknij"></button>
      </div>
      <div class="modal-body p-3 p-md-4">
        <div class="mb-3 p-2 rounded-3 bg-dark border border-secondary border-opacity-25 d-flex justify-content-between align-items-center">
          <span class="text-muted small">Aktualny pokój / miejsce:</span>
          <span class="badge badge-room fs-6" id="quickMoveCurrentRoom"></span>
        </div>

        <!-- Szybkie przyciski weryfikacji w terenie -->
        <label class="form-label text-warning small fw-bold mb-2 d-flex align-items-center gap-1">
          <i class="bi bi-lightning-charge-fill"></i> Szybka weryfikacja w terenie (1 kliknięcie):
        </label>
        <div class="d-grid gap-2 mb-4">
          <div class="row g-2">
            <div class="col-6">
              <button type="button" class="btn btn-outline-danger w-100 py-2 fw-bold quick-target-btn d-flex align-items-center justify-content-center gap-1" data-target="Dozniszczenia">
                ⚠️ Dozniszczenia
              </button>
            </div>
            <div class="col-6">
              <button type="button" class="btn btn-danger w-100 py-2 fw-bold quick-target-btn d-flex align-items-center justify-content-center gap-1" data-target="Zniszczone">
                💥 Zniszczone
              </button>
            </div>
            <div class="col-6">
              <button type="button" class="btn btn-outline-secondary w-100 py-2 quick-target-btn d-flex align-items-center justify-content-center gap-1" data-target="Magazyn">
                📦 Magazyn
              </button>
            </div>
            <div class="col-6">
              <button type="button" class="btn btn-outline-warning w-100 py-2 quick-target-btn d-flex align-items-center justify-content-center gap-1" data-target="Serwis">
                🔧 Serwis
              </button>
            </div>
          </div>
        </div>

        <form method="POST" action="index.php" id="quickMoveForm">
          <input type="hidden" name="action" value="move_room">
          <input type="hidden" name="device_id" id="quickMoveDeviceId">

          <div class="mb-3">
            <label class="form-label text-muted small fw-bold mb-1">Lub wybierz / wpisz dowolny inny pokój:</label>
            <input type="text" name="new_room" id="quickMoveInputRoom" class="form-control bg-dark text-light border-secondary" list="quickMoveRoomsList" placeholder="Wpisz lub wybierz z listy..." required autocomplete="off">
            <datalist id="quickMoveRoomsList">
              <option value="Dozniszczenia">
              <option value="Zniszczone">
              <option value="Magazyn">
              <option value="Serwis">
              <?php foreach ($rooms as $r): ?>
                <option value="<?= htmlspecialchars((string)$r) ?>">
              <?php endforeach; ?>
            </datalist>
          </div>

          <div class="d-flex justify-content-between pt-2 border-top border-secondary border-opacity-25">
            <button type="button" class="btn btn-secondary px-3" data-bs-dismiss="modal">Anuluj</button>
            <button type="submit" class="btn btn-primary fw-bold px-4">
              <i class="bi bi-check-lg me-1"></i> Zapisz zmianę miejsca
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- ========================================================
     MODAL: POTWIERDZENIE USUNIĘCIA SPRZĘTU (DELETE MODAL)
     ======================================================== -->
<div class="modal fade" id="deleteDeviceModal" tabindex="-1" aria-labelledby="deleteDeviceModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg" style="border-radius: 16px; background-color: #18181b;">
      <div class="modal-header border-bottom border-danger border-opacity-25 pb-3">
        <h5 class="modal-title fw-bold text-danger d-flex align-items-center gap-2" id="deleteDeviceModalLabel">
          <i class="bi bi-exclamation-triangle-fill"></i> Potwierdzenie usunięcia
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Zamknij"></button>
      </div>
      <div class="modal-body p-3 p-md-4">
        <p class="text-light mb-2">Czy na pewno chcesz usunąć urządzenie z inwentarza?</p>
        <div class="p-3 mb-3 rounded-3 bg-dark border border-secondary border-opacity-25">
          <div class="fw-bold text-primary fs-5 mb-1" id="deleteDeviceAsset"></div>
          <div class="small text-muted" id="deleteDeviceModel"></div>
        </div>
        <p class="small text-danger mb-4">
          <i class="bi bi-info-circle me-1"></i> Tej operacji nie można cofnąć. Zostanie również usunięta historia tego urządzenia.
        </p>

        <form method="POST" action="index.php">
          <input type="hidden" name="action" value="delete_device">
          <input type="hidden" name="device_id" id="deleteDeviceId">
          
          <div class="d-flex justify-content-between">
            <button type="button" class="btn btn-secondary px-3" data-bs-dismiss="modal">Anuluj</button>
            <button type="submit" class="btn btn-danger fw-bold px-4">
              <i class="bi bi-trash-fill me-1"></i> Tak, usuń trwale
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- ========================================================
     MODAL: DODAJ NOWE URZĄDZENIE (ADD DEVICE MODAL)
     ======================================================== -->
<div class="modal fade" id="addDeviceModal" tabindex="-1" aria-labelledby="addDeviceModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content border-0 shadow-lg" style="border-radius: 16px; background-color: #18181b;">
      <div class="modal-header border-bottom border-secondary border-opacity-25 pb-3">
        <h5 class="modal-title fw-bold text-light d-flex align-items-center gap-2" id="addDeviceModalLabel">
          <span class="badge bg-primary p-2 rounded-3"><i class="bi bi-plus-circle fs-6"></i></span>
          Dodaj nowe urządzenie do inwentarza
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Zamknij"></button>
      </div>
      <div class="modal-body p-3 p-md-4">
        <form method="POST" action="index.php">
          <input type="hidden" name="action" value="create_device">
          <div class="row g-3">
            <div class="col-12 col-md-6">
              <label class="form-label text-muted small fw-bold mb-1">
                Nr inwentarzowy <span class="badge bg-secondary text-light fw-normal ms-1" style="font-size:0.65rem;">Opcjonalny (auto-generowany)</span>
              </label>
              <input type="text" name="native[asset_number]" class="form-control bg-dark text-light border-secondary" placeholder="np. IT-000842 (lub puste dla auto)">
              <div class="form-text text-muted" style="font-size: 0.75rem;">Jeśli nie wpiszesz numeru, system automatycznie nada unikalny numer IT-XXXXXX.</div>
            </div>
            <div class="col-12 col-md-6">
              <label class="form-label text-muted small fw-bold mb-1">Status</label>
              <select name="native[status]" class="form-select bg-dark text-light border-secondary">
                <option value="ACTIVE" selected>ACTIVE (W użyciu)</option>
                <option value="WAREHOUSE">WAREHOUSE (Magazyn)</option>
                <option value="REPAIR">REPAIR (W naprawie)</option>
                <option value="DISPOSED">DISPOSED (Zlikwidowany)</option>
              </select>
            </div>
            <div class="col-12 col-md-6">
              <label class="form-label text-muted small fw-bold mb-1">Typ urządzenia</label>
              <select name="native[type_id]" class="form-select bg-dark text-light border-secondary">
                <option value="">-- Wybierz typ --</option>
                <?php foreach ($device_types as $dt): ?>
                  <option value="<?= $dt['id'] ?>"><?= htmlspecialchars($dt['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-12 col-md-6">
              <label class="form-label text-muted small fw-bold mb-1">Producent</label>
              <input type="text" name="native[manufacturer]" class="form-control bg-dark text-light border-secondary" placeholder="np. Dell, HP, Lenovo">
            </div>
            <div class="col-12 col-md-6">
              <label class="form-label text-muted small fw-bold mb-1">Model</label>
              <input type="text" name="native[model]" class="form-control bg-dark text-light border-secondary" placeholder="np. Latitude 5520, ThinkPad">
            </div>
            <div class="col-12 col-md-6">
              <label class="form-label text-muted small fw-bold mb-1">Numer seryjny (S/N)</label>
              <input type="text" name="native[serial_number]" class="form-control bg-dark text-light border-secondary" placeholder="np. ABC123XYZ">
            </div>
            <div class="col-12 col-md-6">
              <label class="form-label text-muted small fw-bold mb-1">Przypisany pracownik</label>
              <select name="native[user_id]" class="form-select bg-dark text-light border-secondary">
                <option value="">-- Magazyn (Brak przypisania) --</option>
                <?php foreach ($all_users as $u): ?>
                  <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['first_name'] . ' ' . $u['last_name'] . ' (' . $u['username'] . ')') ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-12 col-md-6">
              <label class="form-label text-muted small fw-bold mb-1">Pokój / Miejsce</label>
              <input type="text" name="native[room]" class="form-control bg-dark text-light border-secondary" placeholder="np. 9, Magazyn, Serwerownia">
            </div>
            <div class="col-12">
              <label class="form-label text-muted small fw-bold mb-1">Opis / Uwagi</label>
              <textarea name="native[description]" class="form-control bg-dark text-light border-secondary" rows="2" placeholder="Dodatkowe informacje..."></textarea>
            </div>
            <div class="col-12 col-md-6">
              <label class="form-label text-muted small fw-bold mb-1">Data zakupu</label>
              <input type="date" name="native[purchase_date]" class="form-control bg-dark text-light border-secondary">
            </div>
            <div class="col-12 col-md-6">
              <label class="form-label text-muted small fw-bold mb-1">Cena zakupu (PLN)</label>
              <input type="number" step="0.01" name="native[purchase_price]" class="form-control bg-dark text-light border-secondary" placeholder="0.00">
            </div>
            <div class="col-12 col-md-6">
              <label class="form-label text-muted small fw-bold mb-1">Nr faktury</label>
              <input type="text" name="native[invoice]" class="form-control bg-dark text-light border-secondary" placeholder="FV/2026/...">
            </div>
            <div class="col-12 col-md-6">
              <label class="form-label text-muted small fw-bold mb-1">Gwarancja do</label>
              <input type="date" name="native[warranty_until]" class="form-control bg-dark text-light border-secondary">
            </div>
          </div>
          <div class="mt-4 pt-3 border-top border-secondary border-opacity-25 d-flex justify-content-between">
            <button type="button" class="btn btn-secondary px-3" data-bs-dismiss="modal">Anuluj</button>
            <button type="submit" class="btn btn-primary px-4 fw-bold">
              <i class="bi bi-check-lg me-1"></i> Zapisz i dodaj sprzęt
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- JavaScript for Quick Move, Delete & View Switcher -->
<script>
document.addEventListener('DOMContentLoaded', function() {
  // 1. View switcher logic
  const btnCards = document.getElementById('btnViewCards');
  const btnTable = document.getElementById('btnViewTable');
  const containerCards = document.getElementById('containerCardsView');
  const containerTable = document.getElementById('containerTableView');

  function setView(mode) {
    if (mode === 'table') {
      containerTable.classList.remove('d-none');
      containerCards.classList.add('d-none');
      btnTable.classList.add('active', 'btn-primary');
      btnTable.classList.remove('btn-outline-secondary');
      btnCards.classList.remove('active', 'btn-primary');
      btnCards.classList.add('btn-outline-secondary');
    } else {
      containerCards.classList.remove('d-none');
      containerTable.classList.add('d-none');
      btnCards.classList.add('active', 'btn-primary');
      btnCards.classList.remove('btn-outline-secondary');
      btnTable.classList.remove('active', 'btn-primary');
      btnTable.classList.add('btn-outline-secondary');
    }
    localStorage.setItem('inventaryzator_preferred_view', mode);
  }

  const savedView = localStorage.getItem('inventaryzator_preferred_view');
  if (window.innerWidth < 992) {
    // Mobile / Tablet: always default to cards (or saved table)
    if (savedView === 'table') {
      setView('table');
    } else {
      setView('cards');
    }
  } else {
    // Desktop: default to table (or saved cards)
    if (savedView === 'cards') {
      setView('cards');
    } else {
      setView('table');
    }
  }

  btnCards.addEventListener('click', function() { setView('cards'); });
  btnTable.addEventListener('click', function() { setView('table'); });

  // 2. Quick Move Modal Trigger logic
  const moveModalEl = document.getElementById('quickMoveModal');
  const moveModal = new bootstrap.Modal(moveModalEl);
  const inputMoveId = document.getElementById('quickMoveDeviceId');
  const textMoveAsset = document.getElementById('quickMoveAsset');
  const textMoveCurRoom = document.getElementById('quickMoveCurrentRoom');
  const inputMoveRoom = document.getElementById('quickMoveInputRoom');
  const moveForm = document.getElementById('quickMoveForm');

  document.querySelectorAll('.btn-open-move').forEach(btn => {
    btn.addEventListener('click', function() {
      const id = this.getAttribute('data-id');
      const asset = this.getAttribute('data-asset');
      const room = this.getAttribute('data-room');
      
      inputMoveId.value = id;
      textMoveAsset.textContent = asset;
      textMoveCurRoom.textContent = room;
      inputMoveRoom.value = '';
      moveModal.show();
    });
  });

  // Fast 1-click room button handler inside Quick Move modal
  document.querySelectorAll('.quick-target-btn').forEach(btn => {
    btn.addEventListener('click', function() {
      const targetRoom = this.getAttribute('data-target');
      inputMoveRoom.value = targetRoom;
      moveForm.submit(); // Instant 1-tap submission!
    });
  });

  // 3. Delete Modal Trigger logic
  const deleteModalEl = document.getElementById('deleteDeviceModal');
  const deleteModal = new bootstrap.Modal(deleteModalEl);
  const inputDeleteId = document.getElementById('deleteDeviceId');
  const textDeleteAsset = document.getElementById('deleteDeviceAsset');
  const textDeleteModel = document.getElementById('deleteDeviceModel');

  document.querySelectorAll('.btn-open-delete').forEach(btn => {
    btn.addEventListener('click', function() {
      const id = this.getAttribute('data-id');
      const asset = this.getAttribute('data-asset');
      const model = this.getAttribute('data-model');
      
      inputDeleteId.value = id;
      textDeleteAsset.textContent = asset;
      textDeleteModel.textContent = model ? 'Model: ' + model : '';
      deleteModal.show();
    });
  });

  // 4. Fast 1-tap "Inv" Checkmark handler (AJAX without page reload)
  document.querySelectorAll('.btn-inv-check').forEach(btn => {
    btn.addEventListener('click', async function(e) {
      e.preventDefault();
      e.stopPropagation();
      const id = this.getAttribute('data-id');
      const asset = this.getAttribute('data-asset') || '';
      const model = this.getAttribute('data-model') || '';

      const originalHtml = this.innerHTML;
      this.disabled = true;
      this.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>...';

      try {
        const formData = new FormData();
        formData.append('action', 'verify_device');
        formData.append('device_id', id);
        formData.append('ajax', '1');

        const response = await fetch('index.php', {
          method: 'POST',
          body: formData,
          headers: {
            'X-Requested-With': 'XMLHttpRequest'
          }
        });

        const data = await response.json();
        if (data.success) {
          // Success! Update all buttons for this device (both card and table)
          document.querySelectorAll(`.btn-inv-check[data-id="${id}"]`).forEach(b => {
            b.classList.add('btn-inv-checked');
            b.innerHTML = '<i class="bi bi-check-circle-fill me-1"></i> Inv';
            b.disabled = false;
          });

          // Update time badges on both card and table
          document.querySelectorAll(`.time-badge-${id}`).forEach(badge => {
            badge.className = 'badge badge-verified-today time-badge-' + id;
            badge.innerHTML = `<i class="bi bi-check-circle-fill me-1"></i>${data.time_display}`;
          });

          // Update row and card left border to green
          document.querySelectorAll(`[data-device-id="${id}"]`).forEach(el => {
            el.classList.add('row-verified', 'card-verified');
          });

          showFloatingToast(`✓ Odhaczono ${asset} (${model}) - zaktualizowano sygnaturę!`);
        } else {
          alert('Błąd: ' + (data.error || 'Nie udało się zapisać weryfikacji.'));
          this.innerHTML = originalHtml;
          this.disabled = false;
        }
      } catch (err) {
        console.error(err);
        alert('Wystąpił błąd sieci podczas zapisywania.');
        this.innerHTML = originalHtml;
        this.disabled = false;
      }
    });
  });

  // Floating notification toast helper
  function showFloatingToast(message) {
    let toast = document.getElementById('invFloatingToast');
    if (!toast) {
      toast = document.createElement('div');
      toast.id = 'invFloatingToast';
      toast.style.cssText = 'position: fixed; bottom: 24px; right: 24px; z-index: 9999; min-width: 280px; max-width: 90vw; padding: 12px 20px; border-radius: 12px; font-weight: 600; font-size: 0.95rem; box-shadow: 0 10px 30px rgba(0,0,0,0.6); transition: opacity 0.3s ease, transform 0.3s ease; opacity: 0; transform: translateY(20px); pointer-events: none;';
      document.body.appendChild(toast);
    }
    toast.className = 'bg-success text-white border border-success border-opacity-50';
    toast.innerHTML = `<i class="bi bi-check-circle-fill me-2 fs-5 align-middle"></i><span>${message}</span>`;
    toast.style.opacity = '1';
    toast.style.transform = 'translateY(0)';

    clearTimeout(toast._timeout);
    toast._timeout = setTimeout(() => {
      toast.style.opacity = '0';
      toast.style.transform = 'translateY(20px)';
    }, 2500);
  }
});
</script>