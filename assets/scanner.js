/**
 * INVENTARYZATOR - Barcode & QR Code Scanner Controller
 * Wykorzystuje bibliotekę Html5Qrcode z priorytetem kamery tylnej (environment).
 */

(function () {
  'use strict';

  let html5QrCode = null;
  let currentTargetSelector = null;
  let availableCameras = [];
  let selectedCameraId = null;
  let isTorchOn = false;
  let currentMediaTrack = null;
  let isScanning = false;

  // Dźwięk potwierdzenia (Web Audio API)
  function playBeepSound() {
    try {
      const AudioCtx = window.AudioContext || window.webkitAudioContext;
      if (!AudioCtx) return;
      const ctx = new AudioCtx();
      const osc = ctx.createOscillator();
      const gain = ctx.createGain();
      osc.type = 'sine';
      osc.frequency.setValueAtTime(1400, ctx.currentTime);
      gain.gain.setValueAtTime(0.25, ctx.currentTime);
      gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.15);
      osc.connect(gain);
      gain.connect(ctx.destination);
      osc.start();
      osc.stop(ctx.currentTime + 0.15);
    } catch (e) {
      // Ignoruj brak wsparcia audio
    }
  }

  // Wibracja telefonu
  function triggerVibrate() {
    try {
      if (navigator.vibrate) {
        navigator.vibrate([80, 50, 80]);
      }
    } catch (e) {}
  }

  // Komunikat w modalu
  function showScannerAlert(message, type = 'warning') {
    const $alert = $('#scannerAlert');
    if (!$alert.length) return;
    $alert.removeClass('alert-warning alert-danger alert-info alert-success d-none')
          .addClass(`alert-${type}`)
          .html(message);
  }

  function hideScannerAlert() {
    $('#scannerAlert').addClass('d-none').empty();
  }

  // Sprawdzenie kontekstu bezpieczeństwa (HTTPS / localhost)
  function checkSecureContext() {
    const isLocal = window.location.hostname === 'localhost' || 
                    window.location.hostname === '127.0.0.1' || 
                    window.location.hostname === '::1';
    if (!window.isSecureContext && !isLocal && window.location.protocol !== 'https:') {
      showScannerAlert(
        '<strong>Uwaga:</strong> Przeglądarka mobilna wymaga połączenia <strong>HTTPS</strong>, aby uruchomić podgląd aparatu na żywo. Jeśli strona działa po HTTP, skorzystaj z przycisku <em>"Zdjęcie z aparatu"</em> poniżej.',
        'warning'
      );
      return false;
    }
    return true;
  }

  // Inicjalizacja instancji skanera
  function getScannerInstance() {
    if (!html5QrCode) {
      html5QrCode = new Html5Qrcode('scanner-reader', {
        verbose: false,
        formatsToSupport: [
          Html5QrcodeSupportedFormats.CODE_128,
          Html5QrcodeSupportedFormats.CODE_39,
          Html5QrcodeSupportedFormats.CODE_93,
          Html5QrcodeSupportedFormats.CODABAR,
          Html5QrcodeSupportedFormats.EAN_13,
          Html5QrcodeSupportedFormats.EAN_8,
          Html5QrcodeSupportedFormats.UPC_A,
          Html5QrcodeSupportedFormats.UPC_E,
          Html5QrcodeSupportedFormats.ITF,
          Html5QrcodeSupportedFormats.QR_CODE,
          Html5QrcodeSupportedFormats.DATA_MATRIX,
          Html5QrcodeSupportedFormats.AZTEC,
          Html5QrcodeSupportedFormats.PDF_417
        ]
      });
    }
    return html5QrCode;
  }

  // Sukces odczytu kodu
  function onScanSuccess(decodedText, decodedResult) {
    if (!isScanning) return;
    isScanning = false;

    playBeepSound();
    triggerVibrate();

    // Animacja sukcesu
    $('.scanner-reticle').addClass('border-success shadow-lg');

    // Wstawienie wartości do docelowego pola
    if (currentTargetSelector) {
      const $target = $(currentTargetSelector);
      if ($target.length) {
        $target.val(decodedText).trigger('input').trigger('change');
        
        // Podświetlenie pola na zielono
        $target.addClass('is-valid');
        setTimeout(() => $target.removeClass('is-valid'), 2500);

        // Jeśli to pole wyszukiwarki na dashboardzie, automatycznie wyślij formularz po 300ms
        if ($target.attr('id') === 'dashboard_search_input') {
          setTimeout(() => {
            $target.closest('form').submit();
          }, 350);
        }
      }
    }

    // Zamknięcie modala
    setTimeout(() => {
      const modalEl = document.getElementById('barcodeScannerModal');
      const modalInstance = bootstrap.Modal.getInstance(modalEl);
      if (modalInstance) {
        modalInstance.hide();
      }
    }, 400);
  }

  // Sprawdzenie i konfiguracja latarki
  function setupTorchSupport() {
    const $torchBtn = $('#scannerTorchBtn');
    isTorchOn = false;
    $torchBtn.addClass('d-none').removeClass('btn-warning').addClass('btn-outline-warning');

    try {
      const videoEl = document.querySelector('#scanner-reader video');
      if (videoEl && videoEl.srcObject) {
        const track = videoEl.srcObject.getVideoTracks()[0];
        if (track) {
          currentMediaTrack = track;
          const capabilities = track.getCapabilities ? track.getCapabilities() : {};
          if (capabilities.torch) {
            $torchBtn.removeClass('d-none');
          }
        }
      }
    } catch (e) {
      console.warn('Torch detection error:', e);
    }
  }

  // Przełączanie latarki
  function toggleTorch() {
    if (!currentMediaTrack) return;
    isTorchOn = !isTorchOn;
    currentMediaTrack.applyConstraints({
      advanced: [{ torch: isTorchOn }]
    }).then(() => {
      const $btn = $('#scannerTorchBtn');
      if (isTorchOn) {
        $btn.removeClass('btn-outline-warning').addClass('btn-warning');
      } else {
        $btn.removeClass('btn-warning').addClass('btn-outline-warning');
      }
    }).catch(err => {
      console.warn('Torch toggle failed', err);
    });
  }

  // Uruchomienie kamery
  async function startCamera(cameraIdOrFacing) {
    const scanner = getScannerInstance();
    hideScannerAlert();
    checkSecureContext();

    const config = {
      fps: 20,
      qrbox: (viewfinderWidth, viewfinderHeight) => {
        // Szeroki prostokąt idealny do czytania poziomych kodów kreskowych
        const width = Math.min(Math.floor(viewfinderWidth * 0.88), 420);
        const height = Math.min(Math.floor(viewfinderHeight * 0.55), 240);
        return { width: Math.max(width, 200), height: Math.max(height, 120) };
      },
      aspectRatio: 1.777778, // panoramiczny 16:9
      disableFlip: true // Tylna kamera nie powinna być odbita lustrzanie
    };

    // Preferencja tylnej kamery
    let cameraConfig = { facingMode: { ideal: "environment" } };
    if (cameraIdOrFacing) {
      cameraConfig = typeof cameraIdOrFacing === 'string' ? { deviceId: { exact: cameraIdOrFacing } } : cameraIdOrFacing;
    }

    try {
      $('#scannerReticleOverlay').removeClass('d-none');
      await scanner.start(
        cameraConfig,
        config,
        onScanSuccess,
        (errorMessage) => {
          // Błędy poszczególnych klatek są normalne podczas wyszukiwania kodu
        }
      );
      isScanning = true;
      setupTorchSupport();
    } catch (err) {
      console.error('Błąd startu kamery:', err);
      $('#scannerReticleOverlay').addClass('d-none');

      let userMsg = 'Nie udało się uruchomić aparatu. ';
      if (err.name === 'NotAllowedError' || err.name === 'PermissionDeniedError') {
        userMsg += 'Brak uprawnień do kamery. Zezwól na dostęp w ustawieniach przeglądarki.';
      } else if (err.name === 'NotFoundError' || err.name === 'DevicesNotFoundError') {
        userMsg += 'Nie znaleziono aparatu w urządzeniu.';
      } else if (err.name === 'NotReadableError' || err.name === 'TrackStartError') {
        userMsg += 'Aparat jest aktualnie używany przez inną aplikację.';
      } else {
        userMsg += (err.message || err);
      }
      showScannerAlert(userMsg, 'danger');
    }
  }

  // Zatrzymanie kamery
  async function stopCamera() {
    isScanning = false;
    currentMediaTrack = null;
    isTorchOn = false;
    $('#scannerTorchBtn').addClass('d-none');
    $('#scannerReticleOverlay').addClass('d-none');
    $('.scanner-reticle').removeClass('border-success shadow-lg');

    if (html5QrCode) {
      try {
        if (html5QrCode.isScanning) {
          await html5QrCode.stop();
        }
        await html5QrCode.clear();
      } catch (e) {
        console.warn('Błąd zatrzymywania skanera:', e);
      }
    }
  }

  // Pobranie listy aparatów i wybór domyślnie tylnego
  async function enumerateBackCameras() {
    const $select = $('#scannerCameraSelect');
    $select.empty().append('<option value="">Domyślny aparat (Tylny)</option>');

    try {
      const devices = await Html5Qrcode.getCameras();
      if (devices && devices.length) {
        availableCameras = devices;
        $select.empty();

        let preferredId = null;

        // Szukamy kamer oznaczonych jako tylne (back, rear, environment)
        devices.forEach((cam, idx) => {
          const label = cam.label || `Aparat ${idx + 1}`;
          const isBack = /back|rear|environment|tył|główny/i.test(label);
          $select.append(`<option value="${cam.id}">${label} ${isBack ? '★ (Tylny)' : ''}</option>`);

          if (isBack && !preferredId) {
            preferredId = cam.id;
          }
        });

        // Jeśli etykiety nie mają nazwy 'back', wybierz ostatnią kamerę (zazwyczaj tylna na Androidzie)
        if (!preferredId && devices.length > 1) {
          preferredId = devices[devices.length - 1].id;
        }

        if (preferredId) {
          selectedCameraId = preferredId;
          $select.val(preferredId);
        }
      }
    } catch (e) {
      console.warn('Nie udało się pobrać listy kamer:', e);
    }
  }

  // Obsługa skanowania ze zdjęcia / pliku
  async function handleFileUpload(file) {
    if (!file) return;
    const scanner = getScannerInstance();
    showScannerAlert('Przetwarzanie zdjęcia w poszukiwaniu kodu...', 'info');

    try {
      const decodedText = await scanner.scanFile(file, true);
      if (decodedText) {
        onScanSuccess(decodedText);
      }
    } catch (err) {
      showScannerAlert('Nie udało się odnaleźć czytelnego kodu kreskowego na wskazanym zdjęciu. Upewnij się, że kod jest ostry i dobrze oświetlony.', 'warning');
    }
  }

  // ==========================================================================
  // REJESTRACJA ZDARZEŃ PO ZAŁADOWANIU DOM
  // ==========================================================================
  $(document).ready(function () {
    const modalEl = document.getElementById('barcodeScannerModal');
    if (!modalEl) return;

    // Kliknięcie w dowolny przycisk otwierający skaner
    $(document).on('click', '.btn-scan-barcode', function (e) {
      e.preventDefault();
      currentTargetSelector = $(this).data('target');
      const label = $(this).data('label') || 'kod';
      
      $('#scannerTargetLabel').html(`Skanujesz dla: <strong>${label}</strong>`);
      
      const modal = new bootstrap.Modal(modalEl);
      modal.show();
    });

    // Po otwarciu modala -> start kamery
    modalEl.addEventListener('shown.bs.modal', async function () {
      await enumerateBackCameras();
      const camChoice = selectedCameraId ? selectedCameraId : { facingMode: { ideal: "environment" } };
      await startCamera(camChoice);
    });

    // Po zamknięciu modala -> natychmiast wyłącz aparat
    modalEl.addEventListener('hidden.bs.modal', async function () {
      await stopCamera();
      currentTargetSelector = null;
    });

    // Zmiana wybranego aparatu
    $('#scannerCameraSelect').on('change', async function () {
      selectedCameraId = $(this).val();
      await stopCamera();
      await startCamera(selectedCameraId || { facingMode: { ideal: "environment" } });
    });

    // Przycisk latarki
    $('#scannerTorchBtn').on('click', function () {
      toggleTorch();
    });

    // Przycisk wczytania zdjęcia / zrobienia zdjęcia natywnym aparatem
    $('#scannerUploadBtn').on('click', function () {
      $('#scannerFileInput').val('').click();
    });

    $('#scannerFileInput').on('change', function () {
      const file = this.files && this.files[0];
      if (file) {
        handleFileUpload(file);
      }
    });
  });

})();

