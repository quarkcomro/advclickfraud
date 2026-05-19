<?php
class AdvClickFraudTrackModuleFrontController extends ModuleFrontController
{
    public function initContent()
    {
        // Dezactivăm randarea template-urilor de magazin pentru viteză brută
        $this->ajax = true; 
        parent::initContent();

        // Citim fluxul brut de date JSON asincron (compatibil sendBeacon / Fetch)
        $rawPayload = file_get_contents('php://input');
        $data = json_decode($rawPayload, true);

        if (!$data || empty($data['token'])) {
            header('HTTP/1.1 400 Bad Request');
            exit('Invalide Telemetry Payload');
        }

        $token = $data['token'];
        $ip = Tools::getRemoteAddr();

        // Executăm inserția și parsarea telemetriei în modelul izolat
        require_once _PS_MODULE_DIR_ . 'advclickfraud/classes/ClickFraudLog.php';
        ClickFraudLog::updateSessionTelemetry($token, $ip, $data);

        header('Content-Type: application/json');
        echo json_encode(['status' => 'success']);
        exit;
    }
}
