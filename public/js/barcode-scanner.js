/**
 * Scanner de code-barres pour la recherche de livres
 */
$(document).ready(function () {
    let html5QrCode = null;
    let isScanning = false;
    
    $('#scanBarcodeBtn').click(function() {
        $('#barcodeScannerContainer').slideDown();
        startScanner();
    });
    
    $('#closeScannerBtn').click(function() {
        stopScanner();
        $('#barcodeScannerContainer').slideUp();
    });
    
    function startScanner() {
        if (isScanning) return;
        
        html5QrCode = new Html5Qrcode("barcodeReader");
        
        const config = {
            fps: 10,
            qrbox: { width: 250, height: 100 },
            formatsToSupport: [
                Html5QrcodeSupportedFormats.EAN_13,
                Html5QrcodeSupportedFormats.EAN_8,
                Html5QrcodeSupportedFormats.UPC_A,
                Html5QrcodeSupportedFormats.UPC_E,
                Html5QrcodeSupportedFormats.CODE_128,
                Html5QrcodeSupportedFormats.CODE_39
            ]
        };
        
        html5QrCode.start(
            { facingMode: "environment" },
            config,
            onScanSuccess,
            onScanError
        ).then(() => {
            isScanning = true;
        }).catch((err) => {
            console.error("Erreur démarrage scanner:", err);
            showScannerError("Impossible d'accéder à la caméra. Vérifiez les permissions.");
        });
    }
    
    function stopScanner() {
        if (html5QrCode && isScanning) {
            html5QrCode.stop().then(() => {
                isScanning = false;
                html5QrCode.clear();
            }).catch((err) => {
                console.error("Erreur arrêt scanner:", err);
            });
        }
    }
    
    function onScanSuccess(decodedText, decodedResult) {
        try {
            const audioContext = new (window.AudioContext || window.webkitAudioContext)();
            const oscillator = audioContext.createOscillator();
            oscillator.type = 'sine';
            oscillator.frequency.setValueAtTime(800, audioContext.currentTime);
            oscillator.connect(audioContext.destination);
            oscillator.start();
            oscillator.stop(audioContext.currentTime + 0.1);
        } catch(e) {}
        
        $('#search').val(decodedText);
        showScannerSuccess("Code-barres détecté : " + decodedText);
        stopScanner();
        
        setTimeout(function() {
            $('#barcodeScannerContainer').slideUp();
        }, 1000);
    }
    
    function onScanError(errorMessage) {}
    
    function showScannerSuccess(message) {
        const alertHtml = '<div class="alert alert-success alert-dismissible fade show m-3" role="alert">' +
            '<i class="fas fa-check-circle"></i> ' + message +
            '<button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>' +
            '</div>';
        $('#barcodeReader').after(alertHtml);
    }
    
    function showScannerError(message) {
        const alertHtml = '<div class="alert alert-danger m-3" role="alert">' +
            '<i class="fas fa-exclamation-triangle"></i> ' + message +
            '</div>';
        $('#barcodeReader').html(alertHtml);
    }
});
