<script type="text/javascript">
document.addEventListener("DOMContentLoaded", function() {
    const acfConfig = {
        ajaxUrl: "{$acf_ajax_link|escape:'javascript':'UTF-8'}",
        token: "{$acf_token|escape:'javascript':'UTF-8'}",
        page: "{$current_page|escape:'javascript':'UTF-8'}"
    };

    let metrics = {
        token: acfConfig.token,
        page: acfConfig.page,
        resolution: window.screen.width + "x" + window.screen.height,
        mouseMoves: 0,
        keyPresses: 0,
        duration: 0
    };

    let startTime = performance.now();

    // Monitorizare discretă interacțiuni fizice
    window.addEventListener('mousemove', function throttle() {
        metrics.mouseMoves++;
        window.removeEventListener('mousemove', throttle);
        setTimeout(() => window.addEventListener('mousemove', throttle), 1000); // Throttle 1s
    });

    window.addEventListener('keydown', function() {
        metrics.keyPresses++;
    });

    // Trimitere telemetrie când utilizatorul schimbă tab-ul sau închide pagina
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

    document.addEventListener('visibilitychange', function() {
        if (document.visibilityState === 'hidden') {
            sendTelemetry();
        }
    });

    // Trimitere periodică a datelor la fiecare 15 secunde pentru colectarea precisă a timpului
    setInterval(() => {
        metrics.duration = Math.round((performance.now() - startTime) / 1000);
        sendTelemetry();
        // Resetăm parțial contoarele volatile după trimitere
        metrics.mouseMoves = 0;
        metrics.keyPresses = 0;
    }, 15000);
});
</script>
