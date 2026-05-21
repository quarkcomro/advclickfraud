<script type="text/javascript">
document.addEventListener("DOMContentLoaded", function() {
    const acfConfig = {
        ajaxUrl: "{$acf_ajax_link|escape:'javascript':'UTF-8'}",
        token: "{$acf_token|escape:'javascript':'UTF-8'}",
        page: "{$current_page|escape:'javascript':'UTF-8'}"
    };

	// Enhanced corporate function to generate an absolute static hardware signature
    function generateDeviceFingerprint() {
        try {
            const canvas = document.createElement('canvas');
            const ctx = canvas.getContext('2d');
            if (!ctx) return 'dev_no_canvas';

            // Draw clean unique hidden patterns into memory buffer
            ctx.textBaseline = "top";
            ctx.font = "14px 'Arial'";
            ctx.textStyle = "#f60";
            ctx.fillRect(125,1,62,20);
            ctx.fillStyle = "#069";
            ctx.fillText("advClickFraud_2026_production", 2, 2);
            ctx.fillStyle = "rgba(102, 204, 0, 0.7)";
            ctx.fillText("advClickFraud_2026_production", 4, 4);

            // Collect strict hardware and operating system parameters data array
            const hardwareAttrs = [
                canvas.toDataURL(),
                window.screen.width + "x" + window.screen.height,
                window.screen.colorDepth,
                new Date().getTimezoneOffset(),
                navigator.language,
                navigator.hardwareConcurrency || 4,
                navigator.deviceMemory || 8
            ].join('||');

            // High performance FNV-1a 32-bit stable hash execution loop
            let fnvHash = 2166136261;
            for (let i = 0; i < hardwareAttrs.length; i++) {
                fnvHash ^= hardwareAttrs.charCodeAt(i);
                fnvHash += (fnvHash << 1) + (fnvHash << 4) + (fnvHash << 7) + (fnvHash << 8) + (fnvHash << 24);
            }
            
            // Format to unsigned clean hexadecimal fingerprint identifier token
            const finalHex = (fnvHash >>> 0).toString(16).padStart(8, '0');
            return 'dev_' + finalHex;
        } catch (e) {
            return 'dev_failed_generation';
        }
    }

    const deviceFingerprint = generateDeviceFingerprint();

    let metrics = {
        token: acfConfig.token,
        page: acfConfig.page,
        resolution: window.screen.width + "x" + window.screen.height,
        fingerprint: deviceFingerprint, // Added the unique hash token inside telemetry layer
        mouseMoves: 0,
        keyPresses: 0,
        duration: 0
    };

    let startTime = performance.now();

    // Fast background transmission routine utilizing non-blocking sendBeacon API
    function sendTelemetry(isSync = false) {
        metrics.duration = Math.round((performance.now() - startTime) / 1000);
        const blob = new Blob([JSON.stringify(metrics)], { type: 'application/json' });
        
        if (navigator.sendBeacon) {
            navigator.sendBeacon(acfConfig.ajaxUrl, blob);
        } else {
            fetch(acfConfig.ajaxUrl, {
                method: 'POST',
                body: blob,
                keepalive: true
            });
        }
    }

    // Trigger immediate soft registration to bind fingerprint token directly at first page hit milisecond
    setTimeout(function() {
        sendTelemetry();
    }, 500);

    // Throttle user events to maintain browser speed fluid
    window.addEventListener('mousemove', function throttle() {
        metrics.mouseMoves++;
        window.removeEventListener('mousemove', throttle);
        setTimeout(() => window.addEventListener('mousemove', throttle), 1000);
    });

    window.addEventListener('keydown', function() {
        metrics.keyPresses++;
    });

    document.addEventListener('visibilitychange', function() {
        if (document.visibilityState === 'hidden') {
            sendTelemetry();
        }
    });

    // Periodic telemetry tracking matching user stay lengths blocks
    setInterval(() => {
        metrics.duration = Math.round((performance.now() - startTime) / 1000);
        sendTelemetry();
        metrics.mouseMoves = 0;
        metrics.keyPresses = 0;
    }, 15000);
});
</script>
