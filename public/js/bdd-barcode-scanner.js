/**
 * Scanner code-barres BDD-Hub (html5-qrcode).
 */
(function (window, $) {
    'use strict';

    if (!$ === 'undefined') {
        return;
    }

    function BddBarcodeScanner(options) {
        options = options || {};
        this.inputSelector = options.inputSelector || '#searchInput';
        this.btnSelector = options.btnSelector || '#scanBarcodeBtn';
        this.html5QrCode = null;
        this.isScanning = false;
        this.availableCameras = [];
        this.currentCameraId = null;

        if (options.accentFrom) {
            document.documentElement.style.setProperty('--bdd-scanner-from', options.accentFrom);
        }
        if (options.accentTo) {
            document.documentElement.style.setProperty('--bdd-scanner-to', options.accentTo);
        }

        var self = this;
        $(this.btnSelector).on('click', function () { self.open(); });
        $('#closeScannerBtn').on('click', function () { self.close(); });
        $('#cameraSelect').on('change', function () { self.onCameraChange($(this).val()); });
    }

    BddBarcodeScanner.prototype.isSecureContext = function () {
        return window.isSecureContext ||
            location.protocol === 'https:' ||
            location.hostname === 'localhost' ||
            location.hostname === '127.0.0.1';
    };

    BddBarcodeScanner.prototype.checkCameraAvailable = function () {
        return navigator.mediaDevices && navigator.mediaDevices.getUserMedia;
    };

    BddBarcodeScanner.prototype.open = function () {
        $('#barcodeScannerOverlay').addClass('active');
        $('html').addClass('scanner-open');

        if (!this.isSecureContext()) {
            this.showError('<strong>Connexion non sécurisée</strong><br>Le scanner nécessite HTTPS.');
            return;
        }
        if (!this.checkCameraAvailable()) {
            this.showError('<strong>Caméra non disponible</strong><br>Votre navigateur ne supporte pas la caméra.');
            return;
        }
        this.requestCameraPermission();
    };

    BddBarcodeScanner.prototype.close = function () {
        this.stop();
        $('#barcodeScannerOverlay').removeClass('active');
        $('html').removeClass('scanner-open');
        $('#barcodeReader').empty();
    };

    BddBarcodeScanner.prototype.requestCameraPermission = function () {
        var self = this;
        $('#barcodeReader').html(
            '<div class="d-flex align-items-center justify-content-center h-100" style="color: white;">' +
            '<div class="text-center"><i class="fas fa-spinner fa-spin fa-3x mb-3"></i><p>Accès à la caméra...</p></div></div>'
        );
        navigator.mediaDevices.getUserMedia({
            video: { facingMode: 'environment', width: { ideal: 1920 }, height: { ideal: 1080 } }
        }).then(function (stream) {
            stream.getTracks().forEach(function (track) { track.stop(); });
            $('#barcodeReader').empty();
            self.start();
        }).catch(function (err) {
            var msg = '<strong>Erreur</strong><br>' + (err.message || 'Erreur inconnue');
            if (err.name === 'NotAllowedError') {
                msg = '<strong>Permission refusée</strong><br>Autorisez l\'accès à la caméra.';
            } else if (err.name === 'NotFoundError') {
                msg = '<strong>Aucune caméra</strong><br>Pas de caméra détectée.';
            }
            self.showError(msg);
        });
    };

    BddBarcodeScanner.prototype.scannerConfig = {
        fps: 15,
        qrbox: function (vw, vh) {
            return { width: Math.floor(vw * 0.9), height: Math.floor(vh * 0.5) };
        },
        experimentalFeatures: { useBarCodeDetectorIfSupported: true },
        formatsToSupport: [
            Html5QrcodeSupportedFormats.EAN_13,
            Html5QrcodeSupportedFormats.EAN_8,
            Html5QrcodeSupportedFormats.UPC_A,
            Html5QrcodeSupportedFormats.UPC_E,
            Html5QrcodeSupportedFormats.CODE_128
        ]
    };

    BddBarcodeScanner.prototype.loadCameras = async function () {
        try {
            var allCameras = await Html5Qrcode.getCameras();
            this.availableCameras = allCameras.filter(function (cam) {
                var label = (cam.label || '').toLowerCase();
                return label.indexOf('back') >= 0 || label.indexOf('arrière') >= 0 || label.indexOf('environment') >= 0;
            });
            if (this.availableCameras.length === 0) {
                this.availableCameras = allCameras;
            }
            var select = $('#cameraSelect');
            select.empty();
            if (this.availableCameras.length > 0) {
                this.availableCameras.forEach(function (cam, index) {
                    select.append('<option value="' + cam.id + '">Objectif ' + (index + 1) + '</option>');
                });
                return this.availableCameras.length > 1 ? this.availableCameras[1].id : this.availableCameras[0].id;
            }
            return null;
        } catch (e) {
            return null;
        }
    };

    BddBarcodeScanner.prototype.start = async function () {
        if (this.isScanning) {
            return;
        }
        if (typeof Html5Qrcode === 'undefined') {
            this.showError('<strong>Erreur</strong><br>Bibliothèque scanner non chargée.');
            return;
        }
        var self = this;
        this.html5QrCode = new Html5Qrcode('barcodeReader');
        try {
            var defaultCameraId = await this.loadCameras();
            if (defaultCameraId) {
                this.currentCameraId = defaultCameraId;
                $('#cameraSelect').val(this.currentCameraId);
                await this.startWithCamera(defaultCameraId);
            } else {
                await this.html5QrCode.start(
                    { facingMode: 'environment' },
                    this.scannerConfig,
                    function (text) { self.onScanSuccess(text); },
                    function () {}
                );
                this.isScanning = true;
            }
        } catch (e) {
            this.showError('<strong>Erreur</strong><br>Impossible de démarrer le scanner.');
        }
    };

    BddBarcodeScanner.prototype.startWithCamera = async function (cameraId) {
        var self = this;
        await this.html5QrCode.start(
            cameraId,
            this.scannerConfig,
            function (text) { self.onScanSuccess(text); },
            function () {}
        );
        this.isScanning = true;
        this.currentCameraId = cameraId;
    };

    BddBarcodeScanner.prototype.onCameraChange = async function (newCameraId) {
        if (!newCameraId || newCameraId === this.currentCameraId || !this.isScanning) {
            return;
        }
        try {
            await this.html5QrCode.stop();
            this.isScanning = false;
            await this.startWithCamera(newCameraId);
        } catch (e) { /* ignore */ }
    };

    BddBarcodeScanner.prototype.stop = function () {
        if (this.html5QrCode && this.isScanning) {
            var self = this;
            this.html5QrCode.stop().then(function () {
                self.isScanning = false;
                self.html5QrCode.clear();
            }).catch(function () {
                self.isScanning = false;
            });
        }
    };

    BddBarcodeScanner.prototype.onScanSuccess = function (decodedText) {
        if (navigator.vibrate) {
            navigator.vibrate(100);
        }
        $(this.inputSelector).val(decodedText);
        $('#barcodeScannerOverlay .scanner-footer p').html(
            '<i class="fas fa-check-circle text-success"></i> Code détecté : <strong>' + decodedText + '</strong>'
        );
        var self = this;
        setTimeout(function () { self.close(); }, 800);
    };

    BddBarcodeScanner.prototype.showError = function (message) {
        $('#barcodeReader').html(
            '<div class="d-flex align-items-center justify-content-center h-100">' +
            '<div class="alert alert-danger m-4 text-center">' +
            '<i class="fas fa-exclamation-triangle fa-2x mb-2"></i><br>' + message +
            '<br><button type="button" class="btn btn-outline-danger btn-sm mt-3" id="scannerErrorClose">Fermer</button>' +
            '</div></div>'
        );
        $('#scannerErrorClose').on('click', function () { $('#closeScannerBtn').click(); });
    };

    window.BddBarcodeScanner = BddBarcodeScanner;
    window.BddBarcodeScanner.init = function (options) {
        return new BddBarcodeScanner(options);
    };
})(window, window.jQuery);
